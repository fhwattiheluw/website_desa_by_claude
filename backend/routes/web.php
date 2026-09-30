<?php

use App\Http\Controllers\KerangkaAplikasiController;
use App\Http\Controllers\PetaSitusController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute Web
|--------------------------------------------------------------------------
| Aplikasi ini melayani API, beberapa berkas yang menurut kelaziman web harus
| berada di akar domain, serta kerangka aplikasi React beserta metadata yang
| sudah terisi bagi perayap yang tidak menjalankan JavaScript.
*/

// REQ-F-SRC-003: sitemap dan robots dibuat otomatis dari konten yang tayang.
Route::get('/sitemap.xml', [PetaSitusController::class, 'petaSitus'])->name('peta-situs');
Route::get('/robots.txt', [PetaSitusController::class, 'robots'])->name('robots');

// REQ-F-SRC-006: umpan RSS berita dan pengumuman.
Route::get('/rss/{tipe}.xml', [PetaSitusController::class, 'rss'])
    ->whereIn('tipe', ['berita', 'pengumuman'])
    ->name('rss');

/*
 * REQ-F-SRC-004: seluruh alamat lain dijawab dengan kerangka aplikasi yang
 * judul, deskripsi, URL kanonik, dan metadata Open Graph-nya sudah terisi.
 *
 * Diletakkan paling akhir agar tidak pernah mendahului rute di atasnya, dan
 * dibatasi pada alamat yang bukan berkas: permintaan berkas statis dilayani
 * langsung oleh peladen web, tidak perlu melewati PHP.
 */
Route::get('/{jalur?}', KerangkaAplikasiController::class)
    ->where('jalur', '^(?!api/|storage/)[^.]*$')
    ->name('kerangka');
