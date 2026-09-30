<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** REQ-F-GAL-001, 005, 007. */
class MediaController extends Controller
{
    public function __construct(private readonly MediaService $layanan) {}

    public function index(Request $request): JsonResponse
    {
        $data = Media::where('privat', false)
            ->when($request->filled('koleksi'), fn ($q) => $q->where('koleksi', $request->string('koleksi')))
            ->cariTeks('nama_asli', $request->string('q')->toString())
            ->latest()
            ->paginate(24)
            ->through(fn (Media $m) => [
                'id' => $m->id,
                'nama' => $m->nama_asli,
                'mime' => $m->mime,
                'ukuran' => $m->ukuran,
                'alt' => $m->alt,
                'koleksi' => $m->koleksi,
                'url' => $m->url(),
            ]);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'berkas' => ['required', 'file', 'max:10240'],
            'koleksi' => ['nullable', 'string', 'max:40'],
            'album_id' => ['nullable', 'exists:album,id'],
            // REQ-F-GAL-007: teks alternatif wajib untuk gambar konten.
            'alt' => ['nullable', 'string', 'max:255'],
        ]);

        $media = $this->layanan->simpan(
            berkas: $request->file('berkas'),
            koleksi: $request->input('koleksi', 'umum'),
            pengunggah: $request->user(),
            alt: $request->input('alt'),
        );

        if ($request->filled('album_id')) {
            $media->update(['album_id' => $request->integer('album_id')]);
        }

        return response()->json([
            'pesan' => 'Berkas berhasil diunggah.',
            'data' => ['id' => $media->id, 'url' => $media->url(), 'nama' => $media->nama_asli],
        ], 201);
    }

    public function update(Request $request, Media $media): JsonResponse
    {
        $media->update($request->validate([
            'alt' => ['nullable', 'string', 'max:255'],
            'album_id' => ['nullable', 'exists:album,id'],
        ]));

        return response()->json(['pesan' => 'Metadata media diperbarui.']);
    }

    public function destroy(Media $media): JsonResponse
    {
        $this->layanan->hapus($media);

        return response()->json(['pesan' => 'Berkas dihapus.']);
    }

    public function album(Request $request): JsonResponse
    {
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'nama' => ['required', 'string', 'max:150'],
                'deskripsi' => ['nullable', 'string', 'max:1000'],
                'tanggal_kegiatan' => ['nullable', 'date'],
            ]);

            $album = Album::create([...$data, 'slug' => str($data['nama'])->slug().'-'.str()->random(4)]);

            return response()->json(['pesan' => 'Album dibuat.', 'data' => $album], 201);
        }

        return response()->json(['data' => Album::withCount('media')->latest()->get()]);
    }
}
