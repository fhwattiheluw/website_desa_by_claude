<?php

/*
|--------------------------------------------------------------------------
| Pemindaian berkas unggahan (REQ-NF-SEC-008)
|--------------------------------------------------------------------------
| Berkas lampiran warga memuat identitas dan dibuka petugas desa, sehingga
| berkas berbahaya yang lolos berdampak langsung pada komputer kantor desa.
|
| - nihil  : tanpa pemindai. Pemeriksaan tipe asli, batas ukuran, dan penolakan
|            berkas berisi skrip pada MediaService tetap berlaku.
| - clamav : mengirim berkas ke daemon clamd sebelum disimpan permanen.
*/

return [
    'driver' => env('PEMINDAI_DRIVER', 'nihil'),

    'clamav' => [
        // Contoh: unix:///var/run/clamav/clamd.ctl atau tcp://127.0.0.1:3310
        'alamat' => env('CLAMAV_ALAMAT'),
        'timeout' => (int) env('CLAMAV_TIMEOUT', 10),
    ],
];
