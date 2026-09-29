import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { FileText, Scale } from 'lucide-react'
import { api, pesanGalat } from '@/lib/api'
import { judulKan } from '@/lib/format'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { Isian, Pilihan } from '@/components/ui/Isian'
import { Lencana } from '@/components/ui/Lencana'
import { Paginasi } from '@/components/ui/Paginasi'
import { GalatMuat, KondisiKosong, Rangka } from '@/components/ui/Status'
import type { Halaman } from '@/types'

interface ProdukHukum {
  id: number
  jenis: string
  nomor: string
  tahun: number
  judul: string
  tentang: string | null
  berlaku: boolean
  keterangan: string
  dicabut_oleh: { jenis: string; nomor: string; tahun: number; judul: string } | null
  berkas: string | null
}

/** REQ-F-PID-004..006: repositori produk hukum desa. */
export function ProdukHukum() {
  const [halaman, setHalaman] = useState(1)
  const [jenis, setJenis] = useState('')
  const [q, setQ] = useState('')

  const { data, isPending, error } = useQuery({
    queryKey: ['produk-hukum', halaman, jenis, q],
    queryFn: async () =>
      (
        await api.get<Halaman<ProdukHukum>>('/produk-hukum', {
          params: { page: halaman, jenis: jenis || undefined, q: q || undefined },
        })
      ).data,
  })

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl">Produk Hukum Desa</h1>
        <p className="mt-1 text-slate-600">
          Peraturan Desa, Peraturan Kepala Desa, dan Keputusan Kepala Desa. Dokumen yang telah dicabut tetap dapat
          diakses untuk kepentingan arsip.
        </p>
      </header>

      <div className="grid gap-4 sm:grid-cols-2">
        <Isian
          label="Cari produk hukum"
          value={q}
          onChange={(e) => { setQ(e.target.value); setHalaman(1) }}
          placeholder="Kata kunci judul atau nomor"
        />
        <Pilihan
          label="Jenis"
          value={jenis}
          onChange={(e) => { setJenis(e.target.value); setHalaman(1) }}
          kosong="Semua jenis"
          pilihan={[
            { nilai: 'perdes', teks: 'Peraturan Desa' },
            { nilai: 'perkades', teks: 'Peraturan Kepala Desa' },
            { nilai: 'sk_kades', teks: 'Keputusan Kepala Desa' },
          ]}
        />
      </div>

      {error ? (
        <GalatMuat pesan={pesanGalat(error)} />
      ) : isPending ? (
        <Rangka baris={4} />
      ) : data.data.length === 0 ? (
        <KondisiKosong judul="Produk hukum tidak ditemukan" keterangan="Ubah kata kunci atau saringan jenis." />
      ) : (
        <>
          <ul className="space-y-3">
            {data.data.map((produk) => (
              <li key={produk.id}>
                <Kartu>
                  <IsiKartu>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div className="flex min-w-0 gap-3">
                        <Scale aria-hidden className="mt-0.5 size-5 shrink-0 text-desa-700" />
                        <div className="min-w-0">
                          <p className="font-medium text-slate-900">
                            {judulKan(produk.jenis)} Nomor {produk.nomor} Tahun {produk.tahun}
                          </p>
                          <p className="mt-0.5 text-sm text-slate-700">{produk.judul}</p>
                          {produk.tentang && <p className="mt-1 text-sm text-slate-600">Tentang: {produk.tentang}</p>}
                          {produk.dicabut_oleh && (
                            <p className="mt-1 text-sm text-amber-800">
                              Dicabut oleh {judulKan(produk.dicabut_oleh.jenis)} Nomor {produk.dicabut_oleh.nomor} Tahun{' '}
                              {produk.dicabut_oleh.tahun}
                            </p>
                          )}
                        </div>
                      </div>
                      <div className="flex items-center gap-3">
                        <Lencana nada={produk.berlaku ? 'sukses' : 'peringatan'}>{produk.keterangan}</Lencana>
                        {produk.berkas && (
                          <a
                            href={produk.berkas}
                            className="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-slate-300 px-3 text-sm hover:bg-slate-50"
                          >
                            <FileText aria-hidden className="size-4" /> Unduh
                          </a>
                        )}
                      </div>
                    </div>
                  </IsiKartu>
                </Kartu>
              </li>
            ))}
          </ul>

          <Paginasi halaman={data.current_page} totalHalaman={data.last_page} total={data.total} onPindah={setHalaman} />
        </>
      )}
    </div>
  )
}

interface InformasiPublik {
  judul: string
  ringkasan: string | null
  penanggung_jawab: string | null
  periode_terbit: string | null
  berkas: string | null
}

const KLASIFIKASI: { kunci: 'berkala' | 'serta_merta' | 'setiap_saat'; judul: string; keterangan: string }[] = [
  {
    kunci: 'berkala',
    judul: 'Informasi Berkala',
    keterangan: 'Informasi yang wajib disediakan dan diumumkan secara berkala.',
  },
  {
    kunci: 'serta_merta',
    judul: 'Informasi Serta-Merta',
    keterangan: 'Informasi yang wajib diumumkan segera karena menyangkut hajat hidup dan ketertiban umum.',
  },
  {
    kunci: 'setiap_saat',
    judul: 'Informasi Setiap Saat',
    keterangan: 'Informasi yang wajib tersedia setiap saat dan dapat diminta pemohon.',
  },
]

/** REQ-F-PID-001..003: laman PPID dan permohonan informasi publik. */
export function Ppid() {
  const { data, isPending, error } = useQuery({
    queryKey: ['informasi-publik'],
    queryFn: async () => (await api.get<Record<string, InformasiPublik[]>>('/informasi-publik')).data,
  })

  return (
    <div className="space-y-8">
      <header>
        <h1 className="text-2xl">PPID Desa</h1>
        <p className="mt-1 text-slate-600">
          Pejabat Pengelola Informasi dan Dokumentasi menjamin hak masyarakat memperoleh informasi publik sesuai
          Undang-Undang Nomor 14 Tahun 2008.
        </p>
      </header>

      <Kartu>
        <KepalaKartu judul="Prosedur Permohonan Informasi" />
        <IsiKartu>
          <ol className="list-decimal space-y-2 pl-5 text-slate-700">
            <li>Ajukan permohonan melalui formulir daring atau datang langsung ke kantor desa.</li>
            <li>Petugas mencatat permohonan dan menerbitkan nomor tiket.</li>
            <li>Permohonan dijawab paling lambat 10 hari kerja dan dapat diperpanjang 7 hari kerja dengan pemberitahuan.</li>
            <li>Apabila permohonan ditolak, pemohon dapat mengajukan keberatan kepada atasan PPID.</li>
          </ol>
          <a
            href="/ppid/permohonan"
            className="mt-4 inline-flex min-h-11 items-center rounded-lg bg-desa-700 px-4 text-sm font-medium text-white hover:bg-desa-800"
          >
            Ajukan Permohonan Informasi
          </a>
        </IsiKartu>
      </Kartu>

      {error ? (
        <GalatMuat pesan={pesanGalat(error)} />
      ) : isPending ? (
        <Rangka baris={3} />
      ) : (
        KLASIFIKASI.map((klasifikasi) => (
          <Kartu key={klasifikasi.kunci}>
            <KepalaKartu judul={klasifikasi.judul} deskripsi={klasifikasi.keterangan} />
            <ul className="divide-y divide-slate-100">
              {(data?.[klasifikasi.kunci] ?? []).map((item) => (
                <li key={item.judul} className="flex flex-wrap items-start justify-between gap-3 px-5 py-4">
                  <div className="min-w-0">
                    <p className="font-medium">{item.judul}</p>
                    {item.ringkasan && <p className="mt-0.5 text-sm text-slate-600">{item.ringkasan}</p>}
                    <p className="mt-1 text-xs text-slate-500">
                      {item.penanggung_jawab} · Periode terbit: {item.periode_terbit ?? '-'}
                    </p>
                  </div>
                  {item.berkas && (
                    <a href={item.berkas} className="text-sm font-medium text-desa-700 hover:underline">
                      Unduh berkas
                    </a>
                  )}
                </li>
              ))}
              {(data?.[klasifikasi.kunci] ?? []).length === 0 && (
                <li className="px-5 py-4 text-sm text-slate-600">Belum ada informasi pada klasifikasi ini.</li>
              )}
            </ul>
          </Kartu>
        ))
      )}
    </div>
  )
}
