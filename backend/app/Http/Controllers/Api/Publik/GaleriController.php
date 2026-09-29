<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/** REQ-F-GAL-005: galeri dikelompokkan per album. */
class GaleriController extends Controller
{
    public function index(): JsonResponse
    {
        $album = Album::withCount('media')->latest('tanggal_kegiatan')->paginate(12);

        return response()->json($album);
    }

    public function show(string $slug, MediaService $media): JsonResponse
    {
        $album = Album::where('slug', $slug)->with('media')->firstOrFail();

        return response()->json([
            'id' => $album->id,
            'nama' => $album->nama,
            'slug' => $album->slug,
            'deskripsi' => $album->deskripsi,
            'tanggal_kegiatan' => $album->tanggal_kegiatan?->toDateString(),
            'foto' => $album->media->where('privat', false)->values()->map(fn ($m) => [
                'id' => $m->id,
                'alt' => $m->alt,
                'url' => $m->url(),
                'thumbnail' => Storage::disk($m->disk)->url($media->pathVarian($m->path, 'kecil')),
            ]),
        ]);
    }
}
