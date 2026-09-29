<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Models\KinerjaBumdes;
use App\Models\Lembaga;
use App\Models\Pengaturan;
use App\Models\UnitUsaha;
use Illuminate\Http\JsonResponse;

/**
 * Profil Badan Usaha Milik Desa: unit usaha, ringkasan kinerja, dan kontak
 * pengurus (REQ-F-POT-003).
 */
class BumdesController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $pengaturan = Pengaturan::semua();
        $lembaga = Lembaga::where('jenis', 'bumdes')->with('pengurus')->first();

        return response()->json([
            'profil' => [
                'nama' => $pengaturan['bumdes_nama'] ?? null,
                'deskripsi' => $pengaturan['bumdes_deskripsi'] ?? null,
                'tahun_berdiri' => $pengaturan['bumdes_tahun_berdiri'] ?? null,
                'dasar_hukum' => $pengaturan['bumdes_dasar_hukum'] ?? null,
                'alamat' => $pengaturan['bumdes_alamat'] ?? null,
                'kontak' => $pengaturan['bumdes_kontak'] ?? null,
            ],
            'unit_usaha' => UnitUsaha::where('aktif', true)
                ->with('media')
                ->orderBy('urutan')
                ->get()
                ->map(fn (UnitUsaha $unit) => [
                    'nama' => $unit->nama,
                    'slug' => $unit->slug,
                    'deskripsi' => $unit->deskripsi,
                    'penanggung_jawab' => $unit->penanggung_jawab,
                    'kontak' => $unit->kontak,
                    'foto' => $unit->media?->url(),
                ]),
            // Hanya tahun buku yang sudah disetujui untuk publikasi yang tampil,
            // selaras dengan perlakuan APBDes (BR-08).
            'kinerja' => KinerjaBumdes::where('dipublikasikan', true)
                ->orderByDesc('tahun')
                ->get()
                ->map(fn (KinerjaBumdes $baris) => [
                    'tahun' => $baris->tahun,
                    'pendapatan' => (float) $baris->pendapatan,
                    'laba_bersih' => (float) $baris->laba_bersih,
                    'kontribusi_pades' => (float) $baris->kontribusi_pades,
                    'catatan' => $baris->catatan,
                ]),
            'pengurus' => $lembaga?->pengurus->map(fn ($orang) => [
                'nama' => $orang->nama,
                'jabatan' => $orang->jabatan,
            ]) ?? [],
        ]);
    }
}
