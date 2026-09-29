<?php

namespace App\Services\TandaTangan;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Penandatanganan melalui Penyelenggara Sertifikasi Elektronik (BSrE/PSrE)
 * sesuai REQ-SW-004: dokumen dikirim untuk ditandatangani, berkas hasil dan
 * bukti penandatanganan disimpan kembali.
 *
 * Kontrak HTTP penyedia berbeda-beda. Nilai yang perlu disesuaikan saat
 * sertifikat terbit ada pada config/tte.php, bukan di dalam kode ini.
 */
class TandaTanganPsre implements PenandaTangan
{
    public function metode(): string
    {
        return 'tte';
    }

    public function nama(): string
    {
        return (string) config('tte.psre.nama_penyedia', 'Penyedia TTE tersertifikasi');
    }

    public function menyematkanSpesimen(): bool
    {
        // Penyedia membubuhkan panel tanda tangan digitalnya sendiri.
        return false;
    }

    public function siap(): bool
    {
        return filled(config('tte.psre.url')) && filled(config('tte.psre.token'));
    }

    public function tandaTangani(string $pdf, User $penandatangan, string $nomorSurat): HasilTandaTangan
    {
        if (! $this->siap()) {
            throw new RuntimeException('Konfigurasi penyedia TTE belum lengkap.');
        }

        $nik = $penandatangan->nik;

        if (blank($nik)) {
            throw new RuntimeException(
                'NIK penandatangan belum terisi, padahal diperlukan penyedia TTE untuk mencocokkan sertifikat.'
            );
        }

        $respons = Http::withToken((string) config('tte.psre.token'))
            ->timeout((int) config('tte.psre.timeout', 60))
            ->attach('file', $pdf, str($nomorSurat)->slug().'.pdf')
            ->post((string) config('tte.psre.url'), [
                'nik' => $nik,
                'passphrase' => (string) config('tte.psre.passphrase'),
                'tampilan' => config('tte.psre.tampilan', 'invisible'),
                'image' => config('tte.psre.sematkan_gambar', false) ? 'true' : 'false',
            ]);

        if (! $respons->successful()) {
            throw new RuntimeException(
                'Penyedia TTE menolak permintaan penandatanganan: '.$respons->status().' '.$respons->body()
            );
        }

        $berkas = $respons->body();

        if (! str_starts_with($berkas, '%PDF')) {
            throw new RuntimeException('Penyedia TTE tidak mengembalikan berkas PDF yang sah.');
        }

        return new HasilTandaTangan($berkas, $this->metode(), [
            'penyedia' => $this->nama(),
            'ditandatangani_pada' => now()->toIso8601String(),
            'hash_dokumen' => hash('sha256', $berkas),
            'kode_respons' => $respons->status(),
        ]);
    }
}
