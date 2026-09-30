import { useEffect } from 'react'

interface Meta {
  judul: string
  deskripsi?: string
  gambar?: string | null
  jenis?: 'website' | 'article'
}

function setelTag(selektor: string, buat: () => HTMLElement, atribut: string, nilai: string) {
  let elemen = document.head.querySelector(selektor)

  if (!elemen) {
    elemen = buat()
    document.head.appendChild(elemen)
  }

  elemen.setAttribute(atribut, nilai)
}

/**
 * Menetapkan judul unik, deskripsi meta, URL kanonik, dan metadata Open Graph
 * untuk setiap halaman (REQ-F-SRC-004).
 *
 * Metadata yang sama juga sudah disisipkan server pada kerangka halaman
 * (`KerangkaAplikasiController`), sehingga perayap yang tidak menjalankan
 * JavaScript tetap memperolehnya. Kait ini memperbarui tag yang sudah ada —
 * bukan menambah tag baru — ketika pengguna berpindah halaman tanpa memuat
 * ulang.
 */
export function useMeta({ judul, deskripsi, gambar, jenis = 'website' }: Meta) {
  useEffect(() => {
    /*
     * Nama situs diambil dari tag yang sudah disisipkan server, sehingga judul
     * yang dibentuk di peramban memakai nama desa yang sama persis dengan yang
     * dilihat perayap. Nilai cadangan dipakai bila kerangka dilayani tanpa
     * penyisipan, misalnya saat pengembangan dengan server Vite.
     */
    const namaSitus =
      document.head.querySelector('meta[property="og:site_name"]')?.getAttribute('content') || 'Portal Desa'
    const judulLengkap = judul.includes(namaSitus) ? judul : `${judul} — ${namaSitus}`

    document.title = judulLengkap

    if (deskripsi) {
      setelTag(
        'meta[name="description"]',
        () => Object.assign(document.createElement('meta'), { name: 'description' }),
        'content',
        deskripsi,
      )
    }

    setelTag(
      'link[rel="canonical"]',
      () => Object.assign(document.createElement('link'), { rel: 'canonical' }),
      'href',
      window.location.origin + window.location.pathname,
    )

    const og: [string, string][] = [
      ['og:title', judulLengkap],
      ['og:type', jenis],
      ['og:url', window.location.origin + window.location.pathname],
      ['og:site_name', namaSitus],
      ['og:locale', 'id_ID'],
    ]

    if (deskripsi) og.push(['og:description', deskripsi])
    if (gambar) og.push(['og:image', gambar])

    for (const [properti, nilai] of og) {
      setelTag(
        `meta[property="${properti}"]`,
        () => {
          const elemen = document.createElement('meta')
          elemen.setAttribute('property', properti)

          return elemen
        },
        'content',
        nilai,
      )
    }

    setelTag(
      'meta[name="twitter:card"]',
      () => Object.assign(document.createElement('meta'), { name: 'twitter:card' }),
      'content',
      gambar ? 'summary_large_image' : 'summary',
    )
  }, [judul, deskripsi, gambar, jenis])
}

/**
 * Menyisipkan data terstruktur schema.org agar mesin pencari memahami jenis
 * halaman: lembaga pemerintah, berita, agenda kegiatan, dan usaha lokal
 * (REQ-F-SRC-005).
 */
export function useDataTerstruktur(data: Record<string, unknown> | null) {
  useEffect(() => {
    if (!data) return

    // Blok yang disisipkan server untuk halaman ini disingkirkan lebih dahulu,
    // agar halaman tidak memuat dua data terstruktur sekaligus.
    document.head.querySelector('script[data-sidesa="terstruktur-server"]')?.remove()

    const skrip = document.createElement('script')
    skrip.type = 'application/ld+json'
    skrip.dataset.sidesa = 'terstruktur'
    skrip.textContent = JSON.stringify({ '@context': 'https://schema.org', ...data })

    document.head.appendChild(skrip)

    return () => skrip.remove()
  }, [data])
}
