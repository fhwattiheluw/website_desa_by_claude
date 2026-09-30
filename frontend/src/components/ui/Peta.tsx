import { useEffect, useRef, useState } from 'react'
import { MapPin } from 'lucide-react'

export interface TitikPeta {
  nama: string
  keterangan?: string | null
  lat: number
  lng: number
  utama?: boolean
}

/**
 * Peta wilayah dengan penanda titik (REQ-F-BRD-004, REQ-SW-003).
 *
 * Pustaka peta beserta gayanya diambil hanya ketika peta benar-benar terlihat,
 * lewat impor dinamis. Dengan begitu berkas awal halaman tidak membesar dan
 * *rendering* halaman utama tidak tertunda pada koneksi desa yang lambat
 * (CON-02). Sebelum peta dimuat, daftar titik sudah tersaji sebagai teks,
 * sehingga isinya tetap terbaca meski peta gagal dimuat atau ubinnya terblokir.
 */
export function Peta({
  titik,
  tinggi = 380,
  zum = 14,
  judul,
}: {
  titik: TitikPeta[]
  tinggi?: number
  zum?: number
  judul: string
}) {
  const wadah = useRef<HTMLDivElement>(null)
  // Peramban tanpa IntersectionObserver langsung memuat peta, bukan tidak sama
  // sekali; nilainya ditetapkan saat state dibuat agar tidak perlu efek.
  const [terlihat, setTerlihat] = useState(() => typeof IntersectionObserver !== 'function')
  const [gagal, setGagal] = useState(false)

  // Peta baru disiapkan saat pengguna benar-benar menggulir ke dekatnya.
  useEffect(() => {
    const elemen = wadah.current

    if (!elemen || terlihat) return

    const pengamat = new IntersectionObserver(
      (entri) => entri[0]?.isIntersecting && setTerlihat(true),
      { rootMargin: '200px' },
    )

    pengamat.observe(elemen)

    return () => pengamat.disconnect()
  }, [terlihat])

  useEffect(() => {
    if (!terlihat || !wadah.current || titik.length === 0) return

    let peta: { remove: () => void } | null = null
    let dibatalkan = false

    void (async () => {
      try {
        const [L] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css')])

        if (dibatalkan || !wadah.current) return

        const instans = L.map(wadah.current, { scrollWheelZoom: false })
        peta = instans

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '&copy; Kontributor <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(instans)

        const penanda = titik.map((satu) =>
          L.marker([satu.lat, satu.lng], {
            // Ikon bawaan Leaflet memuat gambarnya dari jalur relatif yang
            // tidak ada setelah dibundel, jadi penandanya digambar sendiri.
            icon: L.divIcon({
              className: '',
              html:
                `<span class="block size-3.5 rounded-full border-2 border-white shadow ` +
                `${satu.utama ? 'bg-desa-700' : 'bg-orange-500'}"></span>`,
              iconSize: [14, 14],
              iconAnchor: [7, 7],
            }),
            title: satu.nama,
            alt: satu.nama,
          })
            .addTo(instans)
            .bindPopup(
              `<strong>${satu.nama}</strong>${satu.keterangan ? `<br>${satu.keterangan}` : ''}`,
            ),
        )

        if (penanda.length === 1) {
          instans.setView([titik[0].lat, titik[0].lng], zum)
        } else {
          instans.fitBounds(L.featureGroup(penanda).getBounds().pad(0.2))
        }
      } catch {
        if (!dibatalkan) setGagal(true)
      }
    })()

    return () => {
      dibatalkan = true
      peta?.remove()
    }
  }, [terlihat, titik, zum])

  if (titik.length === 0) return null

  return (
    <div>
      <div
        ref={wadah}
        role="img"
        aria-label={judul}
        style={{ height: tinggi }}
        className="w-full overflow-hidden rounded-lg border border-slate-200 bg-slate-100"
      />

      {gagal && (
        <p className="mt-2 text-sm text-slate-600">
          Peta tidak dapat dimuat. Daftar lokasi di bawah tetap dapat digunakan.
        </p>
      )}

      {/* Padanan teks: peta bukan satu-satunya jalan ke informasinya. */}
      <ul className="mt-4 grid gap-2 sm:grid-cols-2">
        {titik.map((satu) => (
          <li key={`${satu.nama}-${satu.lat}`} className="flex items-start gap-2 text-sm">
            <MapPin
              aria-hidden
              className={`mt-0.5 size-4 shrink-0 ${satu.utama ? 'text-desa-700' : 'text-orange-600'}`}
            />
            <span>
              <span className="font-medium text-slate-900">{satu.nama}</span>
              {satu.keterangan && <span className="block text-slate-600">{satu.keterangan}</span>}
              <a
                href={`https://www.openstreetmap.org/?mlat=${satu.lat}&mlon=${satu.lng}#map=17/${satu.lat}/${satu.lng}`}
                target="_blank"
                rel="noreferrer noopener"
                className="text-xs text-desa-700 underline underline-offset-2"
              >
                Buka di peta
              </a>
            </span>
          </li>
        ))}
      </ul>
    </div>
  )
}
