import { User } from 'lucide-react'

export interface SimpulBagan {
  id: number
  nama: string
  jabatan: string
  wilayah: string | null
  masa_jabatan: string | null
  tugas_pokok: string | null
  foto: string | null
  bawahan: SimpulBagan[]
}

function Kotak({ orang }: { orang: SimpulBagan }) {
  return (
    <div className="inline-flex min-w-48 max-w-64 flex-col items-center gap-2 rounded-xl border border-slate-200 bg-white p-4 text-center shadow-sm">
      {orang.foto ? (
        <img src={orang.foto} alt={`Foto ${orang.nama}`} loading="lazy" className="size-16 rounded-full object-cover" />
      ) : (
        <span className="flex size-16 items-center justify-center rounded-full bg-slate-100" aria-hidden>
          <User className="size-7 text-slate-400" />
        </span>
      )}
      <span>
        <span className="block text-sm font-semibold text-slate-900">{orang.nama}</span>
        <span className="block text-sm text-desa-700">{orang.jabatan}</span>
        {orang.wilayah && <span className="block text-xs text-slate-500">{orang.wilayah}</span>}
        {orang.masa_jabatan && <span className="block text-xs text-slate-500">Masa jabatan {orang.masa_jabatan}</span>}
      </span>
    </div>
  )
}

function Cabang({ orang, akar = false }: { orang: SimpulBagan; akar?: boolean }) {
  return (
    <li className={akar ? 'bagan-akar' : undefined}>
      <Kotak orang={orang} />

      {orang.bawahan.length > 0 && (
        <ul className="bagan-anak">
          {orang.bawahan.map((anak) => (
            <Cabang key={anak.id} orang={anak} />
          ))}
        </ul>
      )}
    </li>
  )
}

/**
 * Bagan struktur organisasi dengan foto, nama, jabatan, dan masa jabatan
 * (REQ-F-BRD-003).
 *
 * Disusun sebagai daftar bersarang, bukan tabel atau gambar, sehingga urutan
 * atasan–bawahan tersampaikan apa adanya kepada pembaca layar. Garis
 * penghubungnya ada pada kelas `.bagan` di `index.css`.
 */
export function BaganOrganisasi({ bagan, judul }: { bagan: SimpulBagan[]; judul: string }) {
  if (bagan.length === 0) return null

  return (
    <div className="bagan overflow-x-auto pb-2">
      <ul className="md:flex-row md:items-start md:justify-center md:gap-6" aria-label={judul}>
        {bagan.map((akar) => (
          <Cabang key={akar.id} orang={akar} akar />
        ))}
      </ul>
    </div>
  )
}
