import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { MapPin, Phone, Plus, Store } from 'lucide-react'
import { api, pesanGalat } from '@/lib/api'
import { Kartu, IsiKartu } from '@/components/ui/Kartu'
import { Isian } from '@/components/ui/Isian'
import { Lencana } from '@/components/ui/Lencana'
import { Paginasi } from '@/components/ui/Paginasi'
import { GalatMuat, KondisiKosong, Rangka } from '@/components/ui/Status'
import { useBahasa } from '@/lib/bahasa'
import { useDataTerstruktur, useMeta } from '@/lib/meta'
import type { Halaman } from '@/types'

interface Umkm {
  nama_usaha: string
  slug: string
  pemilik: string
  kategori: string
  deskripsi: string | null
  alamat: string | null
  telepon: string | null
  foto: string | null
}

export function DirektoriUmkm() {
  const [halaman, setHalaman] = useState(1)
  const [q, setQ] = useState('')

  const { data, isPending, error } = useQuery({
    queryKey: ['umkm', halaman, q],
    queryFn: async () =>
      (await api.get<Halaman<Umkm>>('/umkm', { params: { page: halaman, q: q || undefined } })).data,
  })

  useMeta({
    judul: 'Direktori UMKM Desa',
    deskripsi: 'Produk dan usaha milik warga desa beserta kontak pelaku usaha yang bersedia dipublikasikan.',
  })

  // REQ-F-SRC-005: tiap usaha dikenali mesin pencari sebagai usaha lokal.
  useDataTerstruktur(
    data && data.data.length > 0
      ? {
          '@type': 'ItemList',
          name: 'Direktori UMKM Desa',
          itemListElement: data.data.map((usaha, indeks) => ({
            '@type': 'ListItem',
            position: indeks + 1,
            item: {
              '@type': 'LocalBusiness',
              name: usaha.nama_usaha,
              description: usaha.deskripsi ?? undefined,
              address: usaha.alamat ?? undefined,
              telephone: usaha.telepon ?? undefined,
              image: usaha.foto ?? undefined,
            },
          })),
        }
      : null,
  )

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl">Direktori UMKM Desa</h1>
          <p className="mt-1 text-slate-600">
            Produk dan usaha milik warga desa. Nomor kontak hanya ditampilkan bila pemilik usaha menyetujuinya.
          </p>
        </div>
        {/* REQ-F-POT-002: pelaku usaha dapat mendaftar sendiri. */}
        <Link
          to="/potensi/umkm/daftar"
          className="inline-flex min-h-11 items-center gap-2 rounded-lg bg-desa-700 px-4 text-sm font-medium text-white hover:bg-desa-800"
        >
          <Plus aria-hidden className="size-4" /> Daftarkan Usaha
        </Link>
      </header>

      <div className="max-w-md">
        <Isian
          label="Cari usaha atau produk"
          value={q}
          onChange={(e) => { setQ(e.target.value); setHalaman(1) }}
          placeholder="Contoh: keripik, kopi, anyaman"
        />
      </div>

      {error ? (
        <GalatMuat pesan={pesanGalat(error)} />
      ) : isPending ? (
        <Rangka baris={3} />
      ) : data.data.length === 0 ? (
        <KondisiKosong judul="Usaha tidak ditemukan" keterangan="Coba kata kunci lain." />
      ) : (
        <>
          <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {data.data.map((usaha) => (
              <li key={usaha.slug}>
                <Kartu className="h-full">
                  {usaha.foto && (
                    <img src={usaha.foto} alt="" loading="lazy" className="h-40 w-full rounded-t-xl object-cover" />
                  )}
                  <IsiKartu>
                    <Lencana nada="sukses">{usaha.kategori}</Lencana>
                    <h2 className="mt-2 flex items-start gap-2 text-base">
                      <Store aria-hidden className="mt-0.5 size-4 shrink-0 text-desa-700" />
                      {usaha.nama_usaha}
                    </h2>
                    <p className="mt-1 text-sm text-slate-600">Pemilik: {usaha.pemilik}</p>
                    {usaha.deskripsi && <p className="mt-2 text-sm text-slate-600">{usaha.deskripsi}</p>}
                    {usaha.alamat && (
                      <p className="mt-2 flex items-start gap-1.5 text-sm text-slate-500">
                        <MapPin aria-hidden className="mt-0.5 size-4 shrink-0" />
                        {usaha.alamat}
                      </p>
                    )}
                    {usaha.telepon && (
                      <a
                        href={`https://wa.me/${usaha.telepon.replace(/^0/, '62')}`}
                        target="_blank"
                        rel="noreferrer noopener"
                        className="mt-3 inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 px-3 text-sm font-medium hover:bg-slate-50"
                      >
                        <Phone aria-hidden className="size-4" /> Hubungi
                      </a>
                    )}
                  </IsiKartu>
                </Kartu>
              </li>
            ))}
          </ul>

          <Paginasi halaman={data.current_page} totalHalaman={data.last_page} total={data.total} onPindah={setHalaman} />
        </>
      )}
    </div>
  )
}

interface Wisata {
  nama: string
  slug: string
  deskripsi: string | null
  jam_operasional: string | null
  tarif: string | null
  alamat: string | null
  koordinat: { lat: number; lng: number } | null
  foto: string | null
}

export function DestinasiWisata() {
  const { t } = useBahasa()
  const { data, isPending, error } = useQuery({
    queryKey: ['wisata'],
    queryFn: async () => (await api.get<{ data: Wisata[] }>('/wisata')).data.data,
  })

  useMeta({ judul: t('wisata.judul'), deskripsi: t('wisata.keterangan') })

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl">{t('wisata.judul')}</h1>
        <p className="mt-1 text-slate-600">{t('wisata.keterangan')}</p>
      </header>

      {error ? (
        <GalatMuat pesan={pesanGalat(error)} />
      ) : isPending ? (
        <Rangka baris={3} />
      ) : (
        <ul className="grid gap-5 md:grid-cols-2">
          {data?.map((wisata) => (
            <li key={wisata.slug}>
              <Kartu className="h-full">
                {wisata.foto && (
                  <img src={wisata.foto} alt="" loading="lazy" className="h-48 w-full rounded-t-xl object-cover" />
                )}
                <IsiKartu>
                  <h2 className="text-lg">{wisata.nama}</h2>
                  <p className="mt-2 text-sm text-slate-600">{wisata.deskripsi}</p>
                  <dl className="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                    <div>
                      <dt className="text-slate-600">{t('wisata.jam')}</dt>
                      <dd className="font-medium">{wisata.jam_operasional ?? '-'}</dd>
                    </div>
                    <div>
                      <dt className="text-slate-600">{t('wisata.tarif')}</dt>
                      <dd className="font-medium">{wisata.tarif ?? '-'}</dd>
                    </div>
                  </dl>
                  {wisata.koordinat && (
                    <a
                      href={`https://www.openstreetmap.org/?mlat=${wisata.koordinat.lat}&mlon=${wisata.koordinat.lng}#map=15/${wisata.koordinat.lat}/${wisata.koordinat.lng}`}
                      target="_blank"
                      rel="noreferrer noopener"
                      className="mt-3 inline-block text-sm font-medium text-desa-700 underline underline-offset-2"
                    >
                      {t('wisata.peta')}
                    </a>
                  )}
                </IsiKartu>
              </Kartu>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
