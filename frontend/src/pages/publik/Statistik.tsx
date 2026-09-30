import { useStatistik } from '@/lib/kueri'
import { pesanGalat } from '@/lib/api'
import { angka, judulKan } from '@/lib/format'
import { useMeta } from '@/lib/meta'
import { GrafikBatang, WARNA_TUNGGAL } from '@/components/chart/GrafikBatang'
import { BatangProporsi } from '@/components/chart/BatangProporsi'
import { Kartu, KartuStatistik, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { GalatMuat, Pemuat } from '@/components/ui/Status'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import { TrendingDown, TrendingUp, Minus } from 'lucide-react'

/** Menuliskan selisih apa adanya, lengkap dengan tandanya. */
function selisihTeks(nilai: number) {
  return `${nilai > 0 ? '+' : nilai < 0 ? '−' : ''}${angka(Math.abs(nilai))}`
}

function IkonArah({ nilai }: { nilai: number }) {
  if (nilai > 0) return <TrendingUp aria-hidden className="size-4 text-desa-700" />
  if (nilai < 0) return <TrendingDown aria-hidden className="size-4 text-orange-600" />

  return <Minus aria-hidden className="size-4 text-slate-400" />
}

const JUDUL_KELOMPOK: Record<string, string> = {
  usia: 'Penduduk menurut Kelompok Usia',
  pendidikan: 'Penduduk menurut Tingkat Pendidikan',
  pekerjaan: 'Penduduk menurut Jenis Pekerjaan',
  agama: 'Penduduk menurut Agama',
  dusun: 'Penduduk menurut Dusun',
}

export function Statistik() {
  const { data, isPending, error } = useStatistik()

  useMeta({
    judul: 'Statistik Desa',
    deskripsi: 'Data agregat kependudukan desa menurut usia, pendidikan, pekerjaan, agama, dan wilayah dusun.',
  })

  if (error) return <GalatMuat pesan={pesanGalat(error)} />
  if (isPending || !data) return <Pemuat />

  const jenisKelamin = (data.kelompok.jenis_kelamin ?? []).map((item) => ({
    label: item.label,
    jumlah: item.jumlah ?? 0,
  }))

  const kelompokLain = Object.entries(data.kelompok).filter(
    ([kunci]) => kunci !== 'jenis_kelamin' && kunci !== 'kepala_keluarga',
  )

  return (
    <div className="space-y-8">
      <header>
        <h1 className="text-2xl">Statistik Desa</h1>
        <p className="mt-1 text-slate-600">
          Data agregat kependudukan periode {data.periode.nama}. Sumber: {data.periode.sumber_data ?? 'Pendataan desa'}.
        </p>
      </header>

      <section className="grid gap-4 sm:grid-cols-3">
        <KartuStatistik label="Total penduduk" nilai={angka(data.total_penduduk)} keterangan="jiwa" nada="positif" />
        <KartuStatistik label="Kepala keluarga" nilai={angka(data.total_kk)} keterangan="KK" />
        <KartuStatistik
          label="Periode data"
          nilai={<span className="text-lg">{data.periode.nama}</span>}
          keterangan={
            data.pembanding ? `Dibandingkan ${data.pembanding.periode.nama}` : 'Belum ada periode pembanding'
          }
        />
      </section>

      {/* REQ-F-STA-004: pembanding antarperiode. */}
      {data.pembanding && (
        <Kartu>
          <KepalaKartu
            judul={`Perubahan dari ${data.pembanding.periode.nama}`}
            deskripsi={`Total penduduk ${angka(data.pembanding.total_penduduk)} jiwa pada periode itu.`}
            aksi={
              <span className="inline-flex items-center gap-1.5 text-sm font-medium">
                <IkonArah nilai={data.pembanding.selisih_total} />
                {selisihTeks(data.pembanding.selisih_total)} jiwa
              </span>
            }
          />

          <div className="max-h-96 overflow-auto">
            <table className="w-full text-sm">
              <caption className="sr-only">
                Perbandingan jumlah penduduk per kelompok antara {data.periode.nama} dan{' '}
                {data.pembanding.periode.nama}
              </caption>
              <thead className="sticky top-0 bg-slate-50">
                <tr className="border-b border-slate-200 text-left text-slate-600">
                  <th scope="col" className="px-5 py-3 font-medium">Kelompok</th>
                  <th scope="col" className="px-5 py-3 text-right font-medium">{data.pembanding.periode.tahun}</th>
                  <th scope="col" className="px-5 py-3 text-right font-medium">{data.periode.tahun}</th>
                  <th scope="col" className="px-5 py-3 text-right font-medium">Selisih</th>
                </tr>
              </thead>
              <tbody>
                {data.pembanding.per_kelompok.map((baris) => (
                  <tr key={`${baris.kelompok}-${baris.label}`} className="border-b border-slate-100 last:border-0">
                    <th scope="row" className="px-5 py-2.5 text-left font-medium">
                      {baris.label}
                      <span className="block text-xs font-normal text-slate-500">{judulKan(baris.kelompok)}</span>
                    </th>
                    <td className="px-5 py-2.5 text-right tabular-nums text-slate-600">{angka(baris.sebelumnya)}</td>
                    <td className="px-5 py-2.5 text-right tabular-nums">{angka(baris.kini)}</td>
                    <td className="px-5 py-2.5 text-right tabular-nums">
                      <span className="inline-flex items-center justify-end gap-1.5">
                        <IkonArah nilai={baris.selisih} />
                        {selisihTeks(baris.selisih)}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <IsiKartu className="border-t border-slate-100">
            <Pemberitahuan jenis="info">{data.pembanding.catatan}</Pemberitahuan>
          </IsiKartu>
        </Kartu>
      )}

      {jenisKelamin.length > 0 && (
        <Kartu>
          <KepalaKartu judul="Komposisi Penduduk menurut Jenis Kelamin" />
          <IsiKartu>
            <BatangProporsi judul="" butir={jenisKelamin} />
          </IsiKartu>
        </Kartu>
      )}

      <div className="space-y-6">
        {kelompokLain.map(([kunci, butir]) => (
          <GrafikBatang
            key={kunci}
            judul={JUDUL_KELOMPOK[kunci] ?? judulKan(kunci)}
            data={butir.map((item) => ({ label: item.label, jumlah: item.jumlah ?? 0 }))}
            kunciLabel="label"
            seri={[{ kunci: 'jumlah', label: 'Jumlah jiwa', warna: WARNA_TUNGGAL }]}
            format={(nilai) => angka(nilai)}
            catatan={butir.some((item) => item.disamarkan) ? data.catatan_privasi : undefined}
          />
        ))}
      </div>

      <p className="text-sm text-slate-500">{data.catatan_privasi}</p>
    </div>
  )
}
