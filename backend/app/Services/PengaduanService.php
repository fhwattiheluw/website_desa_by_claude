<?php

namespace App\Services;

use App\Exceptions\AlurTidakValid;
use App\Models\Pengaduan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Alur pengaduan masyarakat (REQ-F-ADU-003..009; BR-10, BR-11).
 */
class PengaduanService
{
    public const TRANSISI = [
        Pengaduan::BARU => [Pengaduan::DIVERIFIKASI, Pengaduan::DITOLAK],
        Pengaduan::DIVERIFIKASI => [Pengaduan::DIDISPOSISI, Pengaduan::DITOLAK],
        Pengaduan::DIDISPOSISI => [Pengaduan::PROSES],
        Pengaduan::PROSES => [Pengaduan::SELESAI],
        Pengaduan::SELESAI => [],
        Pengaduan::DITOLAK => [],
    ];

    /** REQ-F-ADU-009: tanggapan pertama paling lambat 3 hari kerja. */
    public const SLA_HARI_KERJA = 3;

    public function __construct(
        private readonly NomorService $nomor,
        private readonly KalenderKerja $kalender,
        private readonly AuditLogger $audit,
        private readonly NotifikasiService $notifikasi,
        private readonly NotifikasiPetugasService $lonceng,
    ) {}

    /** @param array<string, mixed> $data */
    public function buat(array $data, ?User $pelapor = null): Pengaduan
    {
        return DB::transaction(function () use ($data, $pelapor): Pengaduan {
            $pengaduan = Pengaduan::create([
                'nomor_tiket' => $this->nomor->nomorPengaduan(),
                'kode_lacak' => $this->nomor->kodeLacak(),
                'pelapor_id' => $pelapor?->id,
                'nama_pelapor' => $data['nama_pelapor'] ?? $pelapor?->name,
                'kontak_pelapor' => $data['kontak_pelapor'],
                'kategori' => $data['kategori'],
                'judul' => $data['judul'],
                'uraian' => $data['uraian'],
                'lokasi' => $data['lokasi'] ?? null,
                'tanggal_kejadian' => $data['tanggal_kejadian'] ?? null,
                'anonim' => (bool) ($data['anonim'] ?? false),
                'status' => Pengaduan::BARU,
                'tenggat_tanggapan' => $this->kalender->tenggat(self::SLA_HARI_KERJA),
            ]);

            $this->audit->catat('create', 'Pengaduan', $pengaduan->id, null, [
                'nomor_tiket' => $pengaduan->nomor_tiket,
                'kategori' => $pengaduan->kategori,
            ]);

            if (filled($pengaduan->kontak_pelapor)) {
                $kanal = str_contains($pengaduan->kontak_pelapor, '@') ? 'email' : 'whatsapp';
                $this->notifikasi->kirim($kanal, $pengaduan->kontak_pelapor, 'pengaduan_diterima', [
                    'nama' => $pengaduan->pelaporTersamar(),
                    'nomor_tiket' => $pengaduan->nomor_tiket,
                    'kode_lacak' => $pengaduan->kode_lacak,
                ]);
            }

            // REQ-F-NOT-005: pengaduan masuk ke antrean petugas yang berwenang.
            $this->lonceng->pengaduanBaru($pengaduan);

            return $pengaduan;
        });
    }

    public function ubahStatus(Pengaduan $pengaduan, string $ke, User $aktor, ?string $catatan = null): Pengaduan
    {
        $dari = $pengaduan->status;

        if (! in_array($ke, self::TRANSISI[$dari] ?? [], true)) {
            throw new AlurTidakValid("Pengaduan berstatus \"{$dari}\" tidak dapat diubah menjadi \"{$ke}\".");
        }

        // BR-10: penolakan wajib disertai alasan agar dapat dipertanggungjawabkan.
        if ($ke === Pengaduan::DITOLAK && strlen(trim((string) $catatan)) < PermohonanService::MINIMAL_ALASAN) {
            throw new AlurTidakValid('Alasan penolakan wajib diisi minimal '.PermohonanService::MINIMAL_ALASAN.' karakter.');
        }

        $pengaduan->update(['status' => $ke]);

        $this->audit->catat('status_change', 'Pengaduan', $pengaduan->id,
            ['status' => $dari],
            ['status' => $ke, 'catatan' => $catatan],
        );

        if (filled($catatan)) {
            $pengaduan->tanggapan()->create([
                'aktor_id' => $aktor->id,
                'isi' => $catatan,
                'internal' => $ke === Pengaduan::DITOLAK ? false : true,
            ]);
        }

        return $pengaduan->refresh();
    }

    public function disposisi(Pengaduan $pengaduan, User $aktor, User $tujuan, ?string $catatan = null): Pengaduan
    {
        if (! in_array($pengaduan->status, [Pengaduan::DIVERIFIKASI, Pengaduan::DIDISPOSISI], true)) {
            throw new AlurTidakValid('Pengaduan harus diverifikasi lebih dahulu sebelum didisposisi.');
        }

        $pengaduan->update([
            'didisposisi_ke' => $tujuan->id,
            'catatan_disposisi' => $catatan,
            'status' => Pengaduan::DIDISPOSISI,
        ]);

        $this->audit->catat('disposisi', 'Pengaduan', $pengaduan->id, null, [
            'kepada' => $tujuan->name,
            'catatan' => $catatan,
        ]);

        return $pengaduan->refresh();
    }

    public function tanggapi(Pengaduan $pengaduan, User $aktor, string $isi, bool $internal = false): Pengaduan
    {
        $pengaduan->tanggapan()->create([
            'aktor_id' => $aktor->id,
            'isi' => $isi,
            'internal' => $internal,
        ]);

        $this->audit->catat('tanggapan', 'Pengaduan', $pengaduan->id, null, ['internal' => $internal]);

        if (! $internal && filled($pengaduan->kontak_pelapor)) {
            $kanal = str_contains($pengaduan->kontak_pelapor, '@') ? 'email' : 'whatsapp';
            $this->notifikasi->kirim($kanal, $pengaduan->kontak_pelapor, 'pengaduan_ditanggapi', [
                'nama' => $pengaduan->pelaporTersamar(),
                'nomor_tiket' => $pengaduan->nomor_tiket,
            ]);
        }

        return $pengaduan->refresh();
    }
}
