import { useState, type FormEvent } from 'react'
import { api, galatKolom, pesanGalat } from '@/lib/api'
import { tanggal } from '@/lib/format'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { AreaTeks, Isian } from '@/components/ui/Isian'
import { Tombol } from '@/components/ui/Tombol'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import { useAuth } from '@/lib/auth'

/** REQ-F-PID-003: permohonan informasi publik secara daring. */
export function PermohonanInformasi() {
  const { pengguna } = useAuth()
  const [hasil, setHasil] = useState<{ pesan: string; nomor_tiket: string; tenggat_jawaban: string } | null>(null)
  const [galat, setGalat] = useState<Record<string, string>>({})
  const [pesan, setPesan] = useState('')
  const [mengirim, setMengirim] = useState(false)

  const kirim = async (peristiwa: FormEvent<HTMLFormElement>) => {
    peristiwa.preventDefault()
    setMengirim(true)
    setGalat({})
    setPesan('')

    try {
      const muatan = Object.fromEntries(new FormData(peristiwa.currentTarget).entries())
      const { data } = await api.post('/permohonan-informasi', muatan)
      setHasil(data)
    } catch (kesalahan) {
      setGalat(galatKolom(kesalahan))
      setPesan(pesanGalat(kesalahan))
    } finally {
      setMengirim(false)
    }
  }

  if (hasil) {
    return (
      <div className="mx-auto max-w-2xl space-y-4">
        <Pemberitahuan jenis="sukses" judul="Permohonan informasi diterima">
          {hasil.pesan}
        </Pemberitahuan>
        <Kartu>
          <IsiKartu>
            <p className="text-sm text-slate-600">Nomor tiket</p>
            <p className="text-lg font-semibold">{hasil.nomor_tiket}</p>
            <p className="mt-3 text-sm text-slate-600">Tenggat jawaban</p>
            <p className="font-medium">{tanggal(hasil.tenggat_jawaban)}</p>
          </IsiKartu>
        </Kartu>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <header>
        <h1 className="text-2xl">Permohonan Informasi Publik</h1>
        <p className="mt-1 text-slate-600">
          Ajukan permohonan informasi kepada PPID Desa. Permohonan dijawab paling lambat 10 hari kerja.
        </p>
      </header>

      <Kartu>
        <KepalaKartu judul="Formulir Permohonan" />
        <IsiKartu>
          {pesan && (
            <div className="mb-4">
              <Pemberitahuan jenis="bahaya">{pesan}</Pemberitahuan>
            </div>
          )}

          <form onSubmit={kirim} className="space-y-4">
            <Isian label="Nama pemohon" name="nama" required defaultValue={pengguna?.nama ?? ''} galat={galat['nama']} />
            <Isian
              label="Surel atau nomor telepon"
              name="kontak"
              required
              defaultValue={pengguna?.email ?? ''}
              galat={galat['kontak']}
            />
            <AreaTeks
              label="Informasi yang diminta"
              name="informasi_diminta"
              required
              rows={5}
              petunjuk="Jelaskan informasi yang Anda butuhkan sejelas mungkin."
              galat={galat['informasi_diminta']}
            />
            <Isian label="Tujuan penggunaan informasi" name="tujuan_penggunaan" galat={galat['tujuan_penggunaan']} />

            <Tombol type="submit" ukuran="besar" memuat={mengirim}>
              Kirim Permohonan
            </Tombol>
          </form>
        </IsiKartu>
      </Kartu>
    </div>
  )
}
