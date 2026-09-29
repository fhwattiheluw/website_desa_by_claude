import { useState } from 'react'
import { Download, FileDown } from 'lucide-react'
import { useApbdes, useTahunApbdes } from '@/lib/kueri'
import { pesanGalat } from '@/lib/api'
import { rupiah, tanggal } from '@/lib/format'
import { GrafikBatang, WARNA_SERI } from '@/components/chart/GrafikBatang'
import { Kartu, KartuStatistik, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { GalatMuat, KondisiKosong, Pemuat } from '@/components/ui/Status'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'

export function Apbdes() {
  const { data: daftarTahun, isPending: memuatTahun } = useTahunApbdes()
  const [tahunDipilih, setTahunDipilih] = useState<number | undefined>()

  // Tahun terbaru dipakai sampai pengguna memilih tahun lain.
  const tahun = tahunDipilih ?? daftarTahun?.[0]?.tahun

  const { data, isPending, error } = useApbdes(tahun)

  if (memuatTahun) return <Pemuat />

  if (!daftarTahun?.length) {
    return (
      <KondisiKosong
        judul="Data APBDes belum dipublikasikan"
        keterangan="Pemerintah desa belum mempublikasikan data anggaran. Silakan periksa kembali nanti."
      />
    )
  }

  const belanja = (data?.item ?? [])
    .filter((item) => item.jenis === 'belanja')
    .map((item) => ({
      bidang: item.kegiatan ? `${item.bidang} — ${item.kegiatan}` : item.bidang,
      pagu: item.pagu,
      realisasi: item.realisasi,
    }))

  const pendapatan = (data?.item ?? [])
    .filter((item) => item.jenis === 'pendapatan')
    .map((item) => ({ bidang: item.bidang, pagu: item.pagu, realisasi: item.realisasi }))

  const penyerapan =
    data && data.ringkasan.belanja.pagu > 0
      ? Math.round((data.ringkasan.belanja.realisasi / data.ringkasan.belanja.pagu) * 100)
      : 0

  return (
    <div className="space-y-8">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl">Transparansi APBDes</h1>
          <p className="mt-1 text-slate-600">
            Anggaran Pendapatan dan Belanja Desa beserta realisasinya.
          </p>
        </div>

        <div>
          <label htmlFor="tahun-anggaran" className="mb-1.5 block text-sm font-medium">
            Tahun anggaran
          </label>
          <select
            id="tahun-anggaran"
            value={tahun ?? ''}
            onChange={(e) => setTahunDipilih(Number(e.target.value))}
            className="min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm"
          >
            {daftarTahun.map((item) => (
              <option key={item.tahun} value={item.tahun}>
                {item.tahun}
              </option>
            ))}
          </select>
        </div>
      </header>

      {error ? (
        <GalatMuat pesan={pesanGalat(error)} />
      ) : isPending || !data ? (
        <Pemuat />
      ) : (
        <>
          <Pemberitahuan jenis="info">
            Data diperbarui terakhir pada {tanggal(data.diperbarui_pada, true)}. Angka realisasi bersifat berjalan dan
            direkonsiliasi dengan laporan keuangan desa.
          </Pemberitahuan>

          <section aria-labelledby="ringkasan" className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <h2 id="ringkasan" className="sr-only">Ringkasan anggaran</h2>
            <KartuStatistik
              label="Pendapatan (pagu)"
              nilai={rupiah(data.ringkasan.pendapatan.pagu, true)}
              keterangan={`Realisasi ${rupiah(data.ringkasan.pendapatan.realisasi, true)}`}
              nada="positif"
            />
            <KartuStatistik
              label="Belanja (pagu)"
              nilai={rupiah(data.ringkasan.belanja.pagu, true)}
              keterangan={`Realisasi ${rupiah(data.ringkasan.belanja.realisasi, true)}`}
            />
            <KartuStatistik
              label="Pembiayaan"
              nilai={rupiah(data.ringkasan.pembiayaan.pagu, true)}
              keterangan={`Realisasi ${rupiah(data.ringkasan.pembiayaan.realisasi, true)}`}
            />
            <KartuStatistik
              label="Penyerapan belanja"
              nilai={`${penyerapan}%`}
              keterangan="Realisasi dibanding pagu belanja"
              nada={penyerapan >= 80 ? 'positif' : 'peringatan'}
            />
          </section>

          {pendapatan.length > 0 && (
            <GrafikBatang
              judul="Pendapatan Desa"
              deskripsi="Perbandingan pagu dan realisasi menurut sumber pendapatan."
              data={pendapatan}
              kunciLabel="bidang"
              seri={[
                { kunci: 'pagu', label: 'Pagu', warna: WARNA_SERI.satu },
                { kunci: 'realisasi', label: 'Realisasi', warna: WARNA_SERI.dua },
              ]}
              format={(nilai) => rupiah(nilai, true)}
            />
          )}

          {belanja.length > 0 && (
            <GrafikBatang
              judul="Belanja Desa menurut Bidang"
              deskripsi="Perbandingan pagu dan realisasi belanja per bidang kegiatan."
              data={belanja}
              kunciLabel="bidang"
              seri={[
                { kunci: 'pagu', label: 'Pagu', warna: WARNA_SERI.satu },
                { kunci: 'realisasi', label: 'Realisasi', warna: WARNA_SERI.dua },
              ]}
              format={(nilai) => rupiah(nilai, true)}
            />
          )}

          <Kartu>
            <KepalaKartu
              judul="Rincian Anggaran"
              deskripsi="Seluruh pos anggaran beserta persentase penyerapan."
              aksi={
                <a
                  href={`/api/v1/apbdes/${data.tahun}/csv`}
                  className="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-slate-300 px-3 text-sm hover:bg-slate-50"
                >
                  <FileDown aria-hidden className="size-4" /> Unduh CSV
                </a>
              }
            />
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <caption className="sr-only">Rincian APBDes tahun {data.tahun}</caption>
                <thead>
                  <tr className="border-b border-slate-200 bg-slate-50 text-left text-slate-600">
                    <th scope="col" className="px-5 py-3 font-medium">Jenis</th>
                    <th scope="col" className="px-5 py-3 font-medium">Bidang / Kegiatan</th>
                    <th scope="col" className="px-5 py-3 text-right font-medium">Pagu</th>
                    <th scope="col" className="px-5 py-3 text-right font-medium">Realisasi</th>
                    <th scope="col" className="px-5 py-3 text-right font-medium">Penyerapan</th>
                  </tr>
                </thead>
                <tbody>
                  {data.item.map((item, indeks) => (
                    <tr key={indeks} className="border-b border-slate-100 last:border-0">
                      <td className="px-5 py-3 capitalize text-slate-600">{item.jenis}</td>
                      <td className="px-5 py-3">
                        <span className="font-medium text-slate-900">{item.bidang}</span>
                        {item.kegiatan && <span className="block text-xs text-slate-500">{item.kegiatan}</span>}
                      </td>
                      <td className="px-5 py-3 text-right tabular-nums">{rupiah(item.pagu)}</td>
                      <td className="px-5 py-3 text-right tabular-nums">{rupiah(item.realisasi)}</td>
                      <td className="px-5 py-3 text-right tabular-nums">{item.penyerapan}%</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Kartu>

          {data.dokumen.length > 0 && (
            <Kartu>
              <KepalaKartu judul="Dokumen Resmi" deskripsi="Berkas APBDes dan laporan pertanggungjawaban." />
              <IsiKartu>
                <ul className="space-y-2">
                  {data.dokumen.map((dokumen) => (
                    <li key={dokumen.judul}>
                      <a
                        href={dokumen.url ?? '#'}
                        className="inline-flex items-center gap-2 text-sm font-medium text-desa-700 hover:underline"
                      >
                        <Download aria-hidden className="size-4" /> {dokumen.judul}
                      </a>
                    </li>
                  ))}
                </ul>
              </IsiKartu>
            </Kartu>
          )}
        </>
      )}
    </div>
  )
}
