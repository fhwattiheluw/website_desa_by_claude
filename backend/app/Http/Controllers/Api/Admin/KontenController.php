<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\KontenResource;
use App\Models\Konten;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** REQ-F-KNT-001..014. */
class KontenController extends Controller
{
    /** Jumlah versi konten yang disimpan (REQ-F-KNT-007). */
    public const MAKS_VERSI = 10;

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $data = Konten::with('kategori', 'penulis', 'gambar')
            ->when($request->filled('tipe'), fn ($q) => $q->where('tipe', $request->string('tipe')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), fn ($q) => $q->where('judul', 'like', '%'.$request->string('q').'%'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return KontenResource::collection($data);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validasi($request);

        $konten = Konten::create([
            ...$data,
            'slug' => $this->slugUnik($data['slug'] ?? $data['judul']),
            'penulis_id' => $request->user()->id,
            'status' => 'draf',
        ]);

        $this->simpanVersi($konten, $request->user()->id);
        $this->audit->catatModel('create', $konten);

        return response()->json([
            'pesan' => 'Konten tersimpan sebagai draf.',
            'data' => new KontenResource($konten->load('kategori')),
        ], 201);
    }

    public function show(Konten $konten): KontenResource
    {
        return new KontenResource($konten->load('kategori', 'penulis', 'gambar', 'versi'));
    }

    public function update(Request $request, Konten $konten): JsonResponse
    {
        $data = $this->validasi($request, $konten);
        $sebelum = $konten->attributesToArray();

        $konten->update([
            ...$data,
            'slug' => isset($data['slug']) ? $this->slugUnik($data['slug'], $konten->id) : $konten->slug,
        ]);

        $this->simpanVersi($konten, $request->user()->id);
        $this->audit->catatModel('update', $konten, $sebelum);

        return response()->json([
            'pesan' => 'Konten diperbarui.',
            'data' => new KontenResource($konten->fresh(['kategori', 'gambar'])),
        ]);
    }

    /**
     * Perubahan status mengikuti siklus draf → review → terbit → arsip
     * (REQ-F-KNT-003). Penerbitan memerlukan izin tersendiri.
     */
    public function ubahStatus(Request $request, Konten $konten): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Konten::STATUS)],
            'terbit_pada' => ['nullable', 'date'],
            'kedaluwarsa_pada' => ['nullable', 'date', 'after:terbit_pada'],
        ]);

        if ($data['status'] === 'terbit') {
            abort_unless($request->user()->punyaIzin('konten.terbit'), 403,
                'Anda tidak memiliki hak untuk menerbitkan konten.');

            // REQ-F-KNT-014: penulis tidak boleh menerbitkan tulisannya sendiri (maker-checker).
            abort_if(
                $konten->penulis_id === $request->user()->id && ! $request->user()->berperan('admin'),
                403,
                'Konten harus ditinjau dan diterbitkan oleh pengguna lain (prinsip maker-checker).',
            );
        }

        $sebelum = ['status' => $konten->status];

        $konten->update([
            'status' => $data['status'],
            'terbit_pada' => $data['status'] === 'terbit'
                ? ($data['terbit_pada'] ?? $konten->terbit_pada ?? now())
                : $konten->terbit_pada,
            'kedaluwarsa_pada' => $data['kedaluwarsa_pada'] ?? $konten->kedaluwarsa_pada,
        ]);

        $this->audit->catat('status_change', 'Konten', $konten->id, $sebelum, ['status' => $data['status']]);

        return response()->json(['pesan' => "Status konten diubah menjadi {$data['status']}."]);
    }

    /** REQ-F-KNT-007: pemulihan ke versi sebelumnya. */
    public function pulihkan(Konten $konten, int $versi): JsonResponse
    {
        $riwayat = $konten->versi()->where('versi', $versi)->firstOrFail();
        $sebelum = $konten->attributesToArray();

        $konten->update(['judul' => $riwayat->judul, 'isi' => $riwayat->isi]);
        $this->audit->catatModel('pulihkan_versi', $konten, $sebelum);

        return response()->json(['pesan' => "Konten dipulihkan ke versi {$versi}."]);
    }

    public function destroy(Konten $konten): JsonResponse
    {
        $this->audit->catatModel('delete', $konten, $konten->attributesToArray());
        $konten->delete();

        return response()->json(['pesan' => 'Konten dipindahkan ke tempat sampah.']);
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?Konten $konten = null): array
    {
        return $request->validate([
            'tipe' => [$konten ? 'sometimes' : 'required', Rule::in(Konten::TIPE)],
            'judul' => [$konten ? 'sometimes' : 'required', 'string', 'min:5', 'max:200'],
            'slug' => ['nullable', 'string', 'max:220'],
            'ringkasan' => ['nullable', 'string', 'max:500'],
            'isi' => ['nullable', 'string'],
            'kategori_id' => ['nullable', 'exists:kategori,id'],
            'gambar_id' => ['nullable', 'exists:media,id'],
            'sorotan' => ['boolean'],
            'tag' => ['array', 'max:10'],
            'tag.*' => ['string', 'max:40'],
            'terbit_pada' => ['nullable', 'date'],
            'kedaluwarsa_pada' => ['nullable', 'date'],
            'mulai_pada' => ['nullable', 'date'],
            'selesai_pada' => ['nullable', 'date', 'after_or_equal:mulai_pada'],
            'lokasi' => ['nullable', 'string', 'max:200'],
            'penyelenggara' => ['nullable', 'string', 'max:150'],
        ]);
    }

    private function simpanVersi(Konten $konten, int $penggunaId): void
    {
        $versi = (int) $konten->versi()->max('versi') + 1;

        $konten->versi()->create([
            'versi' => $versi,
            'judul' => $konten->judul,
            'isi' => $konten->isi,
            'dibuat_oleh' => $penggunaId,
        ]);

        $konten->versi()
            ->orderByDesc('versi')
            ->skip(self::MAKS_VERSI)
            ->take(100)
            ->get()
            ->each->delete();
    }

    private function slugUnik(string $sumber, ?int $abaikanId = null): string
    {
        $dasar = str($sumber)->slug()->limit(200, '')->toString();
        $slug = $dasar;
        $urut = 2;

        while (Konten::withTrashed()->where('slug', $slug)->when($abaikanId, fn ($q) => $q->whereKeyNot($abaikanId))->exists()) {
            $slug = "{$dasar}-{$urut}";
            $urut++;
        }

        return $slug;
    }
}
