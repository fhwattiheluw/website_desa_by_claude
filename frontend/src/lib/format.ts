const BULAN = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]

export function rupiah(nilai: number, ringkas = false): string {
  if (ringkas) {
    if (Math.abs(nilai) >= 1_000_000_000) return `Rp${(nilai / 1_000_000_000).toFixed(1).replace('.', ',')} M`
    if (Math.abs(nilai) >= 1_000_000) return `Rp${(nilai / 1_000_000).toFixed(1).replace('.', ',')} jt`
  }

  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(nilai)
}

export function angka(nilai: number): string {
  return new Intl.NumberFormat('id-ID').format(nilai)
}

/**
 * Membaca komponen waktu persis seperti yang dituliskan server.
 *
 * `new Date(iso).getHours()` menerjemahkan waktu ke zona perangkat pembaca.
 * Untuk portal desa itu keliru: jam musyawarah desa dan jam pelayanan kantor
 * adalah waktu setempat, bukan waktu tempat pembacanya berada. Warga yang
 * sedang merantau harus tetap melihat "09.00", bukan jam di kotanya.
 *
 * Server selalu mengirim waktu pada zona desa (APP_TIMEZONE), sehingga
 * komponennya cukup dibaca apa adanya tanpa penerjemahan.
 */
function bagianWaktu(iso: string) {
  const cocok = /^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/.exec(iso)

  if (!cocok) return null

  return {
    tahun: Number(cocok[1]),
    bulan: Number(cocok[2]) - 1,
    hari: Number(cocok[3]),
    jam: cocok[4] ?? null,
    menit: cocok[5] ?? null,
  }
}

export function tanggal(iso: string | null | undefined, denganWaktu = false): string {
  if (!iso) return '-'

  const bagian = bagianWaktu(iso)
  if (!bagian || Number.isNaN(new Date(iso).getTime())) return '-'

  const dasar = `${bagian.hari} ${BULAN[bagian.bulan]} ${bagian.tahun}`

  return denganWaktu && bagian.jam ? `${dasar}, ${bagian.jam}.${bagian.menit} WIB` : dasar
}

/** Jam dan menit saja, untuk rentang waktu kegiatan pada kalender agenda. */
export function jam(iso: string | null | undefined): string {
  const bagian = iso ? bagianWaktu(iso) : null

  return bagian?.jam ? `${bagian.jam}.${bagian.menit}` : '-'
}

export function tanggalRelatif(iso: string | null | undefined): string {
  if (!iso) return '-'

  const selisih = Date.now() - new Date(iso).getTime()
  const hari = Math.floor(selisih / 86_400_000)

  if (hari === 0) return 'Hari ini'
  if (hari === 1) return 'Kemarin'
  if (hari < 7) return `${hari} hari lalu`
  if (hari < 30) return `${Math.floor(hari / 7)} minggu lalu`

  return tanggal(iso)
}

export function judulKan(teks: string): string {
  return teks
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (huruf) => huruf.toUpperCase())
}

export function ukuranBerkas(bita: number | null | undefined): string {
  if (!bita) return '-'
  if (bita < 1024) return `${bita} B`
  if (bita < 1024 * 1024) return `${(bita / 1024).toFixed(0)} KB`
  return `${(bita / 1024 / 1024).toFixed(1)} MB`
}
