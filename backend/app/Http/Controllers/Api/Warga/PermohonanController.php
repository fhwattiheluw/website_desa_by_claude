<?php

namespace App\Http\Controllers\Api\Warga;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermohonanResource;
use App\Models\JenisLayanan;
use App\Models\Permohonan;
use App\Models\SuratTerbit;
use App\Services\MediaService;
use App\Services\PermohonanService;
use App\Services\SuratService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pengajuan dan pemantauan permohonan oleh warga
 * (REQ-F-SRT-002..008, 011, 018, 027).
 */
class PermohonanController extends Controller
{
    /** REQ-F-SRT-018: masa berlaku tautan unduh surat. */
    public const HARI_TAUTAN_UNDUH = 30;

    public function __construct(private readonly PermohonanService $layanan) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $data = Permohonan::query()
            ->where('pemohon_id', $request->user()->id)
            ->with('jenisLayanan', 'surat')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(10);

        return PermohonanResource::collection($data);
    }

    public function store(Request $request, MediaService $media): JsonResponse
    {
        $layanan = JenisLayanan::where('slug', $request->input('layanan'))->firstOrFail();

        $request->validate([
            'layanan' => ['required', 'string'],
            'draf' => ['boolean'],
            'data_formulir' => ['required', 'array'],
            'lampiran' => ['array', 'max:5'],           // REQ-F-SRT-006
            'lampiran.*' => ['file', 'max:5120'],
            ...$request->boolean('draf') ? [] : $layanan->aturanValidasi(),
        ], [], $this->labelKolom($layanan));

        $permohonan = $this->layanan->buat(
            pemohon: $request->user(),
            layanan: $layanan,
            dataFormulir: $request->input('data_formulir'),
            draf: $request->boolean('draf'),
        );

        $this->simpanLampiran($request, $permohonan, $media);

        return response()->json([
            'pesan' => $permohonan->status === Permohonan::DRAF
                ? 'Draf permohonan tersimpan dan dapat dilanjutkan dalam 7 hari.'
                : 'Permohonan berhasil diajukan.',
            'data' => new PermohonanResource($permohonan->load('jenisLayanan', 'lampiran.media', 'riwayat.aktor')),
        ], 201);
    }

    public function show(Request $request, Permohonan $permohonan): PermohonanResource
    {
        $this->pastikanMilikSendiri($request, $permohonan);

        return new PermohonanResource(
            $permohonan->load('jenisLayanan', 'lampiran.media', 'riwayat.aktor', 'surat')
        );
    }

    /** REQ-F-SRT-011: perbaikan tanpa mengisi ulang seluruh formulir. */
    public function kirimUlang(Request $request, Permohonan $permohonan, MediaService $media): JsonResponse
    {
        $this->pastikanMilikSendiri($request, $permohonan);

        $request->validate([
            'data_formulir' => ['required', 'array'],
            'lampiran' => ['array', 'max:5'],
            'lampiran.*' => ['file', 'max:5120'],
            ...$permohonan->jenisLayanan->aturanValidasi(),
        ], [], $this->labelKolom($permohonan->jenisLayanan));

        $this->layanan->kirimUlang($permohonan, $request->user(), $request->input('data_formulir'));
        $this->simpanLampiran($request, $permohonan, $media);

        return response()->json([
            'pesan' => 'Permohonan telah dikirim ulang dan kembali masuk antrean verifikasi.',
            'data' => new PermohonanResource($permohonan->fresh(['jenisLayanan', 'riwayat.aktor'])),
        ]);
    }

    /**
     * Menyimpan perubahan pada draf tanpa mengirimkannya (REQ-F-SRT-007).
     * Draf yang tidak dilanjutkan dalam tujuh hari dibersihkan terjadwal.
     */
    public function simpanDraf(Request $request, Permohonan $permohonan, MediaService $media): JsonResponse
    {
        $this->pastikanMilikSendiri($request, $permohonan);

        abort_unless(
            $permohonan->status === Permohonan::DRAF,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Hanya permohonan berstatus draf yang dapat disimpan ulang.',
        );

        $request->validate([
            'data_formulir' => ['required', 'array'],
            'lampiran' => ['array', 'max:5'],
            'lampiran.*' => ['file', 'max:5120'],
        ]);

        $permohonan->update(['data_formulir' => $request->input('data_formulir')]);
        $this->simpanLampiran($request, $permohonan, $media);

        return response()->json([
            'pesan' => 'Draf tersimpan. Anda dapat melanjutkannya dalam '
                .PermohonanService::BATAS_DRAF_HARI.' hari.',
            'data' => new PermohonanResource($permohonan->fresh(['jenisLayanan', 'lampiran.media'])),
        ]);
    }

    /**
     * Memberi tautan unduh bertanda tangan digital dan berbatas waktu
     * sehingga dokumen tidak dapat diakses oleh sembarang pihak (REQ-F-SRT-018).
     */
    public function tautanSurat(Request $request, Permohonan $permohonan): JsonResponse
    {
        $this->pastikanMilikSendiri($request, $permohonan);

        $surat = $permohonan->surat;

        abort_if($surat === null, Response::HTTP_NOT_FOUND, 'Surat belum diterbitkan untuk permohonan ini.');

        return response()->json([
            'tautan' => URL::temporarySignedRoute(
                'surat.unduh',
                now()->addDays(self::HARI_TAUTAN_UNDUH),
                ['surat' => $surat->id],
            ),
            'berlaku_sampai' => now()->addDays(self::HARI_TAUTAN_UNDUH)->toIso8601String(),
            'nomor_surat' => $surat->nomor_surat,
        ]);
    }

    public function unduh(SuratTerbit $surat, SuratService $suratService)
    {
        return response($suratService->isiBerkas($surat), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.str($surat->nomor_surat)->slug().'.pdf"',
        ]);
    }

    /** REQ-F-SRT-027: survei kepuasan singkat setelah layanan selesai. */
    public function nilai(Request $request, Permohonan $permohonan): JsonResponse
    {
        $this->pastikanMilikSendiri($request, $permohonan);

        abort_unless($permohonan->status === Permohonan::SELESAI, 422, 'Penilaian hanya untuk permohonan yang selesai.');

        $permohonan->update($request->validate(['kepuasan' => ['required', 'integer', 'min:1', 'max:5']]));

        return response()->json(['pesan' => 'Terima kasih atas penilaian Anda.']);
    }

    private function simpanLampiran(Request $request, Permohonan $permohonan, MediaService $media): void
    {
        foreach ((array) $request->file('lampiran', []) as $indeks => $berkas) {
            // Lampiran identitas bersifat rahasia sehingga disimpan privat (REQ-NF-SEC-007).
            $tersimpan = $media->simpan($berkas, 'lampiran', $request->user(), privat: true);

            $permohonan->lampiran()->create([
                'media_id' => $tersimpan->id,
                'label' => $request->input("label_lampiran.{$indeks}", $berkas->getClientOriginalName()),
            ]);
        }
    }

    private function pastikanMilikSendiri(Request $request, Permohonan $permohonan): void
    {
        abort_unless(
            $permohonan->pemohon_id === $request->user()->id || $request->user()->petugas(),
            Response::HTTP_FORBIDDEN,
            'Anda hanya dapat mengakses permohonan milik sendiri.',
        );
    }

    /** @return array<string, string> */
    private function labelKolom(JenisLayanan $layanan): array
    {
        $label = [];

        foreach ($layanan->kolom_formulir as $kolom) {
            $label['data_formulir.'.$kolom['nama']] = strtolower($kolom['label']);
        }

        return $label;
    }
}
