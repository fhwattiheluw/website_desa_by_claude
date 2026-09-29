import { useState, type FormEvent } from 'react'
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { useAuth } from '@/lib/auth'
import { pesanGalat } from '@/lib/api'
import { useMeta } from '@/lib/meta'
import { Kartu, IsiKartu } from '@/components/ui/Kartu'
import { Isian } from '@/components/ui/Isian'
import { Tombol } from '@/components/ui/Tombol'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'

export function Masuk() {
  const { masuk } = useAuth()
  const navigasi = useNavigate()
  const lokasi = useLocation()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [galat, setGalat] = useState('')
  const [memuat, setMemuat] = useState(false)

  const [parameter] = useSearchParams()
  const statusVerifikasi = parameter.get('verifikasi')

  useMeta({
    judul: 'Masuk ke Akun',
    deskripsi: 'Masuk ke akun warga atau akun petugas untuk mengakses layanan administrasi desa.',
  })

  const tujuan = (lokasi.state as { dari?: string } | null)?.dari

  const kirim = async (peristiwa: FormEvent) => {
    peristiwa.preventDefault()
    setMemuat(true)
    setGalat('')

    try {
      const pengguna = await masuk(email, password)

      // Tujuan yang tersimpan hanya dipakai bila sesuai dengan peran yang masuk,
      // sehingga sisa pengalihan sebelumnya tidak menyesatkan pengguna.
      const keDalamPanel = tujuan?.startsWith('/admin') ?? false
      const tujuanAkhir = pengguna.petugas
        ? (keDalamPanel ? (tujuan as string) : '/admin')
        : (tujuan && !keDalamPanel ? tujuan : '/akun')

      navigasi(tujuanAkhir, { replace: true })
    } catch (kesalahan) {
      setGalat(pesanGalat(kesalahan))
    } finally {
      setMemuat(false)
    }
  }

  return (
    <div className="mx-auto max-w-md py-6">
      <header className="mb-6 text-center">
        <h1 className="text-2xl">Masuk ke Akun</h1>
        <p className="mt-1 text-slate-600">Gunakan akun warga atau akun petugas desa Anda.</p>
      </header>

      {statusVerifikasi === 'berhasil' && (
        <div className="mb-4">
          <Pemberitahuan jenis="sukses" judul="Surel terverifikasi">
            Terima kasih, alamat surel Anda telah terbukti. Silakan masuk. Kewenangan mengajukan layanan tetap
            menunggu verifikasi NIK oleh petugas desa.
          </Pemberitahuan>
        </div>
      )}

      {statusVerifikasi === 'gagal' && (
        <div className="mb-4">
          <Pemberitahuan jenis="peringatan" judul="Verifikasi gagal">
            Tautan verifikasi tidak berlaku atau sudah kedaluwarsa. Anda dapat meminta tautan baru dari halaman ini.
          </Pemberitahuan>
        </div>
      )}

      <Kartu>
        <IsiKartu>
          {galat && (
            <div className="mb-4">
              <Pemberitahuan jenis="bahaya">{galat}</Pemberitahuan>
            </div>
          )}

          <form onSubmit={kirim} className="space-y-4">
            <Isian
              label="Surel"
              type="email"
              autoComplete="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
            <Isian
              label="Kata sandi"
              type="password"
              autoComplete="current-password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
            <Tombol type="submit" ukuran="besar" memuat={memuat} className="w-full">
              Masuk
            </Tombol>
          </form>

          <p className="mt-4 text-center text-sm">
            <Link to="/lupa-kata-sandi" className="font-medium text-desa-700 hover:underline">
              Lupa kata sandi?
            </Link>
          </p>

          <p className="mt-3 text-center text-sm text-slate-600">
            Belum punya akun?{' '}
            <Link to="/daftar" className="font-medium text-desa-700 hover:underline">
              Daftar sebagai warga
            </Link>
          </p>
        </IsiKartu>
      </Kartu>

      <p className="mt-4 text-center text-xs text-slate-500">
        Akun terkunci sementara selama 15 menit setelah 5 kali percobaan masuk yang gagal.
      </p>
    </div>
  )
}
