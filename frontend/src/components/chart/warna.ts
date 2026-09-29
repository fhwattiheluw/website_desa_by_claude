/**
 * Palet grafik portal desa.
 *
 * Divalidasi dengan pemeriksaan keterbacaan warna: pemisahan buta warna
 * ΔE 9,2 (ambang 8) dan pemisahan penglihatan normal ΔE 24,0 (ambang 15)
 * pada seluruh pasangan, di atas latar putih kartu.
 *
 * Slot diberikan berurutan dan tidak pernah diputar ulang.
 */
export const SERI = {
  satu: '#2a78d6', // biru — dipakai untuk pagu/anggaran
  dua: '#eb6834', // oranye — dipakai untuk realisasi
  tiga: '#1baf7a', // aqua — hanya dengan label langsung (kontras 2,8:1)
} as const

/** Warna tunggal untuk grafik seri tunggal (magnitudo). */
export const SEKUENSIAL = '#2a78d6'

export const TEKS = {
  utama: '#0f172a',
  sekunder: '#475569',
  samar: '#94a3b8',
}

export const GARIS_BANTU = '#e2e8f0'
