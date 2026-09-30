import { Link, useLocation, useParams } from 'react-router-dom'
import { ChevronRight, Home } from 'lucide-react'
import { judulKan } from '@/lib/format'

/** Ruas jalur yang punya nama sendiri, bukan hasil pengubahan slug. */
const NAMA_RUAS: Record<string, string> = {
  profil: 'Profil Desa',
  berita: 'Berita',
  artikel: 'Artikel',
  pengumuman: 'Pengumuman',
  agenda: 'Agenda',
  galeri: 'Galeri',
  transparansi: 'Transparansi',
  apbdes: 'APBDes',
  statistik: 'Statistik Desa',
  'produk-hukum': 'Produk Hukum',
  ppid: 'PPID',
  permohonan: 'Permohonan',
  layanan: 'Layanan',
  verifikasi: 'Verifikasi Surat',
  pengaduan: 'Pengaduan',
  lacak: 'Lacak Pengaduan',
  potensi: 'Potensi Desa',
  umkm: 'Direktori UMKM',
  daftar: 'Pendaftaran',
  wisata: 'Destinasi Wisata',
  bumdes: 'BUMDes',
  pencarian: 'Hasil Pencarian',
  kontak: 'Kontak',
  'kebijakan-privasi': 'Kebijakan Privasi',
  'syarat-penggunaan': 'Syarat Penggunaan',
  aksesibilitas: 'Aksesibilitas',
  masuk: 'Masuk',
  akun: 'Akun Saya',
  'lupa-kata-sandi': 'Lupa Kata Sandi',
  'atur-ulang-kata-sandi': 'Atur Ulang Kata Sandi',
}

/** Ruas yang hanya menjadi pengelompok, tidak punya halaman sendiri. */
const TANPA_HALAMAN = new Set(['transparansi', 'potensi'])

function namaRuas(ruas: string) {
  return NAMA_RUAS[ruas] ?? judulKan(ruas.replace(/-/g, ' '))
}

/**
 * Remah roti pada setiap halaman selain beranda (REQ-UI-004).
 *
 * Disusun dari alamat halaman, sehingga tidak perlu didaftarkan ulang setiap
 * kali rute bertambah. Ruas terakhir tidak dijadikan tautan karena itulah
 * halaman yang sedang dibuka, dan ruas yang berasal dari parameter alamat —
 * misalnya slug berita — tidak ditautkan karena tidak ada halaman daftar di
 * belakangnya.
 */
export function RemahRoti() {
  const { pathname } = useLocation()
  const parameter = useParams()

  const ruas = pathname.split('/').filter(Boolean)

  if (ruas.length === 0) return null

  const nilaiParameter = new Set(Object.values(parameter).filter(Boolean))

  const butir = ruas.map((satu, indeks) => ({
    teks: namaRuas(satu),
    ke: '/' + ruas.slice(0, indeks + 1).join('/'),
    tertaut:
      indeks < ruas.length - 1 && !TANPA_HALAMAN.has(satu) && !nilaiParameter.has(satu),
  }))

  return (
    <nav aria-label="Remah roti" className="mb-5 text-sm">
      <ol className="flex flex-wrap items-center gap-1 text-slate-600">
        <li className="flex items-center gap-1">
          <Link to="/" className="inline-flex items-center gap-1 hover:text-desa-700 hover:underline">
            <Home aria-hidden className="size-4" />
            Beranda
          </Link>
        </li>
        {butir.map((satu) => (
          <li key={satu.ke} className="flex items-center gap-1">
            <ChevronRight aria-hidden className="size-4 text-slate-400" />
            {satu.tertaut ? (
              <Link to={satu.ke} className="hover:text-desa-700 hover:underline">
                {satu.teks}
              </Link>
            ) : (
              <span className={satu.ke === pathname ? 'font-medium text-slate-900' : undefined}>
                {satu.teks}
              </span>
            )}
          </li>
        ))}
      </ol>
    </nav>
  )
}
