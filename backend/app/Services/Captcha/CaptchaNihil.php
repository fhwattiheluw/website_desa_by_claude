<?php

namespace App\Services\Captcha;

/**
 * Penyedia kosong: tantangan dimatikan.
 *
 * Dipakai pada uji otomatis dan pengembangan lokal. Pembatasan laju pada grup
 * rute `formulir-publik` tetap berlaku, sehingga formulir tidak pernah tanpa
 * pelindung sama sekali.
 */
class CaptchaNihil implements Captcha
{
    public function metode(): string
    {
        return 'nihil';
    }

    public function aktif(): bool
    {
        return false;
    }

    public function tantangan(): array
    {
        return ['aktif' => false, 'metode' => $this->metode()];
    }

    public function periksa(?string $token, ?string $jawaban): bool
    {
        return true;
    }
}
