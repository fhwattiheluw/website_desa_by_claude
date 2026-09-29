import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useDaftarKonten, useKategori } from '@/lib/kueri'
import { pesanGalat } from '@/lib/api'
import { tanggal, tanggalRelatif } from '@/lib/format'
import { useMeta } from '@/lib/meta'
import { Kartu } from '@/components/ui/Kartu'
import { Lencana } from '@/components/ui/Lencana'
import { Paginasi } from '@/components/ui/Paginasi'
import { GalatMuat, KondisiKosong, Rangka } from '@/components/ui/Status'
import type { TipeKonten } from '@/types'

const JUDUL: Record<TipeKonten, { judul: string; deskripsi: string }> = {
  berita: { judul: 'Berita Desa', deskripsi: 'Kabar terbaru seputar kegiatan dan pembangunan desa.' },
  artikel: { judul: 'Artikel', deskripsi: 'Tulisan dan kajian seputar kehidupan desa.' },
  pengumuman: { judul: 'Pengumuman', deskripsi: 'Informasi resmi yang perlu diketahui warga.' },
  agenda: { judul: 'Agenda Kegiatan', deskripsi: 'Jadwal kegiatan desa yang akan datang.' },
}

export function DaftarKonten({ tipe }: { tipe: TipeKonten }) {
  const [halaman, setHalaman] = useState(1)
  const [kategori, setKategori] = useState('')
  const { data, isPending, error } = useDaftarKonten(tipe, halaman, kategori)
  const { data: daftarKategori } = useKategori()

  const info = JUDUL[tipe]

  useMeta({ judul: info.judul, deskripsi: info.deskripsi })

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl">{info.judul}</h1>
        <p className="mt-1 text-slate-600">{info.deskripsi}</p>
      </header>

      {tipe === 'berita' && (daftarKategori?.length ?? 0) > 0 && (
        <div className="flex flex-wrap gap-2" role="group" aria-label="Saring menurut kategori">
          <button
            type="button"
            onClick={() => { setKategori(''); setHalaman(1) }}
            aria-pressed={kategori === ''}
            className={`min-h-9 rounded-full px-3.5 text-sm ${
              kategori === '' ? 'bg-desa-700 text-white' : 'border border-slate-300 bg-white text-slate-700'
            }`}
          >
            Semua
          </button>
          {daftarKategori?.map((k) => (
            <button
              key={k.slug}
              type="button"
              onClick={() => { setKategori(k.slug); setHalaman(1) }}
              aria-pressed={kategori === k.slug}
              className={`min-h-9 rounded-full px-3.5 text-sm ${
                kategori === k.slug ? 'bg-desa-700 text-white' : 'border border-slate-300 bg-white text-slate-700'
              }`}
            >
              {k.nama}
            </button>
          ))}
        </div>
      )}

      {error ? (
        <GalatMuat pesan={pesanGalat(error)} />
      ) : isPending ? (
        <Rangka baris={4} />
      ) : data && data.data.length === 0 ? (
        <KondisiKosong judul={`Belum ada ${info.judul.toLowerCase()}`} keterangan="Silakan periksa kembali beberapa waktu lagi." />
      ) : (
        <>
          <ul className="space-y-4">
            {data?.data.map((item) => (
              <li key={item.id}>
                <Kartu className="transition-colors hover:border-desa-300">
                  <article className="flex flex-col gap-4 p-5 sm:flex-row">
                    {item.gambar?.url && (
                      <img
                        src={item.gambar.url}
                        alt={item.gambar.alt ?? ''}
                        loading="lazy"
                        className="h-36 w-full rounded-lg object-cover sm:w-52"
                      />
                    )}
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        {item.kategori && <Lencana nada="sukses">{item.kategori.nama}</Lencana>}
                        {tipe === 'agenda' && item.mulai_pada && (
                          <Lencana nada="info">{tanggal(item.mulai_pada, true)}</Lencana>
                        )}
                      </div>
                      <h2 className="mt-2 text-base leading-snug">
                        <Link to={`/${tipe}/${item.slug}`} className="hover:text-desa-700">
                          {item.judul}
                        </Link>
                      </h2>
                      <p className="mt-1.5 line-clamp-2 text-sm text-slate-600">{item.ringkasan}</p>
                      <p className="mt-3 text-xs text-slate-500">
                        {tipe === 'agenda'
                          ? `Lokasi: ${item.lokasi ?? '-'}`
                          : `${tanggalRelatif(item.terbit_pada)} · ${item.dibaca} kali dibaca`}
                      </p>
                    </div>
                  </article>
                </Kartu>
              </li>
            ))}
          </ul>

          <Paginasi
            halaman={data?.current_page ?? 1}
            totalHalaman={data?.last_page ?? 1}
            total={data?.total}
            onPindah={setHalaman}
          />
        </>
      )}
    </div>
  )
}
