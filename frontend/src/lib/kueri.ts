import { useQuery } from '@tanstack/react-query'
import { api } from './api'
import type { Apbdes, Halaman, Konten, JenisLayanan, PengaturanDesa, Statistik, TipeKonten } from '@/types'

/** Kueri bersama untuk data publik yang jarang berubah. */

export function useProfilDesa() {
  return useQuery({
    queryKey: ['profil-desa'],
    queryFn: async () => (await api.get<{ data: PengaturanDesa }>('/profil-desa')).data.data,
    staleTime: 10 * 60 * 1000,
  })
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
