import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Lock } from 'lucide-react'
import { api, pesanGalat } from '@/lib/api'
import { useMeta } from '@/lib/meta'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { Isian } from '@/components/ui/Isian'
import { Lencana } from '@/components/ui/Lencana'
import { GalatMuat, KondisiKosong, Pemuat } from '@/components/ui/Status'

interface Operasi {
  tags: string[]
  summary: string
  description?: string
  security?: unknown[]
  'x-batas-laju'?: string
}

interface Spesifikasi {
  info: { title: string; version: string; description: string }
  servers: { url: string }[]
  tags: { name: string; description: string }[]
  paths: Record<string, Record<string, Operasi>>
}

const WARNA: Record<string, string> = {
  get: 'bg-sky-50 text-sky-800 border-sky-200',
  post: 'bg-emerald-50 text-emerald-800 border-emerald-200',
  put: 'bg-amber-50 text-amber-800 border-amber-200',
  delete: 'bg-red-50 text-red-800 border-red-200',
}

/** REQ-API-004: dokumentasi API yang dapat dibaca tanpa perkakas tambahan. */
export function DokumentasiApi() {
  const [saring, setSaring] = useState('')

  const { data, isPending, error } = useQuery({
    queryKey: ['openapi'],
    queryFn: async () => (await api.get<Spesifikasi>('/openapi.json')).data,
    staleTime: 30 * 60 * 1000,
  })

  useMeta({
    judul: 'Dokumentasi API',
    deskripsi: 'Daftar titik akhir API portal desa beserta izin dan batas lajunya, dalam format OpenAPI 3.1.',
  })

  const kelompok = useMemo(() => {
    if (!data) return []

    const kata = saring.trim().toLowerCase()
    const kumpulan = new Map<string, { alamat: string; metode: string; operasi: Operasi }[]>()

    for (const [alamat, perMetode] of Object.entries(data.paths)) {
      for (const [metode, operasi] of Object.entries(perMetode)) {
        if (kata && !`${metode} ${alamat} ${operasi.summary}`.toLowerCase().includes(kata)) continue

        const tag = operasi.tags[0] ?? 'Lainnya'

        kumpulan.set(tag, [...(kumpulan.get(tag) ?? []), { alamat, metode, operasi }])
      }
    }

    return data.tags
      .map((tag) => ({ ...tag, titik: kumpulan.get(tag.name) ?? [] }))
      .filter((tag) => tag.titik.length > 0)
  }, [data, saring])

  if (error) return <GalatMuat pesan={pesanGalat(error)} />
  if (isPending) return <Pemuat />

  const jumlah = Object.values(data.paths).reduce((total, perMetode) => total + Object.keys(perMetode).length, 0)

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl">{data.info.title}</h1>
        <p className="mt-1 text-slate-600">
          Versi {data.info.version} · {jumlah} titik akhir · alamat dasar{' '}
          <code className="rounded bg-slate-100 px-1.5 py-0.5 text-sm">{data.servers[0]?.url}</code>
        </p>
        <p className="mt-2 text-sm text-slate-600">
          Berkas OpenAPI 3.1 tersedia di{' '}
          <a href="/api/v1/openapi.json" className="font-medium text-desa-700 hover:underline">
            /api/v1/openapi.json
          </a>{' '}
          untuk dibuka dengan perkakas seperti Swagger UI atau Postman.
        </p>
      </header>

      <Kartu>
        <IsiKartu>
          <div className="space-y-3 text-sm leading-relaxed text-slate-700">
            {data.info.description
              .split('\n\n')
              .map((paragraf) => paragraf.trim())
              .filter(Boolean)
              .map((paragraf, indeks) => (
                <p key={indeks}>{paragraf.replace(/\*\*/g, '').replace(/`/g, '')}</p>
              ))}
          </div>
        </IsiKartu>
      </Kartu>

      <Isian
        label="Cari titik akhir"
        value={saring}
        onChange={(e) => setSaring(e.target.value)}
        placeholder="permohonan, /auth, pengaduan…"
      />

      {kelompok.length === 0 ? (
        <KondisiKosong judul="Tidak ada yang cocok" keterangan="Coba kata kunci lain." />
      ) : (
        kelompok.map((tag) => (
          <Kartu key={tag.name}>
            <KepalaKartu judul={tag.name} deskripsi={tag.description} />
            <ul className="divide-y divide-slate-100">
              {tag.titik.map(({ alamat, metode, operasi }) => (
                <li key={`${metode}-${alamat}`} className="px-5 py-3">
                  <div className="flex flex-wrap items-center gap-2">
                    <span
                      className={`rounded border px-2 py-0.5 font-mono text-xs font-semibold uppercase ${
                        WARNA[metode] ?? 'border-slate-200 bg-slate-50 text-slate-700'
                      }`}
                    >
                      {metode}
                    </span>
                    <code className="break-all text-sm text-slate-900">{alamat}</code>
                    {operasi.security && (
                      <span className="inline-flex items-center gap-1 text-xs text-slate-500">
                        <Lock aria-hidden className="size-3" /> perlu token
                      </span>
                    )}
                    {operasi['x-batas-laju'] && <Lencana>batas: {operasi['x-batas-laju']}</Lencana>}
                  </div>
                  <p className="mt-1 text-sm text-slate-600">{operasi.summary}</p>
                  {operasi.description && (
                    <p className="mt-0.5 text-xs text-slate-500">{operasi.description.replace(/`/g, '')}</p>
                  )}
                </li>
              ))}
            </ul>
          </Kartu>
        ))
      )}
    </div>
  )
}
