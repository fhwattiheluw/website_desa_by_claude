import { useQuery } from '@tanstack/react-query'
import { api, pesanGalat } from '@/lib/api'
import { useProfilDesa } from '@/lib/kueri'
import { useMeta } from '@/lib/meta'
import { useBahasa } from '@/lib/bahasa'
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
  const { t } = useBahasa()
  const { data: desa, isPending, error } = useProfilDesa()
  const { data: lembaga } = useQuery({
    queryKey: ['lembaga'],
    queryFn: async () => (await api.get<{ data: Lembaga[] }>('/lembaga')).data.data,
  })

  useMeta({
    judul: desa?.nama_desa ? `Profil Desa ${desa.nama_desa}` : 'Profil Desa',
    deskripsi: 'Sejarah, visi dan misi, letak wilayah, struktur pemerintahan, serta lembaga kemasyarakatan desa.',
  })

  if (error) return <GalatMuat pesan={pesanGalat(error)} />
  if (isPending) return <Pemuat />

  const pemerintahDesa = lembaga?.find((l) => l.jenis === 'pemerintah_desa')
  const lainnya = lembaga?.filter((l) => l.jenis !== 'pemerintah_desa') ?? []

  const batas = [
    [t('profil.utara'), desa?.batas_utara],
    [t('profil.selatan'), desa?.batas_selatan],
    [t('profil.timur'), desa?.batas_timur],
    [t('profil.barat'), desa?.batas_barat],
  ]

  return (
    <div className="space-y-8">
      <header>
        <h1 className="text-2xl">{t('profil.judul_desa', { nama: desa?.nama_desa ?? '' })}</h1>
        <p className="mt-1 text-slate-600">
          Kecamatan {desa?.kecamatan}, Kabupaten {desa?.kabupaten}, {desa?.provinsi}
        </p>
      </header>

      <Kartu>
        <KepalaKartu judul={t('profil.sejarah')} />
        <IsiKartu>
          <p className="leading-relaxed text-slate-700">{desa?.sejarah}</p>
        </IsiKartu>
      </Kartu>

      <div className="grid gap-6 lg:grid-cols-2">
        <Kartu>
          <KepalaKartu judul={t('profil.visi')} />
          <IsiKartu>
            <p className="leading-relaxed text-slate-700">{desa?.visi}</p>
          </IsiKartu>
        </Kartu>

        <Kartu>
          <KepalaKartu judul={t('profil.misi')} />
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
        <KepalaKartu judul={t('profil.letak')} />
        <IsiKartu>
          <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
              <dt className="text-sm text-slate-600">{t('profil.luas')}</dt>
              <dd className="font-medium">{desa?.luas_wilayah ?? '-'}</dd>
            </div>
            <div>
              <dt className="text-sm text-slate-600">{t('profil.pembagian')}</dt>
              <dd className="font-medium">
                {desa?.jumlah_dusun ?? '-'} {t('profil.dusun')} · {desa?.jumlah_rw ?? '-'} RW · {desa?.jumlah_rt ?? '-'} RT
              </dd>
            </div>
            {batas.map(([arah, nilai]) => (
              <div key={arah}>
                <dt className="text-sm text-slate-600">{t('profil.batas')} {arah}</dt>
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
              {t('profil.peta')}
            </a>
          )}
        </IsiKartu>
      </Kartu>

      {pemerintahDesa && (
        <Kartu>
          <KepalaKartu
            judul={t('profil.struktur')}
            deskripsi={t('profil.struktur.keterangan')}
          />
          <IsiKartu>
            <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {pemerintahDesa.pengurus.map((orang) => (
                <li key={`${orang.nama}-${orang.jabatan}`} className="rounded-lg border border-slate-200 p-4">
                  <p className="font-medium text-slate-900">{orang.nama}</p>
                  <p className="text-sm text-desa-700">{orang.jabatan}</p>
                  {orang.masa_jabatan && (
                    <p className="mt-1 text-xs text-slate-500">{t('profil.masa_jabatan')} {orang.masa_jabatan}</p>
                  )}
                  {orang.tugas_pokok && <p className="mt-2 text-sm text-slate-600">{orang.tugas_pokok}</p>}
                </li>
              ))}
            </ul>
          </IsiKartu>
        </Kartu>
      )}

      <section aria-labelledby="lembaga-desa">
        <h2 id="lembaga-desa" className="mb-4 text-xl">{t('profil.lembaga')}</h2>
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
