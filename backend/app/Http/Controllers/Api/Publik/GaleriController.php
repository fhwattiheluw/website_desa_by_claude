<?php

namespace App\Http\Controllers\Api\Publik;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\VideoAlbum;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/** REQ-F-GAL-005: galeri dikelompokkan per album. */
class GaleriController extends Controller
{
    public function index(): JsonResponse
    {
        $album = Album::withCount('media', 'video')->latest('tanggal_kegiatan')->paginate(12);

        return response()->json($album);
    }

    public function show(string $slug, MediaService $media): JsonResponse
    {
        $album = Album::where('slug', $slug)->with('media', 'video')->firstOrFail();

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
            // REQ-F-GAL-006: alamat sematan dibentuk dari pengenal video yang
            // tersimpan, bukan dari alamat yang pernah diketik pengelola.
            'video' => $album->video->map(fn (VideoAlbum $v) => [
                'id' => $v->id,
                'judul' => $v->judul,
                'penyedia' => $v->penyedia,
                'url_sematan' => $v->urlSematan(),
                'url_asli' => $v->url_asli,
                'thumbnail' => $v->urlThumbnail(),
            ]),
        ]);
    }
}
