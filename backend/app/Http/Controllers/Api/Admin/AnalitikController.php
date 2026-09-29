<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalitikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** REQ-SW-006: ringkasan kunjungan bagi petugas desa. */
class AnalitikController extends Controller
{
    public function __invoke(Request $request, AnalitikService $analitik): JsonResponse
    {
        $hari = max(7, min(180, $request->integer('hari', 30)));

        return response()->json($analitik->ringkasan($hari));
    }
}
