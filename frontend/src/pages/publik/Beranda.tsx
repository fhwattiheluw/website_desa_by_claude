import { Link } from 'react-router-dom'
import { ArrowRight, CalendarDays, FileText, Megaphone, UserRound, Users } from 'lucide-react'
import { useBeranda } from '@/lib/kueri'
import { angka, tanggal, tanggalRelatif } from '@/lib/format'
import { pesanGalat } from '@/lib/api'
import { useDataTerstruktur, useMeta } from '@/lib/meta'
import { Kartu, KartuStatistik, IsiKartu } from '@/components/ui/Kartu'
import { TautanTombol } from '@/components/ui/Tombol'
import { GalatMuat, Rangka } from '@/components/ui/Status'
import { Lencana } from '@/components/ui/Lencana'
import { Bergilir } from '@/components/ui/Bergilir'
import type { Konten } from '@/types'

interface DataBeranda {
  desa: Record<string, string>
  // Koleksi resource yang disarangkan pada respons beranda terbit sebagai
  // larik biasa, bukan objek ber-kunci "data".
  sorotan: Konten[]
  berita: Konten[]
  pengumuman: Konten[]
  agenda: Konten[]
  statistik: { periode: string; total_penduduk: number } | null
  sambutan: { nama: string | null; jabatan: string; foto: string | null; kutipan: string } | null
  terpopuler: Konten[]
  produk_unggulan: {
    nama_usaha: string
    slug: string
    kategori: string
    deskripsi: string | null
    foto: string | null
  }[]
}

const TAUTAN_CEPAT = [
  { ke: '/layanan', teks: 'Ajukan Surat', keterangan: '10 jenis layanan daring', ikon: FileText },
  { ke: '/pengaduan', teks: 'Kirim Pengaduan', keterangan: 'Sampaikan aspirasi Anda', ikon: Megaphone },
  { ke: '/transparansi/apbdes', teks: 'Transparansi APBDes', keterangan: 'Pendapatan dan belanja desa', ikon: Users },
  { ke: '/layanan/verifikasi', teks: 'Verifikasi Surat', keterangan: 'Periksa keaslian dokumen', ikon: CalendarDays },
]

export function Beranda() {
  const { data, isPending, error } = useBeranda<DataBeranda>()

  const desa = data?.desa ?? {}

  useMeta({
    judul: desa.nama_desa ? `Desa ${desa.nama_desa}` : 'Portal Desa',
    deskripsi:
      desa.tagline ??
      'Portal resmi pemerintah desa: informasi, transparansi anggaran, layanan administrasi daring, dan pengaduan masyarakat.',
  })

  useDataTerstruktur(
    desa.nama_desa
      ? {
          '@type': 'GovernmentOrganization',
          name: `Pemerintah Desa ${desa.nama_desa}`,
          url: window.location.origin,
          address: {
            '@type': 'PostalAddress',
            streetAddress: desa.alamat,
            addressLocality: desa.kecamatan,
            addressRegion: desa.provinsi,
            postalCode: desa.kode_pos,
            addressCountry: 'ID',
          },
          telephone: desa.telepon,
          email: desa.email,
          areaServed: `Desa ${desa.nama_desa}`,
        }
      : null,
  )

  if (error) return <GalatMuat pesan={pesanGalat(error)} />

  return (
    <div className="space-y-10">
      <section className="rounded-2xl bg-gradient-to-br from-desa-800 to-desa-600 px-6 py-10 text-white sm:px-10 sm:py-14">
        <p className="text-sm font-medium text-desa-100">Portal Resmi Pemerintah Desa</p>
        <h1 className="mt-2 text-3xl font-bold text-white sm:text-4xl">
          Desa {desa.nama_desa ?? '—'}
        </h1>
        <p className="mt-3 max-w-2xl text-desa-50">{desa.tagline ?? 'Pelayanan cepat, informasi terbuka.'}</p>
        <div className="mt-6 flex flex-wrap gap-3">
          <TautanTombol to="/layanan" ragam="halus" ukuran="besar">
            Ajukan Layanan Surat <ArrowRight aria-hidden className="size-4" />
          </TautanTombol>
          <Link
            to="/profil"
            className="inline-flex min-h-12 items-center justify-center rounded-lg border border-white/50 px-6 text-base font-medium text-white transition-colors hover:bg-white/10"
          >
            Kenali Desa Kami
          </Link>
        </div>
      </section>

      <section aria-labelledby="tautan-cepat">
        <h2 id="tautan-cepat" className="sr-only">
          Tautan cepat layanan
        </h2>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {TAUTAN_CEPAT.map((butir) => (
            <Link
              key={butir.ke}
              to={butir.ke}
              className="group rounded-xl border border-slate-200 bg-white p-5 transition-colors hover:border-desa-300 hover:bg-desa-50/40"
            >
              <butir.ikon aria-hidden className="size-6 text-desa-700" />
              <p className="mt-3 font-medium text-slate-900 group-hover:text-desa-800">{butir.teks}</p>
              <p className="mt-0.5 text-sm text-slate-600">{butir.keterangan}</p>
            </Link>
          ))}
        </div>
      </section>

      {(data?.produk_unggulan.length ?? 0) > 0 && (
        <section aria-labelledby="produk-unggulan">
          <div className="mb-4 flex items-end justify-between gap-4">
            <h2 id="produk-unggulan" className="text-xl">
              Produk Unggulan Desa
            </h2>
            <Link to="/potensi/umkm" className="inline-flex items-center gap-1 text-sm font-medium text-desa-700 hover:underline">
              Direktori UMKM <ArrowRight aria-hidden className="size-4" />
            </Link>
          </div>

          <Bergilir jumlah={data!.produk_unggulan.length} judul="Produk unggulan">
            {(indeks) => {
              const produk = data!.produk_unggulan[indeks]

              return (
                <Kartu>
                  <div className="flex flex-col gap-5 sm:flex-row">
                    {produk.foto && (
                      <img
                        src={produk.foto}
                        alt=""
                        loading="lazy"
                        className="h-40 w-full rounded-t-xl object-cover sm:h-auto sm:w-48 sm:rounded-l-xl sm:rounded-tr-none"
                      />
                    )}
                    <IsiKartu className="flex-1">
                      <Lencana>{produk.kategori}</Lencana>
                      <h3 className="mt-2 text-base">{produk.nama_usaha}</h3>
                      {produk.deskripsi && <p className="mt-1 text-sm text-slate-600">{produk.deskripsi}</p>}
                    </IsiKartu>
                  </div>
                </Kartu>
              )
            }}
          </Bergilir>
        </section>
      )}

      {(data?.terpopuler.length ?? 0) > 0 && (
        <section aria-labelledby="terpopuler">
          <h2 id="terpopuler" className="mb-4 text-xl">
            Terpopuler
          </h2>
          <Kartu>
            <ol className="divide-y divide-slate-100">
              {data!.terpopuler.map((konten, urutan) => (
                <li key={konten.id} className="flex items-start gap-3 px-5 py-3">
                  <span aria-hidden className="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-desa-50 text-xs font-semibold text-desa-800">
                    {urutan + 1}
                  </span>
                  <span className="min-w-0">
                    <Link to={`/${konten.tipe}/${konten.slug}`} className="font-medium hover:text-desa-700">
                      {konten.judul}
                    </Link>
                    <span className="mt-0.5 block text-xs text-slate-500">
                      {angka(konten.dibaca ?? 0)} kali dibaca · {tanggal(konten.terbit_pada)}
                    </span>
                  </span>
                </li>
              ))}
            </ol>
          </Kartu>
        </section>
      )}

      {data?.sambutan && (
        <section aria-labelledby="sambutan-kepala-desa">
          <h2 id="sambutan-kepala-desa" className="mb-4 text-xl">
            Sambutan {data.sambutan.jabatan}
          </h2>
          <figure className="flex flex-col gap-5 rounded-xl border border-slate-200 bg-white p-6 sm:flex-row sm:items-start">
            {data.sambutan.foto ? (
              <img
                src={data.sambutan.foto}
                alt={`Foto ${data.sambutan.nama ?? data.sambutan.jabatan}`}
                loading="lazy"
                className="size-24 shrink-0 rounded-full object-cover"
              />
            ) : (
              <span className="flex size-24 shrink-0 items-center justify-center rounded-full bg-desa-50" aria-hidden>
                <UserRound className="size-10 text-desa-700" />
              </span>
            )}
            <div>
              <blockquote className="leading-relaxed text-slate-700">{data.sambutan.kutipan}</blockquote>
              <figcaption className="mt-3 text-sm">
                <span className="font-medium text-slate-900">{data.sambutan.nama ?? '-'}</span>
                <span className="block text-desa-700">{data.sambutan.jabatan}</span>
              </figcaption>
            </div>
          </figure>
        </section>
      )}

      {data?.statistik && (
        <section aria-labelledby="ringkasan-desa" className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <h2 id="ringkasan-desa" className="sr-only">
            Ringkasan data desa
          </h2>
          <KartuStatistik
            label="Jumlah penduduk"
            nilai={angka(data.statistik.total_penduduk)}
            keterangan={data.statistik.periode}
          />
          <KartuStatistik label="Luas wilayah" nilai={desa.luas_wilayah ?? '-'} keterangan="Berdasarkan profil desa" />
          <KartuStatistik label="Jumlah dusun" nilai={desa.jumlah_dusun ?? '-'} keterangan={`${desa.jumlah_rw ?? '-'} RW · ${desa.jumlah_rt ?? '-'} RT`} />
          <KartuStatistik label="Jam pelayanan" nilai={<span className="text-base">{desa.jam_pelayanan ?? '-'}</span>} />
        </section>
      )}

      <section aria-labelledby="berita-terbaru">
        <div className="mb-4 flex items-end justify-between gap-4">
          <h2 id="berita-terbaru" className="text-xl">Berita Desa</h2>
          <Link to="/berita" className="text-sm font-medium text-desa-700 hover:underline">
            Lihat semua berita
          </Link>
        </div>

        {isPending ? (
          <Rangka baris={3} />
        ) : (
          <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {(data?.berita ?? []).map((berita) => (
              <article key={berita.id} className="flex flex-col rounded-xl border border-slate-200 bg-white">
                {berita.gambar?.url && (
                  <img
                    src={berita.gambar.url}
                    alt={berita.gambar.alt ?? ''}
                    loading="lazy"
                    className="h-40 w-full rounded-t-xl object-cover"
                  />
                )}
                <div className="flex flex-1 flex-col p-5">
                  {berita.kategori && <Lencana nada="sukses">{berita.kategori.nama}</Lencana>}
                  <h3 className="mt-2 text-base leading-snug">
                    <Link to={`/berita/${berita.slug}`} className="hover:text-desa-700">
                      {berita.judul}
                    </Link>
                  </h3>
                  <p className="mt-2 line-clamp-3 flex-1 text-sm text-slate-600">{berita.ringkasan}</p>
                  <p className="mt-3 text-xs text-slate-500">{tanggalRelatif(berita.terbit_pada)}</p>
                </div>
              </article>
            ))}
          </div>
        )}
      </section>

      <div className="grid gap-6 lg:grid-cols-2">
        <section aria-labelledby="pengumuman">
          <h2 id="pengumuman" className="mb-4 text-xl">Pengumuman</h2>
          <Kartu>
            <ul className="divide-y divide-slate-100">
              {(data?.pengumuman ?? []).map((item) => (
                <li key={item.id} className="p-5">
                  <Link to={`/pengumuman/${item.slug}`} className="font-medium hover:text-desa-700">
                    {item.judul}
                  </Link>
                  <p className="mt-1 line-clamp-2 text-sm text-slate-600">{item.ringkasan}</p>
                  <p className="mt-2 text-xs text-slate-500">
                    Berlaku sampai {tanggal(item.kedaluwarsa_pada)}
                  </p>
                </li>
              ))}
              {data?.pengumuman.length === 0 && (
                <li className="p-5 text-sm text-slate-600">Belum ada pengumuman aktif.</li>
              )}
            </ul>
          </Kartu>
        </section>

        <section aria-labelledby="agenda">
          <h2 id="agenda" className="mb-4 text-xl">Agenda Kegiatan</h2>
          <Kartu>
            <ul className="divide-y divide-slate-100">
              {(data?.agenda ?? []).map((item) => (
                <li key={item.id} className="flex gap-4 p-5">
                  <div className="grid size-14 shrink-0 place-items-center rounded-lg bg-desa-50 text-desa-800">
                    <span className="text-lg font-semibold leading-none">
                      {item.mulai_pada ? new Date(item.mulai_pada).getDate() : '-'}
                    </span>
                    <span className="text-xs">
                      {item.mulai_pada
                        ? new Date(item.mulai_pada).toLocaleDateString('id-ID', { month: 'short' })
                        : ''}
                    </span>
                  </div>
                  <div className="min-w-0">
                    <p className="font-medium">{item.judul}</p>
                    <p className="mt-0.5 text-sm text-slate-600">{item.lokasi}</p>
                    <p className="mt-1 text-xs text-slate-500">{tanggal(item.mulai_pada, true)}</p>
                  </div>
                </li>
              ))}
              {data?.agenda.length === 0 && (
                <li className="p-5 text-sm text-slate-600">Belum ada agenda mendatang.</li>
              )}
            </ul>
          </Kartu>
        </section>
      </div>
    </div>
  )
}
