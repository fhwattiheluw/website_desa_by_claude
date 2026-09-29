import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { api, galatKolom, pesanGalat } from '@/lib/api'
import { useMeta } from '@/lib/meta'
import { Kartu, IsiKartu } from '@/components/ui/Kartu'
import { Isian } from '@/components/ui/Isian'
import { Tombol } from '@/components/ui/Tombol'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'

/** REQ-F-USR-006: permintaan tautan pemulihan kata sandi. */
export function LupaKataSandi() {
  const [email, setEmail] = useState('')
  const [pesan, setPesan] = useState('')
  const [galat, setGalat] = useState('')
  const [memuat, setMemuat] = useState(false)

  useMeta({
    judul: 'Lupa Kata Sandi',
    deskripsi: 'Ajukan pemulihan kata sandi akun layanan desa melalui surel terdaftar.',
  })

  const kirim = async (peristiwa: FormEvent) => {
    peristiwa.preventDefault()
    setMemuat(true)
    setGalat('')

    try {
      const { data } = await api.post<{ pesan: string }>('/auth/lupa-kata-sandi', { email })
      setPesan(data.pesan)
    } catch (kesalahan) {
      setGalat(pesanGalat(kesalahan))
    } finally {
      setMemuat(false)
    }
  }

  return (
    <div className="mx-auto max-w-md py-6">
      <header className="mb-6 text-center">
        <h1 className="text-2xl">Lupa Kata Sandi</h1>
        <p className="mt-1 text-slate-600">
          Masukkan surel yang Anda daftarkan. Kami akan mengirim tautan untuk membuat kata sandi baru.
        </p>
      </header>

      <Kartu>
        <IsiKartu>
          {pesan ? (
            <Pemberitahuan jenis="sukses" judul="Permintaan diterima">
              {pesan}
            </Pemberitahuan>
          ) : (
            <>
              {galat && (
                <div className="mb-4">
                  <Pemberitahuan jenis="bahaya">{galat}</Pemberitahuan>
                </div>
              )}

              <form onSubmit={kirim} className="space-y-4">
                <Isian
                  label="Surel terdaftar"
                  type="email"
                  autoComplete="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                />
                <Tombol type="submit" ukuran="besar" memuat={memuat} className="w-full">
                  Kirim Tautan Pemulihan
                </Tombol>
              </form>
            </>
          )}

          <p className="mt-5 text-center text-sm text-slate-600">
            <Link to="/masuk" className="font-medium text-desa-700 hover:underline">
              Kembali ke halaman masuk
            </Link>
          </p>
        </IsiKartu>
      </Kartu>

      <p className="mt-4 text-center text-xs text-slate-500">
        Bila surel tidak kunjung diterima, periksa folder spam atau hubungi kantor desa pada jam pelayanan.
      </p>
    </div>
  )
}

/** REQ-F-USR-006: penetapan kata sandi baru melalui tautan sekali pakai. */
export function AturUlangKataSandi() {
  const [parameter] = useSearchParams()
  const navigasi = useNavigate()
  const [galat, setGalat] = useState<Record<string, string>>({})
  const [pesan, setPesan] = useState('')
  const [berhasil, setBerhasil] = useState(false)
  const [memuat, setMemuat] = useState(false)

  const token = parameter.get('token') ?? ''
  const email = parameter.get('email') ?? ''

  useMeta({ judul: 'Atur Ulang Kata Sandi' })

  const kirim = async (peristiwa: FormEvent<HTMLFormElement>) => {
    peristiwa.preventDefault()
    setMemuat(true)
    setGalat({})
    setPesan('')

    const formulir = new FormData(peristiwa.currentTarget)

    try {
      await api.post('/auth/atur-ulang-kata-sandi', {
        token,
        email,
        password: formulir.get('password'),
        password_confirmation: formulir.get('password_confirmation'),
      })
      setBerhasil(true)
    } catch (kesalahan) {
      setGalat(galatKolom(kesalahan))
      setPesan(pesanGalat(kesalahan))
    } finally {
      setMemuat(false)
    }
  }

  if (!token || !email) {
    return (
      <div className="mx-auto max-w-md space-y-4 py-6">
        <Pemberitahuan jenis="peringatan" judul="Tautan tidak lengkap">
          Tautan pemulihan tidak memuat data yang diperlukan. Silakan ajukan permintaan pemulihan baru.
        </Pemberitahuan>
        <Tombol className="w-full" onClick={() => navigasi('/lupa-kata-sandi')}>
          Ajukan Pemulihan Baru
        </Tombol>
      </div>
    )
  }

  if (berhasil) {
    return (
      <div className="mx-auto max-w-md space-y-4 py-6">
        <Pemberitahuan jenis="sukses" judul="Kata sandi berhasil diubah">
          Seluruh sesi lama telah dikeluarkan demi keamanan. Silakan masuk dengan kata sandi baru Anda.
        </Pemberitahuan>
        <Tombol className="w-full" onClick={() => navigasi('/masuk')}>
          Masuk Sekarang
        </Tombol>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-md py-6">
      <header className="mb-6 text-center">
        <h1 className="text-2xl">Buat Kata Sandi Baru</h1>
        <p className="mt-1 text-slate-600">Untuk akun {email}</p>
      </header>

      <Kartu>
        <IsiKartu>
          {pesan && (
            <div className="mb-4">
              <Pemberitahuan jenis="bahaya">{pesan}</Pemberitahuan>
            </div>
          )}

          <form onSubmit={kirim} className="space-y-4">
            <Isian
              label="Kata sandi baru"
              name="password"
              type="password"
              required
              autoComplete="new-password"
              petunjuk="Minimal 8 karakter, memuat huruf dan angka."
              galat={galat['password']}
            />
            <Isian
              label="Ulangi kata sandi baru"
              name="password_confirmation"
              type="password"
              required
              autoComplete="new-password"
              galat={galat['token']}
            />
            <Tombol type="submit" ukuran="besar" memuat={memuat} className="w-full">
              Simpan Kata Sandi Baru
            </Tombol>
          </form>
        </IsiKartu>
      </Kartu>
    </div>
  )
}
