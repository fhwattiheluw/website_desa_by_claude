import { useEffect, useRef, useState } from 'react'
import { Check, Settings2, X } from 'lucide-react'
import {
  ambilKontrasTinggi, ambilUkuranTeks, simpanKontrasTinggi, simpanUkuranTeks, type UkuranTeks,
} from '@/lib/preferensi'
import { useBahasa } from '@/lib/bahasa'
import type { Bahasa } from '@/lib/preferensi'

/**
 * Pengaturan keterbacaan: ukuran teks, kontras tinggi, dan pilihan bahasa
 * (REQ-UI-011, REQ-UI-012).
 */
export function PengaturanTampilan() {
  const { bahasa, ubah: ubahBahasa, t } = useBahasa()
  const [terbuka, setTerbuka] = useState(false)
  const [ukuran, setUkuran] = useState<UkuranTeks>(() => ambilUkuranTeks())
  const [kontras, setKontras] = useState(() => ambilKontrasTinggi())
  const wadah = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!terbuka) return

    const padaKlik = (peristiwa: MouseEvent) => {
      if (!wadah.current?.contains(peristiwa.target as Node)) setTerbuka(false)
    }
    const padaTombol = (peristiwa: KeyboardEvent) => {
      if (peristiwa.key === 'Escape') setTerbuka(false)
    }

    document.addEventListener('mousedown', padaKlik)
    document.addEventListener('keydown', padaTombol)

    return () => {
      document.removeEventListener('mousedown', padaKlik)
      document.removeEventListener('keydown', padaTombol)
    }
  }, [terbuka])

  const pilihUkuran = (nilai: UkuranTeks) => {
    simpanUkuranTeks(nilai)
    setUkuran(nilai)
  }

  const pilihKontras = (nilai: boolean) => {
    simpanKontrasTinggi(nilai)
    setKontras(nilai)
  }

  return (
    <div ref={wadah} className="relative">
      <button
        type="button"
        onClick={() => setTerbuka((buka) => !buka)}
        aria-expanded={terbuka}
        aria-haspopup="dialog"
        className="grid size-11 place-items-center rounded-lg text-slate-700 hover:bg-slate-100"
        title="Pengaturan tampilan"
      >
        <span className="sr-only">Pengaturan tampilan dan bahasa</span>
        {terbuka ? <X aria-hidden className="size-5" /> : <Settings2 aria-hidden className="size-5" />}
      </button>

      {terbuka && (
        <div
          role="dialog"
          aria-label="Pengaturan tampilan dan bahasa"
          className="absolute right-0 top-full z-50 mt-2 w-72 rounded-xl border border-slate-200 bg-white p-4 shadow-lg"
        >
          <fieldset className="mb-4">
            <legend className="mb-2 text-sm font-medium text-slate-800">Ukuran teks</legend>
            <div className="flex gap-2">
              {([
                ['normal', 'Normal'],
                ['besar', 'Besar'],
              ] as [UkuranTeks, string][]).map(([nilai, teks]) => (
                <button
                  key={nilai}
                  type="button"
                  onClick={() => pilihUkuran(nilai)}
                  aria-pressed={ukuran === nilai}
                  className={`inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-lg border px-3 text-sm ${
                    ukuran === nilai ? 'border-desa-600 bg-desa-50 text-desa-800' : 'border-slate-300 text-slate-700'
                  }`}
                >
                  {ukuran === nilai && <Check aria-hidden className="size-4" />}
                  {teks}
                </button>
              ))}
            </div>
          </fieldset>

          <fieldset className="mb-4">
            <legend className="mb-2 text-sm font-medium text-slate-800">Kontras</legend>
            <div className="flex gap-2">
              {([
                [false, 'Normal'],
                [true, 'Tinggi'],
              ] as [boolean, string][]).map(([nilai, teks]) => (
                <button
                  key={teks}
                  type="button"
                  onClick={() => pilihKontras(nilai)}
                  aria-pressed={kontras === nilai}
                  className={`inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-lg border px-3 text-sm ${
                    kontras === nilai ? 'border-desa-600 bg-desa-50 text-desa-800' : 'border-slate-300 text-slate-700'
                  }`}
                >
                  {kontras === nilai && <Check aria-hidden className="size-4" />}
                  {teks}
                </button>
              ))}
            </div>
          </fieldset>

          <fieldset>
            <legend className="mb-2 text-sm font-medium text-slate-800">{t('bahasa.label')}</legend>
            <div className="flex gap-2">
              {([
                ['id', t('bahasa.indonesia')],
                ['en', t('bahasa.inggris')],
              ] as [Bahasa, string][]).map(([nilai, teks]) => (
                <button
                  key={nilai}
                  type="button"
                  onClick={() => ubahBahasa(nilai)}
                  aria-pressed={bahasa === nilai}
                  className={`inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-lg border px-3 text-sm ${
                    bahasa === nilai ? 'border-desa-600 bg-desa-50 text-desa-800' : 'border-slate-300 text-slate-700'
                  }`}
                >
                  {bahasa === nilai && <Check aria-hidden className="size-4" />}
                  {teks}
                </button>
              ))}
            </div>
            <p className="mt-2 text-xs text-slate-500">
              Terjemahan tersedia pada halaman profil desa dan wisata. Halaman layanan tetap berbahasa Indonesia karena
              berkaitan dengan dokumen resmi.
            </p>
          </fieldset>
        </div>
      )}
    </div>
  )
}
