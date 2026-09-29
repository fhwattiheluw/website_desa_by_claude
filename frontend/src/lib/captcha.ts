import { useCallback, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { api } from './api'

export interface TantanganCaptcha {
  aktif: boolean
  metode: 'nihil' | 'bawaan' | 'turnstile'
  token?: string
  pertanyaan?: string
  petunjuk?: string
  kunci_situs?: string
}

export interface KendaliCaptcha {
  tantangan: TantanganCaptcha | null
  jawaban: string
  setJawaban: (nilai: string) => void
  /** Bagian muatan formulir yang dikirim ke server. */
  muatan: () => { captcha_token?: string; captcha_jawaban?: string }
  /** Mengambil tantangan baru; dipakai setelah pengiriman gagal. */
  segarkan: () => void
}

/**
 * Menyiapkan tantangan anti-penyalahgunaan bagi formulir publik
 * (REQ-F-ADU-010). Yang menentukan lolos atau tidak tetap server; kait ini
 * hanya mengangkut tantangan beserta jawabannya.
 */
export function useCaptcha(): KendaliCaptcha {
  const [jawaban, setJawaban] = useState('')

  const { data, refetch } = useQuery({
    queryKey: ['captcha'],
    queryFn: async () => (await api.get<TantanganCaptcha>('/captcha')).data,
    // Tantangan sekali pakai, jadi tidak boleh disinggahkan antar formulir.
    staleTime: 0,
    gcTime: 0,
    retry: false,
  })

  const segarkan = useCallback(() => {
    setJawaban('')
    void refetch()
  }, [refetch])

  const muatan = useCallback(() => {
    if (!data?.aktif) return {}

    return { captcha_token: data.token, captcha_jawaban: jawaban }
  }, [data, jawaban])

  return { tantangan: data ?? null, jawaban, setJawaban, muatan, segarkan }
}
