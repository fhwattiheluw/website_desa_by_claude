import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api, galatKolom, pesanGalat } from '@/lib/api'
import { Kartu, IsiKartu } from '@/components/ui/Kartu'
import { Isian, KotakCentang } from '@/components/ui/Isian'
import { Tombol } from '@/components/ui/Tombol'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'

/** REQ-F-USR-001, 004, 014: registrasi warga dengan persetujuan data pribadi. */
export function Daftar() {
  const navigasi = useNavigate()
  const [galat, setGalat] = useState<Record<string, string>>({})
  const [pesan, setPesan] = useState('')
  const [memuat, setMemuat] = useState(false)
  const [persetujuan, setPersetujuan] = useState(false)
  const [berhasil, setBerhasil] = useState(false)

  const kirim = async (peristiwa: FormEvent<HTMLFormElement>) => {
    peristiwa.preventDefault()
    setMemuat(true)
    setGalat({})
    setPesan('')

    const formulir = Object.fromEntries(new FormData(peristiwa.currentTarget).entries())

    try {
      await api.post('/auth/daftar', { ...formulir, persetujuan })
      setBerhasil(true)
    } catch (kesalahan) {
      setGalat(galatKolom(kesalahan))
      setPesan(pesanGalat(kesalahan))
    } finally {
      setMemuat(false)
    }
  }

  if (berhasil) {
    return (
      <div className="mx-auto max-w-md space-y-4 py-6">
        <Pemberitahuan jenis="sukses" judul="Pendaftaran berhasil">
          Akun Anda telah dibuat dan sedang menunggu verifikasi NIK oleh petugas desa. Anda akan dapat mengajukan
          layanan surat setelah akun diaktifkan. Proses verifikasi umumnya selesai dalam 1 hari kerja.
        </Pemberitahuan>
        <Tombol onClick={() => navigasi('/masuk')} className="w-full">
          Lanjut ke Halaman Masuk
        </Tombol>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-lg py-6">
      <header className="mb-6 text-center">
        <h1 className="text-2xl">Daftar Akun Warga</h1>
        <p className="mt-1 text-slate-600">
          Akun diperlukan untuk mengajukan layanan administrasi secara daring.
        </p>
      </header>

      <Kartu>
        <IsiKartu>
          {pesan && (
            <div className="mb-4">
              <Pemberitahuan jenis="bahaya">{pesan}</Pemberitahuan>
            </div>
          )}

          <form onSubmit={kirim} className="space-y-4">
            <Isian label="Nama lengkap" name="name" required autoComplete="name" galat={galat['name']} />
            <Isian
              label="NIK"
              name="nik"
              required
              inputMode="numeric"
              maxLength={16}
              petunjuk="16 digit sesuai Kartu Tanda Penduduk. NIK disimpan terenkripsi."
              galat={galat['nik']}
            />
            <Isian label="Surel" name="email" type="email" required autoComplete="email" galat={galat['email']} />
            <Isian
              label="Nomor WhatsApp"
              name="telepon"
              required
              inputMode="tel"
              placeholder="08xxxxxxxxxx"
              petunjuk="Dipakai untuk pemberitahuan status permohonan."
              galat={galat['telepon']}
            />
            <Isian label="Alamat (Dusun/RT/RW)" name="alamat" galat={galat['alamat']} />
            <Isian
              label="Kata sandi"
              name="password"
              type="password"
              required
              autoComplete="new-password"
              petunjuk="Minimal 8 karakter, memuat huruf dan angka."
              galat={galat['password']}
            />
            <Isian
              label="Ulangi kata sandi"
              name="password_confirmation"
              type="password"
              required
              autoComplete="new-password"
            />

            <KotakCentang
              checked={persetujuan}
              onChange={(e) => setPersetujuan(e.target.checked)}
              galat={galat['persetujuan']}
              label={
                <>
                  Saya menyetujui pemrosesan data pribadi saya untuk keperluan layanan administrasi desa sebagaimana
                  dijelaskan pada{' '}
                  <Link to="/kebijakan-privasi" className="font-medium text-desa-700 underline underline-offset-2">
                    Kebijakan Privasi
                  </Link>
                  .
                </>
              }
            />

            <Tombol type="submit" ukuran="besar" memuat={memuat} className="w-full">
              Daftar Sekarang
            </Tombol>
          </form>

          <p className="mt-5 text-center text-sm text-slate-600">
            Sudah punya akun?{' '}
            <Link to="/masuk" className="font-medium text-desa-700 hover:underline">
              Masuk di sini
            </Link>
          </p>
        </IsiKartu>
      </Kartu>
    </div>
  )
}
