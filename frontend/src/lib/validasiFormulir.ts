import type { KolomFormulir } from '@/types'

/**
 * Pemeriksaan isian di sisi peramban untuk validasi per langkah (REQ-UI-007).
 *
 * Aturan di sini sengaja mencerminkan aturan server, bukan menggantikannya:
 * server tetap satu-satunya yang menentukan permohonan diterima. Gunanya
 * hanya agar warga tahu ada yang keliru sebelum berpindah langkah, alih-alih
 * baru mengetahuinya setelah seluruh formulir dikirim.
 */
const PESAN_WAJIB = 'Kolom ini wajib diisi.'

const POLA_TELEPON = /^(\+62|62|0)8[1-9][0-9]{6,11}$/

export function periksaKolom(kolom: KolomFormulir, isi: string | boolean): string | null {
  if (kolom.tipe === 'centang') {
    return kolom.wajib && isi !== true ? 'Pernyataan ini harus dicentang.' : null
  }

  const teks = String(isi ?? '').trim()

  if (teks === '') {
    return kolom.wajib ? PESAN_WAJIB : null
  }

  switch (kolom.tipe) {
    case 'nik':
    case 'kk':
      return /^\d{16}$/.test(teks) ? null : 'Harus 16 digit angka tanpa spasi.'
    case 'telepon':
      return POLA_TELEPON.test(teks) ? null : 'Nomor tidak dikenali. Contoh: 081234567890.'
    case 'angka':
      return /^-?\d+([.,]\d+)?$/.test(teks) ? null : 'Isi dengan angka.'
    case 'tanggal':
      return Number.isNaN(new Date(teks).getTime()) ? 'Tanggal tidak dikenali.' : null
    default:
      return null
  }
}

/**
 * @param kolom kolom yang berada pada langkah yang sedang diperiksa
 * @returns galat per nama kolom; kosong bila seluruhnya lolos
 */
export function periksaLangkah(
  kolom: KolomFormulir[],
  nilai: (kolom: KolomFormulir) => string | boolean,
): Record<string, string> {
  const galat: Record<string, string> = {}

  for (const satu of kolom) {
    const pesan = periksaKolom(satu, nilai(satu))
    if (pesan) galat[satu.nama] = pesan
  }

  return galat
}
