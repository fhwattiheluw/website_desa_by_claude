import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { FileDown } from 'lucide-react'
import { api, pesanGalat, unduhBerkas } from '@/lib/api'
import { angka, judulKan } from '@/lib/format'
import { Kartu, KartuStatistik, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { Isian } from '@/components/ui/Isian'
import { Tombol } from '@/components/ui/Tombol'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import { GalatMuat, Rangka } from '@/components/ui/Status'

interface DataLaporan {
  periode: { dari: string; sampai: string }
  total: number
  per_status: Record<string, number>
  per_layanan: Record<string, number>
  per_kanal: Record<string, number>
  rata_hari_kerja: number
  kepatuhan_sla_persen: number
  rata_kepuasan: number
}

/** REQ-F-SRT-025: rekapitulasi kinerja layanan per periode. */
export function Laporan() {
  const [dari, setDari] = useState(() => {
    const sekarang = new Date()
    return new Date(sekarang.getFullYear(), sekarang.getMonth(), 1).toISOString().slice(0, 10)
  })
  const [sampai, setSampai] = useState(() => new Date().toISOString().slice(0, 10))
  const [mengunduh, setMengunduh] = useState(false)
  const [galatUnduh, setGalatUnduh] = useState('')

  const unduh = async () => {
    setMengunduh(true)
    setGalatUnduh('')

    try {
      await unduhBerkas(
        `/admin/permohonan/laporan/csv?dari=${dari}&sampai=${sampai}`,
        `laporan-layanan-${dari}-${sampai}.csv`,
      )
    } catch (kesalahan) {
      setGalatUnduh(pesanGalat(kesalahan))
    } finally {
      setMengunduh(false)
    }
  }

  const { data, isPending, error } = useQuery({
    queryKey: ['laporan-layanan', dari, sampai],
    queryFn: async () => (await api.get<DataLaporan>('/admin/permohonan/laporan', { params: { dari, sampai } })).data,
  })

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl">Laporan Kinerja Layanan</h1>
        <p className="mt-1 text-slate-600">Rekapitulasi permohonan, waktu proses, dan kepatuhan SLA.</p>
      </header>

      <div className="flex flex-wrap items-end gap-4">
        <div className="grid max-w-md flex-1 gap-4 sm:grid-cols-2">
          <Isian label="Dari tanggal" type="date" value={dari} onChange={(e) => setDari(e.target.value)} />
          <Isian label="Sampai tanggal" type="date" value={sampai} onChange={(e) => setSampai(e.target.value)} />
        </div>

        {/* REQ-F-ADM-009: rekapitulasi dapat diunduh untuk keperluan pelaporan. */}
        <Tombol ragam="garis" memuat={mengunduh} onClick={unduh}>
          <FileDown aria-hidden className="size-4" /> Unduh CSV
        </Tombol>
      </div>

      {galatUnduh && <Pemberitahuan jenis="bahaya">{galatUnduh}</Pemberitahuan>}

      {error ? (
        <GalatMuat pesan={pesanGalat(error)} />
      ) : isPending ? (
        <Rangka baris={4} />
      ) : (
        <>
          <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <KartuStatistik label="Total permohonan" nilai={angka(data.total)} keterangan="Pada periode terpilih" />
            <KartuStatistik
              label="Rata-rata waktu proses"
              nilai={`${data.rata_hari_kerja} hari`}
              keterangan="Dihitung dalam hari kerja"
              nada={data.rata_hari_kerja <= 1 ? 'positif' : 'netral'}
            />
            <KartuStatistik
              label="Kepatuhan SLA"
              nilai={`${data.kepatuhan_sla_persen}%`}
              keterangan="Target minimal 95%"
              nada={data.kepatuhan_sla_persen >= 95 ? 'positif' : 'peringatan'}
            />
            <KartuStatistik
              label="Kepuasan warga"
              nilai={data.rata_kepuasan ? `${data.rata_kepuasan} / 5` : '-'}
              keterangan="Dari survei pascalayanan"
            />
          </section>

          <div className="grid gap-6 lg:grid-cols-3">
            {[
              { judul: 'Menurut Status', isi: data.per_status },
              { judul: 'Menurut Jenis Layanan', isi: data.per_layanan },
              { judul: 'Menurut Kanal', isi: data.per_kanal },
            ].map((bagian) => (
              <Kartu key={bagian.judul}>
                <KepalaKartu judul={bagian.judul} />
                <IsiKartu>
                  <ul className="space-y-2">
                    {Object.entries(bagian.isi).map(([label, jumlah]) => (
                      <li key={label} className="flex items-center justify-between gap-4 text-sm">
                        <span className="text-slate-700">{judulKan(label)}</span>
                        <span className="font-medium tabular-nums">{angka(jumlah)}</span>
                      </li>
                    ))}
                    {Object.keys(bagian.isi).length === 0 && (
                      <li className="text-sm text-slate-600">Tidak ada data pada periode ini.</li>
                    )}
                  </ul>
                </IsiKartu>
              </Kartu>
            ))}
          </div>
        </>
      )}
    </div>
  )
}
