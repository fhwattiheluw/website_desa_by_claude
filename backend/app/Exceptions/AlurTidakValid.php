<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Dilempar saat transisi status melanggar alur atau aturan bisnis (Bab 7 SRS). */
class AlurTidakValid extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json([
            'pesan' => $this->getMessage(),
            'kode' => 'ALUR_TIDAK_VALID',
        ], 422);
    }
}
