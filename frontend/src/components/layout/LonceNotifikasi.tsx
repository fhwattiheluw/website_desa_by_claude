import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Bell, CheckCheck } from 'lucide-react'
import { api } from '@/lib/api'
import { tanggalRelatif } from '@/lib/format'

interface Notifikasi {
  id: number
  jenis: string
  judul: string
  ringkasan: string | null
  tautan: string | null
  dibaca: boolean
  waktu: string | null
}

interface DaftarNotifikasi {
  jumlah_belum_dibaca: number
  data: Notifikasi[]
}

/**
 * Lonceng pekerjaan baru bagi petugas (REQ-F-NOT-005).
 *
 * Diperbarui berkala, bukan lewat sambungan tetap: koneksi desa tidak selalu
 * sanggup menahan sambungan terbuka, dan pekerjaan yang terlambat diketahui
 * satu menit tidak mengubah apa pun (CON-02).
 */
export function LonceNotifikasi() {
  const klien = useQueryClient()
  const [terbuka, setTerbuka] = useState(false)
  const wadah = useRef<HTMLDivElement>(null)

  const { data } = useQuery({
    queryKey: ['notifikasi-petugas'],
    queryFn: async () => (await api.get<DaftarNotifikasi>('/admin/notifikasi')).data,
    refetchInterval: 60 * 1000,
    refetchOnWindowFocus: true,
  })

  // Panel ditutup saat pengguna menekan di luarnya atau menekan Escape.
  useEffect(() => {
    if (!terbuka) return

    const padaTekan = (peristiwa: MouseEvent) => {
      if (!wadah.current?.contains(peristiwa.target as Node)) setTerbuka(false)
    }

    const padaTombol = (peristiwa: KeyboardEvent) => {
      if (peristiwa.key === 'Escape') setTerbuka(false)
    }

    document.addEventListener('mousedown', padaTekan)
    document.addEventListener('keydown', padaTombol)

    return () => {
      document.removeEventListener('mousedown', padaTekan)
      document.removeEventListener('keydown', padaTombol)
    }
  }, [terbuka])

  const segarkan = () => void klien.invalidateQueries({ queryKey: ['notifikasi-petugas'] })

  const baca = useMutation({
    mutationFn: async (id: number) => (await api.post(`/admin/notifikasi/${id}/baca`)).data,
    onSuccess: segarkan,
  })

  const bacaSemua = useMutation({
    mutationFn: async () => (await api.post('/admin/notifikasi/baca-semua')).data,
    onSuccess: segarkan,
  })

  const belumDibaca = data?.jumlah_belum_dibaca ?? 0

  return (
    <div ref={wadah} className="relative">
      <button
        type="button"
        onClick={() => setTerbuka((sebelumnya) => !sebelumnya)}
        aria-expanded={terbuka}
        aria-label={
          belumDibaca > 0 ? `Notifikasi, ${belumDibaca} belum dibaca` : 'Notifikasi, tidak ada yang baru'
        }
        className="relative grid size-11 place-items-center rounded-lg text-slate-700 hover:bg-slate-100"
      >
        <Bell aria-hidden className="size-5" />
        {belumDibaca > 0 && (
          <span className="absolute right-1.5 top-1.5 grid min-w-5 place-items-center rounded-full bg-red-600 px-1 text-[11px] font-semibold leading-5 text-white">
            {belumDibaca > 9 ? '9+' : belumDibaca}
          </span>
        )}
      </button>

      {terbuka && (
        <div className="absolute right-0 z-50 mt-1 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
          <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <h2 className="text-sm font-semibold">Pekerjaan Baru</h2>
            {belumDibaca > 0 && (
              <button
                type="button"
                onClick={() => bacaSemua.mutate()}
                className="inline-flex items-center gap-1 text-xs font-medium text-desa-700 underline underline-offset-2"
              >
                <CheckCheck aria-hidden className="size-3.5" /> Tandai semua dibaca
              </button>
            )}
          </div>

          {data?.data.length ? (
            <ul className="max-h-96 divide-y divide-slate-100 overflow-auto">
              {data.data.map((satu) => {
                const isi = (
                  <>
                    <span className="flex items-start gap-2">
                      {!satu.dibaca && (
                        <span aria-hidden className="mt-1.5 size-2 shrink-0 rounded-full bg-desa-700" />
                      )}
                      <span className={satu.dibaca ? 'text-slate-600' : 'font-medium text-slate-900'}>
                        {satu.judul}
                      </span>
                    </span>
                    {satu.ringkasan && (
                      <span className="mt-0.5 block pl-4 text-xs text-slate-600">{satu.ringkasan}</span>
                    )}
                    <span className="mt-0.5 block pl-4 text-xs text-slate-500">{tanggalRelatif(satu.waktu)}</span>
                  </>
                )

                return (
                  <li key={satu.id}>
                    {satu.tautan ? (
                      <Link
                        to={satu.tautan}
                        onClick={() => {
                          if (!satu.dibaca) baca.mutate(satu.id)
                          setTerbuka(false)
                        }}
                        className="block px-4 py-3 text-sm hover:bg-slate-50"
                      >
                        {isi}
                      </Link>
                    ) : (
                      <div className="px-4 py-3 text-sm">{isi}</div>
                    )}
                  </li>
                )
              })}
            </ul>
          ) : (
            <p className="px-4 py-6 text-center text-sm text-slate-600">
              Belum ada pekerjaan baru yang masuk ke antrean Anda.
            </p>
          )}
        </div>
      )}
    </div>
  )
}
