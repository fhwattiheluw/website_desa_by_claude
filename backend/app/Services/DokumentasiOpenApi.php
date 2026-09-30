<?php

namespace App\Services;

use App\Models\Permission;
use Illuminate\Routing\Route as RuteAplikasi;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Dokumentasi OpenAPI 3.1 yang dibangun dari tabel rute aplikasi (REQ-API-004).
 *
 * Disusun dari rute yang benar-benar terdaftar, bukan dari berkas terpisah yang
 * ditulis tangan. Berkas semacam itu selalu tertinggal: rute baru bertambah,
 * dokumentasinya tidak, dan pemakai API menemukan selisihnya sendiri. Di sini
 * satu-satunya cara membuat dokumentasi keliru adalah mengubah rutenya —
 * dan uji `DokumentasiApiTest` menjaga tiap rute tetap terwakili.
 */
class DokumentasiOpenApi
{
    private const AWALAN = 'api/v1';

    /** Ringkasan per pengendali, dipakai bila metodenya tidak punya ringkasan sendiri. */
    private const KELOMPOK = [
        'Publik' => 'Titik akhir terbuka tanpa autentikasi, untuk portal desa dan pemakaian ulang data.',
        'Warga' => 'Layanan bagi warga yang telah masuk: permohonan surat, notifikasi, dan data pribadinya.',
        'Admin' => 'Panel petugas desa. Setiap titik akhir menuntut izin tertentu.',
        'Autentikasi' => 'Pendaftaran, masuk, kode masuk dua faktor, dan pemulihan kata sandi.',
    ];

    /** @return array<string, mixed> */
    public function susun(): array
    {
        $jalur = [];

        foreach ($this->rute() as $rute) {
            $alamat = '/'.Str::after($rute->uri(), self::AWALAN.'/');
            $alamat = preg_replace('/\{(\w+?)\??\}/', '{$1}', $alamat);

            foreach ($this->metode($rute) as $metode) {
                $jalur[$alamat][$metode] = $this->operasi($rute, $alamat);
            }
        }

        ksort($jalur);

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'API Sistem Informasi Desa Terpadu',
                'version' => '1.0.0',
                'description' => $this->pengantar(),
                'license' => ['name' => 'Lihat berkas LICENSE pada repositori'],
            ],
            'servers' => [['url' => url(self::AWALAN), 'description' => 'Server desa ini']],
            'tags' => collect(self::KELOMPOK)->map(fn ($uraian, $nama) => [
                'name' => $nama,
                'description' => $uraian,
            ])->values()->all(),
            'components' => [
                'securitySchemes' => [
                    'tokenSanctum' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'description' => 'Token dari POST /auth/masuk, dikirim sebagai "Authorization: Bearer <token>".',
                    ],
                ],
                'schemas' => $this->skema(),
            ],
            'paths' => $jalur,
        ];
    }

    /** @return list<RuteAplikasi> */
    public function rute(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RuteAplikasi $rute) => str_starts_with($rute->uri(), self::AWALAN.'/'))
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function metode(RuteAplikasi $rute): array
    {
        return collect($rute->methods())
            ->reject(fn (string $metode) => in_array($metode, ['HEAD', 'OPTIONS'], true))
            ->map(fn (string $metode) => strtolower($metode))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function operasi(RuteAplikasi $rute, string $alamat): array
    {
        $izin = $this->izin($rute);
        $perluToken = $this->perluToken($rute);

        $operasi = [
            'tags' => [$this->kelompok($rute)],
            'summary' => $this->ringkasan($rute),
            'operationId' => $this->pengenalOperasi($rute),
            'parameters' => $this->parameter($rute, $alamat),
            'responses' => $this->tanggapan($perluToken, $izin !== null),
        ];

        if ($perluToken) {
            $operasi['security'] = [['tokenSanctum' => []]];
        }

        if ($izin !== null) {
            $operasi['description'] = 'Menuntut izin `'.$izin.'`'
                .($this->namaIzin($izin) ? ' ('.$this->namaIzin($izin).')' : '').'.';
        }

        if ($batas = $this->pembatas($rute)) {
            $operasi['x-batas-laju'] = $batas;
        }

        return $operasi;
    }

    /** @return list<array<string, mixed>> */
    private function parameter(RuteAplikasi $rute, string $alamat): array
    {
        preg_match_all('/\{(\w+)\}/', $alamat, $cocok);

        return collect($cocok[1])->map(fn (string $nama) => [
            'name' => $nama,
            'in' => 'path',
            'required' => ! in_array($nama, $rute->parameterNames(), true) || ! Str::contains($rute->uri(), '{'.$nama.'?}'),
            'schema' => ['type' => 'string'],
        ])->all();
    }

    /** @return array<string, mixed> */
    private function tanggapan(bool $perluToken, bool $perluIzin): array
    {
        $tanggapan = [
            '200' => ['description' => 'Berhasil.'],
            '422' => [
                'description' => 'Masukan tidak lolos validasi.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Galat']]],
            ],
            '429' => ['description' => 'Melampaui batas laju permintaan.'],
        ];

        if ($perluToken) {
            $tanggapan['401'] = [
                'description' => 'Token tidak ada atau sudah kedaluwarsa.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Galat']]],
            ];
        }

        if ($perluIzin) {
            $tanggapan['403'] = [
                'description' => 'Peran yang masuk tidak memegang izin yang dituntut.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Galat']]],
            ];
        }

        return $tanggapan;
    }

    private function kelompok(RuteAplikasi $rute): string
    {
        $aksi = (string) ($rute->getAction('controller') ?? '');

        return match (true) {
            str_contains($aksi, '\\Admin\\') => 'Admin',
            str_contains($aksi, '\\Warga\\') => 'Warga',
            str_contains($aksi, 'AuthController'),
            str_contains($aksi, 'KataSandiController'),
            str_contains($aksi, 'VerifikasiSurelController') => 'Autentikasi',
            default => 'Publik',
        };
    }

    /**
     * Kata kerja baku kerangka kerja diterjemahkan agar ringkasan terbaca oleh
     * pemakai API, bukan hanya oleh orang yang sudah membaca kodenya.
     */
    private const KERJA = [
        'index' => 'Daftar', 'show' => 'Rincian', 'store' => 'Tambah', 'update' => 'Ubah',
        'destroy' => 'Hapus', 'simpan' => 'Simpan', 'hapus' => 'Hapus', 'urutkan' => 'Urutkan',
        '__invoke' => 'Ambil',
    ];

    /**
     * Nama pengendali yang tidak terbaca sebagai kata benda bila diterjemahkan
     * apa adanya. Hanya soal susunan kalimat: cakupannya tetap dari tabel rute.
     */
    private const POKOK = [
        'Auth' => 'akun',
        'KataSandi' => 'kata sandi',
        'VerifikasiSurel' => 'verifikasi surel',
        'Referensi' => 'data referensi',
        'Pustaka' => 'pustaka informasi publik',
        'Kerangka' => 'kerangka aplikasi',
        'Dokumentasi' => 'dokumentasi API',
        'Permohonan' => 'permohonan surat',
        'Konten' => 'konten portal',
        'Menu' => 'menu navigasi',
        'VideoAlbum' => 'video album galeri',
    ];

    private function ringkasan(RuteAplikasi $rute): string
    {
        $aksi = (string) ($rute->getAction('controller') ?? $rute->uri());
        $kelas = Str::of(class_basename(Str::before($aksi, '@')))->replaceLast('Controller', '')->toString();
        $metode = Str::contains($aksi, '@') ? Str::after($aksi, '@') : '__invoke';

        $pokok = self::POKOK[$kelas] ?? Str::of($kelas)->headline()->lower()->toString();
        $kerja = self::KERJA[$metode] ?? Str::of($metode)->headline()->lower()->ucfirst()->toString();

        // Kata benda tidak diulang bila nama metodenya sudah menyebutnya.
        $sudahDisebut = Str::contains(Str::lower($kerja), Str::lower(Str::before($pokok, ' ')));

        return trim($sudahDisebut ? $kerja : $kerja.' '.$pokok);
    }

    private function pengenalOperasi(RuteAplikasi $rute): string
    {
        $aksi = (string) ($rute->getAction('controller') ?? $rute->uri());

        return Str::of($aksi)
            ->replace(['App\\Http\\Controllers\\Api\\', '\\', '@'], ['', '', '.'])
            ->append('.'.strtolower($this->metode($rute)[0] ?? 'get'))
            ->toString();
    }

    private function perluToken(RuteAplikasi $rute): bool
    {
        return collect($rute->gatherMiddleware())
            ->contains(fn ($satu) => is_string($satu)
                && (str_contains($satu, 'Authenticate:sanctum') || $satu === 'auth:sanctum'));
    }

    private function izin(RuteAplikasi $rute): ?string
    {
        $cocok = collect($rute->gatherMiddleware())
            ->first(fn ($satu) => is_string($satu)
                && (str_starts_with($satu, 'izin:') || str_contains($satu, 'PastikanIzin:')));

        return $cocok ? Str::afterLast($cocok, ':') : null;
    }

    private function namaIzin(string $kode): ?string
    {
        static $daftar = null;

        $daftar ??= Permission::pluck('nama', 'kode')->all();

        return $daftar[$kode] ?? null;
    }

    private function pembatas(RuteAplikasi $rute): ?string
    {
        $cocok = collect($rute->gatherMiddleware())
            ->first(fn ($satu) => is_string($satu)
                && (str_starts_with($satu, 'throttle:') || str_contains($satu, 'ThrottleRequests:')));

        $nama = $cocok ? Str::afterLast($cocok, ':') : null;

        return $nama && $nama !== 'api' ? $nama : null;
    }

    /** @return array<string, mixed> */
    private function skema(): array
    {
        return [
            'Galat' => [
                'type' => 'object',
                'description' => 'Bentuk baku seluruh jawaban galat (REQ-API-002).',
                'properties' => [
                    'kode' => ['type' => 'string', 'description' => 'Kode galat yang tetap, misalnya VALIDASI_GAGAL.'],
                    'pesan' => ['type' => 'string', 'description' => 'Penjelasan dalam bahasa Indonesia.'],
                    'korelasi' => ['type' => 'string', 'description' => 'Pengenal permintaan untuk penelusuran log.'],
                    'errors' => ['type' => 'object', 'description' => 'Galat per kolom, hanya pada kegagalan validasi.'],
                ],
                'required' => ['kode', 'pesan'],
            ],
            'Halaman' => [
                'type' => 'object',
                'description' => 'Bentuk baku daftar berhalaman.',
                'properties' => [
                    'data' => ['type' => 'array', 'items' => ['type' => 'object']],
                    'current_page' => ['type' => 'integer'],
                    'last_page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                    'total' => ['type' => 'integer'],
                ],
            ],
        ];
    }

    private function pengantar(): string
    {
        return <<<'TEKS'
        Dokumentasi ini dibangkitkan dari tabel rute aplikasi pada saat diminta,
        sehingga selalu sejalan dengan yang benar-benar dilayani server ini.

        **Autentikasi.** Titik akhir bertanda gembok menuntut token dari
        `POST /auth/masuk`. Peran berwenang menerima tantangan kode masuk lebih
        dulu: jawaban 202 berisi `tantangan`, dan token baru terbit setelah
        `POST /auth/otp` berhasil.

        **Izin.** Titik akhir panel petugas menuntut izin tertentu, disebut pada
        keterangan masing-masing. Token yang sah tanpa izin yang dituntut
        dijawab 403.

        **Batas laju.** Batas bawaan 120 permintaan per menit. Titik akhir
        sensitif memakai batas tersendiri, ditandai `x-batas-laju`.

        **Data terbuka.** Titik akhir dengan batas `terbuka` boleh dipakai ulang
        pihak lain dengan menyebut sumbernya (REQ-API-005).
        TEKS;
    }
}
