import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, pesanGalat } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { rupiah, tanggal } from '@/lib/format'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { Berkas, Isian } from '@/components/ui/Isian'
import { Lencana } from '@/components/ui/Lencana'
import { Tombol } from '@/components/ui/Tombol'
import { Dialog } from '@/components/ui/Dialog'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import { GalatMuat, Rangka } from '@/components/ui/Status'

interface TahunAnggaran {
  id: number
  tahun: number
  dipublikasikan: boolean
  dipublikasikan_pada: string | null
  catatan: string | null
  item_count: number
}

interface HasilImpor {
  pesan: string
  berhasil: number
  gagal: { baris: number; alasan: string }[]
}

export function KelolaApbdes() {
  const klien = useQueryClient()
  const { punyaIzin } = useAuth()
  const [tahunBaru, setTahunBaru] = useState(String(new Date().getFullYear()))
  const [imporUntuk, setImporUntuk] = useState<TahunAnggaran | null>(null)
  const [berkas, setBerkas] = useState<File | null>(null)
  const [hasilImpor, setHasilImpor] = useState<HasilImpor | null>(null)
  const [pesan, setPesan] = useState('')
  const [sukses, setSukses] = useState('')

  const { data, isPending, error } = useQuery({
    queryKey: ['admin-apbdes'],
    queryFn: async () => (await api.get<{ data: TahunAnggaran[] }>('/admin/apbdes')).data.data,
  })

  const segarkan = () => {
    void klien.invalidateQueries({ queryKey: ['admin-apbdes'] })
    void klien.invalidateQueries({ queryKey: ['apbdes-tahun'] })
  }

  const buat = useMutation({
    mutationFn: async () => (await api.post('/admin/apbdes', { tahun: Number(tahunBaru) })).data,
    onSuccess: () => { setSukses('Tahun anggaran dibuat.'); setPesan(''); segarkan() },
    onError: (kesalahan) => setPesan(pesanGalat(kesalahan)),
  })

  const publikasi = useMutation({
    mutationFn: async ({ id, tampil }: { id: number; tampil: boolean }) =>
      (await api.post(`/admin/apbdes/${id}/publikasi`, { dipublikasikan: tampil })).data,
    onSuccess: (hasil: { pesan?: string }) => { setSukses(hasil.pesan ?? 'Tersimpan.'); setPesan(''); segarkan() },
    onError: (kesalahan) => setPesan(pesanGalat(kesalahan)),
  })

  const impor = useMutation({
    mutationFn: async () => {
      const muatan = new FormData()
      muatan.append('berkas', berkas as File)
      return (await api.post<HasilImpor>(`/admin/apbdes/${imporUntuk?.id}/impor`, muatan, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })).data
    },
    onSuccess: (hasil) => { setHasilImpor(hasil); setPesan(''); segarkan() },
    onError: (kesalahan) => setPesan(pesanGalat(kesalahan)),
  })

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl">Transparansi APBDes</h1>
        <p className="mt-1 text-slate-600">
          Kelola rincian anggaran per tahun. Data hanya tampil ke publik setelah dipublikasikan Sekretaris Desa.
        </p>
      </header>

      {sukses && <Pemberitahuan jenis="sukses">{sukses}</Pemberitahuan>}
      {pesan && <Pemberitahuan jenis="bahaya">{pesan}</Pemberitahuan>}

      <Kartu>
        <KepalaKartu judul="Tambah Tahun Anggaran" />
        <IsiKartu>
          <div className="flex flex-wrap items-end gap-3">
            <div className="w-40">
              <Isian label="Tahun" type="number" value={tahunBaru} onChange={(e) => setTahunBaru(e.target.value)} />
            </div>
            <Tombol memuat={buat.isPending} onClick={() => buat.mutate()}>
              Tambah
            </Tombol>
          </div>
        </IsiKartu>
      </Kartu>

      {error ? (
        <GalatMuat pesan={pesanGalat(error)} />
      ) : isPending ? (
        <Rangka baris={3} />
      ) : (
        <ul className="space-y-3">
          {data?.map((tahun) => (
            <li key={tahun.id}>
              <Kartu>
                <IsiKartu>
                  <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                      <p className="flex items-center gap-2 font-medium">
                        APBDes {tahun.tahun}
                        <Lencana nada={tahun.dipublikasikan ? 'sukses' : 'netral'}>
                          {tahun.dipublikasikan ? 'Terpublikasi' : 'Draf'}
                        </Lencana>
                      </p>
                      <p className="mt-0.5 text-sm text-slate-600">
                        {tahun.item_count} pos anggaran
                        {tahun.dipublikasikan_pada && ` · dipublikasikan ${tanggal(tahun.dipublikasikan_pada)}`}
                      </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                      <Tombol
                        ukuran="kecil"
                        ragam="garis"
                        onClick={() => { setImporUntuk(tahun); setHasilImpor(null); setBerkas(null) }}
                      >
                        Impor CSV
                      </Tombol>
                      {punyaIzin('apbdes.publikasi') && (
                        <Tombol
                          ukuran="kecil"
                          ragam={tahun.dipublikasikan ? 'garis' : 'utama'}
                          memuat={publikasi.isPending}
                          onClick={() => publikasi.mutate({ id: tahun.id, tampil: !tahun.dipublikasikan })}
                        >
                          {tahun.dipublikasikan ? 'Sembunyikan' : 'Publikasikan'}
                        </Tombol>
                      )}
                    </div>
                  </div>
                </IsiKartu>
              </Kartu>
            </li>
          ))}
        </ul>
      )}

      <Dialog
        terbuka={imporUntuk !== null}
        judul={`Impor Rincian APBDes ${imporUntuk?.tahun ?? ''}`}
        deskripsi="Berkas CSV wajib memuat kolom: jenis, bidang, kegiatan, pagu, realisasi."
        onTutup={() => { setImporUntuk(null); setHasilImpor(null) }}
        aksi={
          <>
            <Tombol ragam="garis" onClick={() => setImporUntuk(null)}>
              Tutup
            </Tombol>
            <Tombol disabled={!berkas} memuat={impor.isPending} onClick={() => impor.mutate()}>
              Impor Sekarang
            </Tombol>
          </>
        }
      >
        <div className="space-y-4">
          <Berkas
            label="Berkas CSV"
            accept=".csv,text/csv"
            onChange={(e) => setBerkas(e.target.files?.[0] ?? null)}
            petunjuk="Nilai jenis yang diterima: pendapatan, belanja, pembiayaan."
          />

          {hasilImpor && (
            <Pemberitahuan jenis={hasilImpor.gagal.length > 0 ? 'peringatan' : 'sukses'} judul="Hasil impor">
              <p>{hasilImpor.pesan}</p>
              {hasilImpor.gagal.length > 0 && (
                <ul className="mt-2 space-y-1 text-sm">
                  {hasilImpor.gagal.slice(0, 10).map((gagal) => (
                    <li key={gagal.baris}>
                      Baris {gagal.baris}: {gagal.alasan}
                    </li>
                  ))}
                </ul>
              )}
            </Pemberitahuan>
          )}

          <p className="text-xs text-slate-500">
            Contoh baris: <code>belanja,Pelaksanaan Pembangunan Desa,Rabat beton jalan,{rupiah(780000000).replace(/\D/g, '')},702000000</code>
          </p>
        </div>
      </Dialog>
    </div>
  )
}
