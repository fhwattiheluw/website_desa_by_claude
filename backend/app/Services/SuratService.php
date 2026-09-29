<?php

namespace App\Services;

use App\Exceptions\AlurTidakValid;
use App\Models\Pengaturan;
use App\Models\Permohonan;
use App\Models\SpesimenTandaTangan;
use App\Models\SuratTerbit;
use App\Models\User;
use App\Services\TandaTangan\ManajerTandaTangan;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

/**
 * Penerbitan dokumen surat: penomoran resmi, pembuatan PDF, kode QR
 * verifikasi, dan pembatalan (REQ-F-SRT-013..018; BR-04, BR-05).
 */
class SuratService
{
    public const DISK = 'local';

    public function __construct(
        private readonly NomorService $nomor,
        private readonly AuditLogger $audit,
        private readonly PermohonanService $permohonanService,
        private readonly ManajerTandaTangan $tandaTangan,
    ) {}

    public function terbitkan(Permohonan $permohonan, User $penandatangan): SuratTerbit
    {
        if ($permohonan->status !== Permohonan::DISETUJUI) {
            throw new AlurTidakValid('Surat hanya dapat diterbitkan untuk permohonan yang telah disetujui.');
        }

        if ($permohonan->surat()->exists()) {
            throw new AlurTidakValid('Surat untuk permohonan ini sudah pernah diterbitkan.');
        }

        $permohonan->loadMissing('jenisLayanan', 'pemohon');

        $surat = DB::transaction(function () use ($permohonan, $penandatangan): SuratTerbit {
            // BR-04: nomor surat baru diterbitkan tepat saat penandatanganan.
            $this->permohonanService->transisi(
                $permohonan,
                Permohonan::DITANDATANGANI,
                $penandatangan,
                'Surat ditandatangani dan diterbitkan.',
            );

            $surat = new SuratTerbit([
                'permohonan_id' => $permohonan->id,
                'penandatangan_id' => $penandatangan->id,
                'nomor_surat' => $this->nomor->nomorSurat($permohonan->jenisLayanan),
                'tanggal_terbit' => now()->toDateString(),
                'kode_verifikasi' => $this->nomor->kodeVerifikasi(),
                'path_pdf' => '',
                'hash_dokumen' => '',
            ]);

            $penanda = $this->tandaTangan->aktif();
            $pdf = $this->buatPdf($permohonan, $surat, $penandatangan, $penanda->menyematkanSpesimen());

            try {
                $hasil = $penanda->tandaTangani($pdf, $penandatangan, $surat->nomor_surat);
            } catch (\Throwable $galat) {
                // Kegagalan penyedia tersertifikasi tidak boleh menahan pelayanan:
                // dokumen tetap terbit dengan metode dalam sistem, dan kegagalan
                // dicatat agar dapat ditindaklanjuti (REQ-NF-REL-005, DEP-03).
                Log::error('Penandatanganan tersertifikasi gagal', [
                    'nomor_surat' => $surat->nomor_surat,
                    'galat' => $galat->getMessage(),
                ]);

                $this->audit->catat('tte_gagal', 'SuratTerbit', null, null, [
                    'nomor_surat' => $surat->nomor_surat,
                    'galat' => $galat->getMessage(),
                ]);

                $cadangan = $this->tandaTangan->cadangan();
                $pdf = $this->buatPdf($permohonan, $surat, $penandatangan, $cadangan->menyematkanSpesimen());
                $hasil = $cadangan->tandaTangani($pdf, $penandatangan, $surat->nomor_surat);
            }

            $path = "surat/{$permohonan->id}-".str($surat->nomor_surat)->slug().'.pdf';

            // Dokumen memuat data pribadi sehingga disimpan pada disk privat (REQ-NF-SEC-007).
            Storage::disk(self::DISK)->put($path, $hasil->pdf);

            $surat->path_pdf = $path;
            $surat->hash_dokumen = hash('sha256', $hasil->pdf);
            $surat->metode_tanda_tangan = $hasil->metode;
            $surat->bukti_tte = $hasil->bukti;
            $surat->save();

            $this->audit->catat('terbit_surat', 'SuratTerbit', $surat->id, null, [
                'nomor_surat' => $surat->nomor_surat,
                'permohonan' => $permohonan->nomor_tiket,
                'metode_tanda_tangan' => $hasil->metode,
            ]);

            return $surat;
        });

        $this->permohonanService->transisi(
            $permohonan->refresh(),
            Permohonan::SELESAI,
            $penandatangan,
            'Dokumen tersedia untuk diunduh pemohon.',
        );

        return $surat;
    }

    /** BR-05: surat dibatalkan dengan alasan; nomor surat tidak digunakan ulang. */
    public function batalkan(SuratTerbit $surat, User $aktor, string $alasan): SuratTerbit
    {
        if (! $surat->sah()) {
            throw new AlurTidakValid('Surat ini sudah berstatus dibatalkan.');
        }

        if (strlen(trim($alasan)) < PermohonanService::MINIMAL_ALASAN) {
            throw new AlurTidakValid('Alasan pembatalan wajib diisi minimal '.PermohonanService::MINIMAL_ALASAN.' karakter.');
        }

        $surat->update(['status_keabsahan' => 'dibatalkan', 'alasan_pembatalan' => trim($alasan)]);

        $this->audit->catat('batal_surat', 'SuratTerbit', $surat->id,
            ['status_keabsahan' => 'sah'],
            ['status_keabsahan' => 'dibatalkan', 'alasan' => $alasan],
        );

        return $surat;
    }

    public function isiBerkas(SuratTerbit $surat): string
    {
        return Storage::disk(self::DISK)->get($surat->path_pdf) ?? '';
    }

    private function buatPdf(
        Permohonan $permohonan,
        SuratTerbit $surat,
        User $penandatangan,
        bool $sematkanSpesimen,
    ): string {
        $templat = 'surat.'.str($permohonan->jenisLayanan->templat)->afterLast('.');
        $view = View::exists($templat) ? $templat : 'surat.umum';

        $html = View::make($view, [
            'permohonan' => $permohonan,
            'surat' => $surat,
            'layanan' => $permohonan->jenisLayanan,
            'pemohon' => $permohonan->pemohon,
            'data' => $permohonan->data_formulir,
            'penandatangan' => $penandatangan,
            'desa' => Pengaturan::semua(),
            'jabatan_penandatangan' => $this->jabatanPenandatangan($penandatangan),
            'spesimen' => $sematkanSpesimen ? $this->spesimenDataUri($penandatangan) : null,
            'qr' => $this->qrDataUri($surat->kode_verifikasi),
        ])->render();

        $opsi = new Options;
        $opsi->set('isRemoteEnabled', false);   // cegah SSRF melalui konten dokumen
        $opsi->set('isHtml5ParserEnabled', true);
        $opsi->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($opsi);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * Jabatan yang tercetak pada blok tanda tangan. Sekretaris Desa
     * menandatangani atas nama (a.n.) Kepala Desa (REQ-F-SRT-017).
     */
    public function jabatanPenandatangan(User $penandatangan): string
    {
        $namaDesa = Pengaturan::ambil('nama_desa', '-');

        return match ($penandatangan->role?->kode) {
            'kades' => "Kepala Desa {$namaDesa}",
            'sekdes' => "a.n. Kepala Desa {$namaDesa}<br>Sekretaris Desa",
            default => $penandatangan->role?->nama ?? 'Pejabat Desa',
        };
    }

    /**
     * Gambar spesimen tanda tangan pejabat, bila terdaftar. Berkasnya dibaca
     * dari disk privat dan hanya dipakai di dalam dokumen (REQ-F-SRT-017).
     */
    private function spesimenDataUri(User $penandatangan): ?string
    {
        $spesimen = SpesimenTandaTangan::where('user_id', $penandatangan->id)
            ->where('aktif', true)
            ->first();

        if (! $spesimen || ! Storage::disk($spesimen->disk)->exists($spesimen->path)) {
            return null;
        }

        $isi = (string) Storage::disk($spesimen->disk)->get($spesimen->path);
        $tipe = str_ends_with($spesimen->path, '.jpg') ? 'image/jpeg' : 'image/png';

        return "data:{$tipe};base64,".base64_encode($isi);
    }

    /** REQ-F-SRT-015: kode QR menuju halaman verifikasi publik. */
    public function qrDataUri(string $kodeVerifikasi): string
    {
        $url = rtrim((string) config('app.frontend_url'), '/')."/layanan/verifikasi/{$kodeVerifikasi}";

        $writer = new Writer(new ImageRenderer(new RendererStyle(180, 1), new SvgImageBackEnd));
        $svg = $writer->writeString($url, 'UTF-8', ErrorCorrectionLevel::M());

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
