import { useState, type FormEvent } from 'react'
import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { BadgeCheck, ShieldAlert } from 'lucide-react'
import { api } from '@/lib/api'
import { tanggal } from '@/lib/format'
import { useMeta } from '@/lib/meta'
import { Kartu, IsiKartu } from '@/components/ui/Kartu'
import { Isian } from '@/components/ui/Isian'
import { Tombol } from '@/components/ui/Tombol'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'

interface HasilVerifikasi {
  ditemukan: boolean
  nomor_surat?: string
  jenis_surat?: string
  tanggal_terbit?: string
  ditandatangani_oleh?: string
  status_keabsahan?: string
  sah?: boolean
  pesan: string
}

/** REQ-F-SRT-016: verifikasi publik tanpa memaparkan data pribadi pemohon. */
export function VerifikasiSurat() {
  const { kode: kodeParam } = useParams()
  const [kode, setKode] = useState(kodeParam ?? '')
  const [dicari, setDicari] = useState(kodeParam ?? '')

  useMeta({
    judul: 'Verifikasi Keabsahan Surat',
    deskripsi: 'Periksa keaslian surat terbitan pemerintah desa menggunakan kode verifikasi atau kode QR.',
  })

  const { data, isFetching, error } = useQuery({
    queryKey: ['verifikasi-surat', dicari],
    queryFn: async () => {
      try {
        return (await api.get<HasilVerifikasi>(`/surat/verifikasi/${dicari}`)).data
      } catch (galat) {
        // Kode tidak dikenali bukan kegagalan sistem, melainkan hasil pemeriksaan.
        if ((galat as { response?: { status?: number } }).response?.status === 404) {
          return { ditemukan: false, pesan: 'Kode verifikasi tidak dikenali. Pastikan kode disalin dengan benar.' }
        }
        throw galat
      }
    },
    enabled: dicari.length > 0,
  })

  const kirim = (peristiwa: FormEvent) => {
    peristiwa.preventDefault()
    setDicari(kode.trim().toUpperCase())
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <header>
        <h1 className="text-2xl">Verifikasi Keabsahan Surat</h1>
        <p className="mt-1 text-slate-600">
          Masukkan kode verifikasi yang tercantum pada surat, atau pindai kode QR pada dokumen.
        </p>
      </header>

      <Kartu>
        <IsiKartu>
          <form onSubmit={kirim} className="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div className="flex-1">
              <Isian
                label="Kode verifikasi"
                value={kode}
                onChange={(e) => setKode(e.target.value)}
                placeholder="Contoh: A1B2C3D4E5F6G7H8"
                required
                petunjuk="Kode terdiri atas 16 karakter huruf dan angka."
              />
            </div>
            <Tombol type="submit" memuat={isFetching}>
              Periksa Surat
            </Tombol>
          </form>
        </IsiKartu>
      </Kartu>

      {error ? (
        <Pemberitahuan jenis="bahaya" judul="Pemeriksaan gagal">
          Terjadi gangguan saat memeriksa surat. Silakan coba beberapa saat lagi.
        </Pemberitahuan>
      ) : data ? (
        data.ditemukan && data.sah ? (
          <Kartu className="border-desa-300 bg-desa-50/40">
            <IsiKartu>
              <div className="flex items-start gap-3">
                <BadgeCheck aria-hidden className="size-7 shrink-0 text-desa-700" />
                <div>
                  <h2 className="text-lg text-desa-900">Surat Sah</h2>
                  <p className="mt-1 text-sm text-desa-900">{data.pesan}</p>
                  <dl className="mt-4 grid gap-3 sm:grid-cols-2">
                    <div>
                      <dt className="text-sm text-slate-600">Nomor surat</dt>
                      <dd className="font-medium">{data.nomor_surat}</dd>
                    </div>
                    <div>
                      <dt className="text-sm text-slate-600">Jenis surat</dt>
                      <dd className="font-medium">{data.jenis_surat}</dd>
                    </div>
                    <div>
                      <dt className="text-sm text-slate-600">Tanggal terbit</dt>
                      <dd className="font-medium">{tanggal(data.tanggal_terbit)}</dd>
                    </div>
                    <div>
                      <dt className="text-sm text-slate-600">Ditandatangani oleh</dt>
                      <dd className="font-medium">{data.ditandatangani_oleh}</dd>
                    </div>
                  </dl>
                </div>
              </div>
            </IsiKartu>
          </Kartu>
        ) : (
          <Kartu className="border-red-300 bg-red-50/50">
            <IsiKartu>
              <div className="flex items-start gap-3">
                <ShieldAlert aria-hidden className="size-7 shrink-0 text-red-700" />
                <div>
                  <h2 className="text-lg text-red-900">
                    {data.ditemukan ? 'Surat Tidak Berlaku' : 'Surat Tidak Ditemukan'}
                  </h2>
                  <p className="mt-1 text-sm text-red-900">{data.pesan}</p>
                  {data.nomor_surat && (
                    <p className="mt-3 text-sm text-red-900">Nomor surat: {data.nomor_surat}</p>
                  )}
                </div>
              </div>
            </IsiKartu>
          </Kartu>
        )
      ) : null}

      <p className="text-sm text-slate-500">
        Halaman ini hanya menampilkan status keabsahan dokumen dan tidak menampilkan data pribadi pemohon.
      </p>
    </div>
  )
}
