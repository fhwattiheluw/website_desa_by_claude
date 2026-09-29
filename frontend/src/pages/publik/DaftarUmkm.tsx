import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { api, galatKolom, pesanGalat } from '@/lib/api'
import { useMeta } from '@/lib/meta'
import { useAuth } from '@/lib/auth'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { AreaTeks, Isian, KotakCentang, Pilihan } from '@/components/ui/Isian'
import { Tombol } from '@/components/ui/Tombol'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'

const KATEGORI = [
  'Makanan Olahan', 'Minuman', 'Kerajinan', 'Pertanian', 'Perikanan',
  'Peternakan', 'Jasa', 'Perdagangan', 'Lainnya',
]

/** REQ-F-POT-002: pendaftaran mandiri pelaku usaha, tayang setelah diverifikasi operator. */
export function DaftarUmkm() {
  const { pengguna } = useAuth()
  const [galat, setGalat] = useState<Record<string, string>>({})
  const [pesan, setPesan] = useState('')
  const [berhasil, setBerhasil] = useState(false)
  const [mengirim, setMengirim] = useState(false)
  const [setuju, setSetuju] = useState(false)

  useMeta({
    judul: 'Daftarkan Usaha Anda',
    deskripsi: 'Formulir pendaftaran mandiri bagi pelaku usaha desa untuk tampil pada direktori UMKM.',
  })

  const kirim = async (peristiwa: FormEvent<HTMLFormElement>) => {
    peristiwa.preventDefault()
    setMengirim(true)
    setGalat({})
    setPesan('')

    const formulir = Object.fromEntries(new FormData(peristiwa.currentTarget).entries())

    try {
      await api.post('/umkm/daftar', { ...formulir, consent_kontak: setuju })
      setBerhasil(true)
    } catch (kesalahan) {
      setGalat(galatKolom(kesalahan))
      setPesan(pesanGalat(kesalahan))
    } finally {
      setMengirim(false)
    }
  }

  if (berhasil) {
    return (
      <div className="mx-auto max-w-2xl space-y-4">
        <Pemberitahuan jenis="sukses" judul="Pendaftaran usaha diterima">
          Petugas desa akan meninjau data usaha Anda sebelum ditayangkan pada direktori UMKM. Proses peninjauan
          umumnya selesai dalam 2 hari kerja.
        </Pemberitahuan>
        <Link
          to="/potensi/umkm"
          className="inline-flex min-h-11 items-center rounded-lg bg-desa-700 px-4 text-sm font-medium text-white hover:bg-desa-800"
        >
          Lihat Direktori UMKM
        </Link>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <header>
        <h1 className="text-2xl">Daftarkan Usaha Anda</h1>
        <p className="mt-1 text-slate-600">
          Pelaku usaha di desa dapat mendaftarkan usahanya secara mandiri. Data yang masuk ditinjau petugas desa
          sebelum ditayangkan.
        </p>
      </header>

      <Kartu>
        <KepalaKartu judul="Data Usaha" deskripsi="Kolom bertanda bintang wajib diisi." />
        <IsiKartu>
          {pesan && (
            <div className="mb-4">
              <Pemberitahuan jenis="bahaya">{pesan}</Pemberitahuan>
            </div>
          )}

          <form onSubmit={kirim} className="space-y-4">
            <Isian label="Nama usaha" name="nama_usaha" required galat={galat['nama_usaha']} />
            <Isian
              label="Nama pemilik"
              name="pemilik"
              required
              defaultValue={pengguna?.nama ?? ''}
              galat={galat['pemilik']}
            />
            <Pilihan
              label="Kategori usaha"
              name="kategori"
              required
              kosong="Pilih kategori"
              pilihan={KATEGORI.map((k) => ({ nilai: k, teks: k }))}
              galat={galat['kategori']}
            />
            <AreaTeks
              label="Deskripsi usaha"
              name="deskripsi"
              rows={4}
              petunjuk="Ceritakan produk atau jasa yang Anda tawarkan."
              galat={galat['deskripsi']}
            />
            <Isian label="Alamat usaha" name="alamat" galat={galat['alamat']} />
            <Isian
              label="Nomor WhatsApp usaha"
              name="telepon"
              inputMode="tel"
              placeholder="08xxxxxxxxxx"
              defaultValue={pengguna?.telepon ?? ''}
              galat={galat['telepon']}
            />

            <KotakCentang
              checked={setuju}
              onChange={(e) => setSetuju(e.target.checked)}
              label="Saya setuju nomor kontak usaha ditampilkan pada direktori publik agar calon pembeli dapat menghubungi saya."
            />

            <p className="text-sm text-slate-500">
              Bila persetujuan di atas tidak dicentang, usaha Anda tetap dapat tayang namun nomor kontak
              disembunyikan.
            </p>

            <Tombol type="submit" ukuran="besar" memuat={mengirim}>
              Kirim Pendaftaran
            </Tombol>
          </form>
        </IsiKartu>
      </Kartu>
    </div>
  )
}
