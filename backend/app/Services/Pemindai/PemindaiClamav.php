<?php

namespace App\Services\Pemindai;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Pemindaian melalui daemon ClamAV (REQ-NF-SEC-008).
 *
 * Berkas dikirim ke clamd lewat perintah INSTREAM, bukan dengan memberikan
 * jalurnya: dengan begitu daemon tidak perlu dapat membaca direktori sementara
 * PHP, dan keduanya boleh berjalan pada wadah yang berbeda.
 */
class PemindaiClamav implements PemindaiBerkas
{
    /** Potongan yang dikirim per tahap; clamd membatasi ukuran tiap potongan. */
    private const POTONGAN = 8192;

    public function nama(): string
    {
        return 'clamav';
    }

    public function aktif(): bool
    {
        return filled(config('pemindai.clamav.alamat'));
    }

    public function pindai(UploadedFile $berkas): HasilPindai
    {
        $soket = @stream_socket_client(
            (string) config('pemindai.clamav.alamat'),
            $nomorGalat,
            $pesanGalat,
            (float) config('pemindai.clamav.timeout', 10),
        );

        if (! $soket) {
            /*
             * Pemindai tidak dapat dihubungi. Berkas ditolak, bukan diloloskan:
             * pelindung yang gagal terbuka sama dengan tidak ada pelindung.
             */
            throw new RuntimeException("Pemindai berkas tidak dapat dihubungi: {$pesanGalat}");
        }

        stream_set_timeout($soket, (int) config('pemindai.clamav.timeout', 10));
        fwrite($soket, "zINSTREAM\0");

        $isi = fopen($berkas->getRealPath(), 'rb');

        while (! feof($isi)) {
            $potongan = fread($isi, self::POTONGAN);

            if ($potongan === false || $potongan === '') {
                break;
            }

            fwrite($soket, pack('N', strlen($potongan)).$potongan);
        }

        fclose($isi);
        // Potongan berukuran nol menandai akhir aliran.
        fwrite($soket, pack('N', 0));

        $jawaban = trim((string) stream_get_contents($soket));
        fclose($soket);

        if (str_contains($jawaban, 'OK') && ! str_contains($jawaban, 'FOUND')) {
            return HasilPindai::bersih($this->nama());
        }

        if (str_contains($jawaban, 'FOUND')) {
            // Contoh jawaban: "stream: Eicar-Test-Signature FOUND".
            $temuan = trim(str_replace(['stream:', 'FOUND'], '', $jawaban));

            return HasilPindai::terinfeksi($this->nama(), $temuan);
        }

        throw new RuntimeException("Jawaban pemindai tidak dikenali: {$jawaban}");
    }
}
