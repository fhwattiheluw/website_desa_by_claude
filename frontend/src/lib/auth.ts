import { createContext, useContext } from 'react'
import type { Pengguna } from '@/types'

/**
 * Hasil langkah kata sandi: sesi langsung terbit, atau tantangan kode masuk
 * bagi peran yang mewajibkan dua faktor (REQ-F-USR-009).
 */
export type HasilMasuk =
  | { perluOtp: false; pengguna: Pengguna }
  | { perluOtp: true; tantangan: string; tujuan: string; kedaluwarsa: string }

export interface KonteksAuth {
  pengguna: Pengguna | null
  memuat: boolean
  masuk: (email: string, password: string) => Promise<HasilMasuk>
  verifikasiOtp: (tantangan: string, kode: string) => Promise<Pengguna>
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
