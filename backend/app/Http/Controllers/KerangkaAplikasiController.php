<?php

namespace App\Http\Controllers;

use App\Services\MetadataHalaman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response as JawabanDasar;

/**
 * Menyajikan kerangka aplikasi React beserta metadata yang sudah terisi
 * (REQ-F-SRC-004, REQ-F-SRC-005).
 *
 * Berkas `index.html` hasil build dipakai apa adanya; yang ditambahkan hanya
 * tag pada `<head>`. Dengan begitu tidak ada duplikasi pembangunan antarmuka di
 * sisi server, dan aplikasi tetap satu-satunya yang menggambar halaman.
 *
 * Setelah skrip berjalan, `useMeta` di sisi klien menemukan tag yang sama dan
 * memperbaruinya alih-alih menambah tag baru, sehingga tidak terjadi metadata
 * ganda saat pengguna berpindah halaman.
 */
class KerangkaAplikasiController extends Controller
{
    public function __construct(private readonly MetadataHalaman $metadata) {}

    public function __invoke(Request $request): JawabanDasar
    {
        $berkas = rtrim((string) config('app.dist'), '/').'/index.html';

        if (! File::exists($berkas)) {
            /*
             * Antarmuka belum dibangun atau jalurnya keliru. Selama
             * pengembangan, antarmuka memang dilayani server Vite tersendiri,
             * jadi pengunjung diarahkan ke sana alih-alih menerima galat.
             */
            return response()->redirectTo((string) config('app.frontend_url'), 302);
        }

        $meta = $this->metadata->untuk($request->path());
        $html = $this->tanpaMetaBawaan(File::get($berkas));

        return response(
            str_replace('</head>', $this->tag($meta)."\n</head>", $html),
            $meta['ditemukan'] ? 200 : 404,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    /**
     * Membuang judul dan deskripsi bawaan dari berkas build.
     *
     * Bila dibiarkan, halaman memuat dua `<title>` sekaligus dan peramban
     * memakai yang pertama — yaitu judul umum, bukan judul halaman ini.
     */
    private function tanpaMetaBawaan(string $html): string
    {
        return (string) preg_replace(
            ['#<title>.*?</title>#is', '#<meta\s+name=(["\'])description\1[^>]*>#i'],
            '',
            $html,
        );
    }

    /** @param array<string, mixed> $meta */
    private function tag(array $meta): string
    {
        $judul = e($meta['judul']);
        $deskripsi = e($meta['deskripsi']);
        $kanonik = e($meta['kanonik']);

        $baris = [
            "<title>{$judul}</title>",
            '<meta name="description" content="'.$deskripsi.'">',
            '<link rel="canonical" href="'.$kanonik.'">',
            '<meta property="og:title" content="'.$judul.'">',
            '<meta property="og:description" content="'.$deskripsi.'">',
            '<meta property="og:type" content="'.e($meta['jenis']).'">',
            '<meta property="og:url" content="'.$kanonik.'">',
            '<meta property="og:site_name" content="'.e($meta['nama_situs']).'">',
            '<meta property="og:locale" content="id_ID">',
            '<meta name="twitter:card" content="'.($meta['gambar'] ? 'summary_large_image' : 'summary').'">',
        ];

        if ($meta['gambar']) {
            $baris[] = '<meta property="og:image" content="'.e($meta['gambar']).'">';
        }

        // Halaman milik satu pengguna tidak boleh masuk indeks mesin pencari.
        if (! $meta['diindeks']) {
            $baris[] = '<meta name="robots" content="noindex, nofollow">';
        }

        if ($meta['terstruktur']) {
            $json = json_encode(
                ['@context' => 'https://schema.org', ...$meta['terstruktur']],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );

            /*
             * Ditandai `terstruktur-server` agar sisi klien dapat menyingkirkan
             * blok ini saat memasang miliknya sendiri; tanpa itu, halaman akan
             * memuat dua blok data terstruktur sekaligus.
             */
            $baris[] = '<script type="application/ld+json" data-sidesa="terstruktur-server">'
                .str_replace('<', '<', (string) $json).'</script>';
        }

        return "\n    ".implode("\n    ", $baris);
    }
}
