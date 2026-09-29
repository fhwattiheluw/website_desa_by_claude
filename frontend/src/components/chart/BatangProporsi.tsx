import { angka } from '@/lib/format'
import { SERI } from './warna'

/**
 * Perbandingan dua nilai sebagai satu batang bersegmen — dipakai untuk
 * komposisi sederhana seperti jenis kelamin penduduk. Identitas tidak
 * pernah bergantung pada warna saja: setiap segmen diberi label langsung.
 */
export function BatangProporsi({
  judul,
  butir,
}: {
  judul: string
  butir: { label: string; jumlah: number }[]
}) {
  const total = butir.reduce((jumlah, b) => jumlah + b.jumlah, 0)
  const warna = [SERI.satu, SERI.dua, SERI.tiga]

  if (total === 0) return null

  return (
    <div>
      <h3 className="text-sm font-medium text-slate-700">{judul}</h3>
      <div className="mt-2 flex h-3 gap-0.5 overflow-hidden rounded-full" role="img" aria-label={
        butir.map((b) => `${b.label} ${angka(b.jumlah)} jiwa`).join(', ')
      }>
        {butir.map((b, indeks) => (
          <span
            key={b.label}
            style={{ width: `${(b.jumlah / total) * 100}%`, backgroundColor: warna[indeks % warna.length] }}
          />
        ))}
      </div>
      <ul className="mt-3 flex flex-wrap gap-x-6 gap-y-2">
        {butir.map((b, indeks) => (
          <li key={b.label} className="flex items-center gap-2 text-sm">
            <span
              aria-hidden
              className="size-2.5 shrink-0 rounded-full"
              style={{ backgroundColor: warna[indeks % warna.length] }}
            />
            <span className="text-slate-700">{b.label}</span>
            <span className="font-medium tabular-nums text-slate-900">{angka(b.jumlah)}</span>
            <span className="text-slate-500">({Math.round((b.jumlah / total) * 100)}%)</span>
          </li>
        ))}
      </ul>
    </div>
  )
}
