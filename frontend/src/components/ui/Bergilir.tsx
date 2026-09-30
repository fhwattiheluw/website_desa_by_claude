import { useEffect, useState, type ReactNode } from 'react'
import { ChevronLeft, ChevronRight } from 'lucide-react'

/**
 * Menampilkan satu butir bergilir dari sekumpulan butir (REQ-F-POT-007).
 *
 * Pergiliran otomatis dihentikan bila pengguna meminta gerakan dikurangi, saat
 * penunjuk berada di atasnya, atau saat fokus papan ketik masuk ke dalamnya —
 * isi yang bergeser sendiri saat sedang dibaca membuat bagian ini justru
 * terlewat (REQ-UI-009).
 */
export function Bergilir({
  jumlah,
  judul,
  jeda = 6000,
  children,
}: {
  jumlah: number
  judul: string
  jeda?: number
  children: (indeks: number) => ReactNode
}) {
  const [indeks, setIndeks] = useState(0)
  const [berhenti, setBerhenti] = useState(false)

  useEffect(() => {
    if (jumlah < 2 || berhenti) return

    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return

    const pengatur = window.setInterval(() => setIndeks((kini) => (kini + 1) % jumlah), jeda)

    return () => window.clearInterval(pengatur)
  }, [jumlah, jeda, berhenti])

  if (jumlah === 0) return null

  const geser = (arah: number) => setIndeks((kini) => (kini + arah + jumlah) % jumlah)

  return (
    <div
      onMouseEnter={() => setBerhenti(true)}
      onMouseLeave={() => setBerhenti(false)}
      onFocusCapture={() => setBerhenti(true)}
      onBlurCapture={() => setBerhenti(false)}
    >
      {/* Perubahan diumumkan sopan agar tidak memotong bacaan berjalan. */}
      <div aria-live="polite" aria-atomic>
        {children(indeks)}
      </div>

      {jumlah > 1 && (
        <div className="mt-3 flex items-center justify-center gap-2">
          <button
            type="button"
            onClick={() => geser(-1)}
            aria-label={`${judul} sebelumnya`}
            className="grid size-11 place-items-center rounded-lg border border-slate-300 hover:bg-slate-50"
          >
            <ChevronLeft aria-hidden className="size-4" />
          </button>

          <span className="flex gap-1.5">
            {Array.from({ length: jumlah }, (_, nomor) => (
              <button
                key={nomor}
                type="button"
                onClick={() => setIndeks(nomor)}
                aria-label={`${judul} ke-${nomor + 1} dari ${jumlah}`}
                aria-current={nomor === indeks ? 'true' : undefined}
                className={`size-2.5 rounded-full ${nomor === indeks ? 'bg-desa-700' : 'bg-slate-300'}`}
              />
            ))}
          </span>

          <button
            type="button"
            onClick={() => geser(1)}
            aria-label={`${judul} berikutnya`}
            className="grid size-11 place-items-center rounded-lg border border-slate-300 hover:bg-slate-50"
          >
            <ChevronRight aria-hidden className="size-4" />
          </button>
        </div>
      )}
    </div>
  )
}
