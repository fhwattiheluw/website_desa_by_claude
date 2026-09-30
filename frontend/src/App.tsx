import { lazy, Suspense } from 'react'
import { BrowserRouter, Route, Routes } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { PenyediaAuth } from '@/components/layout/PenyediaAuth'
import { PenyediaBahasa } from '@/components/layout/PenyediaBahasa'
import { TataLetakPublik } from '@/components/layout/TataLetakPublik'
import { TataLetakPanel } from '@/components/layout/TataLetakPanel'
import { Terlindungi } from '@/components/layout/Terlindungi'
import { BatasGalat } from '@/components/layout/BatasGalat'
import { Pemuat } from '@/components/ui/Status'

import { Beranda } from '@/pages/publik/Beranda'
import { Profil } from '@/pages/publik/Profil'
import { DaftarKonten } from '@/pages/publik/DaftarKonten'
import { DetailKonten } from '@/pages/publik/DetailKonten'
import { Layanan } from '@/pages/publik/Layanan'
import { VerifikasiSurat } from '@/pages/publik/VerifikasiSurat'
import { Pengaduan } from '@/pages/publik/Pengaduan'
import { LacakPengaduan } from '@/pages/publik/LacakPengaduan'
import { Masuk } from '@/pages/auth/Masuk'
import { Daftar } from '@/pages/auth/Daftar'
import { AturUlangKataSandi, LupaKataSandi } from '@/pages/auth/PemulihanKataSandi'
import {
  Aksesibilitas,
  Galeri,
  KebijakanPrivasi,
  Kontak,
  Pencarian,
  SyaratPenggunaan,
  TidakDitemukan,
} from '@/pages/publik/Halaman'

// Halaman transparansi memuat pustaka grafik, sehingga dipisah dari berkas utama
// agar halaman pertama tetap ringan pada koneksi lambat (REQ-NF-PRF-003).
const Apbdes = lazy(() => import('@/pages/publik/Apbdes').then((m) => ({ default: m.Apbdes })))
const Statistik = lazy(() => import('@/pages/publik/Statistik').then((m) => ({ default: m.Statistik })))
const ProdukHukum = lazy(() => import('@/pages/publik/Pustaka').then((m) => ({ default: m.ProdukHukum })))
const Ppid = lazy(() => import('@/pages/publik/Pustaka').then((m) => ({ default: m.Ppid })))
const PermohonanInformasi = lazy(() =>
  import('@/pages/publik/PermohonanInformasi').then((m) => ({ default: m.PermohonanInformasi })),
)
const LacakInformasi = lazy(() =>
  import('@/pages/publik/LacakInformasi').then((m) => ({ default: m.LacakInformasi })),
)
const DirektoriUmkm = lazy(() => import('@/pages/publik/Potensi').then((m) => ({ default: m.DirektoriUmkm })))
const DaftarUmkm = lazy(() => import('@/pages/publik/DaftarUmkm').then((m) => ({ default: m.DaftarUmkm })))
const Bumdes = lazy(() => import('@/pages/publik/Bumdes').then((m) => ({ default: m.Bumdes })))
const DestinasiWisata = lazy(() => import('@/pages/publik/Potensi').then((m) => ({ default: m.DestinasiWisata })))

const Akun = lazy(() => import('@/pages/warga/Akun').then((m) => ({ default: m.Akun })))
const AjukanSurat = lazy(() => import('@/pages/warga/AjukanSurat').then((m) => ({ default: m.AjukanSurat })))
const DetailPermohonan = lazy(() =>
  import('@/pages/warga/DetailPermohonan').then((m) => ({ default: m.DetailPermohonan })),
)

const Dasbor = lazy(() => import('@/pages/admin/Dasbor').then((m) => ({ default: m.Dasbor })))
const AntreanPermohonan = lazy(() =>
  import('@/pages/admin/AntreanPermohonan').then((m) => ({ default: m.AntreanPermohonan })),
)
const DetailPermohonanAdmin = lazy(() =>
  import('@/pages/admin/DetailPermohonanAdmin').then((m) => ({ default: m.DetailPermohonanAdmin })),
)
const KelolaPengaduan = lazy(() => import('@/pages/admin/KelolaPengaduan').then((m) => ({ default: m.KelolaPengaduan })))
const KelolaKonten = lazy(() => import('@/pages/admin/KelolaKonten').then((m) => ({ default: m.KelolaKonten })))
const KelolaPengguna = lazy(() => import('@/pages/admin/KelolaPengguna').then((m) => ({ default: m.KelolaPengguna })))
const KelolaApbdes = lazy(() => import('@/pages/admin/KelolaApbdes').then((m) => ({ default: m.KelolaApbdes })))
const Laporan = lazy(() => import('@/pages/admin/Laporan').then((m) => ({ default: m.Laporan })))
const AuditLog = lazy(() => import('@/pages/admin/AuditLog').then((m) => ({ default: m.AuditLog })))
const PengaturanSitus = lazy(() => import('@/pages/admin/Pengaturan').then((m) => ({ default: m.Pengaturan })))
const KelolaBumdes = lazy(() => import('@/pages/admin/KelolaBumdes').then((m) => ({ default: m.KelolaBumdes })))
const TandaTangan = lazy(() => import('@/pages/admin/TandaTangan').then((m) => ({ default: m.TandaTangan })))
const PermintaanData = lazy(() =>
  import('@/pages/admin/PermintaanData').then((m) => ({ default: m.PermintaanData })),
)
const Analitik = lazy(() => import('@/pages/admin/Analitik').then((m) => ({ default: m.Analitik })))
const Moderasi = lazy(() => import('@/pages/admin/Moderasi').then((m) => ({ default: m.Moderasi })))

const klienKueri = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 60 * 1000,
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
})

export default function App() {
  return (
    <QueryClientProvider client={klienKueri}>
      <BrowserRouter>
        <PenyediaAuth>
          <PenyediaBahasa>
          <BatasGalat>
            <Suspense fallback={<Pemuat />}>
            <Routes>
              <Route element={<TataLetakPublik />}>
                <Route index element={<Beranda />} />
                <Route path="profil" element={<Profil />} />

                <Route path="berita" element={<DaftarKonten tipe="berita" />} />
                <Route path="berita/:slug" element={<DetailKonten tipe="berita" />} />
                <Route path="artikel" element={<DaftarKonten tipe="artikel" />} />
                <Route path="artikel/:slug" element={<DetailKonten tipe="artikel" />} />
                <Route path="pengumuman" element={<DaftarKonten tipe="pengumuman" />} />
                <Route path="pengumuman/:slug" element={<DetailKonten tipe="pengumuman" />} />
                <Route path="agenda" element={<DaftarKonten tipe="agenda" />} />
                <Route path="agenda/:slug" element={<DetailKonten tipe="agenda" />} />
                <Route path="galeri" element={<Galeri />} />

                <Route path="transparansi/apbdes" element={<Apbdes />} />
                <Route path="transparansi/statistik" element={<Statistik />} />
                <Route path="transparansi/produk-hukum" element={<ProdukHukum />} />
                <Route path="ppid" element={<Ppid />} />
                <Route path="ppid/permohonan" element={<PermohonanInformasi />} />
                <Route path="ppid/lacak" element={<LacakInformasi />} />

                <Route path="layanan" element={<Layanan />} />
                <Route path="layanan/verifikasi" element={<VerifikasiSurat />} />
                <Route path="layanan/verifikasi/:kode" element={<VerifikasiSurat />} />
                <Route
                  path="layanan/:slug"
                  element={
                    <Terlindungi>
                      <AjukanSurat />
                    </Terlindungi>
                  }
                />

                <Route path="pengaduan" element={<Pengaduan />} />
                <Route path="pengaduan/lacak" element={<LacakPengaduan />} />

                <Route path="potensi/umkm" element={<DirektoriUmkm />} />
                <Route path="potensi/umkm/daftar" element={<DaftarUmkm />} />
                <Route path="potensi/wisata" element={<DestinasiWisata />} />
                <Route path="potensi/bumdes" element={<Bumdes />} />

                <Route path="pencarian" element={<Pencarian />} />
                <Route path="kontak" element={<Kontak />} />
                <Route path="kebijakan-privasi" element={<KebijakanPrivasi />} />
                <Route path="syarat-penggunaan" element={<SyaratPenggunaan />} />
                <Route path="aksesibilitas" element={<Aksesibilitas />} />

                <Route path="masuk" element={<Masuk />} />
                <Route path="daftar" element={<Daftar />} />
                <Route path="lupa-kata-sandi" element={<LupaKataSandi />} />
                <Route path="atur-ulang-kata-sandi" element={<AturUlangKataSandi />} />

                <Route
                  path="akun"
                  element={
                    <Terlindungi>
                      <Akun />
                    </Terlindungi>
                  }
                />
                <Route
                  path="akun/permohonan/:id"
                  element={
                    <Terlindungi>
                      <DetailPermohonan />
                    </Terlindungi>
                  }
                />

                <Route path="*" element={<TidakDitemukan />} />
              </Route>

              <Route
                path="/admin"
                element={
                  <Terlindungi izin={['dashboard.lihat', 'permohonan.lihat']}>
                    <TataLetakPanel />
                  </Terlindungi>
                }
              >
                <Route index element={<Dasbor />} />
                <Route path="permohonan" element={<AntreanPermohonan />} />
                <Route path="permohonan/:id" element={<DetailPermohonanAdmin />} />
                <Route path="pengaduan" element={<KelolaPengaduan />} />
                <Route path="konten" element={<KelolaKonten />} />
                <Route path="apbdes" element={<KelolaApbdes />} />
                <Route path="pengguna" element={<KelolaPengguna />} />
                <Route path="laporan" element={<Laporan />} />
                <Route path="analitik" element={<Analitik />} />
                <Route path="moderasi" element={<Moderasi />} />
                <Route path="audit-log" element={<AuditLog />} />
                <Route path="permintaan-data" element={<PermintaanData />} />
                <Route path="bumdes" element={<KelolaBumdes />} />
                <Route path="tanda-tangan" element={<TandaTangan />} />
                <Route path="pengaturan" element={<PengaturanSitus />} />
              </Route>
            </Routes>
            </Suspense>
          </BatasGalat>
          </PenyediaBahasa>
        </PenyediaAuth>
      </BrowserRouter>
    </QueryClientProvider>
  )
}
