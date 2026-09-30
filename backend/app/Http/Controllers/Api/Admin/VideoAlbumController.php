<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\VideoAlbum;
use App\Services\AuditLogger;
use App\Services\PenyematVideo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Penyematan video pada album galeri (REQ-F-GAL-006). */
class VideoAlbumController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PenyematVideo $penyemat,
    ) {}

    public function index(Album $album): JsonResponse
    {
        return response()->json([
            'data' => $album->video()->orderBy('urutan')->get()->map(fn (VideoAlbum $video) => [
                ...$video->only('id', 'judul', 'penyedia', 'url_asli', 'urutan'),
                'url_sematan' => $video->urlSematan(),
                'thumbnail' => $video->urlThumbnail(),
            ]),
        ]);
    }

    public function simpan(Request $request, Album $album): JsonResponse
    {
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:160'],
            'url' => ['required', 'string', 'max:255'],
            'urutan' => ['integer', 'min:0', 'max:999'],
        ]);

        $dikenali = $this->penyemat->urai($data['url']);

        $video = VideoAlbum::updateOrCreate(
            ['album_id' => $album->id, ...$dikenali],
            [
                'judul' => $data['judul'],
                'url_asli' => $data['url'],
                'urutan' => $data['urutan'] ?? 0,
                'thumbnail_path' => $this->penyemat->simpanThumbnail($dikenali['penyedia'], $dikenali['id_video']),
            ],
        );

        $this->audit->catatModel($video->wasRecentlyCreated ? 'create' : 'update', $video);

        return response()->json([
            'pesan' => 'Video tersemat pada album.',
            'data' => [
                ...$video->only('id', 'judul', 'penyedia', 'url_asli', 'urutan'),
                'url_sematan' => $video->urlSematan(),
                'thumbnail' => $video->urlThumbnail(),
            ],
        ]);
    }

    public function hapus(Album $album, VideoAlbum $video): JsonResponse
    {
        abort_if($video->album_id !== $album->id, 404);

        $this->audit->catatModel('delete', $video, $video->attributesToArray());
        $video->delete();

        return response()->json(['pesan' => 'Video dilepas dari album.']);
    }
}
