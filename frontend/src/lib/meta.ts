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
 * Catatan: aplikasi ini dirender di peramban, sehingga metadata terbentuk
 * setelah skrip dijalankan. Mesin pencari yang mengeksekusi JavaScript membaca
 * nilai ini dengan benar; perayap yang tidak menjalankan JavaScript hanya
 * memperoleh metadata bawaan pada index.html. Bila pengindeksan penuh
 * diperlukan, prarender atau render sisi server dijadwalkan pada Fase 4.
 */
export function useMeta({ judul, deskripsi, gambar, jenis = 'website' }: Meta) {
  useEffect(() => {
    const namaSitus = 'Portal Desa'
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
