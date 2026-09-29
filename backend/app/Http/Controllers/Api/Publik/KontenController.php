<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Http\Resources\KontenResource;
use App\Models\Kategori;
use App\Models\Konten;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** REQ-F-KNT-008, 009, 012. */
class KontenController extends Controller
{
    public function index(Request $request, string $tipe): AnonymousResourceCollection
    {
        abort_unless(in_array($tipe, Konten::TIPE, true), 404);

        $konten = Konten::tayang()
            ->where('tipe', $tipe)
            ->with('gambar', 'kategori')
            ->when($request->filled('kategori'), fn ($q) => $q->whereHas(
                'kategori',
                fn ($k) => $k->where('slug', $request->string('kategori'))
            ))
            ->when($request->filled('q'), fn ($q) => $q->where(
                fn ($w) => $w->where('judul', 'like', '%'.$request->string('q').'%')
                    ->orWhere('ringkasan', 'like', '%'.$request->string('q').'%')
            ))
            ->when(
                $tipe === 'agenda',
                fn ($q) => $q->orderBy('mulai_pada'),
                fn ($q) => $q->latest('terbit_pada'),
            )
            ->paginate(min($request->integer('per_halaman', 12), 50))
            ->withQueryString();

        return KontenResource::collection($konten);
    }

    public function show(string $tipe, string $slug): KontenResource
    {
        $konten = Konten::tayang()
            ->where('tipe', $tipe)
            ->where('slug', $slug)
            ->with('gambar', 'kategori', 'penulis')
            ->firstOrFail();

        // REQ-F-KNT-009: penghitung dibaca tidak memengaruhi kolom updated_at.
        $konten->timestamps = false;
        $konten->increment('dibaca');
        $konten->timestamps = true;

        return new KontenResource($konten);
    }

    public function kategori(): JsonResponse
    {
        return response()->json([
            'data' => Kategori::withCount(['konten' => fn ($q) => $q->tayang()])
                ->orderBy('nama')
                ->get(['id', 'nama', 'slug', 'parent_id']),
        ]);
    }

    /** Kalender agenda per bulan (REQ-F-KNT-012). */
    public function kalender(Request $request): JsonResponse
    {
        $bulan = $request->integer('bulan', (int) now()->month);
        $tahun = $request->integer('tahun', (int) now()->year);

        $agenda = Konten::tayang()
            ->where('tipe', 'agenda')
            ->whereYear('mulai_pada', $tahun)
            ->whereMonth('mulai_pada', $bulan)
            ->orderBy('mulai_pada')
            ->get();

        return response()->json([
            'bulan' => $bulan,
            'tahun' => $tahun,
            'data' => KontenResource::collection($agenda),
        ]);
    }
}
