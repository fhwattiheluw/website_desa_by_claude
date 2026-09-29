import { useQuery } from '@tanstack/react-query'
import { Building2, Phone, TrendingUp, Users } from 'lucide-react'
import { api, pesanGalat } from '@/lib/api'
import { useMeta } from '@/lib/meta'
import { rupiah } from '@/lib/format'
import { Kartu, KartuStatistik, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { GalatMuat, KondisiKosong, Pemuat } from '@/components/ui/Status'
import { GrafikBatang, WARNA_SERI } from '@/components/chart/GrafikBatang'

interface DataBumdes {
  profil: {
    nama: string | null
    deskripsi: string | null
    tahun_berdiri: string | null
    dasar_hukum: string | null
    alamat: string | null
    kontak: string | null
  }
  unit_usaha: {
    nama: string
    slug: string
    deskripsi: string | null
    penanggung_jawab: string | null
    kontak: string | null
    foto: string | null
  }[]
  kinerja: {
    tahun: number
    pendapatan: number
    laba_bersih: number
    kontribusi_pades: number
    catatan: string | null
  }[]
  pengurus: { nama: string; jabatan: string }[]
}

/** REQ-F-POT-003: profil BUMDes berisi unit usaha, kinerja ringkas, dan pengurus. */
export function Bumdes() {
  const { data, isPending, error } = useQuery({
    queryKey: ['bumdes'],
    queryFn: async () => (await api.get<DataBumdes>('/bumdes')).data,
  })

  useMeta({
    judul: data?.profil.nama ?? 'BUMDes',
    deskripsi: 'Profil badan usaha milik desa: unit usaha yang dikelola, ringkasan kinerja tahunan, dan pengurus.',
  })

  if (error) return <GalatMuat pesan={pesanGalat(error)} />
  if (isPending) return <Pemuat />

  const { profil, unit_usaha: unitUsaha, kinerja, pengurus } = data

  if (!profil.nama && unitUsaha.length === 0) {
    return (
      <KondisiKosong
        judul="Profil BUMDes belum tersedia"
        keterangan="Pemerintah desa belum mempublikasikan informasi badan usaha milik desa."
      />
    )
  }

  const terbaru = kinerja[0]

  return (
    <div className="space-y-8">
      <header>
        <h1 className="text-2xl">{profil.nama ?? 'Badan Usaha Milik Desa'}</h1>
        {profil.deskripsi && <p className="mt-2 max-w-3xl text-slate-600">{profil.deskripsi}</p>}
        <dl className="mt-4 flex flex-wrap gap-x-8 gap-y-2 text-sm">
          {profil.tahun_berdiri && (
            <div className="flex gap-2">
              <dt className="text-slate-600">Berdiri</dt>
              <dd className="font-medium">{profil.tahun_berdiri}</dd>
            </div>
          )}
          {profil.dasar_hukum && (
            <div className="flex gap-2">
              <dt className="text-slate-600">Dasar hukum</dt>
              <dd className="font-medium">{profil.dasar_hukum}</dd>
            </div>
          )}
          {profil.kontak && (
            <div className="flex gap-2">
              <dt className="text-slate-600">Kontak</dt>
              <dd className="font-medium">{profil.kontak}</dd>
            </div>
          )}
        </dl>
      </header>

      {terbaru && (
        <section aria-labelledby="kinerja-ringkas" className="grid gap-4 sm:grid-cols-3">
          <h2 id="kinerja-ringkas" className="sr-only">
            Ringkasan kinerja tahun {terbaru.tahun}
          </h2>
          <KartuStatistik
            label={`Pendapatan ${terbaru.tahun}`}
            nilai={rupiah(terbaru.pendapatan, true)}
            ikon={<TrendingUp aria-hidden className="size-5" />}
            nada="positif"
          />
          <KartuStatistik label={`Laba bersih ${terbaru.tahun}`} nilai={rupiah(terbaru.laba_bersih, true)} />
          <KartuStatistik
            label="Kontribusi ke PADes"
            nilai={rupiah(terbaru.kontribusi_pades, true)}
            keterangan="Pendapatan asli desa"
          />
        </section>
      )}

      <section aria-labelledby="unit-usaha">
        <h2 id="unit-usaha" className="mb-4 text-xl">
          Unit Usaha
        </h2>
        {unitUsaha.length === 0 ? (
          <KondisiKosong judul="Belum ada unit usaha yang dipublikasikan" />
        ) : (
          <ul className="grid gap-5 sm:grid-cols-2">
            {unitUsaha.map((unit) => (
              <li key={unit.slug}>
                <Kartu className="h-full">
                  {unit.foto && (
                    <img src={unit.foto} alt="" loading="lazy" className="h-40 w-full rounded-t-xl object-cover" />
                  )}
                  <IsiKartu>
                    <h3 className="flex items-start gap-2 text-base">
                      <Building2 aria-hidden className="mt-0.5 size-4 shrink-0 text-desa-700" />
                      {unit.nama}
                    </h3>
                    {unit.deskripsi && <p className="mt-2 text-sm text-slate-600">{unit.deskripsi}</p>}
                    {unit.penanggung_jawab && (
                      <p className="mt-2 text-sm text-slate-500">Penanggung jawab: {unit.penanggung_jawab}</p>
                    )}
                    {unit.kontak && (
                      <a
                        href={`https://wa.me/${unit.kontak.replace(/^0/, '62')}`}
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
        )}
      </section>

      {kinerja.length > 1 && (
        <GrafikBatang
          judul="Kinerja Keuangan per Tahun"
          deskripsi="Pendapatan dan kontribusi BUMDes terhadap pendapatan asli desa."
          data={kinerja.map((baris) => ({
            tahun: String(baris.tahun),
            pendapatan: baris.pendapatan,
            kontribusi: baris.kontribusi_pades,
          }))}
          kunciLabel="tahun"
          seri={[
            { kunci: 'pendapatan', label: 'Pendapatan', warna: WARNA_SERI.satu },
            { kunci: 'kontribusi', label: 'Kontribusi PADes', warna: WARNA_SERI.dua },
          ]}
          format={(nilai) => rupiah(nilai, true)}
        />
      )}

      {kinerja.length > 0 && (
        <Kartu>
          <KepalaKartu judul="Rincian Kinerja Tahunan" deskripsi="Angka yang telah disetujui untuk dipublikasikan." />
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <caption className="sr-only">Kinerja keuangan BUMDes per tahun</caption>
              <thead>
                <tr className="border-b border-slate-200 bg-slate-50 text-left text-slate-600">
                  <th scope="col" className="px-5 py-3 font-medium">Tahun</th>
                  <th scope="col" className="px-5 py-3 text-right font-medium">Pendapatan</th>
                  <th scope="col" className="px-5 py-3 text-right font-medium">Laba bersih</th>
                  <th scope="col" className="px-5 py-3 text-right font-medium">Kontribusi PADes</th>
                  <th scope="col" className="px-5 py-3 font-medium">Catatan</th>
                </tr>
              </thead>
              <tbody>
                {kinerja.map((baris) => (
                  <tr key={baris.tahun} className="border-b border-slate-100 last:border-0">
                    <th scope="row" className="px-5 py-3 text-left font-medium">{baris.tahun}</th>
                    <td className="px-5 py-3 text-right tabular-nums">{rupiah(baris.pendapatan)}</td>
                    <td className="px-5 py-3 text-right tabular-nums">{rupiah(baris.laba_bersih)}</td>
                    <td className="px-5 py-3 text-right tabular-nums">{rupiah(baris.kontribusi_pades)}</td>
                    <td className="px-5 py-3 text-slate-600">{baris.catatan ?? '-'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Kartu>
      )}

      {pengurus.length > 0 && (
        <Kartu>
          <KepalaKartu judul="Pengurus" />
          <IsiKartu>
            <ul className="grid gap-3 sm:grid-cols-3">
              {pengurus.map((orang) => (
                <li key={`${orang.nama}-${orang.jabatan}`} className="flex items-start gap-2 rounded-lg border border-slate-200 p-4">
                  <Users aria-hidden className="mt-0.5 size-4 shrink-0 text-desa-700" />
                  <div>
                    <p className="font-medium text-slate-900">{orang.nama}</p>
                    <p className="text-sm text-desa-700">{orang.jabatan}</p>
                  </div>
                </li>
              ))}
            </ul>
          </IsiKartu>
        </Kartu>
      )}
    </div>
  )
}
