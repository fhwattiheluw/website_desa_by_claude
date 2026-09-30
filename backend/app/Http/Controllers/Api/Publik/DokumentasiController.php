<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Services\DokumentasiOpenApi;
use Illuminate\Http\JsonResponse;

/** Dokumentasi OpenAPI 3.x yang terbuka bagi pemakai API (REQ-API-004). */
class DokumentasiController extends Controller
{
    public function __invoke(DokumentasiOpenApi $dokumentasi): JsonResponse
    {
        return response()->json($dokumentasi->susun(), options: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
