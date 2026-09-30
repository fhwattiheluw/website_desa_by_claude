import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertTriangle, Check, X } from 'lucide-react'
import { api, galatKolom, pesanGalat } from '@/lib/api'
import { tanggal } from '@/lib/format'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { AreaTeks } from '@/components/ui/Isian'
import { Tombol } from '@/components/ui/Tombol'
import { Lencana } from '@/components/ui/Lencana'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import { Paginasi } from '@/components/ui/Paginasi'
import { GalatMuat, KondisiKosong, Rangka } from '@/components/ui/Status'
import { useAuth } from '@/lib/auth'
import type { Halaman } from '@/types'

interface ButirKomentar {
  id: number
  nama: string
  isi: string
  status: 'menunggu' | 'disetujui' | 'ditolak'
  berakun: boolean
  konten: { judul: string; tautan: string } | null
  moderator: string | null
  alasan_penolakan: string | null
  dikirim_pada: string | null
}

interface ButirKeberatan {
  id: number
  alasan: string
  status: 'diajukan' | 'ditanggapi'
  tanggapan: string | null
  petugas: string | null
  permohonan: { nomor_tiket: string; informasi_diminta: string; status: string } | null
  diajukan_pada: string | null
  tenggat_tanggapan: string | null
  melampaui_tenggat: boolean
}

const NADA_KOMENTAR = { menunggu: 'peringatan', disetujui: 'sukses', ditolak: 'bahaya' } as const

function Komentar() {
  const klien = useQueryClient()
  const [halaman, setHalaman] = useState(1)
  const [dibuka, setDibuka] = useState<number | null>(null)
  const [alasan, setAlasan] = useState('')
  const [galat, setGalat] = useState<Record<string, string>>({})
  const [pesan, setPesan] = useState('')

  const { data, isPending, error } = useQuery({
    queryKey: ['admin-komentar', halaman],
    queryFn: async () =>
      (await api.get<Halaman<ButirKomentar>>('/admin/komentar', { params: { page: halaman } })).data,
  })

  const moderasi = useMutation({
    mutationFn: async ({ id, keputusan }: { id: number; keputusan: 'setujui' | 'tolak' }) =>
      (await api.post(`/admin/komentar/${id}/moderasi`, { keputusan, alasan })).data,
    onSuccess: () => {
      setDibuka(null)
      setAlasan('')
      setGalat({})
      setPesan('')
      void klien.invalidateQueries({ queryKey: ['admin-komentar'] })
    },
    onError: (kesalahan) => {
      setGalat(galatKolom(kesalahan))
      setPesan(pesanGalat(kesalahan))
    },
  })

  if (error) return <GalatMuat pesan={pesanGalat(error)} />
  if (isPending) return <Rangka baris={3} />

  if (data.data.length === 0) {
    return <KondisiKosong judul="Belum ada komentar" keterangan="Komentar pembaca akan tampil di sini untuk ditinjau." />
  }

  return (
    <div className="space-y-4">
      {pesan && <Pemberitahuan jenis="bahaya">{pesan}</Pemberitahuan>}

      <ul className="space-y-4">
        {data.data.map((satu) => (
          <li key={satu.id}>
            <Kartu>
              <KepalaKartu
                judul={satu.nama}
                deskripsi={`${satu.berakun ? 'Pengguna terdaftar' : 'Tamu'} · ${tanggal(satu.dikirim_pada, true)}${
                  satu.konten ? ` · pada "${satu.konten.judul}"` : ''
                }`}
                aksi={<Lencana nada={NADA_KOMENTAR[satu.status]}>{satu.status}</Lencana>}
              />
              <IsiKartu className="space-y-3">
                <p className="whitespace-pre-line text-slate-700">{satu.isi}</p>

                {satu.alasan_penolakan && (
                  <p className="text-sm text-slate-600">Alasan penolakan: {satu.alasan_penolakan}</p>
                )}

                {satu.status === 'menunggu' &&
                  (dibuka === satu.id ? (
                    <div className="space-y-3 border-t border-slate-100 pt-3">
                      <AreaTeks
                        label="Alasan penolakan"
                        rows={2}
                        value={alasan}
                        onChange={(e) => setAlasan(e.target.value)}
                        petunjuk="Wajib bila menolak. Dicatat agar keputusan moderasi dapat ditinjau."
                        galat={galat['alasan']}
                      />
                      <div className="flex flex-wrap gap-2">
                        <Tombol
                          ukuran="kecil"
                          memuat={moderasi.isPending}
                          onClick={() => moderasi.mutate({ id: satu.id, keputusan: 'setujui' })}
                        >
                          <Check aria-hidden className="size-4" /> Tayangkan
                        </Tombol>
                        <Tombol
                          ragam="bahaya"
                          ukuran="kecil"
                          memuat={moderasi.isPending}
                          onClick={() => moderasi.mutate({ id: satu.id, keputusan: 'tolak' })}
                        >
                          <X aria-hidden className="size-4" /> Tolak
                        </Tombol>
                        <Tombol ragam="halus" ukuran="kecil" onClick={() => setDibuka(null)}>
                          Batal
                        </Tombol>
                      </div>
                    </div>
                  ) : (
                    <Tombol ukuran="kecil" onClick={() => { setDibuka(satu.id); setAlasan(''); setGalat({}) }}>
                      Tinjau Komentar
                    </Tombol>
                  ))}

                {satu.moderator && satu.status !== 'menunggu' && (
                  <p className="text-sm text-slate-600">Dimoderasi oleh {satu.moderator}.</p>
                )}
              </IsiKartu>
            </Kartu>
          </li>
        ))}
      </ul>

      <Paginasi halaman={data.current_page} totalHalaman={data.last_page} onPindah={setHalaman} />
    </div>
  )
}

function Keberatan() {
  const klien = useQueryClient()
  const [dibuka, setDibuka] = useState<number | null>(null)
  const [tanggapan, setTanggapan] = useState('')
  const [galat, setGalat] = useState<Record<string, string>>({})
  const [pesan, setPesan] = useState('')

  const { data, isPending, error } = useQuery({
    queryKey: ['admin-keberatan'],
    queryFn: async () => (await api.get<Halaman<ButirKeberatan>>('/admin/keberatan-informasi')).data,
  })

  const tanggapi = useMutation({
    mutationFn: async (id: number) =>
      (await api.post(`/admin/keberatan-informasi/${id}/tanggapi`, { tanggapan })).data,
    onSuccess: () => {
      setDibuka(null)
      setTanggapan('')
      setGalat({})
      setPesan('')
      void klien.invalidateQueries({ queryKey: ['admin-keberatan'] })
    },
    onError: (kesalahan) => {
      setGalat(galatKolom(kesalahan))
      setPesan(pesanGalat(kesalahan))
    },
  })

  if (error) return <GalatMuat pesan={pesanGalat(error)} />
  if (isPending) return <Rangka baris={3} />

  if (data.data.length === 0) {
    return (
      <KondisiKosong
        judul="Belum ada keberatan"
        keterangan="Keberatan atas penolakan permohonan informasi akan tampil di sini."
      />
    )
  }

  return (
    <div className="space-y-4">
      {pesan && <Pemberitahuan jenis="bahaya">{pesan}</Pemberitahuan>}

      <ul className="space-y-4">
        {data.data.map((satu) => (
          <li key={satu.id}>
            <Kartu>
              <KepalaKartu
                judul={satu.permohonan?.nomor_tiket ?? 'Permohonan informasi'}
                deskripsi={`Diajukan ${tanggal(satu.diajukan_pada)}`}
                aksi={
                  <Lencana nada={satu.status === 'ditanggapi' ? 'sukses' : 'peringatan'}>
                    {satu.status === 'ditanggapi' ? 'Ditanggapi' : 'Menunggu'}
                  </Lencana>
                }
              />
              <IsiKartu className="space-y-3">
                {satu.melampaui_tenggat && (
                  <p className="flex items-center gap-2 text-sm font-medium text-red-700">
                    <AlertTriangle aria-hidden className="size-4" />
                    Melampaui tenggat {tanggal(satu.tenggat_tanggapan)}.
                  </p>
                )}

                {satu.permohonan && (
                  <p className="text-sm text-slate-600">
                    Informasi diminta: {satu.permohonan.informasi_diminta}
                  </p>
                )}

                <p className="whitespace-pre-line text-slate-700">{satu.alasan}</p>

                {satu.status === 'ditanggapi' ? (
                  <div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm">
                    <p className="font-medium">Tanggapan oleh {satu.petugas ?? 'petugas'}</p>
                    <p className="mt-1 whitespace-pre-line text-slate-700">{satu.tanggapan}</p>
                  </div>
                ) : dibuka === satu.id ? (
                  <div className="space-y-3 border-t border-slate-100 pt-3">
                    <AreaTeks
                      label="Tanggapan atasan PPID"
                      rows={4}
                      value={tanggapan}
                      onChange={(e) => setTanggapan(e.target.value)}
                      petunjuk="Minimal 20 karakter. Tanggapan ini dibaca pemohon dan dapat dibawa ke Komisi Informasi."
                      galat={galat['tanggapan']}
                    />
                    <div className="flex flex-wrap gap-2">
                      <Tombol ukuran="kecil" memuat={tanggapi.isPending} onClick={() => tanggapi.mutate(satu.id)}>
                        Kirim Tanggapan
                      </Tombol>
                      <Tombol ragam="halus" ukuran="kecil" onClick={() => setDibuka(null)}>
                        Batal
                      </Tombol>
                    </div>
                  </div>
                ) : (
                  <Tombol ukuran="kecil" onClick={() => { setDibuka(satu.id); setTanggapan(''); setGalat({}) }}>
                    Tanggapi Keberatan
                  </Tombol>
                )}
              </IsiKartu>
            </Kartu>
          </li>
        ))}
      </ul>
    </div>
  )
}

/** REQ-F-KNT-013 dan REQ-F-PID-007: moderasi komentar dan keberatan informasi. */
export function Moderasi() {
  const { punyaIzin } = useAuth()
  const bolehKeberatan = punyaIzin('konten.terbit')
  const [tab, setTab] = useState<'komentar' | 'keberatan'>('komentar')

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl">Moderasi</h1>
        <p className="mt-1 text-slate-600">
          Komentar pembaca dan keberatan atas permohonan informasi publik yang menunggu tindakan Anda.
        </p>
      </header>

      {bolehKeberatan && (
        <div className="flex gap-2" role="group" aria-label="Pilih jenis moderasi">
          {(['komentar', 'keberatan'] as const).map((pilihan) => (
            <button
              key={pilihan}
              type="button"
              onClick={() => setTab(pilihan)}
              aria-pressed={tab === pilihan}
              className={`min-h-11 rounded-lg border px-4 text-sm font-medium ${
                tab === pilihan
                  ? 'border-desa-700 bg-desa-50 text-desa-800'
                  : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
              }`}
            >
              {pilihan === 'komentar' ? 'Komentar Pembaca' : 'Keberatan Informasi'}
            </button>
          ))}
        </div>
      )}

      {tab === 'komentar' || !bolehKeberatan ? <Komentar /> : <Keberatan />}
    </div>
  )
}
