<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Services\AnalitikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** REQ-SW-006: penerima hitungan kunjungan laman. */
class KunjunganController extends Controller
{
    public function __invoke(Request $request, AnalitikService $analitik): JsonResponse
    {
        $data = $request->validate([
            'jalur' => ['required', 'string', 'max:200'],
        ]);

        $analitik->catat($data['jalur']);

        // Tidak ada yang perlu dikembalikan; jawaban dibuat sekecil mungkin agar
        // pencatatan tidak menambah beban muat laman.
        return response()->json(['dicatat' => true], 202);
    }
}
