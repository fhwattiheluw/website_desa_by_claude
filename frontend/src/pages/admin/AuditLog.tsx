import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { api, pesanGalat } from '@/lib/api'
import { judulKan, tanggal } from '@/lib/format'
import { Kartu } from '@/components/ui/Kartu'
import { Isian } from '@/components/ui/Isian'
import { Lencana } from '@/components/ui/Lencana'
import { Paginasi } from '@/components/ui/Paginasi'
import { GalatMuat, KondisiKosong, Rangka } from '@/components/ui/Status'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import type { Halaman } from '@/types'

interface BarisAudit {
  id: number
  waktu: string
  aktor: string
  aksi: string
  entitas: string
  entitas_id: string | null
  alamat_ip: string | null
}

/** REQ-F-ADM-004, 005: jejak audit bersifat hanya-baca. */
export function AuditLog() {
  const [halaman, setHalaman] = useState(1)
  const [entitas, setEntitas] = useState('')

  const { data, isPending, error } = useQuery({
    queryKey: ['audit-log', halaman, entitas],
    queryFn: async () =>
      (await api.get<Halaman<BarisAudit>>('/admin/audit-log', {
        params: { page: halaman, entitas: entitas || undefined },
      })).data,
  })

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl">Audit Log</h1>
        <p className="mt-1 text-slate-600">
          Catatan seluruh aksi tulis pada sistem. Log tidak dapat diubah maupun dihapus oleh peran mana pun.
        </p>
      </header>

      <Pemberitahuan jenis="info">
        Jejak audit disimpan minimal 24 bulan sesuai kebijakan retensi data sistem.
      </Pemberitahuan>

      <div className="max-w-xs">
        <Isian
          label="Saring entitas"
          value={entitas}
          onChange={(e) => { setEntitas(e.target.value); setHalaman(1) }}
          placeholder="Contoh: Permohonan, User"
        />
      </div>

      {error ? (
        <GalatMuat pesan={pesanGalat(error)} />
      ) : isPending ? (
        <Rangka baris={5} />
      ) : data.data.length === 0 ? (
        <KondisiKosong judul="Belum ada catatan audit" />
      ) : (
        <>
          <Kartu>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <caption className="sr-only">Catatan jejak audit sistem</caption>
                <thead>
                  <tr className="border-b border-slate-200 bg-slate-50 text-left text-slate-600">
                    <th scope="col" className="px-4 py-3 font-medium">Waktu</th>
                    <th scope="col" className="px-4 py-3 font-medium">Pelaku</th>
                    <th scope="col" className="px-4 py-3 font-medium">Aksi</th>
                    <th scope="col" className="px-4 py-3 font-medium">Objek</th>
                    <th scope="col" className="px-4 py-3 font-medium">Alamat IP</th>
                  </tr>
                </thead>
                <tbody>
                  {data.data.map((baris) => (
                    <tr key={baris.id} className="border-b border-slate-100 last:border-0">
                      <td className="px-4 py-3 whitespace-nowrap">{tanggal(baris.waktu, true)}</td>
                      <td className="px-4 py-3">{baris.aktor}</td>
                      <td className="px-4 py-3">
                        <Lencana nada={baris.aksi.includes('delete') ? 'bahaya' : 'netral'}>
                          {judulKan(baris.aksi)}
                        </Lencana>
                      </td>
                      <td className="px-4 py-3">
                        {baris.entitas}
                        {baris.entitas_id && <span className="text-slate-500"> #{baris.entitas_id}</span>}
                      </td>
                      <td className="px-4 py-3 tabular-nums text-slate-500">{baris.alamat_ip ?? '-'}</td>
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
