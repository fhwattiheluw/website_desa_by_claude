/** Tipe data bersama yang mencerminkan kontrak API SIDESA. */

export type Peran = 'warga' | 'operator' | 'verifikator' | 'sekdes' | 'kades' | 'admin'

export interface Pengguna {
  id: number
  nama: string
  email: string
  nik_tersamar: string | null
  telepon: string | null
  alamat: string | null
  tempat_lahir: string | null
  tanggal_lahir: string | null
  jenis_kelamin: 'L' | 'P' | null
  pekerjaan: string | null
  status_akun: 'belum_verifikasi' | 'aktif' | 'nonaktif'
  nik_terverifikasi: boolean
  boleh_mengajukan: boolean
  peran: Peran | null
  peran_nama: string | null
  petugas: boolean
  izin: string[]
}

export type TipeKonten = 'berita' | 'artikel' | 'pengumuman' | 'agenda'

export interface Konten {
  id: number
  tipe: TipeKonten
  judul: string
  slug: string
  ringkasan: string | null
  isi?: string | null
  status: string
  sorotan: boolean
  dibaca: number
  tag: string[]
  gambar?: { url: string | null; alt: string | null } | null
  kategori?: { nama: string; slug: string } | null
  penulis?: string | null
  terbit_pada: string | null
  kedaluwarsa_pada: string | null
  mulai_pada: string | null
  selesai_pada: string | null
  lokasi: string | null
  penyelenggara: string | null
}

export interface KolomFormulir {
  nama: string
  label: string
  tipe: 'teks' | 'teks_panjang' | 'nik' | 'kk' | 'angka' | 'tanggal' | 'pilihan' | 'telepon' | 'centang'
  wajib?: boolean
  pilihan?: string[]
  di_surat?: boolean
}

export interface JenisLayanan {
  kode: string
  nama: string
  slug: string
  deskripsi: string | null
  persyaratan: string[]
  kolom_formulir?: KolomFormulir[]
  sla_hari_kerja: number
  biaya: number
}

export type StatusPermohonan =
  | 'draf' | 'diajukan' | 'diverifikasi' | 'disetujui'
  | 'ditandatangani' | 'selesai' | 'dikembalikan' | 'ditolak'

export interface Permohonan {
  id: number
  nomor_tiket: string
  status: StatusPermohonan
  kanal: 'daring' | 'loket'
  alasan: string | null
  melampaui_sla: boolean
  tenggat_sla: string | null
  diajukan_pada: string | null
  selesai_pada: string | null
  kepuasan: number | null
  layanan?: JenisLayanan
  data_formulir: Record<string, string | number | boolean>
  pemohon?: { nama: string; nik_tersamar: string | null; telepon: string | null }
  lampiran?: { id: number; label: string | null; nama: string | null; ukuran: number | null }[]
  riwayat?: { dari: string | null; ke: string; catatan: string | null; aktor: string | null; waktu: string }[]
  surat?: {
    nomor_surat: string
    tanggal_terbit: string
    kode_verifikasi: string
    status_keabsahan: 'sah' | 'dibatalkan'
  } | null
}

export type StatusPengaduan = 'baru' | 'diverifikasi' | 'didisposisi' | 'proses' | 'selesai' | 'ditolak'

export interface Pengaduan {
  id: number
  nomor_tiket: string
  kode_lacak?: string
  kategori: string
  judul: string
  uraian: string
  lokasi: string | null
  tanggal_kejadian: string | null
  status: StatusPengaduan
  tenggat_tanggapan: string | null
  melampaui_sla: boolean
  tampil_publik: boolean
  pelapor: string
  kontak_pelapor?: string
  didisposisi_ke?: string | null
  dibuat_pada: string
  tanggapan?: { isi: string; internal: boolean; oleh: string; waktu: string }[]
}

export interface ItemApbdes {
  jenis: 'pendapatan' | 'belanja' | 'pembiayaan'
  bidang: string
  kegiatan: string | null
  pagu: number
  realisasi: number
  penyerapan: number
}

export interface Apbdes {
  tahun: number
  diperbarui_pada: string | null
  dipublikasikan_pada: string | null
  ringkasan: Record<'pendapatan' | 'belanja' | 'pembiayaan', { pagu: number; realisasi: number }>
  item: ItemApbdes[]
  dokumen: { judul: string; jenis: string; url: string | null }[]
}

export interface ItemStatistik {
  label: string
  jumlah: number | null
  disamarkan: boolean
}

export interface PembandingStatistik {
  periode: { id: number; nama: string; tahun: number }
  total_penduduk: number
  selisih_total: number
  per_kelompok: { kelompok: string; label: string; kini: number; sebelumnya: number; selisih: number }[]
  catatan: string
}

export interface Statistik {
  periode: { id: number; nama: string; tahun: number; sumber_data: string | null }
  pembanding: PembandingStatistik | null
  total_penduduk: number
  total_kk: number
  kelompok: Record<string, ItemStatistik[]>
  catatan_privasi: string
}

export interface Halaman<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export type PengaturanDesa = Record<string, string>
