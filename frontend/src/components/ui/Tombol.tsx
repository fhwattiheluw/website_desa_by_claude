import type { ButtonHTMLAttributes, ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { Loader2 } from 'lucide-react'

type Ragam = 'utama' | 'sekunder' | 'garis' | 'bahaya' | 'halus'
type Ukuran = 'kecil' | 'sedang' | 'besar'

const RAGAM: Record<Ragam, string> = {
  utama: 'bg-desa-700 text-white hover:bg-desa-800 disabled:bg-desa-700/50',
  sekunder: 'bg-slate-800 text-white hover:bg-slate-900 disabled:bg-slate-400',
  garis: 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 disabled:text-slate-400',
  bahaya: 'bg-red-700 text-white hover:bg-red-800 disabled:bg-red-300',
  halus: 'bg-desa-50 text-desa-800 hover:bg-desa-100 disabled:text-desa-400',
}

// Target sentuh minimum 44 px pada perangkat bergerak (REQ-UI-010).
const UKURAN: Record<Ukuran, string> = {
  kecil: 'min-h-9 px-3 text-sm gap-1.5',
  sedang: 'min-h-11 px-4 text-sm gap-2',
  besar: 'min-h-12 px-6 text-base gap-2',
}

const DASAR =
  'inline-flex items-center justify-center rounded-lg font-medium transition-colors disabled:cursor-not-allowed'

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
  ragam?: Ragam
  ukuran?: Ukuran
  memuat?: boolean
  children: ReactNode
}

export function Tombol({ ragam = 'utama', ukuran = 'sedang', memuat, children, className = '', ...sisa }: Props) {
  return (
    <button
      {...sisa}
      disabled={sisa.disabled || memuat}
      aria-busy={memuat || undefined}
      className={`${DASAR} ${RAGAM[ragam]} ${UKURAN[ukuran]} ${className}`}
    >
      {memuat && <Loader2 aria-hidden className="size-4 animate-spin" />}
      {children}
    </button>
  )
}

export function TautanTombol({
  to,
  ragam = 'utama',
  ukuran = 'sedang',
  children,
  className = '',
}: {
  to: string
  ragam?: Ragam
  ukuran?: Ukuran
  children: ReactNode
  className?: string
}) {
  return (
    <Link to={to} className={`${DASAR} ${RAGAM[ragam]} ${UKURAN[ukuran]} ${className}`}>
      {children}
    </Link>
  )
}
