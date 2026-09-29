<?php

/*
|--------------------------------------------------------------------------
| Analitik web yang menghormati privasi (REQ-SW-006)
|--------------------------------------------------------------------------
| Kunjungan dihitung sendiri oleh sistem, tanpa skrip pihak ketiga, tanpa
| kuki, dan tanpa menyimpan alamat IP, tapak peramban, atau pengenal
| pengunjung. Yang tersimpan hanya jumlah kunjungan per jalur laman per hari,
| sehingga tidak ada data pribadi yang dapat dikaitkan dengan seseorang.
*/

return [
    'aktif' => (bool) env('ANALITIK_AKTIF', true),

    // Panjang maksimum jalur yang dicatat; jalur lebih panjang diabaikan.
    'panjang_jalur' => 120,
];
