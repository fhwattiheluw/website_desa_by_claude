<?php

namespace App\Services\Captcha;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Tantangan bawaan tanpa layanan luar (REQ-SW-007).
 *
 * Bentuknya soal aritmatika sederhana dalam kata, bukan gambar berderau:
 * pembaca layar dapat membacakannya sehingga formulir tetap dapat diisi
 * penyandang disabilitas (REQ-NF-USB-004). Kunci jawaban hanya ada di
 * singgahan sisi server dan hangus sekali pakai, sehingga satu tantangan tidak
 * dapat diputar ulang untuk mengirim banyak formulir.
 */
class CaptchaBawaan implements Captcha
{
    private const PREFIKS = 'captcha:';

    /** Ejaan angka agar jawaban berupa kata tetap diterima. */
    private const KATA_ANGKA = [
        'nol' => 0, 'satu' => 1, 'dua' => 2, 'tiga' => 3, 'empat' => 4, 'lima' => 5,
        'enam' => 6, 'tujuh' => 7, 'delapan' => 8, 'sembilan' => 9, 'sepuluh' => 10,
        'sebelas' => 11, 'dua belas' => 12, 'tiga belas' => 13, 'empat belas' => 14,
        'lima belas' => 15, 'enam belas' => 16, 'tujuh belas' => 17, 'delapan belas' => 18,
        'sembilan belas' => 19, 'dua puluh' => 20,
    ];

    public function metode(): string
    {
        return 'bawaan';
    }

    public function aktif(): bool
    {
        return true;
    }

    public function tantangan(): array
    {
        [$pertanyaan, $jawaban] = $this->susunSoal();

        $token = Str::random(40);

        Cache::put(self::PREFIKS.$token, $jawaban, now()->addMinutes((int) config('captcha.kedaluwarsa_menit', 10)));

        return [
            'aktif' => true,
            'metode' => $this->metode(),
            'token' => $token,
            'pertanyaan' => $pertanyaan,
            'petunjuk' => 'Jawab dengan angka, misalnya 9.',
        ];
    }

    public function periksa(?string $token, ?string $jawaban): bool
    {
        if (blank($token) || blank($jawaban)) {
            return false;
        }

        // Diambil sekali pakai: jawaban salah pun menghanguskan tantangan.
        $benar = Cache::pull(self::PREFIKS.$token);

        if ($benar === null) {
            return false;
        }

        return $this->angkakan($jawaban) === (int) $benar;
    }

    /** @return array{0: string, 1: int} */
    private function susunSoal(): array
    {
        $kiri = random_int(2, 9);
        $kanan = random_int(1, 9);

        // Pengurangan disusun agar hasilnya tidak pernah negatif.
        if (random_int(0, 1) === 1 && $kiri > $kanan) {
            return [
                'Berapa hasil '.$this->ejakan($kiri).' dikurangi '.$this->ejakan($kanan).'?',
                $kiri - $kanan,
            ];
        }

        return [
            'Berapa hasil '.$this->ejakan($kiri).' ditambah '.$this->ejakan($kanan).'?',
            $kiri + $kanan,
        ];
    }

    private function ejakan(int $angka): string
    {
        return (string) array_search($angka, self::KATA_ANGKA, true);
    }

    /** Menerima jawaban berupa angka maupun kata. */
    private function angkakan(string $jawaban): ?int
    {
        $bersih = Str::squish(Str::lower($jawaban));

        if (isset(self::KATA_ANGKA[$bersih])) {
            return self::KATA_ANGKA[$bersih];
        }

        return preg_match('/^-?\d+$/', $bersih) === 1 ? (int) $bersih : null;
    }
}
