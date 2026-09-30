<?php

namespace App\Services;

use App\Models\NotifikasiPetugas;
use App\Models\Pengaduan;
use App\Models\PermintaanDataPribadi;
use App\Models\Permohonan;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Lonceng pekerjaan baru bagi petugas (REQ-F-NOT-005).
 *
 * Pekerjaan desa berpindah tangan antarperan: berkas yang sudah diverifikasi
 * menunggu penyetuju, yang sudah disetujui menunggu penanda tangan. Tanpa
 * penanda, perpindahan itu hanya ketahuan bila seseorang kebetulan membuka
 * antreannya. Lonceng ini memberi tahu peran yang giliran kerjanya tiba.
 *
 * Penerima ditentukan oleh izin, bukan oleh peran: bila desa mengubah susunan
 * peran, pemberitahuan tetap sampai ke orang yang memang berwenang.
 */
class NotifikasiPetugasService
{
    /**
     * Dipanggil pada setiap perpindahan status permohonan. Hanya status yang
     * memindahkan giliran kerja ke peran lain yang membunyikan lonceng;
     * penolakan dan penyelesaian tidak menyisakan pekerjaan bagi siapa pun.
     */
    public function permohonanBerpindah(Permohonan $permohonan, string $status): void
    {
        [$izin, $judul] = match ($status) {
            Permohonan::DIAJUKAN => ['permohonan.verifikasi', 'Permohonan menunggu verifikasi'],
            Permohonan::DIVERIFIKASI => ['permohonan.setujui', 'Permohonan menunggu persetujuan'],
            Permohonan::DISETUJUI => ['permohonan.tanda_tangan', 'Permohonan menunggu tanda tangan'],
            default => [null, null],
        };

        if ($izin === null) {
            return;
        }

        $permohonan->loadMissing('jenisLayanan');

        $this->sebarkan($izin, [
            'jenis' => 'permohonan',
            'judul' => $judul,
            'ringkasan' => $permohonan->jenisLayanan?->nama.' — '.$permohonan->nomor_tiket,
            'tautan' => '/admin/permohonan/'.$permohonan->id,
            'entitas' => 'Permohonan',
            'entitas_id' => (string) $permohonan->id,
        ]);
    }

    public function pengaduanBaru(Pengaduan $pengaduan): void
    {
        $this->sebarkan('pengaduan.lihat', [
            'jenis' => 'pengaduan_baru',
            'judul' => 'Pengaduan baru masuk',
            'ringkasan' => $pengaduan->judul,
            'tautan' => '/admin/pengaduan',
            'entitas' => 'Pengaduan',
            'entitas_id' => (string) $pengaduan->id,
        ]);
    }

    public function permintaanDataBaru(PermintaanDataPribadi $permintaan): void
    {
        $this->sebarkan('data_pribadi.kelola', [
            'jenis' => 'permintaan_data',
            'judul' => 'Permintaan penghapusan data pribadi',
            'ringkasan' => 'Wajib dijawab paling lambat '.DataPribadiService::TENGGAT_JAWABAN_HARI.' hari.',
            'tautan' => '/admin/permintaan-data',
            'entitas' => 'PermintaanDataPribadi',
            'entitas_id' => (string) $permintaan->id,
        ]);
    }

    /**
     * Menyalin satu pemberitahuan kepada setiap petugas aktif yang berwenang.
     *
     * @param  array<string, mixed>  $isi
     */
    private function sebarkan(string $izin, array $isi): void
    {
        $penerima = $this->penerima($izin);

        if ($penerima->isEmpty()) {
            return;
        }

        NotifikasiPetugas::insert(
            $penerima->map(fn (int $id) => [...$isi, 'pengguna_id' => $id, 'created_at' => now()])->all(),
        );
    }

    /** @return Collection<int, int> */
    private function penerima(string $izin): Collection
    {
        return User::query()
            ->where('status_akun', User::AKTIF)
            ->whereHas('role.permissions', fn ($kueri) => $kueri->where('kode', $izin))
            ->pluck('id');
    }
}
