<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Models\MenuNavigasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/** Menu navigasi utama untuk portal publik (REQ-F-ADM-003). */
class MenuController extends Controller
{
    public const KUNCI_TEMBOLOK = 'menu-navigasi-publik';

    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => self::susunan()]);
    }

    /**
     * Susunan menu yang tampil di portal.
     *
     * Disimpan di tembolok karena dibaca pada setiap pemuatan halaman namun
     * jarang berubah. Pengelola menu membersihkannya setiap kali menyimpan.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function susunan(): array
    {
        return Cache::remember(self::KUNCI_TEMBOLOK, now()->addHours(6), function () {
            $butir = MenuNavigasi::where('aktif', true)->orderBy('urutan')->orderBy('id')->get();

            return $butir->whereNull('induk_id')
                ->take(MenuNavigasi::MAKS_TINGKAT_SATU)
                ->values()
                ->map(fn (MenuNavigasi $induk) => [
                    'label' => $induk->label,
                    'tautan' => $induk->tautan,
                    'anak' => $butir->where('induk_id', $induk->id)->values()->map(fn (MenuNavigasi $anak) => [
                        'label' => $anak->label,
                        'tautan' => $anak->tautan,
                    ])->all(),
                ])
                ->all();
        });
    }
}
