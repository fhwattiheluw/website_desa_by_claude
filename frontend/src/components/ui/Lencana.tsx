import type { ReactNode } from 'react'
import type { StatusPengaduan, StatusPermohonan } from '@/types'

type Nada = 'netral' | 'info' | 'sukses' | 'peringatan' | 'bahaya'

const NADA: Record<Nada, string> = {
  netral: 'bg-slate-100 text-slate-700 ring-slate-200',
  info: 'bg-sky-50 text-sky-800 ring-sky-200',
  sukses: 'bg-desa-50 text-desa-800 ring-desa-200',
  peringatan: 'bg-amber-50 text-amber-800 ring-amber-200',
  bahaya: 'bg-red-50 text-red-800 ring-red-200',
}

export function Lencana({ nada = 'netral', children }: { nada?: Nada; children: ReactNode }) {
  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ${NADA[nada]}`}
    >
      {children}
    </span>
  )
}

const LABEL_PERMOHONAN: Record<StatusPermohonan, { teks: string; nada: Nada }> = {
  draf: { teks: 'Draf', nada: 'netral' },
  diajukan: { teks: 'Diajukan', nada: 'info' },
  diverifikasi: { teks: 'Diverifikasi', nada: 'info' },
  disetujui: { teks: 'Disetujui', nada: 'info' },
  ditandatangani: { teks: 'Ditandatangani', nada: 'sukses' },
  selesai: { teks: 'Selesai', nada: 'sukses' },
  dikembalikan: { teks: 'Perlu Perbaikan', nada: 'peringatan' },
  ditolak: { teks: 'Ditolak', nada: 'bahaya' },
}

export function LencanaPermohonan({ status }: { status: StatusPermohonan }) {
  const { teks, nada } = LABEL_PERMOHONAN[status] ?? { teks: status, nada: 'netral' as Nada }

  return <Lencana nada={nada}>{teks}</Lencana>
}

const LABEL_PENGADUAN: Record<StatusPengaduan, { teks: string; nada: Nada }> = {
  baru: { teks: 'Baru', nada: 'info' },
  diverifikasi: { teks: 'Diverifikasi', nada: 'info' },
  didisposisi: { teks: 'Didisposisi', nada: 'info' },
  proses: { teks: 'Dalam Proses', nada: 'peringatan' },
  selesai: { teks: 'Selesai', nada: 'sukses' },
  ditolak: { teks: 'Ditolak', nada: 'bahaya' },
}

export function LencanaPengaduan({ status }: { status: StatusPengaduan }) {
  const { teks, nada } = LABEL_PENGADUAN[status] ?? { teks: status, nada: 'netral' as Nada }

  return <Lencana nada={nada}>{teks}</Lencana>
}
