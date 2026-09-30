import { createContext, useContext } from 'react'
import type { Bahasa } from './preferensi'

/**
 * Dwibahasa Indonesia dan Inggris untuk halaman profil desa dan pariwisata
 * (REQ-UI-012).
 *
 * Halaman layanan, pengaduan, dan panel petugas sengaja tetap berbahasa
 * Indonesia: keduanya berkaitan dengan dokumen resmi berbahasa Indonesia,
 * sehingga terjemahan justru berisiko menimbulkan salah tafsir.
 */
export const KAMUS: Record<string, { id: string; en: string }> = {
  'profil.judul': { id: 'Profil Desa', en: 'Village Profile' },
  'profil.judul_desa': { id: 'Profil Desa {nama}', en: '{nama} Village Profile' },
  'profil.sejarah': { id: 'Sejarah Desa', en: 'Village History' },
  'profil.visi': { id: 'Visi', en: 'Vision' },
  'profil.misi': { id: 'Misi', en: 'Mission' },
  'profil.letak': { id: 'Letak dan Wilayah', en: 'Location and Territory' },
  'profil.luas': { id: 'Luas wilayah', en: 'Total area' },
  'profil.pembagian': { id: 'Pembagian wilayah', en: 'Administrative division' },
  'profil.batas': { id: 'Batas', en: 'Border' },
  'profil.utara': { id: 'Utara', en: 'North' },
  'profil.selatan': { id: 'Selatan', en: 'South' },
  'profil.timur': { id: 'Timur', en: 'East' },
  'profil.barat': { id: 'Barat', en: 'West' },
  'profil.struktur': { id: 'Struktur Pemerintah Desa', en: 'Village Government Structure' },
  'profil.struktur.keterangan': {
    id: 'Perangkat desa beserta tugas pokok dan masa jabatan.',
    en: 'Village officials with their main duties and terms of office.',
  },
  'profil.lembaga': { id: 'Lembaga Kemasyarakatan Desa', en: 'Community Institutions' },
  'profil.masa_jabatan': { id: 'Masa jabatan', en: 'Term of office' },
  'profil.peta': { id: 'Peta Wilayah dan Fasilitas Umum', en: 'Area Map and Public Facilities' },
  'profil.dusun': { id: 'dusun', en: 'hamlets' },

  'wisata.judul': { id: 'Wisata Desa', en: 'Village Tourism' },
  'wisata.keterangan': {
    id: 'Destinasi dan daya tarik yang dikelola bersama masyarakat desa.',
    en: 'Destinations and attractions managed together with the village community.',
  },
  'wisata.jam': { id: 'Jam operasional', en: 'Opening hours' },
  'wisata.tarif': { id: 'Tarif', en: 'Entrance fee' },
  'wisata.peta': { id: 'Lihat pada peta', en: 'View on map' },

  'bahasa.label': { id: 'Bahasa', en: 'Language' },
  'bahasa.indonesia': { id: 'Indonesia', en: 'Indonesian' },
  'bahasa.inggris': { id: 'Inggris', en: 'English' },
  'bahasa.catatan': {
    id: 'Halaman ini tersedia dalam dua bahasa.',
    en: 'This page is available in two languages. Service and complaint pages remain in Indonesian because they relate to official documents.',
  },
}

export interface NilaiBahasa {
  bahasa: Bahasa
  ubah: (nilai: Bahasa) => void
  /** Menerjemahkan kunci; parameter mengisi penanda {nama} di dalam teks. */
  t: (kunci: string, parameter?: Record<string, string>) => string
}

export const KonteksBahasa = createContext<NilaiBahasa | null>(null)

export function useBahasa(): NilaiBahasa {
  const konteks = useContext(KonteksBahasa)

  if (!konteks) throw new Error('useBahasa harus dipakai di dalam PenyediaBahasa.')

  return konteks
}
