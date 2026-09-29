import { Link, useParams } from 'react-router-dom'
import { ArrowLeft, CalendarDays, MapPin, Share2 } from 'lucide-react'
import { useKonten } from '@/lib/kueri'
import { pesanGalat } from '@/lib/api'
import { tanggal } from '@/lib/format'
import { useMeta } from '@/lib/meta'
import { GalatMuat, Pemuat } from '@/components/ui/Status'
import { Lencana } from '@/components/ui/Lencana'
import type { TipeKonten } from '@/types'

export function DetailKonten({ tipe }: { tipe: TipeKonten }) {
  const { slug = '' } = useParams()
  const { data, isPending, error } = useKonten(tipe, slug)

  useMeta({
    judul: data?.judul ?? 'Memuat…',
    deskripsi: data?.ringkasan ?? undefined,
    gambar: data?.gambar?.url ?? null,
    jenis: 'article',
  })

  if (error) return <GalatMuat pesan={pesanGalat(error)} />
  if (isPending || !data) return <Pemuat />

  const url = window.location.href

  return (
    <article className="mx-auto max-w-3xl">
      <Link to={`/${tipe}`} className="inline-flex items-center gap-1.5 text-sm text-desa-700 hover:underline">
        <ArrowLeft aria-hidden className="size-4" /> Kembali ke daftar
      </Link>

      <header className="mt-4">
        {data.kategori && <Lencana nada="sukses">{data.kategori.nama}</Lencana>}
        <h1 className="mt-3 text-3xl leading-tight">{data.judul}</h1>
        <p className="mt-3 text-sm text-slate-600">
          {tanggal(data.terbit_pada)}
          {data.penulis && ` · ${data.penulis}`}
          {` · ${data.dibaca} kali dibaca`}
        </p>
      </header>

      {tipe === 'agenda' && (
        <dl className="mt-5 grid gap-3 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-2">
          <div className="flex items-start gap-2">
            <CalendarDays aria-hidden className="mt-0.5 size-4 text-desa-700" />
            <div>
              <dt className="text-sm text-slate-600">Waktu</dt>
              <dd className="font-medium">{tanggal(data.mulai_pada, true)}</dd>
            </div>
          </div>
          <div className="flex items-start gap-2">
            <MapPin aria-hidden className="mt-0.5 size-4 text-desa-700" />
            <div>
              <dt className="text-sm text-slate-600">Lokasi</dt>
              <dd className="font-medium">{data.lokasi ?? '-'}</dd>
            </div>
          </div>
        </dl>
      )}

      {data.gambar?.url && (
        <img
          src={data.gambar.url}
          alt={data.gambar.alt ?? ''}
          className="mt-6 w-full rounded-xl object-cover"
        />
      )}

      {data.ringkasan && <p className="mt-6 text-lg leading-relaxed text-slate-700">{data.ringkasan}</p>}

      {/* Isi konten berasal dari penyunting panel administrasi milik desa. */}
      <div className="prose-desa mt-6 text-slate-700" dangerouslySetInnerHTML={{ __html: data.isi ?? '' }} />

      {data.tag.length > 0 && (
        <ul className="mt-8 flex flex-wrap gap-2">
          {data.tag.map((tag) => (
            <li key={tag}>
              <Lencana>#{tag}</Lencana>
            </li>
          ))}
        </ul>
      )}

      <footer className="mt-8 flex flex-wrap items-center gap-3 border-t border-slate-200 pt-6">
        <span className="flex items-center gap-1.5 text-sm text-slate-600">
          <Share2 aria-hidden className="size-4" /> Bagikan:
        </span>
        <a
          href={`https://wa.me/?text=${encodeURIComponent(`${data.judul} ${url}`)}`}
          target="_blank"
          rel="noreferrer noopener"
          className="text-sm font-medium text-desa-700 hover:underline"
        >
          WhatsApp
        </a>
        <a
          href={`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`}
          target="_blank"
          rel="noreferrer noopener"
          className="text-sm font-medium text-desa-700 hover:underline"
        >
          Facebook
        </a>
        <button
          type="button"
          onClick={() => void navigator.clipboard?.writeText(url)}
          className="text-sm font-medium text-desa-700 hover:underline"
        >
          Salin tautan
        </button>
      </footer>
    </article>
  )
}
