import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { AlertTriangle, Search } from 'lucide-react'
import { api, pesanGalat } from '@/lib/api'
import { useLayanan } from '@/lib/kueri'
import { tanggal } from '@/lib/format'
import { Kartu, IsiKartu } from '@/components/ui/Kartu'
import { Isian, Pilihan } from '@/components/ui/Isian'
import { Lencana, LencanaPermohonan } from '@/components/ui/Lencana'
import { Paginasi } from '@/components/ui/Paginasi'
import { GalatMuat, KondisiKosong, Rangka } from '@/components/ui/Status'
import type { Halaman, Permohonan } from '@/types'

const STATUS = [
  'diajukan', 'diverifikasi', 'disetujui', 'ditandatangani', 'selesai', 'dikembalikan', 'ditolak',
]

/** REQ-F-SRT-019, 020: antrean kerja terurut tenggat dengan penanda keterlambatan. */
export function AntreanPermohonan() {
  const [parameter, setParameter] = useSearchParams()
  const [halaman, setHalaman] = useState(1)
  const [q, setQ] = useState('')
  const { data: layanan } = useLayanan()

  const status = parameter.get('status') ?? ''
  const kodeLayanan = parameter.get('layanan') ?? ''
  const terlambat = parameter.get('terlambat') === '1'

  const { data, isPending, error } = useQuery({
    queryKey: ['antrean-permohonan', status, kodeLayanan, terlambat, q, halaman],
    queryFn: async () =>
      (
        await api.get<Halaman<Permohonan>>('/admin/permohonan', {
          params: {
            page: halaman,
            status: status || undefined,
            layanan: kodeLayanan || undefined,
            terlambat: terlambat ? 1 : undefined,
            q: q || undefined,
          },
        })
      ).data,
  })

  const ubahSaring = (kunci: string, nilai: string) => {
    const baru = new URLSearchParams(parameter)
    if (nilai) baru.set(kunci, nilai)
    else baru.delete(kunci)
    setParameter(baru)
    setHalaman(1)
  }

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl">Antrean Permohonan</h1>
        <p className="mt-1 text-slate-600">
          Permohonan diurutkan berdasarkan tenggat penyelesaian terdekat.
        </p>
      </header>

      <Kartu>
        <IsiKartu>
          <div className="grid gap-4 md:grid-cols-4">
            <Pilihan
              label="Status"
              value={status}
              onChange={(e) => ubahSaring('status', e.target.value)}
              kosong="Semua status"
              pilihan={STATUS.map((s) => ({ nilai: s, teks: s.charAt(0).toUpperCase() + s.slice(1) }))}
            />
            <Pilihan
              label="Jenis layanan"
              value={kodeLayanan}
              onChange={(e) => ubahSaring('layanan', e.target.value)}
              kosong="Semua layanan"
              pilihan={(layanan ?? []).map((l) => ({ nilai: l.kode, teks: l.nama }))}
            />
            <Isian
              label="Cari"
              value={q}
              onChange={(e) => { setQ(e.target.value); setHalaman(1) }}
              placeholder="Nomor tiket atau nama"
            />
            <div className="flex items-end">
              <button
                type="button"
                onClick={() => ubahSaring('terlambat', terlambat ? '' : '1')}
                aria-pressed={terlambat}
                className={`inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border px-3 text-sm font-medium ${
                  terlambat ? 'border-red-300 bg-red-50 text-red-800' : 'border-slate-300 bg-white text-slate-700'
                }`}
              >
                <AlertTriangle aria-hidden className="size-4" /> Hanya yang terlambat
              </button>
            </div>
          </div>
        </IsiKartu>
      </Kartu>

      {error ? (
        <GalatMuat pesan={pesanGalat(error)} />
      ) : isPending ? (
        <Rangka baris={5} />
      ) : data.data.length === 0 ? (
        <KondisiKosong
          judul="Tidak ada permohonan"
          keterangan="Tidak ada permohonan yang cocok dengan saringan saat ini."
          aksi={
            <button type="button" onClick={() => setParameter({})} className="text-sm font-medium text-desa-700">
              <Search aria-hidden className="mr-1 inline size-4" /> Bersihkan saringan
            </button>
          }
        />
      ) : (
        <>
          <Kartu>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <caption className="sr-only">Daftar permohonan layanan</caption>
                <thead>
                  <tr className="border-b border-slate-200 bg-slate-50 text-left text-slate-600">
                    <th scope="col" className="px-4 py-3 font-medium">Nomor tiket</th>
                    <th scope="col" className="px-4 py-3 font-medium">Pemohon</th>
                    <th scope="col" className="px-4 py-3 font-medium">Layanan</th>
                    <th scope="col" className="px-4 py-3 font-medium">Tenggat</th>
                    <th scope="col" className="px-4 py-3 font-medium">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {data.data.map((permohonan) => (
                    <tr key={permohonan.id} className="border-b border-slate-100 last:border-0 hover:bg-slate-50">
                      <td className="px-4 py-3">
                        <Link
                          to={`/admin/permohonan/${permohonan.id}`}
                          className="font-medium text-desa-700 hover:underline"
                        >
                          {permohonan.nomor_tiket}
                        </Link>
                        {permohonan.kanal === 'loket' && (
                          <span className="ml-2">
                            <Lencana>Loket</Lencana>
                          </span>
                        )}
                      </td>
                      <td className="px-4 py-3">{permohonan.pemohon?.nama ?? '-'}</td>
                      <td className="px-4 py-3">{permohonan.layanan?.nama}</td>
                      <td className="px-4 py-3">
                        <span className={permohonan.melampaui_sla ? 'font-medium text-red-700' : ''}>
                          {tanggal(permohonan.tenggat_sla)}
                        </span>
                        {permohonan.melampaui_sla && (
                          <span className="ml-2">
                            <Lencana nada="bahaya">Terlambat</Lencana>
                          </span>
                        )}
                      </td>
                      <td className="px-4 py-3">
                        <LencanaPermohonan status={permohonan.status} />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Kartu>

          <Paginasi halaman={data.current_page} totalHalaman={data.last_page} total={data.total} onPindah={setHalaman} />
        </>
      )}
    </div>
  )
}
