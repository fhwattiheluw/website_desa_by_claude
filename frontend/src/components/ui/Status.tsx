import type { ReactNode } from 'react'
import { Loader2, SearchX } from 'lucide-react'
import { Pemberitahuan } from './Pemberitahuan'

export function Pemuat({ label = 'Memuat data…' }: { label?: string }) {
  return (
    <div role="status" className="flex items-center justify-center gap-3 py-16 text-slate-600">
      <Loader2 aria-hidden className="size-5 animate-spin" />
      <span className="text-sm">{label}</span>
    </div>
  )
}

export function Rangka({ baris = 3 }: { baris?: number }) {
  return (
    <div aria-hidden className="space-y-3">
      {Array.from({ length: baris }).map((_, indeks) => (
        <div key={indeks} className="h-20 animate-pulse rounded-xl bg-slate-200/70" />
      ))}
    </div>
  )
}

export function KondisiKosong({
  judul = 'Belum ada data',
  keterangan,
  aksi,
}: {
  judul?: string
  keterangan?: string
  aksi?: ReactNode
}) {
  return (
    <div className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
      <SearchX aria-hidden className="mx-auto size-8 text-slate-400" />
      <p className="mt-3 font-medium text-slate-900">{judul}</p>
      {keterangan && <p className="mx-auto mt-1 max-w-md text-sm text-slate-600">{keterangan}</p>}
      {aksi && <div className="mt-4 flex justify-center">{aksi}</div>}
    </div>
  )
}

export function GalatMuat({ pesan }: { pesan: string }) {
  return (
    <Pemberitahuan jenis="bahaya" judul="Data gagal dimuat">
      {pesan}
    </Pemberitahuan>
  )
}
