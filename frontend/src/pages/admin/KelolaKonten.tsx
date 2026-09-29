import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useSearchParams } from 'react-router-dom'
import { Plus } from 'lucide-react'
import { api, galatKolom, pesanGalat } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { useKategori } from '@/lib/kueri'
import { judulKan, tanggal } from '@/lib/format'
import { Kartu } from '@/components/ui/Kartu'
import { AreaTeks, Isian, KotakCentang, Pilihan } from '@/components/ui/Isian'
import { Lencana } from '@/components/ui/Lencana'
import { Tombol } from '@/components/ui/Tombol'
import { Dialog } from '@/components/ui/Dialog'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import { GalatMuat, KondisiKosong, Rangka } from '@/components/ui/Status'
import type { Halaman, Konten } from '@/types'

const NADA_STATUS: Record<string, 'netral' | 'info' | 'sukses' | 'peringatan'> = {
  draf: 'netral',
  review: 'peringatan',
  terbit: 'sukses',
  arsip: 'info',
}

export function KelolaKonten() {
  const klien = useQueryClient()
  const { punyaIzin, pengguna } = useAuth()
  const [parameter, setParameter] = useSearchParams()
  const [formulirTerbuka, setFormulirTerbuka] = useState(false)
  const [disunting, setDisunting] = useState<Konten | null>(null)
  const [galat, setGalat] = useState<Record<string, string>>({})
  const [pesan, setPesan] = useState('')
  const [sukses, setSukses] = useState('')

  const status = parameter.get('status') ?? ''
  const tipe = parameter.get('tipe') ?? ''

  const { data, isPending, error } = useQuery({
    queryKey: ['admin-konten', status, tipe],
    queryFn: async () =>
      (
        await api.get<Halaman<Konten>>('/admin/konten', {
          params: { status: status || undefined, tipe: tipe || undefined },
        })
      ).data,
  })

  const { data: kategori } = useKategori()

  const segarkan = () => {
    void klien.invalidateQueries({ queryKey: ['admin-konten'] })
    void klien.invalidateQueries({ queryKey: ['dasbor'] })
  }

  const simpan = useMutation({
    mutationFn: async (muatan: Record<string, unknown>) =>
      disunting
        ? (await api.put(`/admin/konten/${disunting.id}`, muatan)).data
        : (await api.post('/admin/konten', muatan)).data,
    onSuccess: (hasil: { pesan?: string }) => {
      setSukses(hasil.pesan ?? 'Konten tersimpan.')
      setFormulirTerbuka(false)
      setDisunting(null)
      setGalat({})
      setPesan('')
      segarkan()
    },
    onError: (kesalahan) => { setGalat(galatKolom(kesalahan)); setPesan(pesanGalat(kesalahan)) },
  })

  const ubahStatus = useMutation({
    mutationFn: async ({ id, statusBaru }: { id: number; statusBaru: string }) =>
      (await api.post(`/admin/konten/${id}/status`, { status: statusBaru })).data,
    onSuccess: (hasil: { pesan?: string }) => { setSukses(hasil.pesan ?? 'Status diperbarui.'); setPesan(''); segarkan() },
    onError: (kesalahan) => setPesan(pesanGalat(kesalahan)),
  })

  const kirim = (peristiwa: FormEvent<HTMLFormElement>) => {
    peristiwa.preventDefault()
    const formulir = new FormData(peristiwa.currentTarget)

    simpan.mutate({
      tipe: formulir.get('tipe'),
      judul: formulir.get('judul'),
      ringkasan: formulir.get('ringkasan') || null,
      isi: formulir.get('isi') || null,
      kategori_id: formulir.get('kategori_id') ? Number(formulir.get('kategori_id')) : null,
      sorotan: formulir.get('sorotan') === 'on',
      lokasi: formulir.get('lokasi') || null,
      mulai_pada: formulir.get('mulai_pada') || null,
      kedaluwarsa_pada: formulir.get('kedaluwarsa_pada') || null,
    })
  }

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl">Manajemen Konten</h1>
          <p className="mt-1 text-slate-600">Berita, artikel, pengumuman, dan agenda kegiatan desa.</p>
        </div>
        <Tombol onClick={() => { setDisunting(null); setFormulirTerbuka(true); setGalat({}) }}>
          <Plus aria-hidden className="size-4" /> Konten Baru
        </Tombol>
      </header>

      {sukses && <Pemberitahuan jenis="sukses">{sukses}</Pemberitahuan>}
      {pesan && <Pemberitahuan jenis="bahaya">{pesan}</Pemberitahuan>}

      <div className="grid max-w-lg gap-4 sm:grid-cols-2">
        <Pilihan
          label="Tipe"
          value={tipe}
          onChange={(e) => setParameter({ tipe: e.target.value, status })}
          kosong="Semua tipe"
          pilihan={['berita', 'artikel', 'pengumuman', 'agenda'].map((t) => ({ nilai: t, teks: judulKan(t) }))}
        />
        <Pilihan
          label="Status"
          value={status}
          onChange={(e) => setParameter({ tipe, status: e.target.value })}
          kosong="Semua status"
          pilihan={['draf', 'review', 'terbit', 'arsip'].map((s) => ({ nilai: s, teks: judulKan(s) }))}
        />
      </div>

      {error ? (
        <GalatMuat pesan={pesanGalat(error)} />
      ) : isPending ? (
        <Rangka baris={4} />
      ) : data.data.length === 0 ? (
        <KondisiKosong judul="Belum ada konten" keterangan="Mulai dengan membuat berita atau pengumuman baru." />
      ) : (
        <Kartu>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <caption className="sr-only">Daftar konten</caption>
              <thead>
                <tr className="border-b border-slate-200 bg-slate-50 text-left text-slate-600">
                  <th scope="col" className="px-4 py-3 font-medium">Judul</th>
                  <th scope="col" className="px-4 py-3 font-medium">Tipe</th>
                  <th scope="col" className="px-4 py-3 font-medium">Status</th>
                  <th scope="col" className="px-4 py-3 font-medium">Terbit</th>
                  <th scope="col" className="px-4 py-3 font-medium">Tindakan</th>
                </tr>
              </thead>
              <tbody>
                {data.data.map((konten) => (
                  <tr key={konten.id} className="border-b border-slate-100 last:border-0">
                    <td className="px-4 py-3">
                      <p className="font-medium text-slate-900">{konten.judul}</p>
                      {konten.kategori && <p className="text-xs text-slate-500">{konten.kategori.nama}</p>}
                    </td>
                    <td className="px-4 py-3">{judulKan(konten.tipe)}</td>
                    <td className="px-4 py-3">
                      <Lencana nada={NADA_STATUS[konten.status] ?? 'netral'}>{judulKan(konten.status)}</Lencana>
                    </td>
                    <td className="px-4 py-3">{tanggal(konten.terbit_pada)}</td>
                    <td className="px-4 py-3">
                      <div className="flex flex-wrap gap-2">
                        <Tombol
                          ukuran="kecil"
                          ragam="garis"
                          onClick={() => { setDisunting(konten); setFormulirTerbuka(true); setGalat({}) }}
                        >
                          Sunting
                        </Tombol>
                        {konten.status === 'draf' && (
                          <Tombol
                            ukuran="kecil"
                            ragam="halus"
                            onClick={() => ubahStatus.mutate({ id: konten.id, statusBaru: 'review' })}
                          >
                            Ajukan Review
                          </Tombol>
                        )}
                        {konten.status === 'review' && punyaIzin('konten.terbit') && (
                          <Tombol
                            ukuran="kecil"
                            onClick={() => ubahStatus.mutate({ id: konten.id, statusBaru: 'terbit' })}
                          >
                            Terbitkan
                          </Tombol>
                        )}
                        {konten.status === 'terbit' && punyaIzin('konten.terbit') && (
                          <Tombol
                            ukuran="kecil"
                            ragam="garis"
                            onClick={() => ubahStatus.mutate({ id: konten.id, statusBaru: 'arsip' })}
                          >
                            Arsipkan
                          </Tombol>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Kartu>
      )}

      <Dialog
        terbuka={formulirTerbuka}
        judul={disunting ? 'Sunting Konten' : 'Konten Baru'}
        deskripsi={
          pengguna && !punyaIzin('konten.terbit')
            ? 'Konten tersimpan sebagai draf dan menunggu peninjauan petugas berwenang.'
            : 'Konten baru selalu tersimpan sebagai draf sebelum diterbitkan.'
        }
        onTutup={() => { setFormulirTerbuka(false); setDisunting(null) }}
      >
        <form onSubmit={kirim} className="space-y-4">
          <Pilihan
            label="Tipe konten"
            name="tipe"
            required
            defaultValue={disunting?.tipe ?? 'berita'}
            pilihan={['berita', 'artikel', 'pengumuman', 'agenda'].map((t) => ({ nilai: t, teks: judulKan(t) }))}
            galat={galat['tipe']}
          />
          <Isian label="Judul" name="judul" required defaultValue={disunting?.judul} galat={galat['judul']} />
          <AreaTeks
            label="Ringkasan"
            name="ringkasan"
            rows={2}
            defaultValue={disunting?.ringkasan ?? ''}
            petunjuk="Ditampilkan pada daftar dan hasil pencarian."
            galat={galat['ringkasan']}
          />
          <AreaTeks
            label="Isi konten"
            name="isi"
            rows={8}
            defaultValue={disunting?.isi ?? ''}
            petunjuk="Mendukung penulisan HTML sederhana seperti <p>, <strong>, dan <ul>."
            galat={galat['isi']}
          />
          <Pilihan
            label="Kategori"
            name="kategori_id"
            kosong="Tanpa kategori"
            defaultValue={disunting?.kategori ? String(kategori?.find((k) => k.slug === disunting.kategori?.slug)?.id ?? '') : ''}
            pilihan={(kategori ?? []).map((k) => ({ nilai: String(k.id), teks: k.nama }))}
          />
          <Isian
            label="Berlaku sampai (pengumuman)"
            name="kedaluwarsa_pada"
            type="date"
            petunjuk="Pengumuman otomatis diarsipkan setelah tanggal ini."
          />
          <Isian label="Waktu mulai (agenda)" name="mulai_pada" type="datetime-local" />
          <Isian label="Lokasi (agenda)" name="lokasi" defaultValue={disunting?.lokasi ?? ''} />
          <KotakCentang label="Tandai sebagai sorotan pada beranda" name="sorotan" defaultChecked={disunting?.sorotan} />

          <div className="flex justify-end gap-2 pt-2">
            <Tombol type="button" ragam="garis" onClick={() => setFormulirTerbuka(false)}>
              Batal
            </Tombol>
            <Tombol type="submit" memuat={simpan.isPending}>
              Simpan
            </Tombol>
          </div>
        </form>
      </Dialog>
    </div>
  )
}
