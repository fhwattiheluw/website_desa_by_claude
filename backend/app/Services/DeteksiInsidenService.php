<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Pengaturan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Deteksi dini indikasi kebocoran data (REQ-NF-CMP-005).
 *
 * UU 27/2022 Pasal 46 mewajibkan pengendali data memberi tahu subjek data dan
 * lembaga berwenang paling lambat 3x24 jam sejak kebocoran diketahui. Tenggat
 * itu hanya dapat dipenuhi bila ada yang memperhatikan; berkas ini memeriksa
 * pola pada jejak audit dan memicu peringatan agar petugas menilai lebih lanjut.
 *
 * Penilaian akhir tetap manusia: keluaran di sini adalah indikasi, bukan
 * kesimpulan bahwa kebocoran benar terjadi. Prosedur lengkap ada pada
 * docs/OPERASIONAL.md.
 */
class DeteksiInsidenService
{
    /** Jendela pengamatan dalam jam. */
    public const JENDELA_JAM = 24;

    /** Kegagalan masuk dari satu alamat IP: indikasi percobaan penerobosan. */
    public const BATAS_GAGAL_MASUK = 30;

    /** Pengunduhan berkas data pribadi oleh satu pelaku. */
    public const BATAS_UNDUH_DATA_PRIBADI = 10;

    /** Ekspor laporan berisi data warga oleh satu petugas. */
    public const BATAS_EKSPOR_LAPORAN = 10;

    /** Perubahan peran akun: kenaikan hak akses yang tidak wajar. */
    public const BATAS_UBAH_PERAN = 5;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotifikasiService $notifikasi,
    ) {}

    /**
     * Memeriksa jendela pengamatan terakhir dan melaporkan temuannya.
     *
     * @return array<int, array<string, mixed>>
     */
    public function periksa(): array
    {
        $sejak = now()->subHours(self::JENDELA_JAM);

        $temuan = [
            ...$this->kelompokMelebihiBatas($sejak, 'login_gagal', 'alamat_ip', self::BATAS_GAGAL_MASUK,
                'Percobaan masuk gagal berulang dari satu alamat IP'),
            ...$this->kelompokMelebihiBatas($sejak, 'unduh_data_pribadi', 'aktor_id', self::BATAS_UNDUH_DATA_PRIBADI,
                'Pengunduhan berkas data pribadi dalam jumlah tidak wajar'),
            ...$this->kelompokMelebihiBatas($sejak, 'ekspor_laporan', 'aktor_id', self::BATAS_EKSPOR_LAPORAN,
                'Ekspor laporan berisi data warga dalam jumlah tidak wajar'),
            ...$this->kelompokMelebihiBatas($sejak, 'ubah_peran', 'aktor_id', self::BATAS_UBAH_PERAN,
                'Perubahan peran akun dalam jumlah tidak wajar'),
        ];

        foreach ($temuan as $satu) {
            $this->laporkan($satu);
        }

        return $temuan;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function kelompokMelebihiBatas(
        \DateTimeInterface $sejak,
        string $aksi,
        string $kolom,
        int $batas,
        string $keterangan,
    ): array {
        return DB::table((new AuditLog)->getTable())
            ->selectRaw("{$kolom} as kunci, COUNT(*) as jumlah")
            ->where('aksi', $aksi)
            ->where('created_at', '>=', $sejak)
            ->whereNotNull($kolom)
            ->groupBy($kolom)
            ->having('jumlah', '>', $batas)
            ->get()
            ->map(fn ($baris) => [
                'indikasi' => $aksi,
                'keterangan' => $keterangan,
                'menurut' => $kolom,
                'nilai' => (string) $baris->kunci,
                'jumlah' => (int) $baris->jumlah,
                'batas' => $batas,
                'jendela_jam' => self::JENDELA_JAM,
            ])
            ->all();
    }

    /** @param array<string, mixed> $temuan */
    private function laporkan(array $temuan): void
    {
        // Dicatat pada jejak audit agar waktu "diketahui" punya bukti tanggal.
        $this->audit->catat('insiden_terdeteksi', 'AuditLog', null, null, $temuan);

        Log::alert('Indikasi insiden keamanan terdeteksi.', $temuan);

        foreach ($this->penerima() as $surel) {
            $this->notifikasi->kirim('email', $surel, 'insiden_keamanan', [
                'nama' => 'Administrator',
                'keterangan' => $temuan['keterangan'],
                'jumlah' => $temuan['jumlah'],
                'batas' => $temuan['batas'],
                'jendela_jam' => $temuan['jendela_jam'],
            ]);
        }
    }

    /**
     * Administrator sistem beserta surel resmi desa sebagai penerima cadangan.
     *
     * @return array<int, string>
     */
    private function penerima(): array
    {
        $surel = User::whereHas('role', fn ($q) => $q->where('kode', Role::ADMIN))
            ->where('status_akun', User::AKTIF)
            ->pluck('email')
            ->all();

        $resmi = Pengaturan::semua()['email'] ?? null;

        if (filled($resmi)) {
            $surel[] = $resmi;
        }

        return array_values(array_unique(array_filter($surel)));
    }
}
