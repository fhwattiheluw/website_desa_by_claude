<?php

namespace App\Services\Pemindai;

/** Hasil pemindaian satu berkas. */
readonly class HasilPindai
{
    public function __construct(
        public bool $bersih,
        public string $pemindai,
        public ?string $temuan = null,
    ) {}

    public static function bersih(string $pemindai): self
    {
        return new self(true, $pemindai);
    }

    public static function terinfeksi(string $pemindai, string $temuan): self
    {
        return new self(false, $pemindai, $temuan);
    }
}
