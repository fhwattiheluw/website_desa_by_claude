<?php

namespace App\Http\Controllers;

use App\Models\JenisLayanan;
use App\Models\Konten;
use App\Models\Pengaturan;
use Illuminate\Http\Response;

/**
 * Pembuatan sitemap.xml dan robots.txt secara otomatis (REQ-F-SRC-003).
 *
 * Kedua berkas disajikan langsung oleh API karena ia yang mengetahui konten
 * mana saja yang benar-benar tayang. URL yang dicantumkan adalah URL kanonik
 * pada aplikasi antarmuka, bukan URL API.
 */
class PetaSitusController extends Controller
{
    /** Halaman statis yang selalu ada beserta bobot dan frekuensi perubahannya. */
    private const HALAMAN_TETAP = [
        ['', 'daily', '1.0'],
        ['/profil', 'yearly', '0.7'],
        ['/berita', 'daily', '0.8'],
        ['/pengumuman', 'weekly', '0.8'],
        ['/agenda', 'weekly', '0.6'],
        ['/galeri', 'monthly', '0.5'],
        ['/layanan', 'monthly', '0.9'],
        ['/layanan/verifikasi', 'yearly', '0.5'],
        ['/transparansi/apbdes', 'monthly', '0.9'],
        ['/transparansi/statistik', 'monthly', '0.7'],
        ['/transparansi/produk-hukum', 'monthly', '0.7'],
        ['/ppid', 'monthly', '0.7'],
        ['/pengaduan', 'monthly', '0.8'],
        ['/potensi/umkm', 'weekly', '0.7'],
        ['/potensi/wisata', 'monthly', '0.6'],
        ['/kontak', 'yearly', '0.5'],
        ['/kebijakan-privasi', 'yearly', '0.3'],
        ['/aksesibilitas', 'yearly', '0.3'],
    ];

    public function petaSitus(): Response
    {
        $basis = rtrim((string) config('app.frontend_url'), '/');
        $baris = [];

        foreach (self::HALAMAN_TETAP as [$jalur, $frekuensi, $bobot]) {
            $baris[] = $this->url($basis.$jalur, null, $frekuensi, $bobot);
        }

        Konten::tayang()
            ->select('tipe', 'slug', 'updated_at')
            ->orderByDesc('terbit_pada')
            ->limit(2000)
            ->get()
            ->each(function (Konten $konten) use (&$baris, $basis) {
                $baris[] = $this->url(
                    "{$basis}/{$konten->tipe}/{$konten->slug}",
                    $konten->updated_at?->toAtomString(),
                    'monthly',
                    '0.6',
                );
            });

        JenisLayanan::where('aktif', true)
            ->select('slug', 'updated_at')
            ->get()
            ->each(function (JenisLayanan $layanan) use (&$baris, $basis) {
                $baris[] = $this->url(
                    "{$basis}/layanan/{$layanan->slug}",
                    $layanan->updated_at?->toAtomString(),
                    'monthly',
                    '0.7',
                );
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .implode("\n", $baris)."\n"
            .'</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $basis = rtrim((string) config('app.frontend_url'), '/');

        $baris = [
            'User-agent: *',
            'Allow: /',
            '',
            '# Area pribadi dan panel petugas tidak perlu diindeks.',
            'Disallow: /akun',
            'Disallow: /admin',
            'Disallow: /masuk',
            'Disallow: /daftar',
            'Disallow: /atur-ulang-kata-sandi',
            '',
            "Sitemap: {$basis}/sitemap.xml",
        ];

        return response(implode("\n", $baris)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** REQ-F-SRC-006: umpan RSS untuk berita dan pengumuman. */
    public function rss(string $tipe): Response
    {
        abort_unless(in_array($tipe, ['berita', 'pengumuman'], true), 404);

        $basis = rtrim((string) config('app.frontend_url'), '/');
        $namaDesa = Pengaturan::ambil('nama_desa', 'Desa');
        $judulUmpan = $tipe === 'berita' ? "Berita Desa {$namaDesa}" : "Pengumuman Desa {$namaDesa}";

        $konten = Konten::tayang()
            ->where('tipe', $tipe)
            ->latest('terbit_pada')
            ->limit(50)
            ->get();

        $butir = $konten->map(fn (Konten $k) => implode("\n", [
            '    <item>',
            '      <title>'.htmlspecialchars($k->judul, ENT_XML1).'</title>',
            '      <link>'.htmlspecialchars("{$basis}/{$k->tipe}/{$k->slug}", ENT_XML1).'</link>',
            '      <guid isPermaLink="true">'.htmlspecialchars("{$basis}/{$k->tipe}/{$k->slug}", ENT_XML1).'</guid>',
            '      <description>'.htmlspecialchars((string) $k->ringkasan, ENT_XML1).'</description>',
            '      <pubDate>'.($k->terbit_pada ?? $k->created_at)->toRfc2822String().'</pubDate>',
            '    </item>',
        ]))->implode("\n");

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<rss version="2.0"><channel>'."\n"
            .'    <title>'.htmlspecialchars($judulUmpan, ENT_XML1).'</title>'."\n"
            .'    <link>'.htmlspecialchars("{$basis}/{$tipe}", ENT_XML1).'</link>'."\n"
            .'    <description>'.htmlspecialchars("Kabar terbaru dari portal resmi Desa {$namaDesa}.", ENT_XML1).'</description>'."\n"
            .'    <language>id-ID</language>'."\n"
            .$butir."\n"
            .'</channel></rss>';

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }

    private function url(string $lokasi, ?string $diubah, string $frekuensi, string $bobot): string
    {
        $bagian = ['  <url>', '    <loc>'.htmlspecialchars($lokasi, ENT_XML1).'</loc>'];

        if ($diubah !== null) {
            $bagian[] = "    <lastmod>{$diubah}</lastmod>";
        }

        $bagian[] = "    <changefreq>{$frekuensi}</changefreq>";
        $bagian[] = "    <priority>{$bobot}</priority>";
        $bagian[] = '  </url>';

        return implode("\n", $bagian);
    }
}
