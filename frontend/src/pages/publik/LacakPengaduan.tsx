import { useState, type FormEvent } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { judulKan, tanggal } from '@/lib/format'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { Isian } from '@/components/ui/Isian'
import { Tombol } from '@/components/ui/Tombol'
import { LencanaPengaduan } from '@/components/ui/Lencana'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import type { Pengaduan } from '@/types'

export function LacakPengaduan() {
  const [parameter, setParameter] = useSearchParams()
  const [kode, setKode] = useState(parameter.get('kode') ?? '')
  const dicari = parameter.get('kode') ?? ''

  const { data, isFetching, error } = useQuery({
    queryKey: ['lacak-pengaduan', dicari],
    queryFn: async () => (await api.get<{ data: Pengaduan }>(`/pengaduan/lacak/${dicari}`)).data.data,
    enabled: dicari.length > 0,
    retry: false,
  })

  const cari = (peristiwa: FormEvent) => {
    peristiwa.preventDefault()
    setParameter({ kode: kode.trim().toUpperCase() })
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <header>
        <h1 className="text-2xl">Lacak Pengaduan</h1>
        <p className="mt-1 text-slate-600">Masukkan kode lacak yang Anda terima saat mengirim pengaduan.</p>
      </header>

      <Kartu>
        <IsiKartu>
          <form onSubmit={cari} className="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div className="flex-1">
              <Isian label="Kode lacak" value={kode} onChange={(e) => setKode(e.target.value)} required />
            </div>
            <Tombol type="submit" memuat={isFetching}>
              Lacak
            </Tombol>
          </form>
        </IsiKartu>
      </Kartu>

      {error && (
        <Pemberitahuan jenis="peringatan" judul="Pengaduan tidak ditemukan">
          Kode lacak tidak dikenali. Periksa kembali kode yang Anda masukkan.
        </Pemberitahuan>
      )}

      {data && (
        <Kartu>
          <KepalaKartu
            judul={data.judul}
            deskripsi={`${data.nomor_tiket} · ${judulKan(data.kategori)}`}
            aksi={<LencanaPengaduan status={data.status} />}
          />
          <IsiKartu className="space-y-5">
            <dl className="grid gap-4 sm:grid-cols-2">
              <div>
                <dt className="text-sm text-slate-600">Dilaporkan oleh</dt>
                <dd className="font-medium">{data.pelapor}</dd>
              </div>
              <div>
                <dt className="text-sm text-slate-600">Tanggal laporan</dt>
                <dd className="font-medium">{tanggal(data.dibuat_pada)}</dd>
              </div>
              <div>
                <dt className="text-sm text-slate-600">Lokasi</dt>
                <dd className="font-medium">{data.lokasi ?? '-'}</dd>
              </div>
              <div>
                <dt className="text-sm text-slate-600">Tenggat tanggapan</dt>
                <dd className="font-medium">{tanggal(data.tenggat_tanggapan, true)}</dd>
              </div>
            </dl>

            <div>
              <h3 className="text-sm font-medium text-slate-700">Uraian laporan</h3>
              <p className="mt-1 whitespace-pre-line text-slate-700">{data.uraian}</p>
            </div>

            <div>
              <h3 className="text-sm font-medium text-slate-700">Tanggapan petugas</h3>
              {data.tanggapan?.length ? (
                <ol className="mt-2 space-y-3">
                  {data.tanggapan.map((tanggapan, indeks) => (
                    <li key={indeks} className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                      <p className="text-slate-700">{tanggapan.isi}</p>
                      <p className="mt-2 text-xs text-slate-500">
                        {tanggapan.oleh} · {tanggal(tanggapan.waktu, true)}
                      </p>
                    </li>
                  ))}
                </ol>
              ) : (
                <p className="mt-1 text-sm text-slate-600">Belum ada tanggapan. Pengaduan Anda sedang ditinjau.</p>
              )}
            </div>
          </IsiKartu>
        </Kartu>
      )}
    </div>
  )
}
