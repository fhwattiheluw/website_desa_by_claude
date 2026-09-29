<?php

namespace App\Services;

use App\Exceptions\AlurTidakValid;
use App\Models\Pengaturan;
use App\Models\Permohonan;
use App\Models\SuratTerbit;
use App\Models\User;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
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

            $pdf = $this->buatPdf($permohonan, $surat, $penandatangan);
            $path = "surat/{$permohonan->id}-".str($surat->nomor_surat)->slug().'.pdf';

            // Dokumen memuat data pribadi sehingga disimpan pada disk privat (REQ-NF-SEC-007).
            Storage::disk(self::DISK)->put($path, $pdf);

            $surat->path_pdf = $path;
            $surat->hash_dokumen = hash('sha256', $pdf);
            $surat->save();

            $this->audit->catat('terbit_surat', 'SuratTerbit', $surat->id, null, [
                'nomor_surat' => $surat->nomor_surat,
                'permohonan' => $permohonan->nomor_tiket,
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

    private function buatPdf(Permohonan $permohonan, SuratTerbit $surat, User $penandatangan): string
    {
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

    /** REQ-F-SRT-015: kode QR menuju halaman verifikasi publik. */
    public function qrDataUri(string $kodeVerifikasi): string
    {
        $url = rtrim((string) config('app.frontend_url'), '/')."/layanan/verifikasi/{$kodeVerifikasi}";

        $writer = new Writer(new ImageRenderer(new RendererStyle(180, 1), new SvgImageBackEnd));
        $svg = $writer->writeString($url, 'UTF-8', ErrorCorrectionLevel::M());

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
