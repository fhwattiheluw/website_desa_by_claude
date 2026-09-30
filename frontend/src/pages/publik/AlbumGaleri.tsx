import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, X } from 'lucide-react'
import { api, pesanGalat } from '@/lib/api'
import { tanggal } from '@/lib/format'
import { useMeta } from '@/lib/meta'
import { GalatMuat, KondisiKosong, Pemuat } from '@/components/ui/Status'
import { SematVideo, type VideoTersemat } from '@/components/ui/SematVideo'

interface Foto {
  id: number
  alt: string | null
  url: string | null
  thumbnail: string
}

interface Album {
  id: number
  nama: string
  slug: string
  deskripsi: string | null
  tanggal_kegiatan: string | null
  foto: Foto[]
  video: VideoTersemat[]
}

/** REQ-F-GAL-005, REQ-F-GAL-006: isi album berupa foto dan video tersemat. */
export function AlbumGaleri() {
  const { slug } = useParams()
  const [dibuka, setDibuka] = useState<Foto | null>(null)

  const { data, isPending, error } = useQuery({
    queryKey: ['album', slug],
    queryFn: async () => (await api.get<Album>(`/galeri/${slug}`)).data,
  })

  useMeta({
    judul: data?.nama ?? 'Album Galeri',
    deskripsi: data?.deskripsi ?? 'Dokumentasi kegiatan pemerintah desa dan masyarakat.',
  })

  if (error) return <GalatMuat pesan={pesanGalat(error)} />
  if (isPending) return <Pemuat />

  const kosong = data.foto.length === 0 && data.video.length === 0

  return (
    <div className="space-y-6">
      <header>
        <Link to="/galeri" className="inline-flex items-center gap-1 text-sm font-medium text-desa-700 hover:underline">
          <ArrowLeft aria-hidden className="size-4" /> Semua album
        </Link>
        <h1 className="mt-2 text-2xl">{data.nama}</h1>
        {data.deskripsi && <p className="mt-1 text-slate-600">{data.deskripsi}</p>}
        <p className="mt-2 text-sm text-slate-500">
          {data.foto.length} foto · {data.video.length} video
          {data.tanggal_kegiatan && ` · ${tanggal(data.tanggal_kegiatan)}`}
        </p>
      </header>

      {kosong && (
        <KondisiKosong judul="Album masih kosong" keterangan="Dokumentasi kegiatan ini belum diunggah." />
      )}

      {data.foto.length > 0 && (
        <section aria-labelledby="judul-foto" className="space-y-3">
          <h2 id="judul-foto" className="text-lg">
            Foto
          </h2>
          <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            {data.foto.map((foto) => (
              <li key={foto.id}>
                <button
                  type="button"
                  onClick={() => setDibuka(foto)}
                  className="block w-full overflow-hidden rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-desa-600"
                >
                  <img
                    src={foto.thumbnail}
                    alt={foto.alt ?? `Foto kegiatan ${data.nama}`}
                    loading="lazy"
                    className="aspect-square w-full object-cover transition-transform hover:scale-105"
                  />
                </button>
              </li>
            ))}
          </ul>
        </section>
      )}

      {data.video.length > 0 && (
        <section aria-labelledby="judul-video" className="space-y-3">
          <h2 id="judul-video" className="text-lg">
            Video
          </h2>
          <ul className="grid gap-5 lg:grid-cols-2">
            {data.video.map((video) => (
              <li key={video.id}>
                <SematVideo video={video} />
              </li>
            ))}
          </ul>
        </section>
      )}

      {dibuka?.url && (
        <div
          role="dialog"
          aria-modal="true"
          aria-label={dibuka.alt ?? 'Foto kegiatan'}
          className="fixed inset-0 z-50 grid place-items-center bg-slate-950/80 p-4"
          onClick={() => setDibuka(null)}
        >
          <button
            type="button"
            onClick={() => setDibuka(null)}
            aria-label="Tutup foto"
            className="absolute right-4 top-4 grid size-11 place-items-center rounded-lg bg-white/10 text-white hover:bg-white/20"
          >
            <X aria-hidden className="size-5" />
          </button>
          <img
            src={dibuka.url}
            alt={dibuka.alt ?? `Foto kegiatan ${data.nama}`}
            className="max-h-[85vh] max-w-full rounded-lg object-contain"
          />
        </div>
      )}
    </div>
  )
}
