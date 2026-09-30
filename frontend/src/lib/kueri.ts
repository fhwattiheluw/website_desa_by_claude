import { useQuery } from '@tanstack/react-query'
import { api } from './api'
import type { Apbdes, Halaman, Konten, JenisLayanan, PengaturanDesa, Statistik, TipeKonten } from '@/types'

/** Kueri bersama untuk data publik yang jarang berubah. */

export interface FasilitasUmum {
  nama: string
  jenis: string
  alamat: string | null
  keterangan: string | null
  koordinat: { lat: number; lng: number }
}

interface JawabanProfil {
  data: PengaturanDesa
  analitik_aktif: boolean
  fasilitas_umum: FasilitasUmum[]
}

const KUERI_PROFIL = {
  queryKey: ['profil-desa'],
  queryFn: async () => (await api.get<JawabanProfil>('/profil-desa')).data,
  staleTime: 10 * 60 * 1000,
} as const

export function useProfilDesa() {
  return useQuery({ ...KUERI_PROFIL, select: (jawaban: JawabanProfil) => jawaban.data })
}

/** REQ-F-BRD-004: titik fasilitas umum yang ditandai pada peta wilayah. */
export function useFasilitasUmum() {
  return useQuery({ ...KUERI_PROFIL, select: (jawaban: JawabanProfil) => jawaban.fasilitas_umum })
}

/** REQ-SW-006: portal hanya mengirim hitungan kunjungan bila desa menyalakannya. */
export function useAnalitikAktif() {
  return useQuery({ ...KUERI_PROFIL, select: (jawaban: JawabanProfil) => jawaban.analitik_aktif })
}

export interface ButirMenu {
  label: string
  tautan: string | null
  anak: { label: string; tautan: string | null }[]
}

/**
 * Susunan menu bawaan (REQ-F-ADM-003).
 *
 * Dipakai selama menu dari server belum termuat dan bila permintaannya gagal.
 * Navigasi yang lenyap karena satu permintaan gagal membuat seluruh portal
 * tampak rusak, padahal isinya baik-baik saja.
 */
export const MENU_BAWAAN: ButirMenu[] = [
  { label: 'Beranda', tautan: '/', anak: [] },
  { label: 'Profil Desa', tautan: '/profil', anak: [] },
  { label: 'Informasi', tautan: '/berita', anak: [] },
  { label: 'Transparansi', tautan: '/transparansi/apbdes', anak: [] },
  { label: 'Layanan', tautan: '/layanan', anak: [] },
  { label: 'Partisipasi', tautan: '/pengaduan', anak: [] },
  { label: 'Potensi Desa', tautan: '/potensi/umkm', anak: [] },
]

export function useMenuNavigasi() {
  const { data } = useQuery({
    queryKey: ['menu-navigasi'],
    queryFn: async () => (await api.get<{ data: ButirMenu[] }>('/menu')).data.data,
    staleTime: 30 * 60 * 1000,
  })

  return data?.length ? data : MENU_BAWAAN
}

export function useBeranda<T = unknown>() {
  return useQuery<T>({
    queryKey: ['beranda'],
    queryFn: async () => (await api.get<T>('/beranda')).data,
    staleTime: 2 * 60 * 1000,
  })
}

export function useDaftarKonten(tipe: TipeKonten, halaman = 1, kategori?: string, q?: string) {
  return useQuery({
    queryKey: ['konten', tipe, halaman, kategori ?? '', q ?? ''],
    queryFn: async () =>
      (
        await api.get<Halaman<Konten>>(`/konten/${tipe}`, {
          params: { page: halaman, kategori: kategori || undefined, q: q || undefined },
        })
      ).data,
  })
}

export function useKonten(tipe: TipeKonten, slug: string) {
  return useQuery({
    queryKey: ['konten', tipe, slug],
    queryFn: async () => (await api.get<{ data: Konten }>(`/konten/${tipe}/${slug}`)).data.data,
    enabled: Boolean(slug),
  })
}

export function useKategori() {
  return useQuery({
    queryKey: ['kategori'],
    queryFn: async () =>
      (await api.get<{ data: { id: number; nama: string; slug: string; konten_count: number }[] }>('/kategori')).data.data,
    staleTime: 10 * 60 * 1000,
  })
}

export function useLayanan() {
  return useQuery({
    queryKey: ['layanan'],
    queryFn: async () => (await api.get<{ data: JenisLayanan[] }>('/layanan')).data.data,
    staleTime: 10 * 60 * 1000,
  })
}

export function useDetailLayanan(slug: string) {
  return useQuery({
    queryKey: ['layanan', slug],
    queryFn: async () => (await api.get<JenisLayanan>(`/layanan/${slug}`)).data,
    enabled: Boolean(slug),
  })
}

export function useTahunApbdes() {
  return useQuery({
    queryKey: ['apbdes-tahun'],
    queryFn: async () => (await api.get<{ data: { tahun: number }[] }>('/apbdes')).data.data,
  })
}

export function useApbdes(tahun?: number) {
  return useQuery({
    queryKey: ['apbdes', tahun],
    queryFn: async () => (await api.get<Apbdes>(`/apbdes/${tahun}`)).data,
    enabled: Boolean(tahun),
  })
}

export function useStatistik() {
  return useQuery({
    queryKey: ['statistik'],
    queryFn: async () => (await api.get<Statistik>('/statistik')).data,
  })
}
