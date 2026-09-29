import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import { api, ambilToken, simpanToken } from './api'
import type { Pengguna } from '@/types'

interface KonteksAuth {
  pengguna: Pengguna | null
  memuat: boolean
  masuk: (email: string, password: string) => Promise<Pengguna>
  keluar: () => Promise<void>
  segarkan: () => Promise<void>
  punyaIzin: (...izin: string[]) => boolean
}

const Konteks = createContext<KonteksAuth | null>(null)

export function PenyediaAuth({ children }: { children: ReactNode }) {
  const [pengguna, setPengguna] = useState<Pengguna | null>(null)
  const [memuat, setMemuat] = useState(true)

  const segarkan = useCallback(async () => {
    if (!ambilToken()) {
      setPengguna(null)
      setMemuat(false)
      return
    }

    try {
      const { data } = await api.get<{ pengguna: Pengguna }>('/auth/saya')
      setPengguna(data.pengguna)
    } catch {
      simpanToken(null)
      setPengguna(null)
    } finally {
      setMemuat(false)
    }
  }, [])

  useEffect(() => {
    void segarkan()
  }, [segarkan])

  const masuk = useCallback(async (email: string, password: string) => {
    const { data } = await api.post<{ token: string; pengguna: Pengguna }>('/auth/masuk', { email, password })
    simpanToken(data.token)
    setPengguna(data.pengguna)
    return data.pengguna
  }, [])

  const keluar = useCallback(async () => {
    try {
      await api.post('/auth/keluar')
    } catch {
      /* token mungkin sudah tidak berlaku; pembersihan lokal tetap dilakukan */
    } finally {
      simpanToken(null)
      setPengguna(null)
    }
  }, [])

  // Hak akses di sisi klien hanya untuk menyesuaikan tampilan; penegakan
  // sesungguhnya tetap di server (REQ-F-USR-012).
  const punyaIzin = useCallback(
    (...izin: string[]) => izin.some((kode) => pengguna?.izin.includes(kode) ?? false),
    [pengguna],
  )

  const nilai = useMemo(
    () => ({ pengguna, memuat, masuk, keluar, segarkan, punyaIzin }),
    [pengguna, memuat, masuk, keluar, segarkan, punyaIzin],
  )

  return <Konteks.Provider value={nilai}>{children}</Konteks.Provider>
}

export function useAuth(): KonteksAuth {
  const konteks = useContext(Konteks)

  if (!konteks) throw new Error('useAuth harus dipakai di dalam PenyediaAuth.')

  return konteks
}
