import { useQuery } from '@tanstack/react-query'
import { api, pesanGalat } from '@/lib/api'
import { useProfilDesa } from '@/lib/kueri'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { GalatMuat, Pemuat } from '@/components/ui/Status'

interface Lembaga {
  nama: string
  slug: string
  jenis: string
  deskripsi: string | null
  pengurus: { nama: string; jabatan: string; wilayah: string | null; masa_jabatan?: string | null; tugas_pokok?: string | null; foto: string | null }[]
}

export function Profil() {
  const { data: desa, isPending, error } = useProfilDesa()
  const { data: lembaga } = useQuery({
    queryKey: ['lembaga'],
    queryFn: async () => (await api.get<{ data: Lembaga[] }>('/lembaga')).data.data,
  })

  if (error) return <GalatMuat pesan={pesanGalat(error)} />
  if (isPending) return <Pemuat />

  const pemerintahDesa = lembaga?.find((l) => l.jenis === 'pemerintah_desa')
  const lainnya = lembaga?.filter((l) => l.jenis !== 'pemerintah_desa') ?? []

  const batas = [
    ['Utara', desa?.batas_utara],
    ['Selatan', desa?.batas_selatan],
    ['Timur', desa?.batas_timur],
    ['Barat', desa?.batas_barat],
  ]

  return (
    <div className="space-y-8">
      <header>
        <h1 className="text-2xl">Profil Desa {desa?.nama_desa}</h1>
        <p className="mt-1 text-slate-600">
          Kecamatan {desa?.kecamatan}, Kabupaten {desa?.kabupaten}, {desa?.provinsi}
        </p>
      </header>

      <Kartu>
        <KepalaKartu judul="Sejarah Desa" />
        <IsiKartu>
          <p className="leading-relaxed text-slate-700">{desa?.sejarah}</p>
        </IsiKartu>
      </Kartu>

      <div className="grid gap-6 lg:grid-cols-2">
        <Kartu>
          <KepalaKartu judul="Visi" />
          <IsiKartu>
            <p className="leading-relaxed text-slate-700">{desa?.visi}</p>
          </IsiKartu>
        </Kartu>

        <Kartu>
          <KepalaKartu judul="Misi" />
          <IsiKartu>
            <ol className="list-decimal space-y-2 pl-5 text-slate-700">
              {(desa?.misi ?? '').split('\n').filter(Boolean).map((baris) => (
                <li key={baris}>{baris}</li>
              ))}
            </ol>
          </IsiKartu>
        </Kartu>
      </div>

      <Kartu>
        <KepalaKartu judul="Letak dan Wilayah" />
        <IsiKartu>
          <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
              <dt className="text-sm text-slate-600">Luas wilayah</dt>
              <dd className="font-medium">{desa?.luas_wilayah ?? '-'}</dd>
            </div>
            <div>
              <dt className="text-sm text-slate-600">Pembagian wilayah</dt>
              <dd className="font-medium">
                {desa?.jumlah_dusun ?? '-'} dusun · {desa?.jumlah_rw ?? '-'} RW · {desa?.jumlah_rt ?? '-'} RT
              </dd>
            </div>
            {batas.map(([arah, nilai]) => (
              <div key={arah}>
                <dt className="text-sm text-slate-600">Batas {arah}</dt>
                <dd className="font-medium">{nilai ?? '-'}</dd>
              </div>
            ))}
          </dl>

          {desa?.lat && desa?.lng && (
            <a
              href={`https://www.openstreetmap.org/?mlat=${desa.lat}&mlon=${desa.lng}#map=14/${desa.lat}/${desa.lng}`}
              target="_blank"
              rel="noreferrer noopener"
              className="mt-4 inline-block text-sm font-medium text-desa-700 underline underline-offset-2"
            >
              Lihat lokasi kantor desa pada peta
            </a>
          )}
        </IsiKartu>
      </Kartu>

      {pemerintahDesa && (
        <Kartu>
          <KepalaKartu
            judul="Struktur Pemerintah Desa"
            deskripsi="Perangkat desa beserta tugas pokok dan masa jabatan."
          />
          <IsiKartu>
            <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {pemerintahDesa.pengurus.map((orang) => (
                <li key={`${orang.nama}-${orang.jabatan}`} className="rounded-lg border border-slate-200 p-4">
                  <p className="font-medium text-slate-900">{orang.nama}</p>
                  <p className="text-sm text-desa-700">{orang.jabatan}</p>
                  {orang.masa_jabatan && <p className="mt-1 text-xs text-slate-500">Masa jabatan {orang.masa_jabatan}</p>}
                  {orang.tugas_pokok && <p className="mt-2 text-sm text-slate-600">{orang.tugas_pokok}</p>}
                </li>
              ))}
            </ul>
          </IsiKartu>
        </Kartu>
      )}

      <section aria-labelledby="lembaga-desa">
        <h2 id="lembaga-desa" className="mb-4 text-xl">Lembaga Kemasyarakatan Desa</h2>
        <div className="grid gap-5 lg:grid-cols-2">
          {lainnya.map((item) => (
            <Kartu key={item.slug}>
              <KepalaKartu judul={item.nama} deskripsi={item.deskripsi ?? undefined} />
              <IsiKartu>
                <ul className="space-y-1.5 text-sm">
                  {item.pengurus.map((orang) => (
                    <li key={`${orang.nama}-${orang.jabatan}`} className="flex justify-between gap-4">
                      <span className="text-slate-700">{orang.wilayah ?? orang.nama}</span>
                      <span className="text-slate-500">{orang.jabatan}</span>
                    </li>
                  ))}
                </ul>
              </IsiKartu>
            </Kartu>
          ))}
        </div>
      </section>
    </div>
  )
}
