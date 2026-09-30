<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Role;
use App\Models\VideoAlbum;
use App\Services\PenyematVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** REQ-F-GAL-006: penyematan video dari penyedia eksternal melalui URL. */
class VideoAlbumTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanReferensi();
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('gambar-palsu', 200, ['Content-Type' => 'image/jpeg'])]);
    }

    private function album(): Album
    {
        return Album::create(['nama' => 'Kerja Bakti', 'slug' => 'kerja-bakti']);
    }

    /** Berbagai bentuk alamat yang lazim disalin pengelola harus dikenali sama. */
    public function test_ragam_alamat_youtube_menghasilkan_pengenal_yang_sama(): void
    {
        $penyemat = app(PenyematVideo::class);

        $alamat = [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ',
            'https://m.youtube.com/watch?v=dQw4w9WgXcQ&t=30s',
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ',
        ];

        foreach ($alamat as $satu) {
            $this->assertSame(
                ['penyedia' => 'youtube', 'id_video' => 'dQw4w9WgXcQ'],
                $penyemat->urai($satu),
                "Gagal mengurai: {$satu}",
            );
        }
    }

    public function test_alamat_vimeo_dikenali(): void
    {
        $this->assertSame(
            ['penyedia' => 'vimeo', 'id_video' => '123456789'],
            app(PenyematVideo::class)->urai('https://vimeo.com/123456789'),
        );
    }

    /**
     * Penyedia di luar daftar putih ditolak, bukan disematkan apa adanya.
     * Bingkai yang menunjuk ke sembarang alamat menjalankan halaman pihak lain
     * di dalam laman desa.
     */
    public function test_penyedia_tidak_dikenal_ditolak(): void
    {
        $penyemat = app(PenyematVideo::class);

        foreach ([
            'https://situs-asing.example/video/1',
            'javascript:alert(1)',
            'data:text/html,<script>alert(1)</script>',
            'https://youtube.com.penipu.example/watch?v=dQw4w9WgXcQ',
        ] as $jahat) {
            try {
                $penyemat->urai($jahat);
                $this->fail("Seharusnya ditolak: {$jahat}");
            } catch (ValidationException $galat) {
                $this->assertArrayHasKey('url', $galat->errors());
            }
        }
    }

    /** Alamat sematan dibentuk dari pengenal, tanpa memakai masukan pengelola. */
    public function test_alamat_sematan_memakai_ranah_tanpa_kuki(): void
    {
        $this->assertSame(
            'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            PenyematVideo::urlSematan('youtube', 'dQw4w9WgXcQ'),
        );
    }

    public function test_petugas_menyematkan_video_dan_sampulnya_disimpan_lokal(): void
    {
        $album = $this->album();

        $jawab = $this->actingAs($this->buatPengguna(Role::OPERATOR))
            ->postJson("/api/v1/admin/media/album/{$album->id}/video", [
                'judul' => 'Kerja bakti bulan ini',
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ])->assertOk();

        $this->assertSame('youtube', $jawab->json('data.penyedia'));
        Storage::disk('public')->assertExists('video-sampul/youtube-dQw4w9WgXcQ.jpg');

        // Sampul dilayani dari server desa, bukan dari penyedia.
        $this->assertStringNotContainsString('ytimg.com', (string) $jawab->json('data.thumbnail'));
    }

    /** Sampul gagal diambil bukan alasan menolak video. */
    public function test_video_tetap_tersimpan_saat_sampul_gagal_diambil(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $album = $this->album();

        $this->actingAs($this->buatPengguna(Role::OPERATOR))
            ->postJson("/api/v1/admin/media/album/{$album->id}/video", [
                'judul' => 'Tanpa sampul',
                'url' => 'https://vimeo.com/987654321',
            ])->assertOk()
            ->assertJsonPath('data.thumbnail', null);

        $this->assertDatabaseHas('video_album', ['id_video' => '987654321', 'thumbnail_path' => null]);
    }

    public function test_video_tampil_pada_album_publik(): void
    {
        $album = $this->album();
        VideoAlbum::create([
            'album_id' => $album->id,
            'judul' => 'Kerja bakti',
            'penyedia' => 'youtube',
            'id_video' => 'dQw4w9WgXcQ',
            'url_asli' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $this->getJson('/api/v1/galeri/kerja-bakti')
            ->assertOk()
            ->assertJsonPath('video.0.judul', 'Kerja bakti')
            ->assertJsonPath('video.0.url_sematan', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
    }

    public function test_video_yang_sama_tidak_tersemat_dua_kali(): void
    {
        $album = $this->album();
        $petugas = $this->buatPengguna(Role::OPERATOR);

        foreach (['Judul pertama', 'Judul diperbaiki'] as $judul) {
            $this->actingAs($petugas)
                ->postJson("/api/v1/admin/media/album/{$album->id}/video", [
                    'judul' => $judul,
                    'url' => 'https://youtu.be/dQw4w9WgXcQ',
                ])->assertOk();
        }

        $this->assertSame(1, VideoAlbum::count());
        $this->assertSame('Judul diperbaiki', VideoAlbum::first()->judul);
    }

    public function test_penyematan_tertutup_bagi_warga(): void
    {
        $album = $this->album();

        $this->actingAs($this->buatWarga())
            ->postJson("/api/v1/admin/media/album/{$album->id}/video", [
                'judul' => 'Coba',
                'url' => 'https://youtu.be/dQw4w9WgXcQ',
            ])->assertForbidden();
    }
}
