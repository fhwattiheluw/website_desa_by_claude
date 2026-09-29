import { useEffect } from 'react'
import { useLocation } from 'react-router-dom'
import { api } from './api'
import { useAnalitikAktif } from './kueri'

/**
 * Mengirim hitungan kunjungan laman (REQ-SW-006).
 *
 * Yang dikirim hanya jalur laman. Tidak ada kuki, pengenal pengunjung, maupun
 * perujuk, dan server menyimpannya sebagai angka agregat per hari. Kegagalan
 * pengiriman diabaikan: pencatatan statistik tidak boleh mengganggu pemakaian
 * portal.
 */
export function useCatatKunjungan() {
  const { pathname } = useLocation()
  const { data: aktif } = useAnalitikAktif()

  useEffect(() => {
    if (!aktif) return

    void api.post('/kunjungan', { jalur: pathname }).catch(() => undefined)
  }, [aktif, pathname])
}
