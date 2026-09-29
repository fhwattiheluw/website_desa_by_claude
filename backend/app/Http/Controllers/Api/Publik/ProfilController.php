<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Models\Pengaturan;
use App\Services\AnalitikService;
use Illuminate\Http\JsonResponse;

/** REQ-F-BRD-002, 006: profil desa dan kontak resmi. */
class ProfilController extends Controller
{
    public function __invoke(AnalitikService $analitik): JsonResponse
    {
        return response()->json([
            'data' => Pengaturan::semua(),
            // Portal hanya mengirim hitungan kunjungan bila desa menyalakannya
            // (REQ-SW-006).
            'analitik_aktif' => $analitik->aktif(),
        ]);
    }
}
