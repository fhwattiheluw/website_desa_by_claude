import { Check } from 'lucide-react'

/**
 * Indikator kemajuan pengisian formulir bertahap (REQ-UI-007).
 *
 * Ditulis sebagai daftar berurut dengan `aria-current="step"`, sehingga
 * pembaca layar mengumumkan "langkah 2 dari 4" tanpa perlu teks tambahan.
 * Pada layar sempit hanya langkah berjalan yang dinamai; menampilkan seluruh
 * nama langkah di sana memaksa teks mengecil sampai sulit dibaca (CON-06).
 */
export function Langkah({
  langkah,
  aktif,
  onPindah,
}: {
  langkah: string[]
  aktif: number
  /** Diberikan bila pengguna boleh melompat mundur ke langkah yang sudah dilewati. */
  onPindah?: (indeks: number) => void
}) {
  return (
    <nav aria-label="Kemajuan pengisian">
      <p className="mb-2 text-sm font-medium text-slate-700 sm:hidden">
        Langkah {aktif + 1} dari {langkah.length}: {langkah[aktif]}
      </p>

      <ol className="flex items-center gap-1">
        {langkah.map((nama, indeks) => {
          const selesai = indeks < aktif
          const sekarang = indeks === aktif
          const dapatDilompati = selesai && onPindah

          return (
            <li key={nama} className="flex min-w-0 flex-1 items-center gap-2">
              <span className="flex min-w-0 flex-1 flex-col gap-1.5">
                <span
                  aria-hidden
                  className={`h-1 rounded-full ${selesai || sekarang ? 'bg-desa-700' : 'bg-slate-200'}`}
                />
                <span className="hidden min-w-0 items-center gap-1.5 sm:flex">
                  <span
                    aria-hidden
                    className={`grid size-5 shrink-0 place-items-center rounded-full text-[11px] font-semibold ${
                      selesai
                        ? 'bg-desa-700 text-white'
                        : sekarang
                          ? 'bg-desa-100 text-desa-800 ring-1 ring-desa-700'
                          : 'bg-slate-200 text-slate-600'
                    }`}
                  >
                    {selesai ? <Check className="size-3" /> : indeks + 1}
                  </span>

                  {dapatDilompati ? (
                    <button
                      type="button"
                      onClick={() => onPindah(indeks)}
                      className="min-w-0 truncate text-left text-xs text-slate-600 underline underline-offset-2 hover:text-desa-700"
                    >
                      {nama}
                    </button>
                  ) : (
                    <span
                      aria-current={sekarang ? 'step' : undefined}
                      className={`min-w-0 truncate text-xs ${sekarang ? 'font-medium text-slate-900' : 'text-slate-500'}`}
                    >
                      {nama}
                    </span>
                  )}
                </span>
              </span>
            </li>
          )
        })}
      </ol>
    </nav>
  )
}
