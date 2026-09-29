<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SpesimenTandaTangan;
use App\Services\AuditLogger;
use App\Services\TandaTangan\ManajerTandaTangan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Pengelolaan spesimen tanda tangan pejabat (REQ-F-SRT-017).
 *
 * Spesimen bersifat sangat sensitif: pejabat hanya dapat mengelola
 * spesimennya sendiri, berkasnya disimpan pada disk privat, dan tidak pernah
 * memiliki URL publik.
 */
class TandaTanganController extends Controller
{
    public const DISK = 'local';

    public const MAKS_UKURAN = 1024 * 1024; // 1 MB

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ManajerTandaTangan $manajer,
    ) {}

    public function status(Request $request): JsonResponse
    {
        $penanda = $this->manajer->aktif();
        $spesimen = SpesimenTandaTangan::where('user_id', $request->user()->id)->first();

        return response()->json([
            'metode_aktif' => $penanda->metode(),
            'penyedia' => $penanda->nama(),
            'perlu_spesimen' => $penanda->menyematkanSpesimen(),
            'spesimen_terpasang' => $spesimen !== null && $spesimen->aktif,
            'diperbarui_pada' => $spesimen?->updated_at?->toIso8601String(),
            'catatan' => $penanda->menyematkanSpesimen()
                ? 'Penyedia tanda tangan elektronik tersertifikasi belum aktif. Dokumen ditandatangani di dalam '
                    .'sistem memakai spesimen Anda dan diverifikasi melalui kode QR.'
                : 'Dokumen ditandatangani melalui penyedia tersertifikasi, sehingga spesimen tidak diperlukan.',
        ]);
    }

    public function simpan(Request $request): JsonResponse
    {
        $request->validate([
            'berkas' => ['required', 'file', 'mimetypes:image/png,image/jpeg', 'max:1024'],
        ], [
            'berkas.mimetypes' => 'Spesimen harus berupa gambar PNG atau JPG. PNG berlatar transparan memberi hasil terbaik.',
        ]);

        $berkas = $request->file('berkas');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($berkas->getRealPath());

        if (! in_array($mime, ['image/png', 'image/jpeg'], true)) {
            throw ValidationException::withMessages(['berkas' => 'Isi berkas bukan gambar PNG atau JPG yang sah.']);
        }

        if ($berkas->getSize() > self::MAKS_UKURAN) {
            throw ValidationException::withMessages(['berkas' => 'Ukuran spesimen melebihi 1 MB.']);
        }

        $pengguna = $request->user();
        $isi = (string) file_get_contents($berkas->getRealPath());
        $path = 'tanda-tangan/'.Str::uuid()->toString().($mime === 'image/png' ? '.png' : '.jpg');

        Storage::disk(self::DISK)->put($path, $isi);

        $lama = SpesimenTandaTangan::where('user_id', $pengguna->id)->first();

        if ($lama) {
            Storage::disk($lama->disk)->delete($lama->path);
        }

        SpesimenTandaTangan::updateOrCreate(['user_id' => $pengguna->id], [
            'path' => $path,
            'disk' => self::DISK,
            'hash_berkas' => hash('sha256', $isi),
            'aktif' => true,
        ]);

        $this->audit->catat('simpan_spesimen_tanda_tangan', 'User', $pengguna->id);

        return response()->json([
            'pesan' => 'Spesimen tanda tangan tersimpan dan akan dipakai pada surat yang Anda tandatangani.',
        ]);
    }

    public function hapus(Request $request): JsonResponse
    {
        $spesimen = SpesimenTandaTangan::where('user_id', $request->user()->id)->first();

        abort_if($spesimen === null, 404, 'Anda belum memiliki spesimen tanda tangan.');

        Storage::disk($spesimen->disk)->delete($spesimen->path);
        $spesimen->delete();

        $this->audit->catat('hapus_spesimen_tanda_tangan', 'User', $request->user()->id);

        return response()->json(['pesan' => 'Spesimen tanda tangan dihapus.']);
    }

    /**
     * Pratinjau spesimen milik sendiri. Berkas dialirkan langsung, bukan
     * melalui URL penyimpanan, agar tidak dapat diakses pihak lain.
     */
    public function pratinjau(Request $request)
    {
        $spesimen = SpesimenTandaTangan::where('user_id', $request->user()->id)->firstOrFail();

        return response(Storage::disk($spesimen->disk)->get($spesimen->path), 200, [
            'Content-Type' => str_ends_with($spesimen->path, '.png') ? 'image/png' : 'image/jpeg',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
