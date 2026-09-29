<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PeriodeStatistik;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** REQ-F-STA-003, 005. */
class StatistikController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => PeriodeStatistik::withCount('item')->orderByDesc('tahun')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:80'],
            'tahun' => ['required', 'integer', 'min:2015', 'max:2100'],
            'sumber_data' => ['nullable', 'string', 'max:150'],
            'aktif' => ['boolean'],
        ]);

        $periode = DB::transaction(function () use ($data) {
            if ($data['aktif'] ?? false) {
                PeriodeStatistik::where('aktif', true)->update(['aktif' => false]);
            }

            return PeriodeStatistik::create($data);
        });

        $this->audit->catatModel('create', $periode);

        return response()->json(['pesan' => 'Periode statistik dibuat.', 'data' => $periode], 201);
    }

    public function simpanItem(Request $request, PeriodeStatistik $periodeStatistik): JsonResponse
    {
        $data = $request->validate([
            'item' => ['required', 'array', 'min:1'],
            'item.*.kelompok' => ['required', 'string', 'max:40'],
            'item.*.label' => ['required', 'string', 'max:120'],
            'item.*.jumlah' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $periodeStatistik) {
            $periodeStatistik->item()->delete();

            foreach ($data['item'] as $urutan => $item) {
                $periodeStatistik->item()->create([...$item, 'urutan' => $urutan]);
            }
        });

        $this->audit->catat('update', 'PeriodeStatistik', $periodeStatistik->id, null, [
            'jumlah_item' => count($data['item']),
        ]);

        return response()->json(['pesan' => 'Data statistik tersimpan.']);
    }

    public function aktifkan(PeriodeStatistik $periodeStatistik): JsonResponse
    {
        DB::transaction(function () use ($periodeStatistik) {
            PeriodeStatistik::where('aktif', true)->update(['aktif' => false]);
            $periodeStatistik->update(['aktif' => true]);
        });

        return response()->json(['pesan' => "Periode {$periodeStatistik->nama} kini menjadi periode aktif."]);
    }
}
