import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { api, galatKolom, pesanGalat } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { rupiah } from '@/lib/format'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { AreaTeks, Isian } from '@/components/ui/Isian'
import { Lencana } from '@/components/ui/Lencana'
import { Tombol } from '@/components/ui/Tombol'
import { Dialog } from '@/components/ui/Dialog'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import { GalatMuat, KondisiKosong, Rangka } from '@/components/ui/Status'

interface UnitUsaha {
  id: number
  nama: string
  slug: string
  deskripsi: string | null
  penanggung_jawab: string | null
  kontak: string | null
  aktif: boolean
  urutan: number
}

interface Kinerja {
  id: number
  tahun: number
  pendapatan: string
  laba_bersih: string
  kontribusi_pades: string
  catatan: string | null
  dipublikasikan: boolean
}

/** REQ-F-POT-003: pengelolaan unit usaha dan kinerja BUMDes. */
export function KelolaBumdes() {
  const klien = useQueryClient()
  const { punyaIzin } = useAuth()
  const [formulirUnit, setFormulirUnit] = useState(false)
  const [unitDisunting, setUnitDisunting] = useState<UnitUsaha | null>(null)
  const [galat, setGalat] = useState<Record<string, string>>({})
  const [pesan, setPesan] = useState('')
  const [sukses, setSukses] = useState('')

  const { data, isPending, error } = useQuery({
    queryKey: ['admin-bumdes'],
    queryFn: async () =>
      (await api.get<{ unit_usaha: UnitUsaha[]; kinerja: Kinerja[] }>('/admin/bumdes')).data,
  })

  const segarkan = () => {
    void klien.invalidateQueries({ queryKey: ['admin-bumdes'] })
    void klien.invalidateQueries({ queryKey: ['bumdes'] })
  }

  const simpanUnit = useMutation({
    mutationFn: async (muatan: Record<string, unknown>) =>
      unitDisunting
        ? (await api.put(`/admin/bumdes/unit/${unitDisunting.id}`, muatan)).data
        : (await api.post('/admin/bumdes/unit', muatan)).data,
    onSuccess: (hasil: { pesan?: string }) => {
      setSukses(hasil.pesan ?? 'Tersimpan.')
      setPesan('')
      setFormulirUnit(false)
      setUnitDisunting(null)
      segarkan()
    },
    onError: (kesalahan) => { setGalat(galatKolom(kesalahan)); setPesan(pesanGalat(kesalahan)) },
  })

  const hapusUnit = useMutation({
    mutationFn: async (id: number) => (await api.delete(`/admin/bumdes/unit/${id}`)).data,
    onSuccess: () => { setSukses('Unit usaha dihapus.'); segarkan() },
    onError: (kesalahan) => setPesan(pesanGalat(kesalahan)),
  })

  const simpanKinerja = useMutation({
    mutationFn: async (muatan: Record<string, unknown>) => (await api.post('/admin/bumdes/kinerja', muatan)).data,
    onSuccess: (hasil: { pesan?: string }) => { setSukses(hasil.pesan ?? 'Tersimpan.'); setPesan(''); segarkan() },
    onError: (kesalahan) => { setGalat(galatKolom(kesalahan)); setPesan(pesanGalat(kesalahan)) },
  })

  const publikasi = useMutation({
    mutationFn: async ({ id, tampil }: { id: number; tampil: boolean }) =>
      (await api.post(`/admin/bumdes/kinerja/${id}/publikasi`, { dipublikasikan: tampil })).data,
    onSuccess: (hasil: { pesan?: string }) => { setSukses(hasil.pesan ?? 'Tersimpan.'); setPesan(''); segarkan() },
    onError: (kesalahan) => setPesan(pesanGalat(kesalahan)),
  })

  const kirimUnit = (peristiwa: FormEvent<HTMLFormElement>) => {
    peristiwa.preventDefault()
    const formulir = new FormData(peristiwa.currentTarget)

    simpanUnit.mutate({
      nama: formulir.get('nama'),
      deskripsi: formulir.get('deskripsi') || null,
      penanggung_jawab: formulir.get('penanggung_jawab') || null,
      kontak: formulir.get('kontak') || null,
      aktif: formulir.get('aktif') !== null,
      urutan: Number(formulir.get('urutan') ?? 0),
    })
  }

  const kirimKinerja = (peristiwa: FormEvent<HTMLFormElement>) => {
    peristiwa.preventDefault()
    const formulir = new FormData(peristiwa.currentTarget)

    simpanKinerja.mutate({
      tahun: Number(formulir.get('tahun')),
      pendapatan: Number(formulir.get('pendapatan')),
      laba_bersih: Number(formulir.get('laba_bersih')),
      kontribusi_pades: Number(formulir.get('kontribusi_pades')),
      catatan: formulir.get('catatan') || null,
    })
    peristiwa.currentTarget.reset()
  }

  if (error) return <GalatMuat pesan={pesanGalat(error)} />
  if (isPending) return <Rangka baris={4} />

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl">BUMDes</h1>
          <p className="mt-1 text-slate-600">
            Unit usaha dan kinerja badan usaha milik desa. Angka kinerja baru tampil publik setelah dipublikasikan.
          </p>
        </div>
        <Tombol onClick={() => { setUnitDisunting(null); setFormulirUnit(true); setGalat({}) }}>
          <Plus aria-hidden className="size-4" /> Unit Usaha
        </Tombol>
      </header>

      {sukses && <Pemberitahuan jenis="sukses">{sukses}</Pemberitahuan>}
      {pesan && <Pemberitahuan jenis="bahaya">{pesan}</Pemberitahuan>}

      <Kartu>
        <KepalaKartu judul="Unit Usaha" />
        {data.unit_usaha.length === 0 ? (
          <IsiKartu>
            <KondisiKosong judul="Belum ada unit usaha" keterangan="Tambahkan unit usaha yang dikelola BUMDes." />
          </IsiKartu>
        ) : (
          <ul className="divide-y divide-slate-100">
            {data.unit_usaha.map((unit) => (
              <li key={unit.id} className="flex flex-wrap items-start justify-between gap-3 px-5 py-4">
                <div className="min-w-0">
                  <p className="flex items-center gap-2 font-medium">
                    {unit.nama}
                    {!unit.aktif && <Lencana>Nonaktif</Lencana>}
                  </p>
                  {unit.deskripsi && <p className="mt-0.5 text-sm text-slate-600">{unit.deskripsi}</p>}
                  {unit.penanggung_jawab && (
                    <p className="mt-1 text-xs text-slate-500">Penanggung jawab: {unit.penanggung_jawab}</p>
                  )}
                </div>
                <div className="flex gap-2">
                  <Tombol
                    ukuran="kecil"
                    ragam="garis"
                    onClick={() => { setUnitDisunting(unit); setFormulirUnit(true); setGalat({}) }}
                  >
                    Sunting
                  </Tombol>
                  <Tombol ukuran="kecil" ragam="bahaya" onClick={() => hapusUnit.mutate(unit.id)}>
                    <Trash2 aria-hidden className="size-4" />
                    <span className="sr-only">Hapus {unit.nama}</span>
                  </Tombol>
                </div>
              </li>
            ))}
          </ul>
        )}
      </Kartu>

      <Kartu>
        <KepalaKartu judul="Kinerja Tahunan" deskripsi="Isi tahun yang sama untuk memperbarui angka yang sudah ada." />
        <IsiKartu>
          <form onSubmit={kirimKinerja} className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Isian label="Tahun buku" name="tahun" type="number" required galat={galat['tahun']} />
            <Isian label="Pendapatan (Rp)" name="pendapatan" type="number" required galat={galat['pendapatan']} />
            <Isian label="Laba bersih (Rp)" name="laba_bersih" type="number" required galat={galat['laba_bersih']} />
            <Isian
              label="Kontribusi PADes (Rp)"
              name="kontribusi_pades"
              type="number"
              required
              galat={galat['kontribusi_pades']}
            />
            <div className="sm:col-span-2 lg:col-span-1">
              <Isian label="Catatan" name="catatan" galat={galat['catatan']} />
            </div>
            <div className="flex items-end">
              <Tombol type="submit" memuat={simpanKinerja.isPending}>
                Simpan Kinerja
              </Tombol>
            </div>
          </form>
        </IsiKartu>

        {data.kinerja.length > 0 && (
          <div className="overflow-x-auto border-t border-slate-100">
            <table className="w-full text-sm">
              <caption className="sr-only">Kinerja tahunan BUMDes</caption>
              <thead>
                <tr className="border-b border-slate-200 bg-slate-50 text-left text-slate-600">
                  <th scope="col" className="px-5 py-3 font-medium">Tahun</th>
                  <th scope="col" className="px-5 py-3 text-right font-medium">Pendapatan</th>
                  <th scope="col" className="px-5 py-3 text-right font-medium">Laba bersih</th>
                  <th scope="col" className="px-5 py-3 text-right font-medium">Kontribusi PADes</th>
                  <th scope="col" className="px-5 py-3 font-medium">Status</th>
                  <th scope="col" className="px-5 py-3 font-medium">Tindakan</th>
                </tr>
              </thead>
              <tbody>
                {data.kinerja.map((baris) => (
                  <tr key={baris.id} className="border-b border-slate-100 last:border-0">
                    <th scope="row" className="px-5 py-3 text-left font-medium">{baris.tahun}</th>
                    <td className="px-5 py-3 text-right tabular-nums">{rupiah(Number(baris.pendapatan))}</td>
                    <td className="px-5 py-3 text-right tabular-nums">{rupiah(Number(baris.laba_bersih))}</td>
                    <td className="px-5 py-3 text-right tabular-nums">{rupiah(Number(baris.kontribusi_pades))}</td>
                    <td className="px-5 py-3">
                      <Lencana nada={baris.dipublikasikan ? 'sukses' : 'netral'}>
                        {baris.dipublikasikan ? 'Terpublikasi' : 'Draf'}
                      </Lencana>
                    </td>
                    <td className="px-5 py-3">
                      {punyaIzin('bumdes.publikasi') && (
                        <Tombol
                          ukuran="kecil"
                          ragam={baris.dipublikasikan ? 'garis' : 'utama'}
                          memuat={publikasi.isPending}
                          onClick={() => publikasi.mutate({ id: baris.id, tampil: !baris.dipublikasikan })}
                        >
                          {baris.dipublikasikan ? 'Sembunyikan' : 'Publikasikan'}
                        </Tombol>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Kartu>

      <Dialog
        terbuka={formulirUnit}
        judul={unitDisunting ? 'Sunting Unit Usaha' : 'Unit Usaha Baru'}
        onTutup={() => { setFormulirUnit(false); setUnitDisunting(null) }}
      >
        <form onSubmit={kirimUnit} className="space-y-4">
          <Isian label="Nama unit usaha" name="nama" required defaultValue={unitDisunting?.nama} galat={galat['nama']} />
          <AreaTeks label="Deskripsi" name="deskripsi" rows={3} defaultValue={unitDisunting?.deskripsi ?? ''} />
          <Isian label="Penanggung jawab" name="penanggung_jawab" defaultValue={unitDisunting?.penanggung_jawab ?? ''} />
          <Isian label="Kontak" name="kontak" defaultValue={unitDisunting?.kontak ?? ''} galat={galat['kontak']} />
          <Isian label="Urutan tampil" name="urutan" type="number" defaultValue={unitDisunting?.urutan ?? 0} />

          <div className="flex items-start gap-2.5">
            <input
              type="checkbox"
              id="unit-aktif"
              name="aktif"
              defaultChecked={unitDisunting ? unitDisunting.aktif : true}
              className="mt-0.5 size-5 rounded border-slate-300 text-desa-700 focus:ring-desa-600"
            />
            <label htmlFor="unit-aktif" className="text-sm text-slate-700">
              Tampilkan unit usaha ini pada laman publik
            </label>
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <Tombol type="button" ragam="garis" onClick={() => setFormulirUnit(false)}>
              Batal
            </Tombol>
            <Tombol type="submit" memuat={simpanUnit.isPending}>
              Simpan
            </Tombol>
          </div>
        </form>
      </Dialog>
    </div>
  )
}
