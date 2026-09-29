<?php

namespace App\Services;

use App\Models\KunjunganHarian;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Analitik web tanpa data pribadi (REQ-SW-006).
 *
 * Hanya jumlah kunjungan per jalur laman per hari yang disimpan. Tidak ada
 * alamat IP, kuki, pengenal sesi, maupun perujuk, sehingga angka ini tidak
 * dapat dipakai untuk melacak seseorang dan tidak tergolong data pribadi.
 */
class AnalitikService
{
    /**
     * Wilayah yang tidak dihitung: laman milik akun dan panel petugas.
     *
     * Jalurnya memuat pengenal seperti nomor permohonan, dan jumlah
     * kunjungannya tidak menggambarkan minat publik terhadap informasi desa.
     */
    private const JALUR_DIKECUALIKAN = ['/akun', '/admin', '/masuk', '/daftar', '/lupa-kata-sandi', '/atur-ulang-kata-sandi'];

    public function aktif(): bool
    {
        return (bool) config('analitik.aktif');
    }

    /**
     * Mencatat satu kunjungan. Mengembalikan false bila analitik dimatikan atau
     * jalur tidak berbentuk jalur laman yang wajar.
     */
    public function catat(string $jalur): bool
    {
        if (! $this->aktif()) {
            return false;
        }

        $bersih = $this->bakukanJalur($jalur);

        if ($bersih === null) {
            return false;
        }

        $tanggal = now()->toDateString();
        $tabel = (new KunjunganHarian)->getTable();

        $terpengaruh = DB::table($tabel)
            ->where('tanggal', $tanggal)
            ->where('jalur', $bersih)
            ->increment('jumlah');

        if ($terpengaruh > 0) {
            return true;
        }

        try {
            DB::table($tabel)->insert([
                'tanggal' => $tanggal,
                'jalur' => $bersih,
                'jumlah' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException) {
            // Baris dibuat permintaan lain pada saat yang sama; cukup naikkan.
            DB::table($tabel)->where('tanggal', $tanggal)->where('jalur', $bersih)->increment('jumlah');
        }

        return true;
    }

    /**
     * Ringkasan kunjungan untuk panel petugas.
     *
     * @return array<string, mixed>
     */
    public function ringkasan(int $hari = 30): array
    {
        $mulai = now()->subDays($hari - 1)->toDateString();
        $tabel = (new KunjunganHarian)->getTable();

        $perHari = DB::table($tabel)
            ->selectRaw('tanggal, SUM(jumlah) as jumlah')
            ->where('tanggal', '>=', $mulai)
            ->groupBy('tanggal')
            ->orderBy('tanggal')
            ->get()
            ->map(fn ($baris) => ['tanggal' => $baris->tanggal, 'jumlah' => (int) $baris->jumlah]);

        $lamanTeratas = DB::table($tabel)
            ->selectRaw('jalur, SUM(jumlah) as jumlah')
            ->where('tanggal', '>=', $mulai)
            ->groupBy('jalur')
            ->orderByDesc('jumlah')
            ->limit(15)
            ->get()
            ->map(fn ($baris) => ['jalur' => $baris->jalur, 'jumlah' => (int) $baris->jumlah]);

        return [
            'aktif' => $this->aktif(),
            'rentang_hari' => $hari,
            'mulai' => $mulai,
            'total_kunjungan' => (int) $perHari->sum('jumlah'),
            'per_hari' => $perHari->values(),
            'laman_teratas' => $lamanTeratas->values(),
            'catatan' => 'Angka dihitung tanpa kuki dan tanpa menyimpan alamat IP, sehingga mewakili jumlah '
                .'pembukaan laman, bukan jumlah orang.',
        ];
    }

    /**
     * Menerima hanya jalur laman yang wajar: diawali garis miring, tanpa
     * parameter kueri, dan tanpa karakter di luar pola jalur. Ini sekaligus
     * mencegah data pribadi ikut tercatat lewat parameter URL.
     */
    private function bakukanJalur(string $jalur): ?string
    {
        $jalur = '/'.trim(strtok($jalur, '?#') ?: '', '/');

        if (mb_strlen($jalur) > (int) config('analitik.panjang_jalur', 120)) {
            return null;
        }

        if (preg_match('#^/[A-Za-z0-9\-_/]*$#', $jalur) !== 1) {
            return null;
        }

        foreach (self::JALUR_DIKECUALIKAN as $awalan) {
            if ($jalur === $awalan || str_starts_with($jalur, $awalan.'/')) {
                return null;
            }
        }

        return $jalur;
    }
}
