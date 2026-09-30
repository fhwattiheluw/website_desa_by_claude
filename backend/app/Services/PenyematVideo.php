<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Penyematan video dari penyedia eksternal (REQ-F-GAL-006).
 *
 * Yang disimpan hanya pengenal video pada penyedia yang dikenali, bukan alamat
 * yang diketik pengelola. Alamat sematan dibentuk ulang dari pengenal itu,
 * sehingga tidak ada masukan pengguna yang pernah menjadi atribut `src` sebuah
 * bingkai — satu-satunya jalan agar penyematan tidak berubah menjadi celah
 * penyisipan skrip.
 */
class PenyematVideo
{
    /**
     * Penyedia yang dikenali beserta pola pengenal videonya.
     *
     * Daftar putih. Penyedia yang tidak ada di sini ditolak, bukan dicoba
     * disematkan apa adanya.
     */
    private const PENYEDIA = [
        'youtube' => [
            'inang' => ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'www.youtu.be'],
            'pola' => '/^[A-Za-z0-9_-]{11}$/',
        ],
        'vimeo' => [
            'inang' => ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'],
            'pola' => '/^[0-9]{6,12}$/',
        ],
    ];

    /**
     * Mengurai alamat yang diketik pengelola menjadi penyedia dan pengenal.
     *
     * @return array{penyedia: string, id_video: string}
     */
    public function urai(string $url): array
    {
        $bagian = parse_url(trim($url));
        $inang = strtolower((string) ($bagian['host'] ?? ''));
        $skema = strtolower((string) ($bagian['scheme'] ?? ''));

        if (! in_array($skema, ['http', 'https'], true)) {
            $this->tolak('Alamat video harus diawali http:// atau https://.');
        }

        foreach (self::PENYEDIA as $nama => $aturan) {
            if (! in_array($inang, $aturan['inang'], true)) {
                continue;
            }

            $id = $this->pengenal($nama, $bagian);

            if ($id !== null && preg_match($aturan['pola'], $id) === 1) {
                return ['penyedia' => $nama, 'id_video' => $id];
            }

            $this->tolak('Alamat video dikenali sebagai '.Str::title($nama)
                .', tetapi pengenal videonya tidak terbaca. Salin alamat langsung dari halaman videonya.');
        }

        $this->tolak('Penyedia video belum didukung. Saat ini tersedia YouTube dan Vimeo.');
    }

    /** @param array<string, mixed> $bagian */
    private function pengenal(string $penyedia, array $bagian): ?string
    {
        $jalur = trim((string) ($bagian['path'] ?? ''), '/');
        $inang = strtolower((string) ($bagian['host'] ?? ''));
        parse_str((string) ($bagian['query'] ?? ''), $kueri);

        if ($penyedia === 'vimeo') {
            // vimeo.com/123456789 maupun player.vimeo.com/video/123456789
            return Str::afterLast($jalur, '/') ?: null;
        }

        if (str_contains($inang, 'youtu.be')) {
            return $jalur ?: null;
        }

        if (is_string($kueri['v'] ?? null)) {
            return $kueri['v'];
        }

        // youtube.com/embed/ID, /shorts/ID, /live/ID
        return in_array(Str::before($jalur, '/'), ['embed', 'shorts', 'live'], true)
            ? Str::after($jalur, '/')
            : null;
    }

    public static function urlSematan(string $penyedia, string $idVideo): string
    {
        return match ($penyedia) {
            // Ranah tanpa kuki dipakai agar pemutaran tidak meninggalkan
            // penanda iklan pada peramban warga (REQ-SW-006).
            'youtube' => 'https://www.youtube-nocookie.com/embed/'.$idVideo,
            'vimeo' => 'https://player.vimeo.com/video/'.$idVideo,
            default => '',
        };
    }

    /**
     * Mengambil gambar sampul dan menyimpannya di server desa.
     *
     * Dilakukan satu kali saat pengelola menyimpan video, bukan saat pengunjung
     * membuka galeri. Bila gagal — server desa tidak selalu punya akses keluar —
     * video tetap tersimpan dan galeri menampilkan kartu tanpa gambar.
     */
    public function simpanThumbnail(string $penyedia, string $idVideo): ?string
    {
        $sumber = match ($penyedia) {
            'youtube' => ['https://i.ytimg.com/vi/'.$idVideo.'/hqdefault.jpg'],
            'vimeo' => [],
            default => [],
        };

        if ($penyedia === 'vimeo') {
            $sumber = array_filter([$this->sampulVimeo($idVideo)]);
        }

        foreach ($sumber as $alamat) {
            try {
                $jawab = Http::timeout(8)->get($alamat);

                if ($jawab->successful() && str_starts_with((string) $jawab->header('Content-Type'), 'image/')) {
                    $berkas = 'video-sampul/'.$penyedia.'-'.$idVideo.'.jpg';
                    Storage::disk('public')->put($berkas, $jawab->body());

                    return $berkas;
                }
            } catch (\Throwable $galat) {
                Log::warning('Gagal mengambil sampul video.', ['penyedia' => $penyedia, 'pesan' => $galat->getMessage()]);
            }
        }

        return null;
    }

    private function sampulVimeo(string $idVideo): ?string
    {
        try {
            $jawab = Http::timeout(8)->get('https://vimeo.com/api/oembed.json', [
                'url' => 'https://vimeo.com/'.$idVideo,
            ]);

            return $jawab->successful() ? ($jawab->json('thumbnail_url') ?: null) : null;
        } catch (\Throwable $galat) {
            Log::warning('Gagal membaca oEmbed Vimeo.', ['pesan' => $galat->getMessage()]);

            return null;
        }
    }

    private function tolak(string $pesan): never
    {
        throw ValidationException::withMessages(['url' => $pesan]);
    }
}
