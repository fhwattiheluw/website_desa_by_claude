import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Eye } from 'lucide-react'
import { api, pesanGalat } from '@/lib/api'
import { angka, tanggal } from '@/lib/format'
import { Kartu, KartuStatistik, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { Pilihan } from '@/components/ui/Isian'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import { GalatMuat, KondisiKosong, Rangka } from '@/components/ui/Status'
import { GrafikBatang, WARNA_SERI } from '@/components/chart/GrafikBatang'

interface Ringkasan {
  aktif: boolean
  rentang_hari: number
  mulai: string
  total_kunjungan: number
  per_hari: { tanggal: string; jumlah: number }[]
  laman_teratas: { jalur: string; jumlah: number }[]
  catatan: string
}

const RENTANG = [
  { nilai: '7', teks: '7 hari terakhir' },
  { nilai: '30', teks: '30 hari terakhir' },
  { nilai: '90', teks: '90 hari terakhir' },
  { nilai: '180', teks: '180 hari terakhir' },
]

/** REQ-SW-006: statistik kunjungan laman, dihitung tanpa data pribadi. */
export function Analitik() {
  const [hari, setHari] = useState('30')

  const { data, isPending, error } = useQuery({
    queryKey: ['analitik', hari],
    queryFn: async () => (await api.get<Ringkasan>('/admin/analitik', { params: { hari } })).data,
  })

  if (error) return <GalatMuat pesan={pesanGalat(error)} />

  const rerata = data && data.per_hari.length > 0 ? Math.round(data.total_kunjungan / data.per_hari.length) : 0

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl">Statistik Kunjungan</h1>
          <p className="mt-1 text-slate-600">
            Seberapa sering laman portal dibuka. Dihitung sendiri oleh sistem, tanpa layanan pihak ketiga.
          </p>
        </div>
        <div className="w-56">
          <Pilihan
            label="Rentang waktu"
            value={hari}
            onChange={(e) => setHari(e.target.value)}
            pilihan={RENTANG}
          />
        </div>
      </header>

      {isPending ? (
        <Rangka baris={3} />
      ) : !data.aktif ? (
        <Pemberitahuan jenis="peringatan" judul="Analitik dimatikan">
          Penghitungan kunjungan sedang dimatikan pada konfigurasi server, sehingga angka di bawah tidak bertambah.
          Administrator dapat menyalakannya kembali melalui variabel lingkungan ANALITIK_AKTIF.
        </Pemberitahuan>
      ) : null}

      {!isPending && (
        <>
          <section className="grid gap-4 sm:grid-cols-3">
            <KartuStatistik
              label="Total pembukaan laman"
              nilai={angka(data.total_kunjungan)}
              ikon={<Eye aria-hidden className="size-5" />}
              nada="positif"
            />
            <KartuStatistik label="Rata-rata per hari" nilai={angka(rerata)} />
            <KartuStatistik
              label="Sejak"
              nilai={tanggal(data.mulai)}
              keterangan={`Rentang ${data.rentang_hari} hari`}
            />
          </section>

          <Pemberitahuan jenis="info">{data.catatan}</Pemberitahuan>

          {data.laman_teratas.length === 0 ? (
            <KondisiKosong
              judul="Belum ada kunjungan tercatat"
              keterangan="Angka akan muncul setelah warga membuka laman portal."
            />
          ) : (
            <>
              <GrafikBatang
                judul="Laman Paling Sering Dibuka"
                deskripsi={`Lima belas laman teratas dalam ${data.rentang_hari} hari terakhir.`}
                data={data.laman_teratas.map((baris) => ({ jalur: baris.jalur, jumlah: baris.jumlah }))}
                kunciLabel="jalur"
                seri={[{ kunci: 'jumlah', label: 'Pembukaan laman', warna: WARNA_SERI.satu }]}
                format={(nilai) => angka(nilai)}
              />

              <Kartu>
                <KepalaKartu
                  judul="Kunjungan per Hari"
                  deskripsi="Jumlah pembukaan laman setiap hari pada rentang terpilih."
                />
                {data.per_hari.length === 0 ? (
                  <IsiKartu>
                    <p className="text-sm text-slate-600">Belum ada data pada rentang ini.</p>
                  </IsiKartu>
                ) : (
                  <div className="max-h-96 overflow-auto">
                    <table className="w-full text-sm">
                      <caption className="sr-only">Jumlah pembukaan laman per hari</caption>
                      <thead className="sticky top-0 bg-slate-50">
                        <tr className="border-b border-slate-200 text-left text-slate-600">
                          <th scope="col" className="px-5 py-3 font-medium">Tanggal</th>
                          <th scope="col" className="px-5 py-3 text-right font-medium">Pembukaan laman</th>
                        </tr>
                      </thead>
                      <tbody>
                        {[...data.per_hari].reverse().map((baris) => (
                          <tr key={baris.tanggal} className="border-b border-slate-100 last:border-0">
                            <th scope="row" className="px-5 py-2.5 text-left font-medium">{tanggal(baris.tanggal)}</th>
                            <td className="px-5 py-2.5 text-right tabular-nums">{angka(baris.jumlah)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </Kartu>
            </>
          )}
        </>
      )}
    </div>
  )
}
