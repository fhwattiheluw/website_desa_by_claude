<?php

namespace App\Services;

use App\Exceptions\AlurTidakValid;
use App\Models\JenisLayanan;
use App\Models\Permohonan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Mesin alur permohonan surat (REQ-F-SRT-009..011, 021, 022; BR-01..BR-07).
 */
class PermohonanService
{
    /** Transisi status yang diizinkan (REQ-F-SRT-009). */
    public const TRANSISI = [
        Permohonan::DRAF => [Permohonan::DIAJUKAN],
        Permohonan::DIAJUKAN => [Permohonan::DIVERIFIKASI, Permohonan::DIKEMBALIKAN, Permohonan::DITOLAK],
        Permohonan::DIKEMBALIKAN => [Permohonan::DIAJUKAN, Permohonan::DITOLAK],
        Permohonan::DIVERIFIKASI => [Permohonan::DISETUJUI, Permohonan::DIKEMBALIKAN, Permohonan::DITOLAK],
        Permohonan::DISETUJUI => [Permohonan::DITANDATANGANI],
        Permohonan::DITANDATANGANI => [Permohonan::SELESAI],
        Permohonan::SELESAI => [],
        Permohonan::DITOLAK => [],
    ];

    /** Status yang mewajibkan alasan tertulis (REQ-F-SRT-010). */
    public const WAJIB_ALASAN = [Permohonan::DIKEMBALIKAN, Permohonan::DITOLAK];

    public const MINIMAL_ALASAN = 20;

    /** Batas perbaikan permohonan yang dikembalikan sebelum kedaluwarsa (BR-07). */
    public const BATAS_PERBAIKAN_HARI = 14;

    public function __construct(
        private readonly NomorService $nomor,
        private readonly KalenderKerja $kalender,
        private readonly AuditLogger $audit,
        private readonly NotifikasiService $notifikasi,
    ) {}

    /**
     * @param  array<string, mixed>  $dataFormulir
     */
    public function buat(
        User $pemohon,
        JenisLayanan $layanan,
        array $dataFormulir,
        string $kanal = 'daring',
        ?User $dibuatOleh = null,
        bool $draf = false,
    ): Permohonan {
        // BR-01: hanya warga aktif dengan NIK terverifikasi yang boleh mengajukan.
        if (! $pemohon->bolehMengajukanLayanan()) {
            throw new AlurTidakValid(
                'Akun pemohon belum aktif atau NIK belum diverifikasi petugas desa, sehingga permohonan belum dapat diajukan.'
            );
        }

        if (! $layanan->aktif) {
            throw new AlurTidakValid('Jenis layanan ini sedang tidak melayani permohonan baru.');
        }

        // REQ-F-SRT-022: cegah pengajuan ganda untuk layanan yang sama.
        if (! $draf && $this->punyaPermohonanAktif($pemohon, $layanan)) {
            throw new AlurTidakValid(
                'Anda masih memiliki permohonan aktif untuk layanan ini. Selesaikan atau batalkan permohonan tersebut lebih dahulu.'
            );
        }

        return DB::transaction(function () use ($pemohon, $layanan, $dataFormulir, $kanal, $dibuatOleh, $draf) {
            $permohonan = Permohonan::create([
                'nomor_tiket' => $this->nomor->nomorTiket($layanan),
                'pemohon_id' => $pemohon->id,
                'jenis_layanan_id' => $layanan->id,
                'dibuat_oleh' => $dibuatOleh?->id,
                'data_formulir' => $dataFormulir,
                'status' => $draf ? Permohonan::DRAF : Permohonan::DIAJUKAN,
                'kanal' => $kanal,
                'tenggat_sla' => $draf ? null : $this->kalender->tenggat($layanan->sla_hari_kerja),
                'diajukan_pada' => $draf ? null : now(),
            ]);

            $permohonan->riwayat()->create([
                'aktor_id' => $dibuatOleh?->id ?? $pemohon->id,
                'dari' => null,
                'ke' => $permohonan->status,
                'catatan' => $draf ? 'Draf permohonan dibuat.' : 'Permohonan diajukan oleh pemohon.',
            ]);

            $this->audit->catat('create', 'Permohonan', $permohonan->id, null, [
                'nomor_tiket' => $permohonan->nomor_tiket,
                'status' => $permohonan->status,
                'jenis_layanan' => $layanan->kode,
            ]);

            if (! $draf) {
                $this->notifikasi->permohonanBerubah($permohonan, 'permohonan_diterima');
            }

            return $permohonan;
        });
    }

    /** Pengiriman ulang setelah perbaikan (REQ-F-SRT-011). */
    public function kirimUlang(Permohonan $permohonan, User $aktor, array $dataFormulir): Permohonan
    {
        if (! in_array($permohonan->status, [Permohonan::DRAF, Permohonan::DIKEMBALIKAN], true)) {
            throw new AlurTidakValid('Permohonan ini tidak sedang menunggu perbaikan.');
        }

        $permohonan->update([
            'data_formulir' => $dataFormulir,
            'tenggat_sla' => $this->kalender->tenggat($permohonan->jenisLayanan->sla_hari_kerja),
            'diajukan_pada' => now(),
            'alasan' => null,
        ]);

        return $this->transisi($permohonan, Permohonan::DIAJUKAN, $aktor, 'Permohonan diperbaiki dan dikirim ulang.');
    }

    public function transisi(Permohonan $permohonan, string $ke, User $aktor, ?string $catatan = null): Permohonan
    {
        $dari = $permohonan->status;

        if (! in_array($ke, self::TRANSISI[$dari] ?? [], true)) {
            throw new AlurTidakValid("Permohonan berstatus \"{$dari}\" tidak dapat diubah menjadi \"{$ke}\".");
        }

        if (in_array($ke, self::WAJIB_ALASAN, true) && strlen(trim((string) $catatan)) < self::MINIMAL_ALASAN) {
            throw new AlurTidakValid(
                'Alasan wajib diisi minimal '.self::MINIMAL_ALASAN.' karakter agar pemohon memahami langkah perbaikan.'
            );
        }

        // BR-03: pembuat permohonan atas nama warga tidak boleh menjadi penyetuju.
        if ($ke === Permohonan::DISETUJUI && $permohonan->dibuat_oleh === $aktor->id) {
            throw new AlurTidakValid('Petugas yang membuat permohonan tidak dapat menyetujui permohonan yang sama.');
        }

        return DB::transaction(function () use ($permohonan, $dari, $ke, $aktor, $catatan) {
            $perubahan = ['status' => $ke];

            if (in_array($ke, self::WAJIB_ALASAN, true)) {
                $perubahan['alasan'] = trim((string) $catatan);
            }

            if ($ke === Permohonan::DIVERIFIKASI) {
                $perubahan['verifikator_id'] = $aktor->id;
            }

            if ($ke === Permohonan::DISETUJUI) {
                $perubahan['penyetuju_id'] = $aktor->id;
            }

            if (in_array($ke, Permohonan::FINAL, true)) {
                $perubahan['selesai_pada'] = now();
            }

            $permohonan->update($perubahan);

            $permohonan->riwayat()->create([
                'aktor_id' => $aktor->id,
                'dari' => $dari,
                'ke' => $ke,
                'catatan' => $catatan,
            ]);

            // REQ-F-SRT-021: seluruh transisi status tercatat di audit log.
            $this->audit->catat('status_change', 'Permohonan', $permohonan->id,
                ['status' => $dari],
                ['status' => $ke, 'catatan' => $catatan],
            );

            $templat = match ($ke) {
                Permohonan::DIKEMBALIKAN => 'permohonan_dikembalikan',
                Permohonan::DITOLAK => 'permohonan_ditolak',
                Permohonan::SELESAI => 'permohonan_selesai',
                default => null,
            };

            if ($templat !== null) {
                $this->notifikasi->permohonanBerubah($permohonan, $templat);
            }

            return $permohonan->refresh();
        });
    }

    /** BR-07: permohonan dikembalikan yang tidak diperbaiki dalam 14 hari otomatis ditolak. */
    public function tutupYangKedaluwarsa(): int
    {
        $jumlah = 0;

        Permohonan::query()
            ->where('status', Permohonan::DIKEMBALIKAN)
            ->where('updated_at', '<', now()->subDays(self::BATAS_PERBAIKAN_HARI))
            ->get()
            ->each(function (Permohonan $permohonan) use (&$jumlah) {
                $permohonan->update([
                    'status' => Permohonan::DITOLAK,
                    'alasan' => 'Permohonan kedaluwarsa karena tidak diperbaiki dalam '
                        .self::BATAS_PERBAIKAN_HARI.' hari kalender.',
                    'selesai_pada' => now(),
                ]);

                $permohonan->riwayat()->create([
                    'dari' => Permohonan::DIKEMBALIKAN,
                    'ke' => Permohonan::DITOLAK,
                    'catatan' => 'Ditutup otomatis oleh sistem (BR-07).',
                ]);

                $this->audit->catat('status_change', 'Permohonan', $permohonan->id,
                    ['status' => Permohonan::DIKEMBALIKAN],
                    ['status' => Permohonan::DITOLAK, 'catatan' => 'kedaluwarsa'],
                );

                $jumlah++;
            });

        return $jumlah;
    }

    /** REQ-F-SRT-026: lampiran dihapus 12 bulan setelah permohonan selesai. */
    public function bersihkanLampiranKedaluwarsa(): int
    {
        $jumlah = 0;

        Permohonan::query()
            ->whereIn('status', Permohonan::FINAL)
            ->whereNull('lampiran_dibersihkan_pada')
            ->where('selesai_pada', '<', now()->subMonths(12))
            ->with('lampiran.media')
            ->get()
            ->each(function (Permohonan $permohonan) use (&$jumlah) {
                foreach ($permohonan->lampiran as $lampiran) {
                    $media = $lampiran->media;

                    if ($media) {
                        Storage::disk($media->disk)->delete($media->path);
                        $media->delete();
                    }

                    $lampiran->delete();
                }

                $permohonan->update(['lampiran_dibersihkan_pada' => now()]);
                $this->audit->catat('retensi', 'Permohonan', $permohonan->id, null, ['lampiran' => 'dihapus']);
                $jumlah++;
            });

        return $jumlah;
    }

    public function punyaPermohonanAktif(User $pemohon, JenisLayanan $layanan): bool
    {
        return Permohonan::query()
            ->where('pemohon_id', $pemohon->id)
            ->where('jenis_layanan_id', $layanan->id)
            ->whereIn('status', Permohonan::AKTIF)
            ->exists();
    }
}
