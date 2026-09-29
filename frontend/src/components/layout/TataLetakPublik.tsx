import { useState } from 'react'
import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom'
import { Menu, Search, X } from 'lucide-react'
import { useProfilDesa } from '@/lib/kueri'
import { useAuth } from '@/lib/auth'
import { Tombol } from '@/components/ui/Tombol'

/** Navigasi utama maksimal tujuh butir tingkat pertama (REQ-UI-003). */
const MENU = [
  { ke: '/', teks: 'Beranda' },
  { ke: '/profil', teks: 'Profil Desa' },
  { ke: '/berita', teks: 'Informasi' },
  { ke: '/transparansi/apbdes', teks: 'Transparansi' },
  { ke: '/layanan', teks: 'Layanan' },
  { ke: '/pengaduan', teks: 'Partisipasi' },
  { ke: '/potensi/umkm', teks: 'Potensi Desa' },
]

export function TataLetakPublik() {
  const { data: desa } = useProfilDesa()
  const { pengguna } = useAuth()
  const [menuTerbuka, setMenuTerbuka] = useState(false)
  const [kataKunci, setKataKunci] = useState('')
  const navigasi = useNavigate()

  const namaDesa = desa?.nama_desa ? `Desa ${desa.nama_desa}` : 'Portal Desa'

  const cari = (peristiwa: React.FormEvent) => {
    peristiwa.preventDefault()
    if (kataKunci.trim().length >= 3) {
      navigasi(`/pencarian?q=${encodeURIComponent(kataKunci.trim())}`)
      setMenuTerbuka(false)
    }
  }

  return (
    <div className="flex min-h-screen flex-col">
      <a
        href="#konten-utama"
        className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-desa-700 focus:px-4 focus:py-2 focus:text-white"
      >
        Langsung ke konten utama
      </a>

      <header className="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div className="mx-auto flex max-w-6xl items-center gap-3 px-4 py-3">
          <Link to="/" className="flex min-w-0 items-center gap-3">
            <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-desa-700 text-sm font-bold text-white">
              {desa?.nama_desa?.slice(0, 2).toUpperCase() ?? 'DS'}
            </span>
            <span className="min-w-0">
              <span className="block truncate font-semibold text-slate-900">{namaDesa}</span>
              <span className="block truncate text-xs text-slate-500">
                {desa?.kecamatan ? `Kecamatan ${desa.kecamatan}` : 'Portal resmi pemerintah desa'}
              </span>
            </span>
          </Link>

          <div className="ml-auto flex items-center gap-2">
            <form role="search" onSubmit={cari} className="hidden items-center lg:flex">
              <label htmlFor="cari-desktop" className="sr-only">
                Cari informasi
              </label>
              <div className="relative">
                <Search aria-hidden className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input
                  id="cari-desktop"
                  value={kataKunci}
                  onChange={(e) => setKataKunci(e.target.value)}
                  placeholder="Cari informasi…"
                  className="min-h-10 w-52 rounded-lg border border-slate-300 py-2 pl-9 pr-3 text-sm focus:border-desa-600 focus:ring-2 focus:ring-desa-600/20"
                />
              </div>
            </form>

            {pengguna ? (
              <Tombol ukuran="kecil" onClick={() => navigasi(pengguna.petugas ? '/admin' : '/akun')}>
                {pengguna.petugas ? 'Panel Petugas' : 'Akun Saya'}
              </Tombol>
            ) : (
              <Tombol ukuran="kecil" ragam="garis" onClick={() => navigasi('/masuk')}>
                Masuk
              </Tombol>
            )}

            <button
              type="button"
              onClick={() => setMenuTerbuka((buka) => !buka)}
              aria-expanded={menuTerbuka}
              aria-controls="menu-utama"
              aria-label={menuTerbuka ? 'Tutup menu navigasi' : 'Buka menu navigasi'}
              className="grid size-11 place-items-center rounded-lg text-slate-700 hover:bg-slate-100 lg:hidden"
            >
              {menuTerbuka ? <X aria-hidden className="size-5" /> : <Menu aria-hidden className="size-5" />}
            </button>
          </div>
        </div>

        <nav id="menu-utama" aria-label="Navigasi utama" className="border-t border-slate-100 bg-white">
          <ul
            className={`mx-auto max-w-6xl gap-1 px-2 lg:flex ${menuTerbuka ? 'block pb-2' : 'hidden lg:flex'}`}
          >
            {MENU.map((butir) => (
              <li key={butir.ke}>
                <NavLink
                  to={butir.ke}
                  onClick={() => setMenuTerbuka(false)}
                  className={({ isActive }) =>
                    `block min-h-11 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${
                      isActive ? 'bg-desa-50 text-desa-800' : 'text-slate-700 hover:bg-slate-100'
                    }`
                  }
                >
                  {butir.teks}
                </NavLink>
              </li>
            ))}
          </ul>
        </nav>
      </header>

      <main id="konten-utama" className="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        <Outlet />
      </main>

      <footer className="mt-auto border-t border-slate-200 bg-white">
        <div className="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <h2 className="text-sm font-semibold">{namaDesa}</h2>
            <p className="mt-2 text-sm text-slate-600">{desa?.alamat}</p>
            <p className="mt-1 text-sm text-slate-600">Telepon: {desa?.telepon ?? '-'}</p>
            <p className="text-sm text-slate-600">Surel: {desa?.email ?? '-'}</p>
          </div>
          <div>
            <h2 className="text-sm font-semibold">Layanan</h2>
            <ul className="mt-2 space-y-1.5 text-sm text-slate-600">
              <li><Link to="/layanan" className="hover:text-desa-700">Katalog Layanan Surat</Link></li>
              <li><Link to="/layanan/verifikasi" className="hover:text-desa-700">Verifikasi Keabsahan Surat</Link></li>
              <li><Link to="/pengaduan" className="hover:text-desa-700">Pengaduan Masyarakat</Link></li>
              <li><Link to="/pengaduan/lacak" className="hover:text-desa-700">Lacak Pengaduan</Link></li>
            </ul>
          </div>
          <div>
            <h2 className="text-sm font-semibold">Transparansi</h2>
            <ul className="mt-2 space-y-1.5 text-sm text-slate-600">
              <li><Link to="/transparansi/apbdes" className="hover:text-desa-700">APBDes</Link></li>
              <li><Link to="/transparansi/statistik" className="hover:text-desa-700">Statistik Desa</Link></li>
              <li><Link to="/transparansi/produk-hukum" className="hover:text-desa-700">Produk Hukum</Link></li>
              <li><Link to="/ppid" className="hover:text-desa-700">PPID</Link></li>
            </ul>
          </div>
          <div>
            <h2 className="text-sm font-semibold">Jam Pelayanan</h2>
            <p className="mt-2 text-sm text-slate-600">{desa?.jam_pelayanan ?? 'Senin–Jumat, 08.00–15.00 WIB'}</p>
            <p className="mt-3 text-sm text-slate-600">
              <Link to="/aksesibilitas" className="underline underline-offset-2 hover:text-desa-700">
                Pernyataan aksesibilitas
              </Link>
            </p>
            <p className="text-sm text-slate-600">
              <Link to="/kebijakan-privasi" className="underline underline-offset-2 hover:text-desa-700">
                Kebijakan privasi
              </Link>
            </p>
          </div>
        </div>
        <div className="border-t border-slate-100 py-4">
          <p className="mx-auto max-w-6xl px-4 text-xs text-slate-500">
            © {new Date().getFullYear()} Pemerintah {namaDesa}. Portal ini dikelola sesuai Undang-Undang Keterbukaan
            Informasi Publik dan Undang-Undang Pelindungan Data Pribadi.
          </p>
        </div>
      </footer>
    </div>
  )
}
