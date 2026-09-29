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

export function tanggal(iso: string | null | undefined, denganWaktu = false): string {
  if (!iso) return '-'

  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return '-'

  const dasar = `${d.getDate()} ${BULAN[d.getMonth()]} ${d.getFullYear()}`

  return denganWaktu
    ? `${dasar}, ${String(d.getHours()).padStart(2, '0')}.${String(d.getMinutes()).padStart(2, '0')} WIB`
    : dasar
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
