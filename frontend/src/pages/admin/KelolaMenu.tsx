import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ChevronDown, ChevronUp, Pencil, Plus, Trash2 } from 'lucide-react'
import { api, galatKolom, pesanGalat } from '@/lib/api'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { Isian, Pilihan } from '@/components/ui/Isian'
import { Tombol } from '@/components/ui/Tombol'
import { Lencana } from '@/components/ui/Lencana'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import { GalatMuat, Rangka } from '@/components/ui/Status'

interface Butir {
  id: number
  label: string
  tautan: string | null
  urutan: number
  aktif: boolean
}

interface Induk extends Butir {
  anak: Butir[]
}

interface Formulir {
  id?: number
  label: string
  tautan: string
  induk_id: string
  aktif: boolean
}

const KOSONG: Formulir = { label: '', tautan: '', induk_id: '', aktif: true }

/** REQ-F-ADM-003: pengelola menu navigasi portal publik. */
export function KelolaMenu() {
  const klien = useQueryClient()
  const [formulir, setFormulir] = useState<Formulir | null>(null)
  const [galat, setGalat] = useState<Record<string, string>>({})
  const [pesan, setPesan] = useState('')
  const [sukses, setSukses] = useState('')

  const { data, isPending, error } = useQuery({
    queryKey: ['menu-admin'],
    queryFn: async () =>
      (await api.get<{ data: Induk[]; maks_tingkat_satu: number }>('/admin/menu')).data,
  })

  const setelahUbah = (kabar: string) => {
    setSukses(kabar)
    setPesan('')
    setGalat({})
    setFormulir(null)
    void klien.invalidateQueries({ queryKey: ['menu-admin'] })
    void klien.invalidateQueries({ queryKey: ['menu-navigasi'] })
  }

  const gagal = (kesalahan: unknown) => {
    setGalat(galatKolom(kesalahan))
    setPesan(pesanGalat(kesalahan))
    setSukses('')
  }

  const simpan = useMutation({
    mutationFn: async (isi: Formulir) => {
      const muatan = {
        label: isi.label,
        tautan: isi.tautan || null,
        induk_id: isi.induk_id ? Number(isi.induk_id) : null,
        aktif: isi.aktif,
      }

      return isi.id
        ? (await api.put(`/admin/menu/${isi.id}`, muatan)).data
        : (await api.post('/admin/menu', muatan)).data
    },
    onSuccess: () => setelahUbah('Menu tersimpan.'),
    onError: gagal,
  })

  const hapus = useMutation({
    mutationFn: async (id: number) => (await api.delete(`/admin/menu/${id}`)).data,
    onSuccess: () => setelahUbah('Butir menu dihapus.'),
    onError: gagal,
  })

  const urutkan = useMutation({
    mutationFn: async (urutan: { id: number; urutan: number }[]) =>
      (await api.post('/admin/menu/urutkan', { urutan })).data,
    onSuccess: () => setelahUbah('Urutan menu tersimpan.'),
    onError: gagal,
  })

  if (error) return <GalatMuat pesan={pesanGalat(error)} />
  if (isPending) return <Rangka baris={6} />

  const geser = (daftar: Butir[], indeks: number, arah: -1 | 1) => {
    const tujuan = indeks + arah

    if (tujuan < 0 || tujuan >= daftar.length) return

    const disusun = [...daftar]

    ;[disusun[indeks], disusun[tujuan]] = [disusun[tujuan], disusun[indeks]]

    urutkan.mutate(disusun.map((butir, urut) => ({ id: butir.id, urutan: urut })))
  }

  const aktifTingkatSatu = data.data.filter((butir) => butir.aktif).length

  const barisButir = (butir: Butir, daftar: Butir[], indeks: number, anak: boolean) => (
    <div
      key={butir.id}
      className={`flex flex-wrap items-center gap-3 border-b border-slate-100 px-5 py-3 last:border-0 ${
        anak ? 'ps-12' : ''
      }`}
    >
      <div className="min-w-0 flex-1">
        <p className="font-medium text-slate-900">
          {butir.label}
          {!butir.aktif && (
            <span className="ms-2">
              <Lencana>Nonaktif</Lencana>
            </span>
          )}
        </p>
        <p className="mt-0.5 truncate text-xs text-slate-500">{butir.tautan ?? 'Tanpa tautan (hanya menaungi)'}</p>
      </div>

      <div className="flex items-center gap-1">
        <button
          type="button"
          onClick={() => geser(daftar, indeks, -1)}
          disabled={indeks === 0}
          aria-label={`Naikkan ${butir.label}`}
          className="grid size-11 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 disabled:opacity-30"
        >
          <ChevronUp aria-hidden className="size-4" />
        </button>
        <button
          type="button"
          onClick={() => geser(daftar, indeks, 1)}
          disabled={indeks === daftar.length - 1}
          aria-label={`Turunkan ${butir.label}`}
          className="grid size-11 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 disabled:opacity-30"
        >
          <ChevronDown aria-hidden className="size-4" />
        </button>
        <button
          type="button"
          onClick={() =>
            setFormulir({
              id: butir.id,
              label: butir.label,
              tautan: butir.tautan ?? '',
              induk_id: anak ? String(data.data.find((i) => i.anak.some((a) => a.id === butir.id))?.id ?? '') : '',
              aktif: butir.aktif,
            })
          }
          aria-label={`Ubah ${butir.label}`}
          className="grid size-11 place-items-center rounded-lg text-slate-600 hover:bg-slate-100"
        >
          <Pencil aria-hidden className="size-4" />
        </button>
        <button
          type="button"
          onClick={() => {
            const peringatan = anak
              ? `Hapus butir "${butir.label}"?`
              : `Hapus "${butir.label}" beserta butir di bawahnya?`

            if (confirm(peringatan)) hapus.mutate(butir.id)
          }}
          aria-label={`Hapus ${butir.label}`}
          className="grid size-11 place-items-center rounded-lg text-red-700 hover:bg-red-50"
        >
          <Trash2 aria-hidden className="size-4" />
        </button>
      </div>
    </div>
  )

  return (
    <div className="max-w-3xl space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl">Menu Navigasi</h1>
          <p className="mt-1 text-slate-600">
            Susunan menu portal publik. Paling banyak {data.maks_tingkat_satu} butir tingkat pertama, bersarang dua
            tingkat.
          </p>
        </div>
        <Tombol onClick={() => { setFormulir(KOSONG); setSukses('') }}>
          <Plus aria-hidden className="size-4" /> Tambah Butir
        </Tombol>
      </header>

      {sukses && <Pemberitahuan jenis="sukses">{sukses}</Pemberitahuan>}
      {pesan && <Pemberitahuan jenis="bahaya">{pesan}</Pemberitahuan>}

      {formulir && (
        <Kartu>
          <KepalaKartu judul={formulir.id ? 'Ubah Butir Menu' : 'Butir Menu Baru'} />
          <IsiKartu>
            <form
              className="space-y-4"
              onSubmit={(peristiwa) => {
                peristiwa.preventDefault()
                simpan.mutate(formulir)
              }}
            >
              <Isian
                label="Label"
                required
                maxLength={60}
                value={formulir.label}
                onChange={(e) => setFormulir({ ...formulir, label: e.target.value })}
                galat={galat['label']}
              />
              <Isian
                label="Tautan"
                value={formulir.tautan}
                onChange={(e) => setFormulir({ ...formulir, tautan: e.target.value })}
                petunjuk='Alamat dalam situs diawali "/", misalnya /berita. Alamat luar ditulis lengkap dengan https://.'
                galat={galat['tautan']}
              />
              <Pilihan
                label="Berada di bawah"
                value={formulir.induk_id}
                onChange={(e) => setFormulir({ ...formulir, induk_id: e.target.value })}
                kosong="Tingkat pertama"
                pilihan={data.data
                  .filter((induk) => induk.id !== formulir.id)
                  .map((induk) => ({ nilai: String(induk.id), teks: induk.label }))}
                galat={galat['induk_id']}
              />
              <label className="flex items-center gap-2 text-sm text-slate-700">
                <input
                  type="checkbox"
                  className="size-4 rounded border-slate-300 text-desa-700 focus:ring-desa-600"
                  checked={formulir.aktif}
                  onChange={(e) => setFormulir({ ...formulir, aktif: e.target.checked })}
                />
                Tampilkan butir ini di portal
              </label>

              <div className="flex gap-2">
                <Tombol type="submit" memuat={simpan.isPending}>
                  Simpan
                </Tombol>
                <Tombol type="button" ragam="garis" onClick={() => { setFormulir(null); setGalat({}); setPesan('') }}>
                  Batal
                </Tombol>
              </div>
            </form>
          </IsiKartu>
        </Kartu>
      )}

      <Kartu>
        <KepalaKartu
          judul="Susunan Saat Ini"
          deskripsi={`${aktifTingkatSatu} dari ${data.maks_tingkat_satu} butir tingkat pertama terpakai.`}
        />
        {data.data.length === 0 ? (
          <IsiKartu>
            <p className="text-sm text-slate-600">
              Belum ada butir menu. Portal akan memakai susunan bawaan sampai butir pertama ditambahkan.
            </p>
          </IsiKartu>
        ) : (
          <div>
            {data.data.map((induk, indeks) => (
              <div key={induk.id}>
                {barisButir(induk, data.data, indeks, false)}
                {induk.anak.map((anak, indeksAnak) => barisButir(anak, induk.anak, indeksAnak, true))}
              </div>
            ))}
          </div>
        )}
      </Kartu>
    </div>
  )
}
