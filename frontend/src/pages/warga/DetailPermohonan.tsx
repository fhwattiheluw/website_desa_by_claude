import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Download, Paperclip, Star } from 'lucide-react'
import { api, pesanGalat } from '@/lib/api'
import { judulKan, tanggal, ukuranBerkas } from '@/lib/format'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { LencanaPermohonan } from '@/components/ui/Lencana'
import { Tombol } from '@/components/ui/Tombol'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import { GalatMuat, Pemuat } from '@/components/ui/Status'
import type { Permohonan } from '@/types'

export function DetailPermohonan() {
  const { id = '' } = useParams()
  const klien = useQueryClient()
  const [pesanUnduh, setPesanUnduh] = useState('')
  const [nilaiKepuasan, setNilaiKepuasan] = useState(0)

  const { data, isPending, error } = useQuery({
    queryKey: ['permohonan', id],
    queryFn: async () => (await api.get<{ data: Permohonan }>(`/permohonan/${id}`)).data.data,
  })

  const unduh = useMutation({
    mutationFn: async () =>
      (await api.get<{ tautan: string; berlaku_sampai: string }>(`/permohonan/${id}/tautan-surat`)).data,
    onSuccess: (hasil) => {
      window.open(hasil.tautan, '_blank', 'noopener')
      setPesanUnduh(`Tautan unduh berlaku sampai ${tanggal(hasil.berlaku_sampai)}.`)
    },
    onError: (kesalahan) => setPesanUnduh(pesanGalat(kesalahan)),
  })

  const nilai = useMutation({
    mutationFn: async (kepuasan: number) => api.post(`/permohonan/${id}/penilaian`, { kepuasan }),
    onSuccess: () => klien.invalidateQueries({ queryKey: ['permohonan', id] }),
  })

  if (error) return <GalatMuat pesan={pesanGalat(error)} />
  if (isPending || !data) return <Pemuat />

  const kolom = data.layanan?.kolom_formulir ?? []

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <Link to="/akun" className="inline-flex items-center gap-1.5 text-sm text-desa-700 hover:underline">
        <ArrowLeft aria-hidden className="size-4" /> Kembali ke akun saya
      </Link>

      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl">{data.layanan?.nama}</h1>
          <p className="mt-1 text-slate-600">{data.nomor_tiket}</p>
        </div>
        <LencanaPermohonan status={data.status} />
      </header>

      {data.status === 'dikembalikan' && (
        <Pemberitahuan jenis="peringatan" judul="Permohonan perlu diperbaiki">
          {data.alasan}
        </Pemberitahuan>
      )}

      {data.status === 'ditolak' && (
        <Pemberitahuan jenis="bahaya" judul="Permohonan ditolak">
          {data.alasan}
        </Pemberitahuan>
      )}

      {data.melampaui_sla && data.status !== 'selesai' && (
        <Pemberitahuan jenis="peringatan" judul="Melewati target waktu">
          Permohonan Anda melewati target penyelesaian. Petugas desa telah diberi tanda untuk menindaklanjuti.
        </Pemberitahuan>
      )}

      {data.status === 'selesai' && data.surat && (
        <Kartu className="border-desa-300 bg-desa-50/40">
          <IsiKartu>
            <h2 className="text-lg text-desa-900">Surat Anda telah terbit</h2>
            <dl className="mt-3 grid gap-3 sm:grid-cols-2">
              <div>
                <dt className="text-sm text-slate-600">Nomor surat</dt>
                <dd className="font-medium">{data.surat.nomor_surat}</dd>
              </div>
              <div>
                <dt className="text-sm text-slate-600">Tanggal terbit</dt>
                <dd className="font-medium">{tanggal(data.surat.tanggal_terbit)}</dd>
              </div>
            </dl>
            <Tombol className="mt-4" memuat={unduh.isPending} onClick={() => unduh.mutate()}>
              <Download aria-hidden className="size-4" /> Unduh Surat (PDF)
            </Tombol>
            {pesanUnduh && <p className="mt-2 text-sm text-slate-600">{pesanUnduh}</p>}
          </IsiKartu>
        </Kartu>
      )}

      <Kartu>
        <KepalaKartu judul="Riwayat Proses" deskripsi="Setiap perubahan status tercatat beserta waktu dan pelaksananya." />
        <IsiKartu>
          <ol className="space-y-4">
            {(data.riwayat ?? []).map((langkah, indeks) => (
              <li key={indeks} className="flex gap-3">
                <span aria-hidden className="mt-1.5 size-2.5 shrink-0 rounded-full bg-desa-600" />
                <div className="min-w-0">
                  <p className="font-medium text-slate-900">{judulKan(langkah.ke)}</p>
                  {langkah.catatan && <p className="mt-0.5 text-sm text-slate-600">{langkah.catatan}</p>}
                  <p className="mt-0.5 text-xs text-slate-500">
                    {tanggal(langkah.waktu, true)}
                    {langkah.aktor && ` · ${langkah.aktor}`}
                  </p>
                </div>
              </li>
            ))}
          </ol>
        </IsiKartu>
      </Kartu>

      <Kartu>
        <KepalaKartu judul="Data yang Diajukan" />
        <IsiKartu>
          <dl className="grid gap-4 sm:grid-cols-2">
            {kolom
              .filter((k) => k.tipe !== 'centang')
              .map((k) => (
                <div key={k.nama}>
                  <dt className="text-sm text-slate-600">{k.label}</dt>
                  <dd className="font-medium break-words">{String(data.data_formulir[k.nama] ?? '-')}</dd>
                </div>
              ))}
          </dl>

          {(data.lampiran?.length ?? 0) > 0 && (
            <div className="mt-5 border-t border-slate-100 pt-4">
              <h3 className="text-sm font-medium text-slate-700">Lampiran</h3>
              <ul className="mt-2 space-y-1.5">
                {data.lampiran?.map((lampiran) => (
                  <li key={lampiran.id} className="flex items-center gap-2 text-sm text-slate-600">
                    <Paperclip aria-hidden className="size-4 shrink-0" />
                    {lampiran.label ?? lampiran.nama} · {ukuranBerkas(lampiran.ukuran)}
                  </li>
                ))}
              </ul>
            </div>
          )}
        </IsiKartu>
      </Kartu>

      {data.status === 'selesai' && data.kepuasan === null && (
        <Kartu>
          <KepalaKartu judul="Bagaimana kualitas layanan kami?" deskripsi="Penilaian Anda membantu kami berbenah." />
          <IsiKartu>
            <div className="flex items-center gap-2" role="group" aria-label="Beri nilai 1 sampai 5">
              {[1, 2, 3, 4, 5].map((angka) => (
                <button
                  key={angka}
                  type="button"
                  onClick={() => { setNilaiKepuasan(angka); nilai.mutate(angka) }}
                  aria-label={`Beri nilai ${angka} dari 5`}
                  className="grid size-11 place-items-center rounded-lg border border-slate-300 hover:bg-slate-50"
                >
                  <Star
                    aria-hidden
                    className={`size-5 ${angka <= nilaiKepuasan ? 'fill-amber-400 text-amber-500' : 'text-slate-400'}`}
                  />
                </button>
              ))}
            </div>
            {nilai.isSuccess && <p className="mt-3 text-sm text-desa-700">Terima kasih atas penilaian Anda.</p>}
          </IsiKartu>
        </Kartu>
      )}
    </div>
  )
}
