<?php

namespace App\Services;

use App\Models\Album;
use App\Models\JenisLayanan;
use App\Models\Konten;
use App\Models\Pengaturan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Metadata setiap halaman untuk perayap yang tidak menjalankan JavaScript
 * (REQ-F-SRC-004, REQ-F-SRC-005).
 *
 * Portal dirender di peramban, sehingga judul dan deskripsi baru terbentuk
 * setelah skrip dijalankan. Mesin pencari besar memang menjalankannya, tetapi
 * banyak perayap lain — termasuk pembuat pratinjau tautan pada aplikasi pesan
 * yang paling banyak dipakai warga desa — tidak. Berkas ini menyiapkan
 * metadata yang sama di sisi server agar tautan yang dibagikan tetap tampil
 * utuh.
 */
class MetadataHalaman
{
    /** Metadata jarang berubah, sedangkan perayap dapat datang bertubi-tubi. */
    private const SINGGAH_MENIT = 10;

    /** Keterangan halaman yang isinya tidak berasal dari basis data. */
    private const HALAMAN_TETAP = [
        '/profil' => ['Profil Desa', 'Sejarah, visi dan misi, letak wilayah, struktur pemerintahan, serta lembaga kemasyarakatan desa.'],
        '/berita' => ['Berita Desa', 'Kabar terbaru kegiatan pemerintah desa dan masyarakat.'],
        '/artikel' => ['Artikel', 'Tulisan dan liputan mendalam seputar kehidupan desa.'],
        '/pengumuman' => ['Pengumuman', 'Pengumuman resmi pemerintah desa yang masih berlaku.'],
        '/agenda' => ['Agenda Kegiatan', 'Jadwal kegiatan dan musyawarah desa.'],
        '/galeri' => ['Galeri Kegiatan', 'Dokumentasi kegiatan pemerintah desa dan masyarakat.'],
        '/dokumentasi-api' => [
            'Dokumentasi API',
            'Daftar titik akhir API portal desa beserta izin dan batas lajunya, dalam format OpenAPI 3.1.',
        ],
        '/layanan' => ['Katalog Layanan', 'Daftar surat dan layanan administrasi yang dapat diajukan secara daring.'],
        '/layanan/verifikasi' => ['Verifikasi Surat', 'Periksa keaslian surat yang diterbitkan pemerintah desa melalui kode verifikasi.'],
        '/transparansi/apbdes' => ['Transparansi APBDes', 'Rincian pendapatan, belanja, dan realisasi anggaran pendapatan dan belanja desa.'],
        '/transparansi/statistik' => ['Statistik Desa', 'Data kependudukan desa yang disajikan secara agregat.'],
        '/transparansi/produk-hukum' => ['Produk Hukum Desa', 'Peraturan desa, peraturan kepala desa, dan keputusan yang berlaku.'],
        '/ppid' => ['PPID Desa', 'Daftar informasi publik yang wajib disediakan dan diumumkan pemerintah desa.'],
        '/ppid/permohonan' => ['Permohonan Informasi Publik', 'Ajukan permohonan informasi kepada PPID Desa secara daring.'],
        '/ppid/lacak' => ['Lacak Permohonan Informasi Publik', 'Periksa status permohonan informasi publik dan ajukan keberatan bila permohonan ditolak.'],
        '/pengaduan' => ['Pengaduan dan Aspirasi Masyarakat', 'Sampaikan laporan, keluhan, atau usulan kepada pemerintah desa dan pantau tindak lanjutnya.'],
        '/pengaduan/lacak' => ['Lacak Pengaduan', 'Pantau status tindak lanjut pengaduan dengan kode lacak.'],
        '/potensi/umkm' => ['Direktori UMKM Desa', 'Produk dan jasa pelaku usaha mikro, kecil, dan menengah di desa.'],
        '/potensi/umkm/daftar' => ['Daftarkan Usaha Anda', 'Formulir pendaftaran mandiri bagi pelaku usaha desa.'],
        '/potensi/wisata' => ['Destinasi Wisata Desa', 'Tempat wisata desa beserta jam operasional, tarif, dan lokasinya.'],
        '/potensi/bumdes' => ['BUMDes', 'Profil badan usaha milik desa: unit usaha, kinerja tahunan, dan pengurus.'],
        '/kontak' => ['Kontak Pemerintah Desa', 'Alamat kantor, telepon, surel, dan jam pelayanan pemerintah desa.'],
        '/kebijakan-privasi' => ['Kebijakan Privasi', 'Bagaimana pemerintah desa mengumpulkan, melindungi, menyimpan, dan menghapus data pribadi warga.'],
        '/syarat-penggunaan' => ['Syarat Penggunaan', 'Ketentuan penggunaan portal desa: hak, kewajiban, dan batasan bagi pengguna layanan daring.'],
        '/aksesibilitas' => ['Pernyataan Aksesibilitas', 'Komitmen dan langkah portal desa memenuhi WCAG 2.1 tingkat AA.'],
    ];

    /**
     * Halaman yang isinya milik satu orang atau belum tentu tayang, sehingga
     * tidak boleh diindeks dan tidak perlu metadata khusus.
     */
    private const TIDAK_DIINDEKS = ['/akun', '/admin', '/masuk', '/daftar', '/lupa-kata-sandi', '/atur-ulang-kata-sandi'];

    /** @return array<string, mixed> */
    public function untuk(string $jalur): array
    {
        $jalur = '/'.trim(parse_url($jalur, PHP_URL_PATH) ?: '', '/');
        $kunci = 'metadata-halaman:'.$jalur;

        if (is_array($tersimpan = Cache::get($kunci))) {
            return $tersimpan;
        }

        $meta = $this->susun($jalur);

        /*
         * Hanya halaman yang benar-benar dikenali yang disinggahkan. Perayap
         * kerap mencoba alamat acak, dan menyinggahkan tiap alamat yang tidak
         * dikenali akan menggelembungkan singgahan tanpa manfaat apa pun.
         */
        if ($meta['dikenali']) {
            Cache::put($kunci, $meta, now()->addMinutes(self::SINGGAH_MENIT));
        }

        return $meta;
    }

    /** @return array<string, mixed> */
    private function susun(string $jalur): array
    {
        $desa = Pengaturan::semua();
        $namaSitus = filled($desa['nama_desa'] ?? null) ? 'Desa '.$desa['nama_desa'] : 'Portal Desa';

        $dasar = [
            'judul' => $namaSitus,
            'deskripsi' => 'Portal resmi pemerintah desa: informasi publik, transparansi anggaran, '
                .'layanan administrasi daring, dan kanal pengaduan masyarakat.',
            'gambar' => null,
            'jenis' => 'website',
            'kanonik' => $this->kanonik($jalur),
            'nama_situs' => $namaSitus,
            'diindeks' => true,
            'terstruktur' => null,
            'ditemukan' => true,
            'dikenali' => false,
        ];

        foreach (self::TIDAK_DIINDEKS as $awalan) {
            if ($jalur === $awalan || str_starts_with($jalur, $awalan.'/')) {
                // Subjalurnya tak terbatas (/akun/permohonan/12), jadi tidak
                // disinggahkan; menyusunnya pun tidak menyentuh basis data.
                return [...$dasar, 'diindeks' => false];
            }
        }

        if ($jalur === '/') {
            return [
                ...$dasar,
                'terstruktur' => $this->lembagaPemerintah($desa, $namaSitus),
                'dikenali' => true,
            ];
        }

        if (isset(self::HALAMAN_TETAP[$jalur])) {
            [$judul, $deskripsi] = self::HALAMAN_TETAP[$jalur];

            return [...$dasar, 'judul' => $judul.' — '.$namaSitus, 'deskripsi' => $deskripsi, 'dikenali' => true];
        }

        if ($temuan = $this->halamanDinamis($jalur, $dasar, $namaSitus)) {
            return $temuan;
        }

        /*
         * Alamat berpola halaman rinci — /berita/{slug}, /layanan/{slug} —
         * yang isinya tidak ada dijawab 404, bukan 200 berisi kerangka kosong.
         * Perayap perlu tahu halaman itu memang tidak ada agar tidak
         * mengindeksnya.
         */
        $bagian = explode('/', trim($jalur, '/'));
        $berpolaRinci = count($bagian) === 2
            && in_array($bagian[0], [...Konten::TIPE, 'layanan', 'galeri'], true);

        return $berpolaRinci ? [...$dasar, 'ditemukan' => false, 'diindeks' => false] : $dasar;
    }

    /**
     * @param  array<string, mixed>  $dasar
     * @return array<string, mixed>|null
     */
    private function halamanDinamis(string $jalur, array $dasar, string $namaSitus): ?array
    {
        $bagian = explode('/', trim($jalur, '/'));

        if (count($bagian) === 2 && in_array($bagian[0], Konten::TIPE, true)) {
            $konten = Konten::tayang()->with('gambar')->where('slug', $bagian[1])->first();

            if (! $konten) {
                return null;
            }

            $ringkasan = Str::limit(strip_tags((string) ($konten->ringkasan ?: $konten->isi)), 180);

            return [
                ...$dasar,
                'dikenali' => true,
                'judul' => $konten->judul.' — '.$namaSitus,
                'deskripsi' => $ringkasan,
                'gambar' => $konten->gambar?->url(),
                'jenis' => 'article',
                // Nilai kosong dibuang: schema.org menganggap properti bernilai
                // null sebagai data yang keliru, bukan data yang tidak ada.
                'terstruktur' => array_filter([
                    '@type' => $konten->tipe === 'berita' ? 'NewsArticle' : 'Article',
                    'headline' => $konten->judul,
                    'description' => $ringkasan,
                    'datePublished' => $konten->terbit_pada?->toIso8601String(),
                    'dateModified' => $konten->updated_at?->toIso8601String(),
                    'image' => $konten->gambar?->url(),
                    'publisher' => ['@type' => 'GovernmentOrganization', 'name' => $namaSitus],
                    'mainEntityOfPage' => $dasar['kanonik'],
                ]),
            ];
        }

        if (count($bagian) === 2 && $bagian[0] === 'galeri') {
            $album = Album::withCount('media', 'video')->where('slug', $bagian[1])->first();

            if (! $album) {
                return null;
            }

            return [
                ...$dasar,
                'dikenali' => true,
                'judul' => $album->nama.' — '.$namaSitus,
                'deskripsi' => Str::limit((string) $album->deskripsi, 180)
                    ?: 'Dokumentasi kegiatan '.$album->nama.' berupa '.$album->media_count.' foto dan '
                        .$album->video_count.' video.',
                'jenis' => 'article',
                'terstruktur' => array_filter([
                    '@type' => 'ImageGallery',
                    'name' => $album->nama,
                    'description' => $album->deskripsi,
                    'datePublished' => $album->tanggal_kegiatan?->toDateString(),
                    'publisher' => ['@type' => 'GovernmentOrganization', 'name' => $namaSitus],
                    'mainEntityOfPage' => $dasar['kanonik'],
                ]),
            ];
        }

        if (count($bagian) === 2 && $bagian[0] === 'layanan') {
            $layanan = JenisLayanan::where('aktif', true)->where('slug', $bagian[1])->first();

            if (! $layanan) {
                return null;
            }

            return [
                ...$dasar,
                'dikenali' => true,
                'judul' => $layanan->nama.' — '.$namaSitus,
                'deskripsi' => Str::limit((string) $layanan->deskripsi, 180)
                    ?: 'Ajukan '.$layanan->nama.' secara daring tanpa datang ke kantor desa.',
                'terstruktur' => array_filter([
                    '@type' => 'GovernmentService',
                    'name' => $layanan->nama,
                    'description' => $layanan->deskripsi,
                    'provider' => ['@type' => 'GovernmentOrganization', 'name' => $namaSitus],
                    'serviceType' => 'Layanan administrasi kependudukan',
                ]),
            ];
        }

        return null;
    }

    /** @param array<string, mixed> $desa */
    private function lembagaPemerintah(array $desa, string $namaSitus): array
    {
        return array_filter([
            '@type' => 'GovernmentOrganization',
            'name' => $namaSitus,
            'url' => rtrim((string) config('app.frontend_url'), '/').'/',
            'email' => $desa['email'] ?? null,
            'telephone' => $desa['telepon'] ?? null,
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $desa['alamat'] ?? null,
                'addressRegion' => $desa['provinsi'] ?? null,
                'addressCountry' => 'ID',
            ]),
        ]);
    }

    private function kanonik(string $jalur): string
    {
        return rtrim((string) config('app.frontend_url'), '/').($jalur === '/' ? '/' : $jalur);
    }
}
