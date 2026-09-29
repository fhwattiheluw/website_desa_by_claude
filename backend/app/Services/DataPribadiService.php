<?php

namespace App\Services;

use App\Models\Pengaduan;
use App\Models\PermintaanDataPribadi;
use App\Models\Permohonan;
use App\Models\PermohonanInformasi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hak subjek data atas data pribadinya: melihat, mengunduh, dan mengajukan
 * penghapusan (REQ-F-USR-013, REQ-NF-CMP-003, UU 27/2022).
 *
 * Penghapusan tidak dijalankan seketika oleh warga sendiri karena sebagian
 * arsip layanan wajib disimpan menurut ketentuan kearsipan (UU 27/2022 Pasal 30
 * ayat 2). Permintaan ditinjau petugas, keputusannya tercatat, dan apa yang
 * tetap disimpan dinyatakan terbuka kepada warga.
 */
class DataPribadiService
{
    /** Tenggat pengendali data menjawab permintaan subjek data (3x24 jam). */
    public const TENGGAT_JAWABAN_HARI = 3;

    /** Penanda nama akun yang datanya telah dihapus. */
    public const NAMA_ANONIM = 'Warga (data dihapus)';

    /**
     * Kanal kontak pada pengaduan dan permohonan informasi tidak boleh kosong
     * karena wajib ada satu kanal (BR-10), jadi diganti penanda alih-alih
     * dinolkan.
     */
    public const KONTAK_ANONIM = '[dihapus atas permintaan pemilik data]';

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Menyusun seluruh data pribadi yang sistem simpan tentang satu pengguna,
     * dalam bentuk yang dapat dibaca manusia maupun mesin (hak akses dan hak
     * memperoleh salinan).
     *
     * @return array<string, mixed>
     */
    public function berkas(User $pengguna): array
    {
        $pengguna->loadMissing('role');

        $permohonan = Permohonan::with('jenisLayanan:id,nama', 'surat:id,permohonan_id,nomor_surat,tanggal_terbit')
            ->where('pemohon_id', $pengguna->id)
            ->orderBy('id')
            ->get();

        $pengaduan = Pengaduan::with('tanggapan')
            ->where('pelapor_id', $pengguna->id)
            ->orderBy('id')
            ->get();

        $this->audit->catat('unduh_data_pribadi', 'User', $pengguna->id);

        return [
            'disusun_pada' => now()->toIso8601String(),
            'tentang' => 'Salinan data pribadi yang disimpan portal desa atas nama pemilik akun ini.',

            'identitas' => [
                'nama' => $pengguna->name,
                'surel' => $pengguna->email,
                // Pemilik data berhak melihat NIK-nya utuh; pada tampilan
                // petugas nilai ini selalu tersamar.
                'nik' => $pengguna->nik,
                'telepon' => $pengguna->telepon,
                'alamat' => $pengguna->alamat,
                'tempat_lahir' => $pengguna->tempat_lahir,
                'tanggal_lahir' => $pengguna->tanggal_lahir?->toDateString(),
                'jenis_kelamin' => $pengguna->jenis_kelamin,
                'pekerjaan' => $pengguna->pekerjaan,
            ],

            'akun' => [
                'peran' => $pengguna->role?->nama,
                'status_akun' => $pengguna->status_akun,
                'terdaftar_pada' => $pengguna->created_at?->toIso8601String(),
                'surel_terverifikasi_pada' => $pengguna->email_verified_at?->toIso8601String(),
                'nik_terverifikasi_pada' => $pengguna->verifikasi_nik_at?->toIso8601String(),
                'persetujuan_pemrosesan_pada' => $pengguna->consent_at?->toIso8601String(),
                'masuk_terakhir' => $pengguna->last_login_at?->toIso8601String(),
            ],

            'permohonan_layanan' => $permohonan->map(fn (Permohonan $p) => [
                'nomor_tiket' => $p->nomor_tiket,
                'layanan' => $p->jenisLayanan?->nama,
                'status' => $p->status,
                'kanal' => $p->kanal,
                'diajukan_pada' => $p->diajukan_pada?->toIso8601String(),
                'selesai_pada' => $p->selesai_pada?->toIso8601String(),
                'data_formulir' => $p->data_formulir,
                'nomor_surat' => $p->surat?->nomor_surat,
                'surat_terbit_pada' => $p->surat?->tanggal_terbit?->toDateString(),
            ])->all(),

            'pengaduan' => $pengaduan->map(fn (Pengaduan $a) => [
                'nomor_tiket' => $a->nomor_tiket,
                'kategori' => $a->kategori,
                'judul' => $a->judul,
                'uraian' => $a->uraian,
                'status' => $a->status,
                'anonim' => $a->anonim,
                'dikirim_pada' => $a->created_at?->toIso8601String(),
                'tanggapan' => $a->tanggapan->where('internal', false)->map(fn ($t) => [
                    'isi' => $t->isi,
                    'waktu' => $t->created_at?->toIso8601String(),
                ])->values()->all(),
            ])->all(),

            'permohonan_informasi_publik' => PermohonanInformasi::where('pemohon_id', $pengguna->id)
                ->orderBy('id')
                ->get()
                ->map(fn (PermohonanInformasi $p) => [
                    'nomor_tiket' => $p->nomor_tiket,
                    'informasi_diminta' => $p->informasi_diminta,
                    'tujuan_penggunaan' => $p->tujuan_penggunaan,
                    'status' => $p->status,
                    'diajukan_pada' => $p->created_at?->toIso8601String(),
                ])->all(),

            'permintaan_hak_subjek_data' => PermintaanDataPribadi::where('pengguna_id', $pengguna->id)
                ->orderBy('id')
                ->get()
                ->map(fn (PermintaanDataPribadi $p) => [
                    'jenis' => $p->jenis,
                    'status' => $p->status,
                    'alasan' => $p->alasan,
                    'catatan_petugas' => $p->catatan_petugas,
                    'diajukan_pada' => $p->created_at?->toIso8601String(),
                    'ditindak_pada' => $p->ditindak_pada?->toIso8601String(),
                ])->all(),

            'catatan_retensi' => [
                'Berkas lampiran permohonan dihapus 12 bulan setelah layanan selesai.',
                'Jejak audit disimpan 24 bulan lalu dihapus otomatis.',
                'Surat yang telah diterbitkan beserta data pada formulirnya tetap disimpan sebagai arsip '
                    .'pemerintahan desa meskipun akun dihapus, sebagaimana dikecualikan UU 27/2022 Pasal 30.',
            ],
        ];
    }

    /** Mengajukan penghapusan data pribadi untuk ditinjau petugas. */
    public function ajukanPenghapusan(User $pengguna, ?string $alasan): PermintaanDataPribadi
    {
        $tertunda = PermintaanDataPribadi::where('pengguna_id', $pengguna->id)
            ->where('status', PermintaanDataPribadi::MENUNGGU)
            ->first();

        if ($tertunda) {
            throw ValidationException::withMessages([
                'alasan' => 'Permintaan penghapusan Anda sebelumnya masih ditinjau petugas desa.',
            ]);
        }

        $permintaan = PermintaanDataPribadi::create([
            'pengguna_id' => $pengguna->id,
            'jenis' => PermintaanDataPribadi::HAPUS,
            'status' => PermintaanDataPribadi::MENUNGGU,
            'alasan' => $alasan,
            'tenggat_jawaban' => now()->addDays(self::TENGGAT_JAWABAN_HARI),
        ]);

        $this->audit->catat('ajukan_hapus_data', 'PermintaanDataPribadi', $permintaan->id);

        return $permintaan;
    }

    /** Menyetujui permintaan: data pribadi pada akun dihapus atau dianonimkan. */
    public function setujui(PermintaanDataPribadi $permintaan, User $petugas, ?string $catatan): PermintaanDataPribadi
    {
        $this->pastikanMasihMenunggu($permintaan);

        return DB::transaction(function () use ($permintaan, $petugas, $catatan) {
            $pengguna = $permintaan->pengguna;

            $ringkasan = $this->anonimkan($pengguna);

            $permintaan->update([
                'status' => PermintaanDataPribadi::DISETUJUI,
                'catatan_petugas' => $catatan,
                'ditindak_oleh' => $petugas->id,
                'ditindak_pada' => now(),
            ]);

            $this->audit->catat(
                'setujui_hapus_data',
                'PermintaanDataPribadi',
                $permintaan->id,
                null,
                ['pengguna_id' => $pengguna->id, ...$ringkasan],
            );

            return $permintaan->fresh(['pengguna', 'petugas']);
        });
    }

    /** Menolak permintaan; alasan penolakan wajib dan disampaikan kepada warga. */
    public function tolak(PermintaanDataPribadi $permintaan, User $petugas, string $catatan): PermintaanDataPribadi
    {
        $this->pastikanMasihMenunggu($permintaan);

        $permintaan->update([
            'status' => PermintaanDataPribadi::DITOLAK,
            'catatan_petugas' => $catatan,
            'ditindak_oleh' => $petugas->id,
            'ditindak_pada' => now(),
        ]);

        $this->audit->catat('tolak_hapus_data', 'PermintaanDataPribadi', $permintaan->id, null, ['alasan' => $catatan]);

        return $permintaan->fresh(['pengguna', 'petugas']);
    }

    /**
     * Menghapus data pribadi yang tidak wajib disimpan dan menganonimkan
     * sisanya, agar catatan layanan tetap dapat dipertanggungjawabkan tanpa
     * lagi menunjuk orang tertentu.
     *
     * @return array<string, int>
     */
    private function anonimkan(User $pengguna): array
    {
        // Draf tidak memiliki nilai arsip apa pun, jadi dihapus seutuhnya.
        $draf = Permohonan::where('pemohon_id', $pengguna->id)
            ->where('status', Permohonan::DRAF)
            ->get();

        foreach ($draf as $satu) {
            $satu->lampiran()->delete();
            $satu->delete();
        }

        $pengaduan = Pengaduan::where('pelapor_id', $pengguna->id)->get();

        foreach ($pengaduan as $satu) {
            $satu->nama_pelapor = null;
            $satu->anonim = true;
            $satu->kontak_pelapor = self::KONTAK_ANONIM;
            $satu->save();
        }

        $informasi = PermohonanInformasi::where('pemohon_id', $pengguna->id)->get();

        foreach ($informasi as $satu) {
            $satu->nama = 'Pemohon (data dihapus)';
            $satu->kontak = self::KONTAK_ANONIM;
            $satu->save();
        }

        $pengguna->tokens()->delete();

        $pengguna->nik = null;
        $pengguna->forceFill([
            'name' => self::NAMA_ANONIM,
            // Surel dikosongkan dengan nilai unik agar kendala keunikan tetap terjaga.
            'email' => 'terhapus-'.$pengguna->id.'@dihapus.invalid',
            'email_verified_at' => null,
            'telepon' => null,
            'alamat' => null,
            'tempat_lahir' => null,
            'tanggal_lahir' => null,
            'jenis_kelamin' => null,
            'pekerjaan' => null,
            'verifikasi_nik_at' => null,
            'status_akun' => User::NONAKTIF,
        ])->save();

        return [
            'draf_dihapus' => $draf->count(),
            'pengaduan_dianonimkan' => $pengaduan->count(),
            'permohonan_informasi_dianonimkan' => $informasi->count(),
            // Permohonan yang telah berujung surat tetap tersimpan sebagai arsip.
            'permohonan_diarsipkan' => Permohonan::where('pemohon_id', $pengguna->id)->count(),
        ];
    }

    private function pastikanMasihMenunggu(PermintaanDataPribadi $permintaan): void
    {
        if (! $permintaan->menunggu()) {
            throw ValidationException::withMessages([
                'status' => 'Permintaan ini sudah pernah ditindak.',
            ]);
        }
    }
}
