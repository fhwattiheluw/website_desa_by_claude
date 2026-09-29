<?php

/*
|--------------------------------------------------------------------------
| Anti-penyalahgunaan formulir publik (REQ-SW-007, REQ-F-ADU-010)
|--------------------------------------------------------------------------
| Verifikasi selalu dilakukan di sisi server. Pemasang dapat memilih
| tantangan bawaan yang tidak memerlukan layanan luar, atau layanan pihak
| ketiga bila desa memilikinya.
|
| - bawaan   : tantangan aritmatika berbasis teks, tanpa jaringan keluar.
|              Dapat dibaca pembaca layar sehingga tidak melanggar WCAG.
| - turnstile: Cloudflare Turnstile, token diverifikasi ke titik akhir resmi.
| - nihil    : dimatikan. Hanya untuk uji otomatis dan pengembangan lokal;
|              pembatasan laju tetap berlaku.
*/

return [
    'driver' => env('CAPTCHA_DRIVER', 'bawaan'),

    // Masa berlaku satu tantangan bawaan. Sekali dipakai langsung hangus.
    'kedaluwarsa_menit' => (int) env('CAPTCHA_KEDALUWARSA_MENIT', 10),

    'turnstile' => [
        'kunci_situs' => env('TURNSTILE_SITE_KEY'),
        'kunci_rahasia' => env('TURNSTILE_SECRET_KEY'),
        'titik_akhir' => env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
        'timeout' => (int) env('TURNSTILE_TIMEOUT', 5),
    ],
];
