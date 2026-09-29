import { useEffect, useRef, type ReactNode } from 'react'
import { X } from 'lucide-react'

/**
 * Dialog konfirmasi untuk aksi penting seperti penolakan atau pembatalan
 * (REQ-UI-008). Fokus dipindahkan ke dalam dialog dan tombol Esc menutupnya.
 */
export function Dialog({
  terbuka,
  judul,
  deskripsi,
  onTutup,
  children,
  aksi,
}: {
  terbuka: boolean
  judul: string
  deskripsi?: string
  onTutup: () => void
  children?: ReactNode
  aksi?: ReactNode
}) {
  const wadah = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!terbuka) return

    const sebelumnya = document.activeElement as HTMLElement | null
    wadah.current?.querySelector<HTMLElement>('input, textarea, select, button')?.focus()

    const padaTombol = (peristiwa: KeyboardEvent) => {
      if (peristiwa.key === 'Escape') onTutup()
    }

    document.addEventListener('keydown', padaTombol)
    document.body.style.overflow = 'hidden'

    return () => {
      document.removeEventListener('keydown', padaTombol)
      document.body.style.overflow = ''
      sebelumnya?.focus()
    }
  }, [terbuka, onTutup])

  if (!terbuka) return null

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center">
      <div
        ref={wadah}
        role="dialog"
        aria-modal="true"
        aria-labelledby="judul-dialog"
        className="w-full max-w-lg rounded-xl bg-white shadow-xl"
      >
        <div className="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
          <div>
            <h2 id="judul-dialog" className="text-base font-semibold">
              {judul}
            </h2>
            {deskripsi && <p className="mt-0.5 text-sm text-slate-600">{deskripsi}</p>}
          </div>
          <button
            type="button"
            onClick={onTutup}
            aria-label="Tutup dialog"
            className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100"
          >
            <X aria-hidden className="size-5" />
          </button>
        </div>
        <div className="px-5 py-4">{children}</div>
        {aksi && <div className="flex flex-wrap justify-end gap-2 border-t border-slate-200 px-5 py-4">{aksi}</div>}
      </div>
    </div>
  )
}
