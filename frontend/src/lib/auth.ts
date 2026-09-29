import { createContext, useContext } from 'react'
import type { Pengguna } from '@/types'

export interface KonteksAuth {
  pengguna: Pengguna | null
  memuat: boolean
  masuk: (email: string, password: string) => Promise<Pengguna>
  keluar: () => Promise<void>
  segarkan: () => Promise<void>
  punyaIzin: (...izin: string[]) => boolean
}

export const KonteksAutentikasi = createContext<KonteksAuth | null>(null)

export function useAuth(): KonteksAuth {
  const konteks = useContext(KonteksAutentikasi)

  if (!konteks) throw new Error('useAuth harus dipakai di dalam PenyediaAuth.')

  return konteks
}
