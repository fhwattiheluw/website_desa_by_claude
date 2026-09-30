import { useState, type FormEvent } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertTriangle, Search } from 'lucide-react'
import { api, galatKolom, pesanGalat } from '@/lib/api'
import { useMeta } from '@/lib/meta'
import { tanggal } from '@/lib/format'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { AreaTeks, Isian } from '@/components/ui/Isian'
import { Tombol } from '@/components/ui/Tombol'
import { Lencana } from '@/components/ui/Lencana'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import { Captcha } from '@/components/ui/Captcha'
import { useCaptcha } from '@/lib/captcha'

interface Keberatan {
  alasan: string
  status: string
  tanggapan: string | null
  diajukan_pada: string | null
  tenggat_tanggapan: string | null
  ditanggapi_pada: string | null
}

interface HasilLacak {
  nomor_tiket: string
  informasi_diminta: string
  status: string
  jawaban: string | null
  diajukan_pada: string | null
  tenggat_jawaban: string | null
  melampaui_tenggat: boolean
  dapat_mengajukan_keberatan: boolean
  keberatan: Keberatan[]
}

const NADA: Record<string, 'netral' | 'info' | 'sukses' | 'bahaya'> = {
  diajukan: 'info',
  diproses: 'info',
  selesai: 'sukses',
  ditolak: 'bahaya',
}

/**
 * Pelacakan permohonan informasi publik beserta pengajuan keberatan
 * (REQ-F-PID-003, REQ-F-PID-007).
 */
export function LacakInformasi() {
  const [parameter, setParameter] = useSearchParams()
  const klien = useQueryClient()
  const captcha = useCaptcha()
  const kode = parameter.get('kode') ?? ''
  const [masukan, setMasukan] = useState(kode)
  const [alasan, setAlasan] = useState('')
  const [galat, setGalat] = useState<Record<string, string>>({})
  const [pesan, setPesan] = useState('')
  const [sukses, setSukses] = useState('')

  useMeta({
    judul: 'Lacak Permohonan Informasi Publik',
    deskripsi: 'Periksa status permohonan informasi publik dan ajukan keberatan bila permohonan ditolak.',
  })

  const { data, error, isFetching } = useQuery({
    queryKey: ['lacak-informasi', kode],
    queryFn: async () => (await api.get<HasilLacak>(`/permohonan-informasi/lacak/${kode}`)).data,
    enabled: kode.length > 0,
    retry: false,
  })

  const ajukan = useMutation({
    mutationFn: async () =>
      (
        await api.post<{ pesan: string }>(`/permohonan-informasi/lacak/${kode}/keberatan`, {
          alasan,
          ...captcha.muatan(),
        })
      ).data,
    onSuccess: (hasil) => {
      setSukses(hasil.pesan)
      setAlasan('')
      setGalat({})
      setPesan('')
      void klien.invalidateQueries({ queryKey: ['lacak-informasi', kode] })
    },
    onError: (kesalahan) => {
      setGalat(galatKolom(kesalahan))
      setPesan(pesanGalat(kesalahan))
      // Tantangan sekali pakai: pengiriman gagal selalu memerlukan yang baru.
      captcha.segarkan()
    },
  })

  const cari = (peristiwa: FormEvent) => {
    peristiwa.preventDefault()
    setSukses('')
    setPesan('')
    setParameter(masukan.trim() ? { kode: masukan.trim().toUpperCase() } : {})
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <header>
        <h1 className="text-2xl">Lacak Permohonan Informasi Publik</h1>
        <p className="mt-1 text-slate-600">
          Masukkan kode lacak yang Anda terima saat mengajukan permohonan informasi kepada PPID Desa.
        </p>
      </header>

      <Kartu>
        <IsiKartu>
          <form onSubmit={cari} className="flex flex-wrap items-end gap-3">
            <div className="min-w-48 flex-1">
              <Isian
                label="Kode lacak"
                value={masukan}
                onChange={(e) => setMasukan(e.target.value)}
                placeholder="Contoh: A1B2C3D4"
                autoComplete="off"
              />
            </div>
            <Tombol type="submit" memuat={isFetching}>
              <Search aria-hidden className="size-4" /> Lacak
            </Tombol>
          </form>
        </IsiKartu>
      </Kartu>

      {error && <Pemberitahuan jenis="bahaya">{pesanGalat(error)}</Pemberitahuan>}

      {data && (
        <>
          <Kartu>
            <KepalaKartu
              judul={data.nomor_tiket}
              deskripsi={`Diajukan ${tanggal(data.diajukan_pada)}`}
              aksi={<Lencana nada={NADA[data.status] ?? 'netral'}>{data.status}</Lencana>}
            />
            <IsiKartu className="space-y-3">
              <div>
                <p className="text-sm text-slate-600">Informasi yang diminta</p>
                <p className="whitespace-pre-line">{data.informasi_diminta}</p>
              </div>

              <div>
                <p className="text-sm text-slate-600">Tenggat jawaban</p>
                <p className="font-medium">{tanggal(data.tenggat_jawaban)}</p>
              </div>

              {data.melampaui_tenggat && (
                <Pemberitahuan jenis="peringatan" judul="Melampaui tenggat jawaban">
                  <span className="inline-flex items-center gap-1.5">
                    <AlertTriangle aria-hidden className="size-4" />
                    Permohonan Anda belum dijawab hingga melewati tenggat. Anda berhak mengajukan keberatan.
                  </span>
                </Pemberitahuan>
              )}

              {data.jawaban && (
                <div>
                  <p className="text-sm text-slate-600">Jawaban PPID</p>
                  <p className="whitespace-pre-line">{data.jawaban}</p>
                </div>
              )}
            </IsiKartu>
          </Kartu>

          {data.keberatan.map((satu, indeks) => (
            <Kartu key={indeks}>
              <KepalaKartu
                judul="Keberatan Anda"
                deskripsi={`Diajukan ${tanggal(satu.diajukan_pada)}`}
                aksi={
                  <Lencana nada={satu.status === 'ditanggapi' ? 'sukses' : 'peringatan'}>
                    {satu.status === 'ditanggapi' ? 'Sudah ditanggapi' : 'Menunggu tanggapan'}
                  </Lencana>
                }
              />
              <IsiKartu className="space-y-3">
                <p className="whitespace-pre-line text-slate-700">{satu.alasan}</p>
                {satu.status === 'ditanggapi' ? (
                  <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p className="text-sm font-medium text-slate-900">Tanggapan atasan PPID</p>
                    <p className="mt-1 whitespace-pre-line text-slate-700">{satu.tanggapan}</p>
                    <p className="mt-2 text-xs text-slate-500">{tanggal(satu.ditanggapi_pada)}</p>
                  </div>
                ) : (
                  <p className="text-sm text-slate-600">
                    Ditanggapi paling lambat {tanggal(satu.tenggat_tanggapan)} sesuai UU 14/2008.
                  </p>
                )}
              </IsiKartu>
            </Kartu>
          ))}

          {sukses && <Pemberitahuan jenis="sukses">{sukses}</Pemberitahuan>}

          {data.dapat_mengajukan_keberatan && (
            <Kartu>
              <KepalaKartu
                judul="Ajukan Keberatan"
                deskripsi="Bila permohonan Anda ditolak atau tidak dijawab dalam tenggat, Anda berhak mengajukan keberatan kepada atasan PPID."
              />
              <IsiKartu className="space-y-4">
                {pesan && <Pemberitahuan jenis="bahaya">{pesan}</Pemberitahuan>}

                <AreaTeks
                  label="Alasan keberatan"
                  rows={4}
                  value={alasan}
                  onChange={(e) => setAlasan(e.target.value)}
                  petunjuk="Jelaskan dasar keberatan Anda, minimal 20 karakter."
                  galat={galat['alasan']}
                />

                <Captcha kendali={captcha} galat={galat['captcha_jawaban']} />

                <p className="text-sm text-slate-500">
                  Keberatan ditanggapi paling lambat 30 hari kerja. Bila tanggapan belum memuaskan, Anda dapat
                  mengajukan sengketa informasi kepada Komisi Informasi provinsi.
                </p>

                <Tombol memuat={ajukan.isPending} onClick={() => ajukan.mutate()}>
                  Kirim Keberatan
                </Tombol>
              </IsiKartu>
            </Kartu>
          )}
        </>
      )}
    </div>
  )
}
