import type { ReactNode } from 'react'
import { AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-react'

type Jenis = 'info' | 'sukses' | 'peringatan' | 'bahaya'

const GAYA: Record<Jenis, { wadah: string; ikon: ReactNode }> = {
  info: { wadah: 'border-sky-200 bg-sky-50 text-sky-900', ikon: <Info aria-hidden className="size-5 text-sky-700" /> },
  sukses: {
    wadah: 'border-desa-200 bg-desa-50 text-desa-900',
    ikon: <CheckCircle2 aria-hidden className="size-5 text-desa-700" />,
  },
  peringatan: {
    wadah: 'border-amber-200 bg-amber-50 text-amber-900',
    ikon: <AlertTriangle aria-hidden className="size-5 text-amber-700" />,
  },
  bahaya: { wadah: 'border-red-200 bg-red-50 text-red-900', ikon: <XCircle aria-hidden className="size-5 text-red-700" /> },
}

export function Pemberitahuan({
  jenis = 'info',
  judul,
  children,
}: {
  jenis?: Jenis
  judul?: string
  children?: ReactNode
}) {
  const gaya = GAYA[jenis]

  return (
    <div
      role={jenis === 'bahaya' ? 'alert' : 'status'}
      className={`flex gap-3 rounded-lg border p-4 text-sm ${gaya.wadah}`}
    >
      <span className="mt-0.5 shrink-0">{gaya.ikon}</span>
      <div className="min-w-0">
        {judul && <p className="font-semibold">{judul}</p>}
        {children && <div className={judul ? 'mt-1' : ''}>{children}</div>}
      </div>
    </div>
  )
}
