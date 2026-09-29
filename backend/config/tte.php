<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Metode Penandatanganan Surat
    |--------------------------------------------------------------------------
    |
    | "internal" membubuhkan gambar spesimen tanda tangan pejabat ke dokumen
    | dan mengandalkan kode QR untuk verifikasi keasliannya. "psre" mengirim
    | dokumen ke Penyelenggara Sertifikasi Elektronik (BSrE atau PSrE lain)
    | untuk ditandatangani secara tersertifikasi (REQ-F-SRT-017, REQ-SW-004).
    |
    | Selama konfigurasi psre belum lengkap, sistem otomatis kembali ke metode
    | internal agar pelayanan surat tidak terhenti.
    |
    */

    'driver' => env('TTE_DRIVER', 'internal'),

    'psre' => [
        'nama_penyedia' => env('TTE_PSRE_NAMA', 'Balai Sertifikasi Elektronik'),

        // Titik akhir penandatanganan milik penyedia.
        'url' => env('TTE_PSRE_URL'),

        // Kredensial layanan. Simpan hanya pada berkas .env, jangan di repositori.
        'token' => env('TTE_PSRE_TOKEN'),
        'passphrase' => env('TTE_PSRE_PASSPHRASE'),

        // "invisible" tanpa panel tampak, "visible" dengan panel tanda tangan.
        'tampilan' => env('TTE_PSRE_TAMPILAN', 'invisible'),

        // Sebagian penyedia dapat menempelkan gambar spesimen pada panel.
        'sematkan_gambar' => env('TTE_PSRE_SEMATKAN_GAMBAR', false),

        'timeout' => env('TTE_PSRE_TIMEOUT', 60),
    ],

];
