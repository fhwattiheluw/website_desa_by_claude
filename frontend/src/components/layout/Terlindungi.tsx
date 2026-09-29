import type { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { useAuth } from '@/lib/auth'
import { Pemuat } from '@/components/ui/Status'

/**
 * Penjaga rute di sisi klien. Ini hanya soal pengalaman pengguna — otorisasi
 * yang mengikat tetap diperiksa server pada setiap permintaan (REQ-F-USR-012).
 */
export function Terlindungi({ children, izin }: { children: ReactNode; izin?: string[] }) {
  const { pengguna, memuat, punyaIzin } = useAuth()
  const lokasi = useLocation()

  if (memuat) return <Pemuat label="Memeriksa sesi…" />

  if (!pengguna) {
    return <Navigate to="/masuk" state={{ dari: lokasi.pathname }} replace />
  }

  if (izin && !punyaIzin(...izin)) {
    return <Navigate to="/akun" replace />
  }

  return <>{children}</>
}
