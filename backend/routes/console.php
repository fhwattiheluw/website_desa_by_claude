<?php

use App\Models\Konten;
use App\Services\NotifikasiService;
use App\Services\PermohonanService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/** BR-07: menutup permohonan dikembalikan yang tidak diperbaiki tepat waktu. */
Artisan::command('sidesa:tutup-permohonan-kedaluwarsa', function (PermohonanService $layanan) {
    $this->info('Permohonan kedaluwarsa ditutup: '.$layanan->tutupYangKedaluwarsa());
})->purpose('Menutup permohonan berstatus dikembalikan yang melewati batas perbaikan');

/** REQ-F-SRT-026: retensi lampiran 12 bulan setelah permohonan selesai. */
Artisan::command('sidesa:bersihkan-lampiran', function (PermohonanService $layanan) {
    $this->info('Permohonan dibersihkan lampirannya: '.$layanan->bersihkanLampiranKedaluwarsa());
})->purpose('Menghapus lampiran permohonan yang melewati masa retensi');

/** REQ-F-NOT-003: percobaan ulang notifikasi yang gagal terkirim. */
Artisan::command('sidesa:ulangi-notifikasi', function (NotifikasiService $notifikasi) {
    $this->info('Notifikasi berhasil dikirim ulang: '.$notifikasi->cobaUlangYangGagal());
})->purpose('Mengirim ulang notifikasi yang sebelumnya gagal');

/** REQ-F-KNT-004 & BR-09: penjadwalan terbit dan pengarsipan otomatis. */
Artisan::command('sidesa:segarkan-konten', function () {
    $diarsipkan = Konten::where('status', 'terbit')
        ->whereNotNull('kedaluwarsa_pada')
        ->where('kedaluwarsa_pada', '<=', now())
        ->update(['status' => 'arsip']);

    $this->info("Konten diarsipkan otomatis: {$diarsipkan}");
})->purpose('Mengarsipkan pengumuman yang telah melewati tanggal kedaluwarsa');

Schedule::command('sidesa:tutup-permohonan-kedaluwarsa')->dailyAt('01:00');
Schedule::command('sidesa:bersihkan-lampiran')->dailyAt('01:30');
Schedule::command('sidesa:segarkan-konten')->hourly();
Schedule::command('sidesa:ulangi-notifikasi')->everyFifteenMinutes();
