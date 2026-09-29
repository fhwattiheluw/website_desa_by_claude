/**
 * Preferensi tampilan yang disimpan di peramban masing-masing pengguna
 * (REQ-UI-011, REQ-UI-012).
 *
 * Nilai disimpan pada localStorage sebagai kemudahan per perangkat. Bila
 * penyimpanan diblokir, aplikasi tetap berjalan dengan nilai bawaan.
 */

export type UkuranTeks = 'normal' | 'besar'
export type Bahasa = 'id' | 'en'

const KUNCI = {
  ukuran: 'sidesa.ukuran-teks',
  kontras: 'sidesa.kontras-tinggi',
  bahasa: 'sidesa.bahasa',
} as const

function baca(kunci: string): string | null {
  try {
    return localStorage.getItem(kunci)
  } catch {
    return null
  }
}

function tulis(kunci: string, nilai: string): void {
  try {
    localStorage.setItem(kunci, nilai)
  } catch {
    /* penyimpanan peramban dapat diblokir; preferensi berlaku untuk sesi ini saja */
  }
}

export function ambilUkuranTeks(): UkuranTeks {
  return baca(KUNCI.ukuran) === 'besar' ? 'besar' : 'normal'
}

export function simpanUkuranTeks(nilai: UkuranTeks): void {
  tulis(KUNCI.ukuran, nilai)
  terapkanUkuranTeks(nilai)
}

export function terapkanUkuranTeks(nilai: UkuranTeks): void {
  document.documentElement.dataset.ukuranTeks = nilai
}

export function ambilKontrasTinggi(): boolean {
  return baca(KUNCI.kontras) === '1'
}

export function simpanKontrasTinggi(aktif: boolean): void {
  tulis(KUNCI.kontras, aktif ? '1' : '0')
  terapkanKontrasTinggi(aktif)
}

export function terapkanKontrasTinggi(aktif: boolean): void {
  document.documentElement.dataset.kontras = aktif ? 'tinggi' : 'normal'
}

export function ambilBahasa(): Bahasa {
  return baca(KUNCI.bahasa) === 'en' ? 'en' : 'id'
}

export function simpanBahasa(nilai: Bahasa): void {
  tulis(KUNCI.bahasa, nilai)
  document.documentElement.lang = nilai
}

/** Dipanggil sekali saat aplikasi dimuat agar tampilan langsung sesuai preferensi. */
export function terapkanPreferensiAwal(): void {
  terapkanUkuranTeks(ambilUkuranTeks())
  terapkanKontrasTinggi(ambilKontrasTinggi())
  document.documentElement.lang = ambilBahasa()
}
