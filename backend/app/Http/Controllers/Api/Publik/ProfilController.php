<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Models\Pengaturan;
use Illuminate\Http\JsonResponse;

/** REQ-F-BRD-002, 006: profil desa dan kontak resmi. */
class ProfilController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => Pengaturan::semua()]);
    }
}
