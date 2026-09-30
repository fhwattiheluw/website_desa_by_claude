import { useState } from 'react'
import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom'
import {
  BarChart3, Building2, Eye, FileText, Files, Gauge, Inbox, LogOut, Menu, MessageSquare, MessageSquareWarning,
  PenLine, Settings, ShieldCheck, UserX, Users, X,
} from 'lucide-react'
import { useAuth } from '@/lib/auth'
import { useProfilDesa } from '@/lib/kueri'
import { LonceNotifikasi } from '@/components/layout/LonceNotifikasi'

interface ButirMenu {
  ke: string
  teks: string
  ikon: typeof Gauge
  izin?: string[]
}

const MENU: ButirMenu[] = [
  { ke: '/admin', teks: 'Dasbor', ikon: Gauge, izin: ['dashboard.lihat'] },
  { ke: '/admin/permohonan', teks: 'Antrean Permohonan', ikon: Inbox, izin: ['permohonan.lihat'] },
  { ke: '/admin/pengaduan', teks: 'Pengaduan', ikon: MessageSquareWarning, izin: ['pengaduan.lihat'] },
  { ke: '/admin/konten', teks: 'Konten', ikon: FileText, izin: ['konten.kelola'] },
  { ke: '/admin/moderasi', teks: 'Moderasi', ikon: MessageSquare, izin: ['konten.kelola'] },
  { ke: '/admin/apbdes', teks: 'APBDes', ikon: BarChart3, izin: ['apbdes.kelola'] },
  { ke: '/admin/bumdes', teks: 'BUMDes', ikon: Building2, izin: ['bumdes.kelola'] },
  { ke: '/admin/pengguna', teks: 'Pengguna', ikon: Users, izin: ['pengguna.lihat'] },
  { ke: '/admin/tanda-tangan', teks: 'Tanda Tangan', ikon: PenLine, izin: ['permohonan.tanda_tangan'] },
  { ke: '/admin/laporan', teks: 'Laporan Layanan', ikon: Files, izin: ['laporan.lihat'] },
  { ke: '/admin/permintaan-data', teks: 'Hak Subjek Data', ikon: UserX, izin: ['data_pribadi.kelola'] },
  { ke: '/admin/analitik', teks: 'Statistik Kunjungan', ikon: Eye, izin: ['laporan.lihat'] },
  { ke: '/admin/audit-log', teks: 'Audit Log', ikon: ShieldCheck, izin: ['audit.lihat'] },
  { ke: '/admin/pengaturan', teks: 'Pengaturan', ikon: Settings, izin: ['pengaturan.kelola'] },
]

export function TataLetakPanel() {
  const { pengguna, keluar, punyaIzin } = useAuth()
  const { data: desa } = useProfilDesa()
  const [sidebarTerbuka, setSidebarTerbuka] = useState(false)
  const navigasi = useNavigate()

  const menu = MENU.filter((butir) => !butir.izin || punyaIzin(...butir.izin))

  const padaKeluar = async () => {
    await keluar()
    navigasi('/masuk')
  }

  return (
    <div className="flex min-h-screen bg-slate-100">
      <aside
        className={`fixed inset-y-0 left-0 z-40 w-64 shrink-0 border-r border-slate-200 bg-white transition-transform lg:static lg:translate-x-0 ${
          sidebarTerbuka ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <div className="flex items-center justify-between border-b border-slate-200 px-4 py-4">
          <Link to="/" className="flex items-center gap-2">
            <span className="grid size-9 place-items-center rounded-lg bg-desa-700 text-xs font-bold text-white">
              {desa?.nama_desa?.slice(0, 2).toUpperCase() ?? 'DS'}
            </span>
            <span className="text-sm font-semibold">Panel Petugas</span>
          </Link>
          <button
            type="button"
            onClick={() => setSidebarTerbuka(false)}
            aria-label="Tutup menu"
            className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
          >
            <X aria-hidden className="size-5" />
          </button>
        </div>

        <nav aria-label="Navigasi panel" className="p-2">
          <ul className="space-y-1">
            {menu.map((butir) => (
              <li key={butir.ke}>
                <NavLink
                  to={butir.ke}
                  end={butir.ke === '/admin'}
                  onClick={() => setSidebarTerbuka(false)}
                  className={({ isActive }) =>
                    `flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium ${
                      isActive ? 'bg-desa-50 text-desa-800' : 'text-slate-700 hover:bg-slate-100'
                    }`
                  }
                >
                  <butir.ikon aria-hidden className="size-4.5" />
                  {butir.teks}
                </NavLink>
              </li>
            ))}
          </ul>
        </nav>

        <div className="mt-auto border-t border-slate-200 p-3">
          <p className="px-2 text-sm font-medium text-slate-900">{pengguna?.nama}</p>
          <p className="px-2 text-xs text-slate-500">{pengguna?.peran_nama}</p>
          <button
            type="button"
            onClick={padaKeluar}
            className="mt-2 flex min-h-11 w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100"
          >
            <LogOut aria-hidden className="size-4" /> Keluar
          </button>
        </div>
      </aside>

      {sidebarTerbuka && (
        <div
          aria-hidden
          onClick={() => setSidebarTerbuka(false)}
          className="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"
        />
      )}

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3">
          <button
            type="button"
            onClick={() => setSidebarTerbuka(true)}
            aria-label="Buka menu panel"
            className="grid size-11 place-items-center rounded-lg text-slate-700 hover:bg-slate-100 lg:hidden"
          >
            <Menu aria-hidden className="size-5" />
          </button>
          <span className="font-semibold lg:sr-only">Panel Petugas</span>

          {/* REQ-F-NOT-005: lonceng pekerjaan baru, terlihat pada seluruh lebar layar. */}
          <div className="ml-auto">
            <LonceNotifikasi />
          </div>
        </header>

        <main className="min-w-0 flex-1 p-4 sm:p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
