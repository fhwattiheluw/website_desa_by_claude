import type { ReactNode } from 'react'

export function Kartu({ children, className = '' }: { children: ReactNode; className?: string }) {
  return (
    <div className={`rounded-xl border border-slate-200 bg-white shadow-sm ${className}`}>{children}</div>
  )
}

export function KepalaKartu({
  judul,
  deskripsi,
  aksi,
}: {
  judul: ReactNode
  deskripsi?: ReactNode
  aksi?: ReactNode
}) {
  return (
    <div className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
      <div>
        <h2 className="text-base font-semibold">{judul}</h2>
        {deskripsi && <p className="mt-0.5 text-sm text-slate-600">{deskripsi}</p>}
      </div>
      {aksi}
    </div>
  )
}

export function IsiKartu({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <div className={`px-5 py-4 ${className}`}>{children}</div>
}

export function KartuStatistik({
  label,
  nilai,
  keterangan,
  ikon,
  nada = 'netral',
}: {
  label: string
  nilai: ReactNode
  keterangan?: string
  ikon?: ReactNode
  nada?: 'netral' | 'positif' | 'peringatan' | 'bahaya'
}) {
  const nadaKelas = {
    netral: 'text-slate-900',
    positif: 'text-desa-700',
    peringatan: 'text-amber-700',
    bahaya: 'text-red-700',
  }[nada]

  return (
    <Kartu className="p-5">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-sm text-slate-600">{label}</p>
          <p className={`mt-1 text-2xl font-semibold tabular-nums ${nadaKelas}`}>{nilai}</p>
          {keterangan && <p className="mt-1 text-xs text-slate-500">{keterangan}</p>}
        </div>
        {ikon && <span className="shrink-0 rounded-lg bg-desa-50 p-2 text-desa-700">{ikon}</span>}
      </div>
    </Kartu>
  )
}
