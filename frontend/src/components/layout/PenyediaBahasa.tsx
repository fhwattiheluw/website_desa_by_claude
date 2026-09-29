import { useCallback, useMemo, useState, type ReactNode } from 'react'
import { KAMUS, KonteksBahasa } from '@/lib/bahasa'
import { ambilBahasa, simpanBahasa, type Bahasa } from '@/lib/preferensi'

export function PenyediaBahasa({ children }: { children: ReactNode }) {
  const [bahasa, setBahasa] = useState<Bahasa>(() => ambilBahasa())

  const ubah = useCallback((nilai: Bahasa) => {
    simpanBahasa(nilai)
    setBahasa(nilai)
  }, [])

  const t = useCallback(
    (kunci: string, parameter?: Record<string, string>) => {
      const teks = KAMUS[kunci]?.[bahasa] ?? kunci

      return parameter
        ? Object.entries(parameter).reduce((hasil, [nama, nilai]) => hasil.replaceAll(`{${nama}}`, nilai), teks)
        : teks
    },
    [bahasa],
  )

  const nilai = useMemo(() => ({ bahasa, ubah, t }), [bahasa, ubah, t])

  return <KonteksBahasa.Provider value={nilai}>{children}</KonteksBahasa.Provider>
}
