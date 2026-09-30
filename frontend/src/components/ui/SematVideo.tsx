import { useState } from 'react'
import { ExternalLink, Play } from 'lucide-react'

export interface VideoTersemat {
  id: number
  judul: string
  penyedia: string
  url_sematan: string
  url_asli: string
  thumbnail: string | null
}

const NAMA_PENYEDIA: Record<string, string> = { youtube: 'YouTube', vimeo: 'Vimeo' }

/**
 * Video tersemat yang baru menghubungi penyedia setelah warga menekan putar
 * (REQ-F-GAL-006, REQ-SW-006).
 *
 * Bingkai yang dipasang sejak halaman terbuka membuat peramban setiap
 * pengunjung menghubungi penyedia dan menerima penanda darinya, walau videonya
 * tidak pernah ditonton. Gambar sampul pun dilayani dari server desa sendiri,
 * bukan dari penyedia, agar tidak ada permintaan keluar sebelum warga memilih.
 */
export function SematVideo({ video }: { video: VideoTersemat }) {
  const [diputar, setDiputar] = useState(false)
  const penyedia = NAMA_PENYEDIA[video.penyedia] ?? 'penyedia video'

  return (
    <figure className="overflow-hidden rounded-xl border border-slate-200 bg-white">
      <div className="relative aspect-video bg-slate-900">
        {diputar ? (
          <iframe
            src={`${video.url_sematan}?autoplay=1`}
            title={video.judul}
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowFullScreen
            referrerPolicy="strict-origin-when-cross-origin"
            className="absolute inset-0 size-full"
          />
        ) : (
          <button
            type="button"
            onClick={() => setDiputar(true)}
            className="group absolute inset-0 size-full cursor-pointer"
          >
            {video.thumbnail ? (
              <img
                src={video.thumbnail}
                alt=""
                loading="lazy"
                className="size-full object-cover opacity-80 transition-opacity group-hover:opacity-100"
              />
            ) : (
              <span className="absolute inset-0 bg-gradient-to-br from-slate-800 to-slate-900" />
            )}
            <span className="absolute inset-0 grid place-items-center">
              <span className="grid size-16 place-items-center rounded-full bg-white/90 text-desa-800 shadow-lg transition-transform group-hover:scale-110">
                <Play aria-hidden className="ms-1 size-7 fill-current" />
              </span>
            </span>
            <span className="sr-only">Putar video {video.judul} dari {penyedia}</span>
          </button>
        )}
      </div>

      <figcaption className="p-4">
        <p className="font-medium text-slate-900">{video.judul}</p>
        <p className="mt-1 text-xs text-slate-500">
          {diputar
            ? `Diputar melalui ${penyedia}.`
            : `Menekan putar akan menghubungi ${penyedia} dan memuat video dari sana.`}{' '}
          <a
            href={video.url_asli}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1 font-medium text-desa-700 hover:underline"
          >
            Buka di {penyedia}
            <ExternalLink aria-hidden className="size-3" />
          </a>
        </p>
      </figcaption>
    </figure>
  )
}
