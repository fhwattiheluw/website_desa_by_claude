import axios, { AxiosError } from 'axios'

const KUNCI_TOKEN = 'sidesa.token'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api/v1',
  headers: { Accept: 'application/json' },
})

export function ambilToken(): string | null {
  try {
    return localStorage.getItem(KUNCI_TOKEN)
  } catch {
    return null
  }
}

export function simpanToken(token: string | null): void {
  try {
    if (token) localStorage.setItem(KUNCI_TOKEN, token)
    else localStorage.removeItem(KUNCI_TOKEN)
  } catch {
    /* penyimpanan peramban dapat diblokir; aplikasi tetap berjalan dalam sesi ini */
  }
}

api.interceptors.request.use((konfigurasi) => {
  const token = ambilToken()
  if (token) konfigurasi.headers.Authorization = `Bearer ${token}`
  return konfigurasi
})

api.interceptors.response.use(
  (respons) => respons,
  (galat: AxiosError) => {
    // Sesi kedaluwarsa: bersihkan token agar pengguna diarahkan untuk masuk kembali.
    if (galat.response?.status === 401) {
      simpanToken(null)
    }
    return Promise.reject(galat)
  },
)

interface GalatApi {
  pesan?: string
  message?: string
  errors?: Record<string, string[]>
}

/** Menerjemahkan galat HTTP menjadi pesan berbahasa Indonesia yang dapat ditindaklanjuti (REQ-UI-006). */
export function pesanGalat(galat: unknown): string {
  if (!axios.isAxiosError(galat)) {
    return 'Terjadi gangguan tak terduga. Silakan muat ulang halaman.'
  }

  const data = galat.response?.data as GalatApi | undefined

  if (data?.errors) {
    const pertama = Object.values(data.errors)[0]
    if (pertama?.[0]) return pertama[0]
  }

  if (data?.pesan) return data.pesan
  if (data?.message && galat.response?.status !== 500) return data.message

  switch (galat.response?.status) {
    case 401:
      return 'Sesi Anda telah berakhir. Silakan masuk kembali.'
    case 403:
      return 'Anda tidak memiliki hak akses untuk tindakan ini.'
    case 404:
      return 'Data yang Anda cari tidak ditemukan.'
    case 429:
      return 'Terlalu banyak permintaan. Mohon tunggu sejenak lalu coba lagi.'
    case 500:
      return 'Terjadi gangguan pada server. Tim teknis telah dicatat untuk menindaklanjuti.'
    default:
      return galat.message === 'Network Error'
        ? 'Tidak dapat terhubung ke server. Periksa koneksi internet Anda.'
        : 'Permintaan gagal diproses. Silakan coba lagi.'
  }
}

/** Mengambil galat validasi per kolom untuk ditampilkan di bawah masing-masing isian. */
export function galatKolom(galat: unknown): Record<string, string> {
  if (!axios.isAxiosError(galat)) return {}

  const errors = (galat.response?.data as GalatApi | undefined)?.errors ?? {}

  return Object.fromEntries(Object.entries(errors).map(([kolom, pesan]) => [kolom, pesan[0]]))
}
