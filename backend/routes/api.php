<?php

use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DataPribadiController;
use App\Http\Controllers\Api\KataSandiController;
use App\Http\Controllers\Api\PreferensiNotifikasiController;
use App\Http\Controllers\Api\Publik;
use App\Http\Controllers\Api\VerifikasiSurelController;
use App\Http\Controllers\Api\Warga;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API SIDESA — Sistem Informasi Website Desa
|--------------------------------------------------------------------------
| Seluruh titik akhir memakai prefiks /api/v1. Otorisasi diberlakukan di
| sisi server melalui middleware `izin` (REQ-F-USR-012, REQ-API-002).
*/

Route::prefix('v1')->group(function () {
    /* ---------------------------------------------------------------- Publik */
    Route::middleware('throttle:api')->group(function () {
        Route::get('beranda', Publik\BerandaController::class);
        Route::get('captcha', Publik\CaptchaController::class);
        Route::get('profil-desa', Publik\ProfilController::class);
        Route::get('pencarian', Publik\PencarianController::class);

        Route::get('kategori', [Publik\KontenController::class, 'kategori']);
        Route::get('agenda/kalender', [Publik\KontenController::class, 'kalender']);
        Route::get('konten/{tipe}', [Publik\KontenController::class, 'index']);
        Route::get('konten/{tipe}/{slug}', [Publik\KontenController::class, 'show'])->name('konten.show');

        Route::get('galeri', [Publik\GaleriController::class, 'index']);
        Route::get('galeri/{slug}', [Publik\GaleriController::class, 'show']);

        Route::get('apbdes', [Publik\ApbdesController::class, 'index']);
        Route::get('apbdes/{tahun}', [Publik\ApbdesController::class, 'show']);
        Route::get('apbdes/{tahun}/csv', [Publik\ApbdesController::class, 'csv']);

        Route::get('statistik', [Publik\StatistikController::class, 'index']);
        Route::get('statistik/periode', [Publik\StatistikController::class, 'periode']);

        Route::get('layanan', [Publik\LayananController::class, 'index']);
        Route::get('layanan/{slug}', [Publik\LayananController::class, 'show']);
        Route::get('surat/verifikasi/{kode}', [Publik\SuratController::class, 'verifikasi']);

        Route::get('pengaduan/kategori', [Publik\PengaduanController::class, 'kategori']);
        Route::get('pengaduan/publik', [Publik\PengaduanController::class, 'publik']);
        Route::get('pengaduan/lacak/{kode}', [Publik\PengaduanController::class, 'lacak']);

        Route::get('produk-hukum', [Publik\PustakaController::class, 'produkHukum']);
        Route::get('informasi-publik', [Publik\PustakaController::class, 'informasiPublik']);
        // REQ-F-PID-007: pelacakan permohonan informasi dan keberatannya.
        Route::get('permohonan-informasi/lacak/{kode}', [Publik\KeberatanController::class, 'lacak']);
        // REQ-F-KNT-013: komentar yang sudah lolos moderasi.
        Route::get('konten/{tipe}/{slug}/komentar', [Publik\KomentarController::class, 'index']);

        Route::get('umkm', [Publik\PotensiController::class, 'umkm']);
        Route::get('wisata', [Publik\PotensiController::class, 'wisata']);
        Route::get('bumdes', Publik\BumdesController::class);
        // REQ-API-004: dokumentasi OpenAPI, dibangkitkan dari tabel rute.
        Route::get('openapi.json', Publik\DokumentasiController::class);

        // REQ-F-ADM-003: susunan menu navigasi yang dipakai portal publik.
        Route::get('menu', Publik\MenuController::class);
        Route::get('lembaga', [Publik\LembagaController::class, 'index']);
        Route::get('lembaga/{slug}', [Publik\LembagaController::class, 'show']);
    });

    /*
     * Formulir publik dilindungi dua lapis (REQ-F-ADU-010): pembatasan laju
     * pengiriman dan tantangan CAPTCHA yang diverifikasi di sisi server.
     */
    Route::middleware(['throttle:formulir-publik', 'captcha'])->group(function () {
        Route::post('pengaduan', [Publik\PengaduanController::class, 'store']);
        Route::post('permohonan-informasi', [Publik\PustakaController::class, 'ajukanInformasi']);
        Route::post('umkm/daftar', [Publik\PotensiController::class, 'daftarUmkm']);
        Route::post('konten/{tipe}/{slug}/komentar', [Publik\KomentarController::class, 'store']);
        Route::post('permohonan-informasi/lacak/{kode}/keberatan', [Publik\KeberatanController::class, 'ajukan']);
    });

    /*
     * Pencatat kunjungan laman (REQ-SW-006). Lajunya dibatasi tersendiri karena
     * dipanggil pada setiap perpindahan laman.
     */
    Route::post('kunjungan', Publik\KunjunganController::class)->middleware('throttle:analitik');

    /* ------------------------------------------------------- Data terbuka */
    Route::prefix('terbuka')->middleware('throttle:terbuka')->group(function () {
        Route::get('/', [Publik\DataTerbukaController::class, 'indeks']);
        Route::get('statistik', [Publik\DataTerbukaController::class, 'statistik']);
        Route::get('apbdes', [Publik\DataTerbukaController::class, 'daftarApbdes']);
        Route::get('apbdes/{tahun}', [Publik\DataTerbukaController::class, 'apbdes'])->whereNumber('tahun');
        Route::get('layanan', [Publik\DataTerbukaController::class, 'layanan']);
        Route::get('produk-hukum', [Publik\DataTerbukaController::class, 'produkHukum']);
    });

    /* ------------------------------------------------------------ Autentikasi */
    Route::post('auth/daftar', [AuthController::class, 'daftar'])->middleware('throttle:registrasi');
    Route::post('auth/masuk', [AuthController::class, 'masuk'])->middleware('throttle:masuk');
    // REQ-F-USR-009: langkah kedua bagi peran berwenang.
    Route::post('auth/otp', [AuthController::class, 'verifikasiOtp'])->middleware('throttle:otp');

    // Pemulihan kata sandi dan verifikasi kepemilikan surel (REQ-F-USR-002, 006).
    Route::post('auth/lupa-kata-sandi', [KataSandiController::class, 'kirimTautan'])->middleware('throttle:pemulihan');
    Route::post('auth/atur-ulang-kata-sandi', [KataSandiController::class, 'aturUlang'])->middleware('throttle:pemulihan');
    Route::post('auth/kirim-ulang-verifikasi', [VerifikasiSurelController::class, 'kirimUlang'])->middleware('throttle:pemulihan');
    Route::get('auth/verifikasi-surel/{pengguna}/{sidik}', [VerifikasiSurelController::class, 'verifikasi'])
        ->name('verifikasi.surel')
        ->middleware('signed');

    // Unduhan surat memakai tautan bertanda tangan berbatas waktu (REQ-F-SRT-018).
    Route::get('surat/{surat}/unduh', [Warga\PermohonanController::class, 'unduh'])
        ->name('surat.unduh')
        ->middleware('signed');

    /* ------------------------------------------------- Area pengguna terautentikasi */
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('auth/keluar', [AuthController::class, 'keluar']);
        Route::get('auth/saya', [AuthController::class, 'saya']);
        Route::put('auth/profil', [AuthController::class, 'perbaruiProfil']);
        Route::put('auth/kata-sandi', [AuthController::class, 'ubahKataSandi']);
        // REQ-F-USR-015: riwayat masuk pemilik akun.
        Route::get('auth/riwayat-masuk', [AuthController::class, 'riwayatMasuk']);

        // Hak subjek data: salinan data pribadi dan permintaan penghapusan
        // (REQ-F-USR-013).
        Route::get('auth/data-pribadi', [DataPribadiController::class, 'ringkasan']);
        Route::get('auth/data-pribadi/unduh', [DataPribadiController::class, 'unduh']);
        Route::post('auth/data-pribadi/penghapusan', [DataPribadiController::class, 'ajukanPenghapusan']);

        // REQ-F-NOT-006: kanal notifikasi diatur pemilik akun sendiri.
        Route::get('auth/preferensi-notifikasi', [PreferensiNotifikasiController::class, 'tampil']);
        Route::put('auth/preferensi-notifikasi', [PreferensiNotifikasiController::class, 'simpan']);

        Route::prefix('permohonan')->group(function () {
            Route::get('/', [Warga\PermohonanController::class, 'index']);
            Route::post('/', [Warga\PermohonanController::class, 'store'])->middleware('throttle:unggah');
            Route::get('{permohonan}', [Warga\PermohonanController::class, 'show']);
            Route::put('{permohonan}/draf', [Warga\PermohonanController::class, 'simpanDraf']);
            Route::post('{permohonan}/kirim-ulang', [Warga\PermohonanController::class, 'kirimUlang']);
            Route::get('{permohonan}/tautan-surat', [Warga\PermohonanController::class, 'tautanSurat']);
            Route::post('{permohonan}/penilaian', [Warga\PermohonanController::class, 'nilai']);
        });
    });

    /* --------------------------------------------------------- Panel administrasi */
    Route::prefix('admin')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('dashboard', Admin\DashboardController::class)->middleware('izin:dashboard.lihat');

        // Lonceng pekerjaan baru; terbuka bagi seluruh petugas (REQ-F-NOT-005).
        Route::get('notifikasi', [Admin\NotifikasiController::class, 'index']);
        Route::post('notifikasi/baca-semua', [Admin\NotifikasiController::class, 'bacaSemua']);
        Route::post('notifikasi/{notifikasi}/baca', [Admin\NotifikasiController::class, 'baca']);

        // Antrean layanan surat
        Route::prefix('permohonan')->group(function () {
            Route::get('/', [Admin\PermohonanController::class, 'index'])->middleware('izin:permohonan.lihat');
            Route::get('laporan', [Admin\PermohonanController::class, 'laporan'])->middleware('izin:laporan.lihat');
            Route::get('laporan/csv', [Admin\PermohonanController::class, 'eksporCsv'])->middleware('izin:laporan.lihat');
            // REQ-F-SRT-023: pengunduhan massal surat yang telah terbit.
            Route::post('unduh-massal', [Admin\PermohonanController::class, 'unduhMassal'])
                ->middleware('izin:permohonan.lihat');
            Route::post('loket', [Admin\PermohonanController::class, 'buatkan'])->middleware('izin:permohonan.buat_loket');
            Route::get('{permohonan}', [Admin\PermohonanController::class, 'show'])->middleware('izin:permohonan.lihat');
            Route::post('{permohonan}/verifikasi', [Admin\PermohonanController::class, 'verifikasi'])->middleware('izin:permohonan.verifikasi');
            Route::post('{permohonan}/kembalikan', [Admin\PermohonanController::class, 'kembalikan'])->middleware('izin:permohonan.verifikasi');
            Route::post('{permohonan}/tolak', [Admin\PermohonanController::class, 'tolak'])->middleware('izin:permohonan.verifikasi');
            Route::post('{permohonan}/setujui', [Admin\PermohonanController::class, 'setujui'])->middleware('izin:permohonan.setujui');
            Route::post('{permohonan}/tanda-tangani', [Admin\PermohonanController::class, 'tandaTangani'])->middleware('izin:permohonan.tanda_tangan');
            Route::post('{permohonan}/batalkan-surat', [Admin\PermohonanController::class, 'batalkanSurat'])->middleware('izin:surat.batalkan');
        });

        // Manajemen konten
        Route::prefix('konten')->middleware('izin:konten.kelola')->group(function () {
            Route::get('/', [Admin\KontenController::class, 'index']);
            Route::post('/', [Admin\KontenController::class, 'store']);
            Route::get('{konten}', [Admin\KontenController::class, 'show']);
            Route::put('{konten}', [Admin\KontenController::class, 'update']);
            Route::post('{konten}/status', [Admin\KontenController::class, 'ubahStatus']);
            Route::post('{konten}/pulihkan/{versi}', [Admin\KontenController::class, 'pulihkan']);
            Route::delete('{konten}', [Admin\KontenController::class, 'destroy']);
        });

        // Media
        Route::prefix('media')->middleware('izin:konten.kelola')->group(function () {
            Route::get('/', [Admin\MediaController::class, 'index']);
            Route::post('/', [Admin\MediaController::class, 'store'])->middleware('throttle:unggah');
            Route::put('{media}', [Admin\MediaController::class, 'update']);
            Route::delete('{media}', [Admin\MediaController::class, 'destroy']);
            Route::match(['get', 'post'], 'album/daftar', [Admin\MediaController::class, 'album']);

            // REQ-F-GAL-006: video dari penyedia eksternal pada album galeri.
            Route::get('album/{album}/video', [Admin\VideoAlbumController::class, 'index']);
            Route::post('album/{album}/video', [Admin\VideoAlbumController::class, 'simpan']);
            Route::delete('album/{album}/video/{video}', [Admin\VideoAlbumController::class, 'hapus']);
        });

        // Pengaduan
        Route::prefix('pengaduan')->group(function () {
            Route::get('/', [Admin\PengaduanController::class, 'index'])->middleware('izin:pengaduan.lihat');
            Route::get('{pengaduan}', [Admin\PengaduanController::class, 'show'])->middleware('izin:pengaduan.lihat');
            Route::post('{pengaduan}/status', [Admin\PengaduanController::class, 'ubahStatus'])->middleware('izin:pengaduan.kelola');
            Route::post('{pengaduan}/disposisi', [Admin\PengaduanController::class, 'disposisi'])->middleware('izin:pengaduan.disposisi');
            Route::post('{pengaduan}/tanggapan', [Admin\PengaduanController::class, 'tanggapi'])->middleware('izin:pengaduan.kelola');
            Route::post('{pengaduan}/publikasi', [Admin\PengaduanController::class, 'publikasi'])->middleware('izin:pengaduan.moderasi');
        });

        // Transparansi anggaran
        Route::prefix('apbdes')->group(function () {
            Route::get('/', [Admin\ApbdesController::class, 'index'])->middleware('izin:apbdes.kelola');
            Route::post('/', [Admin\ApbdesController::class, 'store'])->middleware('izin:apbdes.kelola');
            Route::get('{tahunAnggaran}', [Admin\ApbdesController::class, 'show'])->middleware('izin:apbdes.kelola');
            Route::post('{tahunAnggaran}/item', [Admin\ApbdesController::class, 'simpanItem'])->middleware('izin:apbdes.kelola');
            Route::post('{tahunAnggaran}/impor', [Admin\ApbdesController::class, 'impor'])->middleware('izin:apbdes.kelola');
            Route::post('{tahunAnggaran}/publikasi', [Admin\ApbdesController::class, 'publikasikan'])->middleware('izin:apbdes.publikasi');
        });

        // Statistik desa
        Route::prefix('statistik')->middleware('izin:statistik.kelola')->group(function () {
            Route::get('/', [Admin\StatistikController::class, 'index']);
            Route::post('/', [Admin\StatistikController::class, 'store']);
            Route::post('{periodeStatistik}/item', [Admin\StatistikController::class, 'simpanItem']);
            Route::post('{periodeStatistik}/aktifkan', [Admin\StatistikController::class, 'aktifkan']);
        });

        // Pengguna dan peran
        Route::prefix('pengguna')->group(function () {
            Route::get('/', [Admin\PenggunaController::class, 'index'])->middleware('izin:pengguna.lihat');
            Route::post('/', [Admin\PenggunaController::class, 'store'])->middleware('izin:pengguna.kelola');
            Route::get('peran', [Admin\PenggunaController::class, 'peran'])->middleware('izin:pengguna.lihat');
            Route::post('{user}/verifikasi-nik', [Admin\PenggunaController::class, 'verifikasiNik'])->middleware('izin:pengguna.verifikasi');
            Route::post('{user}/peran', [Admin\PenggunaController::class, 'ubahPeran'])->middleware('izin:pengguna.kelola');
            Route::post('{user}/status', [Admin\PenggunaController::class, 'ubahStatus'])->middleware('izin:pengguna.kelola');
            Route::post('{user}/atur-ulang-kata-sandi', [Admin\PenggunaController::class, 'aturUlangKataSandi'])->middleware('izin:pengguna.kelola');
        });

        // Moderasi komentar (REQ-F-KNT-013) dan keberatan informasi (REQ-F-PID-007)
        Route::get('komentar', [Admin\KomentarController::class, 'index'])->middleware('izin:konten.kelola');
        Route::post('komentar/{komentar}/moderasi', [Admin\KomentarController::class, 'moderasi'])
            ->middleware('izin:konten.kelola');
        Route::get('keberatan-informasi', [Admin\KeberatanController::class, 'index'])->middleware('izin:konten.terbit');
        Route::post('keberatan-informasi/{keberatan}/tanggapi', [Admin\KeberatanController::class, 'tanggapi'])
            ->middleware('izin:konten.terbit');

        // Referensi: produk hukum & UMKM
        Route::post('produk-hukum', [Admin\ReferensiController::class, 'simpanProdukHukum'])->middleware('izin:konten.kelola');
        Route::put('produk-hukum/{produkHukum}', [Admin\ReferensiController::class, 'simpanProdukHukum'])->middleware('izin:konten.kelola');
        Route::delete('produk-hukum/{produkHukum}', [Admin\ReferensiController::class, 'hapusProdukHukum'])->middleware('izin:konten.kelola');
        Route::get('umkm/menunggu', [Admin\ReferensiController::class, 'umkmMenunggu'])->middleware('izin:konten.kelola');
        Route::post('umkm/{umkm}/verifikasi', [Admin\ReferensiController::class, 'verifikasiUmkm'])->middleware('izin:konten.kelola');

        // Spesimen tanda tangan pejabat penanda tangan (REQ-F-SRT-017)
        Route::prefix('tanda-tangan')->middleware('izin:permohonan.tanda_tangan')->group(function () {
            Route::get('/', [Admin\TandaTanganController::class, 'status']);
            Route::post('/', [Admin\TandaTanganController::class, 'simpan'])->middleware('throttle:unggah');
            Route::get('pratinjau', [Admin\TandaTanganController::class, 'pratinjau']);
            Route::delete('/', [Admin\TandaTanganController::class, 'hapus']);
        });

        // BUMDes: unit usaha dan kinerja
        Route::prefix('bumdes')->group(function () {
            Route::get('/', [Admin\BumdesController::class, 'index'])->middleware('izin:bumdes.kelola');
            Route::post('unit', [Admin\BumdesController::class, 'simpanUnit'])->middleware('izin:bumdes.kelola');
            Route::put('unit/{unitUsaha}', [Admin\BumdesController::class, 'simpanUnit'])->middleware('izin:bumdes.kelola');
            Route::delete('unit/{unitUsaha}', [Admin\BumdesController::class, 'hapusUnit'])->middleware('izin:bumdes.kelola');
            Route::post('kinerja', [Admin\BumdesController::class, 'simpanKinerja'])->middleware('izin:bumdes.kelola');
            Route::post('kinerja/{kinerjaBumdes}/publikasi', [Admin\BumdesController::class, 'publikasikanKinerja'])
                ->middleware('izin:bumdes.publikasi');
            Route::delete('kinerja/{kinerjaBumdes}', [Admin\BumdesController::class, 'hapusKinerja'])
                ->middleware('izin:bumdes.kelola');
        });

        // Pengaturan situs dan audit
        Route::get('pengaturan', [Admin\PengaturanController::class, 'index'])->middleware('izin:pengaturan.kelola');
        Route::put('pengaturan', [Admin\PengaturanController::class, 'update'])->middleware('izin:pengaturan.kelola');

        // REQ-F-ADM-003: pengelola menu navigasi.
        Route::prefix('menu')->middleware('izin:pengaturan.kelola')->group(function () {
            Route::get('/', [Admin\MenuController::class, 'index']);
            Route::post('/', [Admin\MenuController::class, 'simpan']);
            Route::post('urutkan', [Admin\MenuController::class, 'urutkan']);
            Route::put('{menu}', [Admin\MenuController::class, 'simpan']);
            Route::delete('{menu}', [Admin\MenuController::class, 'hapus']);
        });
        Route::get('audit-log', [Admin\AuditLogController::class, 'index'])->middleware('izin:audit.lihat');
        Route::get('analitik', Admin\AnalitikController::class)->middleware('izin:laporan.lihat');

        // Permintaan hak subjek data (REQ-F-USR-013)
        Route::get('permintaan-data', [Admin\PermintaanDataController::class, 'index'])
            ->middleware('izin:data_pribadi.kelola');
        Route::post('permintaan-data/{permintaan}/tindak', [Admin\PermintaanDataController::class, 'tindak'])
            ->middleware('izin:data_pribadi.kelola');
    });
});
