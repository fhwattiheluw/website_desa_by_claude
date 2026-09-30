<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->daftarkanPembatasLaju();
        $this->daftarkanPencarianTeks();
    }

    /**
     * Pencarian teks yang tidak membedakan huruf besar dan kecil pada seluruh
     * mesin basis data (REQ-F-SRC-001).
     *
     * `LIKE` di SQLite dan MySQL mengabaikan besar kecil huruf, tetapi di
     * PostgreSQL tidak. Tanpa penyeragaman ini, pencarian "Berita" tidak
     * menemukan "berita" begitu desa memakai PostgreSQL — gagal diam-diam,
     * tanpa galat apa pun.
     */
    private function daftarkanPencarianTeks(): void
    {
        Builder::macro('cariTeks', function (string|array $kolom, ?string $kata) {
            /** @var Builder $this */
            if (blank($kata)) {
                return $this;
            }

            $operator = $this->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $pola = '%'.$kata.'%';

            return $this->where(function (Builder $kueri) use ($kolom, $operator, $pola) {
                foreach ((array) $kolom as $satu) {
                    $kueri->orWhere($satu, $operator, $pola);
                }
            });
        });
    }

    /**
     * Pembatasan laju pada titik akhir sensitif dan formulir publik
     * (REQ-NF-SEC-009, REQ-F-ADU-010, REQ-API-003).
     */
    private function daftarkanPembatasLaju(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        // Batas per akun dibuat ketat untuk menahan penebakan kata sandi, sedangkan
        // batas per alamat IP lebih longgar karena satu kantor desa lazim berbagi
        // satu koneksi internet (REQ-NF-SEC-009, CON-02).
        RateLimiter::for('masuk', fn (Request $request) => [
            Limit::perMinute(5)->by('masuk-akun:'.strtolower((string) $request->input('email'))),
            Limit::perMinute(20)->by('masuk-ip:'.$request->ip()),
        ]);

        /*
         * Verifikasi kode masuk memakai pembatas tersendiri, bukan pembatas
         * `masuk` (REQ-F-USR-009). Permintaan ini tidak membawa alamat surel,
         * sehingga bila memakai pembatas yang sama seluruh petugas akan berbagi
         * satu kuota — lima percobaan per menit untuk sekantor, dan petugas
         * kedua yang masuk pada menit yang sama akan tertolak.
         *
         * Kuncinya tantangan masing-masing: kekeliruan satu orang tidak pernah
         * menjegal yang lain. Batas percobaan sesungguhnya ada pada OtpService;
         * yang di sini hanya penahan banjir permintaan.
         */
        RateLimiter::for('otp', fn (Request $request) => [
            Limit::perMinute(10)->by('otp-tantangan:'.$request->input('tantangan')),
            Limit::perMinute(30)->by('otp-ip:'.$request->ip()),
        ]);

        RateLimiter::for('registrasi', fn (Request $request) => Limit::perHour(5)->by($request->ip()));

        RateLimiter::for('pemulihan', fn (Request $request) => [
            Limit::perMinute(3)->by('pemulihan-akun:'.strtolower((string) $request->input('email'))),
            Limit::perHour(15)->by('pemulihan-ip:'.$request->ip()),
        ]);

        RateLimiter::for('formulir-publik', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip()),
            Limit::perDay(20)->by($request->ip()),
        ]);

        RateLimiter::for('unggah', fn (Request $request) => Limit::perMinute(20)
            ->by($request->user()?->id ?: $request->ip()));

        // Kuota API data terbuka: cukup untuk pemakaian wajar, cukup ketat untuk
        // mencegah pengambilan massal yang membebani server desa (REQ-API-005).
        RateLimiter::for('terbuka', fn (Request $request) => [
            Limit::perMinute(60)->by('terbuka:'.$request->ip()),
            Limit::perDay(2000)->by('terbuka:'.$request->ip()),
        ]);

        // Pencatat kunjungan dipanggil sekali per perpindahan laman, jadi
        // kuotanya lebih longgar daripada formulir namun tetap terbatas agar
        // angka tidak mudah digelembungkan (REQ-SW-006).
        RateLimiter::for('analitik', fn (Request $request) => Limit::perMinute(120)
            ->by('analitik:'.$request->ip()));
    }
}
