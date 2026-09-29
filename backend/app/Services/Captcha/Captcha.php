<?php

namespace App\Services\Captcha;

/**
 * Kontrak penyedia anti-penyalahgunaan formulir publik (REQ-SW-007).
 *
 * Verifikasi selalu berjalan di sisi server: nilai yang dikirim peramban tidak
 * pernah dipercaya apa adanya.
 */
interface Captcha
{
    /** Pengenal penyedia yang dicatat pada audit log. */
    public function metode(): string;

    /** Apakah tantangan diberlakukan pada formulir publik. */
    public function aktif(): bool;

    /**
     * Bahan tantangan yang boleh dikirim ke peramban. Kunci jawaban tidak
     * pernah termasuk di dalamnya.
     *
     * @return array<string, mixed>
     */
    public function tantangan(): array;

    /** Memeriksa jawaban atau token yang dikirim bersama formulir. */
    public function periksa(?string $token, ?string $jawaban): bool;
}
