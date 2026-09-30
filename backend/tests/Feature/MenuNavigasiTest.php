<?php

namespace Tests\Feature;

use App\Models\MenuNavigasi;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** REQ-F-ADM-003: pengelola menu navigasi. */
class MenuNavigasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanReferensi();
    }

    private function butir(array $atribut = []): MenuNavigasi
    {
        return MenuNavigasi::create([
            'label' => 'Butir '.fake()->unique()->numberBetween(1, 9999),
            'tautan' => '/contoh',
            ...$atribut,
        ]);
    }

    public function test_menu_publik_menyusun_dua_tingkat(): void
    {
        $induk = $this->butir(['label' => 'Informasi', 'tautan' => '/berita', 'urutan' => 0]);
        $this->butir(['label' => 'Agenda', 'tautan' => '/agenda', 'induk_id' => $induk->id]);
        $this->butir(['label' => 'Tersembunyi', 'tautan' => '/rahasia', 'aktif' => false, 'urutan' => 1]);

        $jawab = $this->getJson('/api/v1/menu')->assertOk();

        $this->assertCount(1, $jawab->json('data'));
        $this->assertSame('Informasi', $jawab->json('data.0.label'));
        $this->assertSame('Agenda', $jawab->json('data.0.anak.0.label'));
        $this->assertStringNotContainsString('Tersembunyi', $jawab->getContent());
    }

    public function test_penyarangan_dibatasi_dua_tingkat(): void
    {
        $induk = $this->butir();
        $anak = $this->butir(['induk_id' => $induk->id]);

        $this->actingAs($this->buatPengguna(Role::ADMIN))
            ->postJson('/api/v1/admin/menu', [
                'label' => 'Cucu',
                'tautan' => '/cucu',
                'induk_id' => $anak->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('induk_id');
    }

    public function test_butir_beranak_tidak_dapat_dijadikan_anak(): void
    {
        $satu = $this->butir();
        $dua = $this->butir();
        $this->butir(['induk_id' => $dua->id]);

        $this->actingAs($this->buatPengguna(Role::ADMIN))
            ->putJson("/api/v1/admin/menu/{$dua->id}", [
                'label' => $dua->label,
                'tautan' => '/pindah',
                'induk_id' => $satu->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('induk_id');
    }

    public function test_butir_tingkat_satu_dibatasi_tujuh(): void
    {
        for ($ke = 0; $ke < MenuNavigasi::MAKS_TINGKAT_SATU; $ke++) {
            $this->butir(['urutan' => $ke]);
        }

        $this->actingAs($this->buatPengguna(Role::ADMIN))
            ->postJson('/api/v1/admin/menu', ['label' => 'Kedelapan', 'tautan' => '/delapan'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('induk_id');
    }

    /**
     * Tautan `javascript:` pada atribut href menjalankan kode di peramban setiap
     * pengunjung. Pengelola menu adalah satu-satunya tempat di sistem ini yang
     * membiarkan seseorang menulis alamat tautan bebas.
     */
    public function test_tautan_berskema_berbahaya_ditolak(): void
    {
        $admin = $this->buatPengguna(Role::ADMIN);

        foreach (['javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'data:text/html,<script>'] as $jahat) {
            $this->actingAs($admin)
                ->postJson('/api/v1/admin/menu', ['label' => 'Jahat', 'tautan' => $jahat])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('tautan');
        }

        $this->assertSame(0, MenuNavigasi::count());
    }

    public function test_tautan_dalam_situs_dan_alamat_lengkap_diterima(): void
    {
        $admin = $this->buatPengguna(Role::ADMIN);

        foreach (['/berita', 'https://kemendesa.go.id', 'mailto:desa@contoh.id'] as $sah) {
            $this->actingAs($admin)
                ->postJson('/api/v1/admin/menu', ['label' => 'Sah', 'tautan' => $sah])
                ->assertOk();
        }

        $this->assertSame(3, MenuNavigasi::count());
    }

    public function test_menghapus_induk_ikut_menghapus_anaknya(): void
    {
        $induk = $this->butir();
        $anak = $this->butir(['induk_id' => $induk->id]);

        $this->actingAs($this->buatPengguna(Role::ADMIN))
            ->deleteJson("/api/v1/admin/menu/{$induk->id}")
            ->assertOk();

        $this->assertDatabaseMissing('menu_navigasi', ['id' => $anak->id]);
    }

    public function test_urutan_tersimpan_dan_dipakai_menu_publik(): void
    {
        $satu = $this->butir(['label' => 'Pertama', 'urutan' => 0]);
        $dua = $this->butir(['label' => 'Kedua', 'urutan' => 1]);

        $this->actingAs($this->buatPengguna(Role::ADMIN))
            ->postJson('/api/v1/admin/menu/urutkan', [
                'urutan' => [['id' => $dua->id, 'urutan' => 0], ['id' => $satu->id, 'urutan' => 1]],
            ])->assertOk();

        $this->assertSame('Kedua', $this->getJson('/api/v1/menu')->json('data.0.label'));
    }

    public function test_pengelola_menu_tertutup_bagi_peran_tanpa_izin(): void
    {
        $this->actingAs($this->buatPengguna(Role::OPERATOR))
            ->getJson('/api/v1/admin/menu')
            ->assertForbidden();

        $this->actingAs($this->buatWarga())
            ->postJson('/api/v1/admin/menu', ['label' => 'Coba', 'tautan' => '/coba'])
            ->assertForbidden();
    }

    /** Perubahan menu harus segera tampak di portal, bukan setelah tembolok kedaluwarsa. */
    public function test_tembolok_menu_disegarkan_setelah_perubahan(): void
    {
        $this->butir(['label' => 'Lama', 'tautan' => '/lama']);
        $this->getJson('/api/v1/menu')->assertJsonPath('data.0.label', 'Lama');

        $this->actingAs($this->buatPengguna(Role::ADMIN))
            ->postJson('/api/v1/admin/menu', ['label' => 'Baru', 'tautan' => '/baru'])
            ->assertOk();

        $this->assertCount(2, $this->getJson('/api/v1/menu')->json('data'));
    }
}
