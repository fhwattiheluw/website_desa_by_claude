import { useEffect, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { BadgeCheck, ShieldCheck, Trash2 } from 'lucide-react'
import { api, pesanGalat } from '@/lib/api'
import { tanggal } from '@/lib/format'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { Berkas } from '@/components/ui/Isian'
import { Tombol } from '@/components/ui/Tombol'
import { Lencana } from '@/components/ui/Lencana'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import { GalatMuat, Rangka } from '@/components/ui/Status'

interface StatusTandaTangan {
  metode_aktif: 'internal' | 'tte'
  penyedia: string
  perlu_spesimen: boolean
  spesimen_terpasang: boolean
  diperbarui_pada: string | null
  catatan: string
}

/** REQ-F-SRT-017: pengelolaan spesimen tanda tangan oleh pejabat penanda tangan. */
export function TandaTangan() {
  const klien = useQueryClient()
  const [berkas, setBerkas] = useState<File | null>(null)
  const [pesan, setPesan] = useState('')
  const [sukses, setSukses] = useState('')
  const [alamatPratinjau, setAlamatPratinjau] = useState<string | null>(null)

  const { data, isPending, error } = useQuery({
    queryKey: ['tanda-tangan'],
    queryFn: async () => (await api.get<StatusTandaTangan>('/admin/tanda-tangan')).data,
  })

  const segarkan = () => {
    void klien.invalidateQueries({ queryKey: ['tanda-tangan'] })
  }

  /*
   * Spesimen hanya dapat diambil dengan token, sehingga tidak bisa dipasang
   * langsung sebagai sumber gambar. Berkas diambil melalui klien API lalu
   * dijadikan alamat objek sementara.
   */
  useEffect(() => {
    if (!data?.spesimen_terpasang) return

    let dibatalkan = false
    let alamat: string | null = null

    api
      .get<Blob>('/admin/tanda-tangan/pratinjau', { responseType: 'blob' })
      .then(({ data: gambar }) => {
        if (dibatalkan) return

        alamat = URL.createObjectURL(gambar)
        setAlamatPratinjau(alamat)
      })
      .catch(() => setAlamatPratinjau(null))

    return () => {
      dibatalkan = true
      if (alamat) URL.revokeObjectURL(alamat)
      setAlamatPratinjau(null)
    }
  }, [data?.spesimen_terpasang, data?.diperbarui_pada])

  const unggah = useMutation({
    mutationFn: async () => {
      const muatan = new FormData()
      muatan.append('berkas', berkas as File)

      return (await api.post('/admin/tanda-tangan', muatan, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })).data
    },
    onSuccess: (hasil: { pesan?: string }) => {
      setSukses(hasil.pesan ?? 'Spesimen tersimpan.')
      setPesan('')
      setBerkas(null)
      segarkan()
    },
    onError: (kesalahan) => setPesan(pesanGalat(kesalahan)),
  })

  const hapus = useMutation({
    mutationFn: async () => (await api.delete('/admin/tanda-tangan')).data,
    onSuccess: (hasil: { pesan?: string }) => {
      setSukses(hasil.pesan ?? 'Spesimen dihapus.')
      setPesan('')
      segarkan()
    },
    onError: (kesalahan) => setPesan(pesanGalat(kesalahan)),
  })

  const kirim = (peristiwa: FormEvent) => {
    peristiwa.preventDefault()
    if (berkas) unggah.mutate()
  }

  if (error) return <GalatMuat pesan={pesanGalat(error)} />
  if (isPending) return <Rangka baris={3} />

  return (
    <div className="max-w-2xl space-y-6">
      <header>
        <h1 className="text-2xl">Tanda Tangan Elektronik</h1>
        <p className="mt-1 text-slate-600">
          Pengaturan tanda tangan yang dipakai pada surat yang Anda terbitkan.
        </p>
      </header>

      {sukses && <Pemberitahuan jenis="sukses">{sukses}</Pemberitahuan>}
      {pesan && <Pemberitahuan jenis="bahaya">{pesan}</Pemberitahuan>}

      <Kartu>
        <KepalaKartu
          judul="Metode yang aktif"
          aksi={
            <Lencana nada={data.metode_aktif === 'tte' ? 'sukses' : 'peringatan'}>
              {data.metode_aktif === 'tte' ? 'Tersertifikasi' : 'Dalam sistem'}
            </Lencana>
          }
        />
        <IsiKartu>
          <p className="flex items-start gap-2 text-sm text-slate-700">
            {data.metode_aktif === 'tte' ? (
              <BadgeCheck aria-hidden className="mt-0.5 size-5 shrink-0 text-desa-700" />
            ) : (
              <ShieldCheck aria-hidden className="mt-0.5 size-5 shrink-0 text-amber-600" />
            )}
            <span>
              <span className="font-medium">{data.penyedia}</span>
              <br />
              {data.catatan}
            </span>
          </p>
        </IsiKartu>
      </Kartu>

      {data.perlu_spesimen && (
        <Kartu>
          <KepalaKartu
            judul="Spesimen Tanda Tangan"
            deskripsi="Gambar tanda tangan Anda yang akan dibubuhkan pada dokumen surat."
          />
          <IsiKartu className="space-y-4">
            {data.spesimen_terpasang ? (
              <div className="space-y-3">
                <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                  {alamatPratinjau ? (
                    <img src={alamatPratinjau} alt="Spesimen tanda tangan Anda" className="max-h-24" />
                  ) : (
                    <p className="text-sm text-slate-500">Memuat pratinjau spesimen…</p>
                  )}
                </div>
                <p className="text-sm text-slate-600">
                  Terakhir diperbarui {tanggal(data.diperbarui_pada, true)}.
                </p>
                <Tombol ragam="bahaya" ukuran="kecil" memuat={hapus.isPending} onClick={() => hapus.mutate()}>
                  <Trash2 aria-hidden className="size-4" /> Hapus Spesimen
                </Tombol>
              </div>
            ) : (
              <Pemberitahuan jenis="peringatan" judul="Spesimen belum dipasang">
                Surat yang Anda tandatangani akan terbit tanpa gambar tanda tangan. Unggah spesimen agar dokumen
                tampil sebagaimana mestinya.
              </Pemberitahuan>
            )}

            <form onSubmit={kirim} className="space-y-4 border-t border-slate-100 pt-4">
              <Berkas
                label={data.spesimen_terpasang ? 'Ganti spesimen' : 'Unggah spesimen'}
                accept="image/png,image/jpeg"
                onChange={(e) => setBerkas(e.target.files?.[0] ?? null)}
                petunjuk="PNG berlatar transparan memberi hasil terbaik. Ukuran maksimal 1 MB."
              />
              <Tombol type="submit" disabled={!berkas} memuat={unggah.isPending}>
                Simpan Spesimen
              </Tombol>
            </form>

            <p className="text-xs text-slate-500">
              Berkas spesimen disimpan pada penyimpanan privat, tidak memiliki alamat publik, dan hanya dapat diakses
              oleh Anda sendiri. Setiap penyimpanan dan penghapusan tercatat pada audit log.
            </p>
          </IsiKartu>
        </Kartu>
      )}
    </div>
  )
}
