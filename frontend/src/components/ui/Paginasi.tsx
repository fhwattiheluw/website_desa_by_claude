import { ChevronLeft, ChevronRight } from 'lucide-react'
import { Tombol } from './Tombol'

export function Paginasi({
  halaman,
  totalHalaman,
  total,
  onPindah,
}: {
  halaman: number
  totalHalaman: number
  total?: number
  onPindah: (halaman: number) => void
}) {
  if (totalHalaman <= 1) return null

  return (
    <nav aria-label="Navigasi halaman" className="flex flex-wrap items-center justify-between gap-3 pt-2">
      <p className="text-sm text-slate-600" aria-live="polite">
        Halaman {halaman} dari {totalHalaman}
        {total !== undefined && ` · ${total} data`}
      </p>
      <div className="flex gap-2">
        <Tombol
          ragam="garis"
          ukuran="kecil"
          disabled={halaman <= 1}
          onClick={() => onPindah(halaman - 1)}
          aria-label="Halaman sebelumnya"
        >
          <ChevronLeft aria-hidden className="size-4" /> Sebelumnya
        </Tombol>
        <Tombol
          ragam="garis"
          ukuran="kecil"
          disabled={halaman >= totalHalaman}
          onClick={() => onPindah(halaman + 1)}
          aria-label="Halaman berikutnya"
        >
          Berikutnya <ChevronRight aria-hidden className="size-4" />
        </Tombol>
      </div>
    </nav>
  )
}
