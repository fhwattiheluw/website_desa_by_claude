import { useQuery } from '@tanstack/react-query'
import { api, pesanGalat } from '@/lib/api'
import { useFasilitasUmum, useProfilDesa } from '@/lib/kueri'
import { useMeta } from '@/lib/meta'
import { useBahasa } from '@/lib/bahasa'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { GalatMuat, Pemuat } from '@/components/ui/Status'
import { BaganOrganisasi, type SimpulBagan } from '@/components/ui/BaganOrganisasi'
import { Peta, type TitikPeta } from '@/components/ui/Peta'

interface Lembaga {
  nama: string
  slug: string
  jenis: string
  deskripsi: string | null
  pengurus: { nama: string; jabatan: string; wilayah: string | null; masa_jabatan?: string | null; tugas_pokok?: string | null; foto: string | null }[]
  bagan: SimpulBagan[]
}

export function Profil() {
  const { t } = useBahasa()
  const { data: desa, isPending, error } = useProfilDesa()
  const { data: fasilitas } = useFasilitasUmum()
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

  /*
   * Kantor desa ditandai berbeda dari fasilitas lain. Bila koordinatnya sama
   * dengan koordinat desa pada pengaturan, cukup satu penanda.
   */
  const titikPeta: TitikPeta[] = (fasilitas ?? []).map((satu) => ({
    nama: satu.nama,
    keterangan: satu.keterangan ?? satu.alamat,
    lat: satu.koordinat.lat,
    lng: satu.koordinat.lng,
    utama: satu.jenis === 'kantor',
  }))

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

        </IsiKartu>
      </Kartu>

      {titikPeta.length > 0 && (
        <Kartu>
          <KepalaKartu
            judul={t('profil.peta')}
            deskripsi="Kantor desa dan fasilitas umum utama. Peta dimuat setelah Anda menggulir ke bagian ini."
          />
          <IsiKartu>
            <Peta
              titik={titikPeta}
              judul={t('profil.peta')}
              arahKe={titikPeta.find((satu) => satu.utama) ?? null}
            />
          </IsiKartu>
        </Kartu>
      )}

      {pemerintahDesa && (
        <Kartu>
          <KepalaKartu
            judul={t('profil.struktur')}
            deskripsi={t('profil.struktur.keterangan')}
          />
          <IsiKartu>
            <BaganOrganisasi bagan={pemerintahDesa.bagan} judul={t('profil.struktur')} />
          </IsiKartu>

          {/*
            * Tugas pokok tidak dimuat ke dalam kotak bagan agar bagan tetap
            * terbaca; rinciannya disajikan sebagai tabel yang sekaligus menjadi
            * padanan teks bagan.
            */}
          <div className="overflow-x-auto border-t border-slate-100">
            <table className="w-full text-sm">
              <caption className="sr-only">
                Perangkat desa beserta jabatan, masa jabatan, dan tugas pokoknya
              </caption>
              <thead>
                <tr className="border-b border-slate-200 bg-slate-50 text-left text-slate-600">
                  <th scope="col" className="px-5 py-3 font-medium">Nama</th>
                  <th scope="col" className="px-5 py-3 font-medium">Jabatan</th>
                  <th scope="col" className="px-5 py-3 font-medium">{t('profil.masa_jabatan')}</th>
                  <th scope="col" className="px-5 py-3 font-medium">Tugas pokok</th>
                </tr>
              </thead>
              <tbody>
                {pemerintahDesa.pengurus.map((orang) => (
                  <tr key={`${orang.nama}-${orang.jabatan}`} className="border-b border-slate-100 last:border-0">
                    <th scope="row" className="px-5 py-3 text-left font-medium">{orang.nama}</th>
                    <td className="px-5 py-3 text-desa-700">{orang.jabatan}</td>
                    <td className="px-5 py-3 text-slate-600">{orang.masa_jabatan ?? '-'}</td>
                    <td className="px-5 py-3 text-slate-600">{orang.tugas_pokok ?? '-'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
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
