<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Publik\MenuController as MenuPublik;
use App\Http\Controllers\Controller;
use App\Models\MenuNavigasi;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/** Pengelola menu navigasi: tambah, ubah, urutkan, sarangkan (REQ-F-ADM-003). */
class MenuController extends Controller
{
    /**
     * Skema tautan yang boleh ditulis pengelola.
     *
     * Daftar putih, bukan daftar hitam. `javascript:` dan `data:` pada atribut
     * href menjalankan kode di peramban pengunjung; menyaringnya satu per satu
     * berarti menebak semua penulisan yang mungkin. Yang tidak dikenali ditolak.
     */
    private const SKEMA_DIIZINKAN = ['http', 'https', 'mailto', 'tel'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        $butir = MenuNavigasi::orderBy('urutan')->orderBy('id')->get();

        return response()->json([
            'data' => $butir->whereNull('induk_id')->values()->map(fn (MenuNavigasi $induk) => [
                ...$induk->only('id', 'label', 'tautan', 'urutan', 'aktif'),
                'anak' => $butir->where('induk_id', $induk->id)->values()
                    ->map(fn (MenuNavigasi $anak) => $anak->only('id', 'label', 'tautan', 'urutan', 'aktif'))
                    ->all(),
            ])->all(),
            'maks_tingkat_satu' => MenuNavigasi::MAKS_TINGKAT_SATU,
        ]);
    }

    public function simpan(Request $request, ?MenuNavigasi $menu = null): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'tautan' => ['nullable', 'string', 'max:255'],
            'induk_id' => ['nullable', 'integer', 'exists:menu_navigasi,id'],
            'urutan' => ['integer', 'min:0', 'max:999'],
            'aktif' => ['boolean'],
        ]);

        $data['tautan'] = $this->tautanBersih($data['tautan'] ?? null);

        $this->pastikanPenyarangan($data['induk_id'] ?? null, $menu);
        $this->pastikanMuatTingkatSatu($data, $menu);

        if (blank($data['tautan']) && blank($data['induk_id'] ?? null)) {
            // Butir tingkat satu tanpa tautan hanya masuk akal bila menaungi anak.
            $punyaAnak = $menu?->exists && $menu->anak()->exists();

            if (! $punyaAnak) {
                throw ValidationException::withMessages([
                    'tautan' => 'Butir menu tanpa tautan hanya boleh dipakai bila menaungi butir lain.',
                ]);
            }
        }

        $butir = $menu?->exists
            ? tap($menu)->update($data)
            : MenuNavigasi::create($data);

        $this->audit->catatModel($menu?->exists ? 'update' : 'create', $butir);
        $this->segarkanTembolok();

        return response()->json(['pesan' => 'Menu tersimpan.', 'data' => $butir]);
    }

    public function hapus(MenuNavigasi $menu): JsonResponse
    {
        $this->audit->catatModel('delete', $menu, $menu->attributesToArray());
        // Anak ikut terhapus melalui batasan basis data; tidak ada butir yatim.
        $menu->delete();
        $this->segarkanTembolok();

        return response()->json(['pesan' => 'Menu dihapus beserta butir di bawahnya.']);
    }

    /** Menyimpan urutan baru sekaligus, seperti yang dihasilkan pengurutan di layar. */
    public function urutkan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'urutan' => ['required', 'array', 'min:1'],
            'urutan.*.id' => ['required', 'integer', 'exists:menu_navigasi,id'],
            'urutan.*.urutan' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        foreach ($data['urutan'] as $baris) {
            MenuNavigasi::whereKey($baris['id'])->update(['urutan' => $baris['urutan']]);
        }

        $this->audit->catat('urutkan', 'MenuNavigasi', null, null, ['jumlah' => count($data['urutan'])]);
        $this->segarkanTembolok();

        return response()->json(['pesan' => 'Urutan menu tersimpan.']);
    }

    /**
     * Menjaga penyarangan tetap dua tingkat (REQ-F-ADM-003, REQ-UI-003).
     */
    private function pastikanPenyarangan(?int $indukId, ?MenuNavigasi $menu): void
    {
        if ($indukId === null) {
            return;
        }

        if ($menu?->exists && $indukId === $menu->id) {
            throw ValidationException::withMessages(['induk_id' => 'Butir menu tidak dapat menjadi induk dirinya sendiri.']);
        }

        if (MenuNavigasi::whereKey($indukId)->whereNotNull('induk_id')->exists()) {
            throw ValidationException::withMessages([
                'induk_id' => 'Menu hanya boleh bersarang dua tingkat. Pilih butir tingkat pertama sebagai induk.',
            ]);
        }

        if ($menu?->exists && $menu->anak()->exists()) {
            throw ValidationException::withMessages([
                'induk_id' => 'Butir ini menaungi butir lain, sehingga tidak dapat dijadikan anak menu lain.',
            ]);
        }
    }

    /**
     * Menahan jumlah butir tingkat pertama pada batas REQ-UI-003.
     *
     * Batas ini bukan selera tata letak: navigasi yang lebih panjang tidak muat
     * pada satu baris layar ponsel, dan butir kedelapan akan hilang dari
     * pandangan tanpa memberi tanda apa pun kepada pengelola.
     *
     * @param  array<string, mixed>  $data
     */
    private function pastikanMuatTingkatSatu(array $data, ?MenuNavigasi $menu): void
    {
        $menjadiTingkatSatu = blank($data['induk_id'] ?? null) && ($data['aktif'] ?? true);

        if (! $menjadiTingkatSatu) {
            return;
        }

        $terpakai = MenuNavigasi::whereNull('induk_id')
            ->where('aktif', true)
            ->when($menu?->exists, fn ($kueri) => $kueri->whereKeyNot($menu->id))
            ->count();

        if ($terpakai >= MenuNavigasi::MAKS_TINGKAT_SATU) {
            throw ValidationException::withMessages([
                'induk_id' => 'Menu tingkat pertama paling banyak '.MenuNavigasi::MAKS_TINGKAT_SATU
                    .' butir. Nonaktifkan atau sarangkan salah satu butir yang ada lebih dulu.',
            ]);
        }
    }

    private function tautanBersih(?string $tautan): ?string
    {
        $tautan = trim((string) $tautan);

        if ($tautan === '') {
            return null;
        }

        // Tautan dalam situs: selalu diawali garis miring tunggal. "//contoh.id"
        // bukan tautan dalam situs melainkan alamat luar tanpa skema.
        if (str_starts_with($tautan, '/') && ! str_starts_with($tautan, '//')) {
            return $tautan;
        }

        $skema = strtolower((string) parse_url($tautan, PHP_URL_SCHEME));

        if (! in_array($skema, self::SKEMA_DIIZINKAN, true)) {
            throw ValidationException::withMessages([
                'tautan' => 'Tautan harus berupa alamat dalam situs yang diawali "/" '
                    .'atau alamat lengkap berawalan http:// maupun https://.',
            ]);
        }

        return $tautan;
    }

    private function segarkanTembolok(): void
    {
        Cache::forget(MenuPublik::KUNCI_TEMBOLOK);
    }
}
