<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Kebijakan retensi data (REQ-NF-CMP-004, UU 27/2022).
 *
 * Retensi lampiran layanan ditangani PermohonanService; berkas ini mengurus dua
 * sisanya: jejak audit 24 bulan dan peninjauan akun tidak aktif 36 bulan.
 */
class RetensiService
{
    public const AUDIT_BULAN = 24;

    public const AKUN_TIDAK_AKTIF_BULAN = 36;

    /** Jeda sebelum akun yang sama ditandai lagi bila belum ditindak. */
    public const JEDA_TINJAUAN_BULAN = 12;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Menghapus jejak audit yang melewati masa retensi.
     *
     * Dilakukan lewat query builder, bukan model: model AuditLog memang menolak
     * penghapusan agar tidak ada jalur menghapus jejak satu per satu
     * (REQ-F-ADM-005). Pemangkasan terjadwal ini adalah satu-satunya
     * pengecualian, dan ia sendiri meninggalkan catatan.
     */
    public function bersihkanAuditLog(): int
    {
        $batas = now()->subMonths(self::AUDIT_BULAN);

        $jumlah = DB::table((new AuditLog)->getTable())->where('created_at', '<', $batas)->delete();

        if ($jumlah > 0) {
            $this->audit->catat('retensi_audit_log', 'AuditLog', null, null, [
                'dihapus' => $jumlah,
                'batas' => $batas->toIso8601String(),
                'retensi_bulan' => self::AUDIT_BULAN,
            ]);
        }

        return $jumlah;
    }

    /**
     * Menandai akun warga yang tidak dipakai melewati batas agar ditinjau
     * petugas. Statusnya tidak diubah otomatis: keputusan menonaktifkan tetap
     * pada petugas desa.
     *
     * @return int jumlah akun yang baru ditandai
     */
    public function tinjauAkunTidakAktif(): int
    {
        $batas = now()->subMonths(self::AKUN_TIDAK_AKTIF_BULAN);
        $jedaTinjauan = now()->subMonths(self::JEDA_TINJAUAN_BULAN);

        $akun = User::query()
            ->whereHas('role', fn ($q) => $q->where('kode', Role::WARGA))
            ->where('status_akun', User::AKTIF)
            // Akun yang belum pernah dipakai dihitung dari tanggal pendaftaran.
            ->whereRaw('COALESCE(last_login_at, created_at) < ?', [$batas])
            ->where(fn ($q) => $q->whereNull('tinjauan_akun_pada')->orWhere('tinjauan_akun_pada', '<', $jedaTinjauan))
            ->get();

        foreach ($akun as $satu) {
            $satu->forceFill(['tinjauan_akun_pada' => now()])->save();

            $this->audit->catat('akun_tidak_aktif_ditinjau', 'User', $satu->id, null, [
                'aktivitas_terakhir' => ($satu->last_login_at ?? $satu->created_at)?->toIso8601String(),
                'batas_bulan' => self::AKUN_TIDAK_AKTIF_BULAN,
            ]);
        }

        return $akun->count();
    }
}
