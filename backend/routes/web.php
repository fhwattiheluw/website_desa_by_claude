<?php

use App\Http\Controllers\PetaSitusController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute Web
|--------------------------------------------------------------------------
| Aplikasi ini melayani API dan beberapa berkas yang menurut kelaziman web
| harus berada di akar domain. Antarmuka pengguna dilayani terpisah oleh
| aplikasi React.
*/

Route::get('/', fn () => redirect()->away((string) config('app.frontend_url')));

// REQ-F-SRC-003: sitemap dan robots dibuat otomatis dari konten yang tayang.
Route::get('/sitemap.xml', [PetaSitusController::class, 'petaSitus'])->name('peta-situs');
Route::get('/robots.txt', [PetaSitusController::class, 'robots'])->name('robots');

// REQ-F-SRC-006: umpan RSS berita dan pengumuman.
Route::get('/rss/{tipe}.xml', [PetaSitusController::class, 'rss'])
    ->whereIn('tipe', ['berita', 'pengumuman'])
    ->name('rss');
