import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { ambilToken, api, simpanToken } from '@/lib/api'
import { KonteksAutentikasi, type HasilMasuk } from '@/lib/auth'
import type { Pengguna } from '@/types'

interface JawabanMasuk {
  perlu_otp?: boolean
  tantangan?: string
  tujuan?: string
  kedaluwarsa?: string
  token?: string
  pengguna?: Pengguna
}

export function PenyediaAuth({ children }: { children: ReactNode }) {
  const [pengguna, setPengguna] = useState<Pengguna | null>(null)
  // Tanpa token tersimpan, tidak ada sesi yang perlu dipulihkan.
  const [memuat, setMemuat] = useState(() => Boolean(ambilToken()))

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

  // Pemulihan sesi saat aplikasi dibuka: keadaan awal sudah "memuat", sehingga
  // pembaruan keadaan hanya terjadi setelah jawaban server diterima.
  useEffect(() => {
    if (!ambilToken()) return

    let dibatalkan = false

    api
      .get<{ pengguna: Pengguna }>('/auth/saya')
      .then(({ data }) => {
        if (!dibatalkan) setPengguna(data.pengguna)
      })
      .catch(() => {
        simpanToken(null)
        if (!dibatalkan) setPengguna(null)
      })
      .finally(() => {
        if (!dibatalkan) setMemuat(false)
      })

    return () => {
      dibatalkan = true
    }
  }, [])

  const masuk = useCallback(async (email: string, password: string): Promise<HasilMasuk> => {
    const { data, status } = await api.post<JawabanMasuk>('/auth/masuk', { email, password })

    // 202 berarti kata sandi benar tetapi sesi belum terbit: peran ini wajib
    // melewati kode masuk lebih dahulu (REQ-F-USR-009).
    if (status === 202 && data.perlu_otp) {
      return {
        perluOtp: true,
        tantangan: data.tantangan!,
        tujuan: data.tujuan!,
        kedaluwarsa: data.kedaluwarsa!,
      }
    }

    simpanToken(data.token!)
    setPengguna(data.pengguna!)

    return { perluOtp: false, pengguna: data.pengguna! }
  }, [])

  const verifikasiOtp = useCallback(async (tantangan: string, kode: string) => {
    const { data } = await api.post<{ token: string; pengguna: Pengguna }>('/auth/otp', { tantangan, kode })
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
    () => ({ pengguna, memuat, masuk, verifikasiOtp, keluar, segarkan, punyaIzin }),
    [pengguna, memuat, masuk, verifikasiOtp, keluar, segarkan, punyaIzin],
  )

  return <KonteksAutentikasi.Provider value={nilai}>{children}</KonteksAutentikasi.Provider>
}
