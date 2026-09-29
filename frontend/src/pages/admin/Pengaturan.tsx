import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, pesanGalat } from '@/lib/api'
import { judulKan } from '@/lib/format'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { AreaTeks, Isian } from '@/components/ui/Isian'
import { Tombol } from '@/components/ui/Tombol'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import { GalatMuat, Rangka } from '@/components/ui/Status'

interface BarisPengaturan {
  id: number
  kunci: string
  nilai: string | null
  grup: string
  label: string | null
}

const PANJANG = ['sejarah', 'visi', 'misi', 'ppid_maklumat']

/** REQ-F-ADM-002: profil dan identitas situs dapat disunting tanpa ubah kode. */
export function Pengaturan() {
  const klien = useQueryClient()
  const [suntingan, setSuntingan] = useState<Record<string, string>>({})
  const [pesan, setPesan] = useState('')
  const [sukses, setSukses] = useState('')

  const { data, isPending, error } = useQuery({
    queryKey: ['pengaturan'],
    queryFn: async () => (await api.get<{ data: BarisPengaturan[] }>('/admin/pengaturan')).data.data,
  })

  const simpan = useMutation({
    mutationFn: async () =>
      (await api.put('/admin/pengaturan', {
        pengaturan: Object.entries(suntingan).map(([kunci, isi]) => ({ kunci, nilai: isi })),
      })).data,
    onSuccess: () => {
      setSukses('Pengaturan situs berhasil diperbarui.')
      setPesan('')
      setSuntingan({})
      void klien.invalidateQueries({ queryKey: ['profil-desa'] })
      void klien.invalidateQueries({ queryKey: ['pengaturan'] })
    },
    onError: (kesalahan) => setPesan(pesanGalat(kesalahan)),
  })

  if (error) return <GalatMuat pesan={pesanGalat(error)} />
  if (isPending) return <Rangka baris={5} />

  const grup = data.reduce<Record<string, BarisPengaturan[]>>((kumpulan, baris) => {
    kumpulan[baris.grup] = [...(kumpulan[baris.grup] ?? []), baris]
    return kumpulan
  }, {})

  return (
    <div className="max-w-3xl space-y-6">
      <header>
        <h1 className="text-2xl">Pengaturan Situs</h1>
        <p className="mt-1 text-slate-600">Identitas desa, kontak resmi, dan isi halaman profil.</p>
      </header>

      {sukses && <Pemberitahuan jenis="sukses">{sukses}</Pemberitahuan>}
      {pesan && <Pemberitahuan jenis="bahaya">{pesan}</Pemberitahuan>}

      {Object.entries(grup).map(([namaGrup, baris]) => (
        <Kartu key={namaGrup}>
          <KepalaKartu judul={judulKan(namaGrup)} />
          <IsiKartu className="space-y-4">
            {baris.map((butir) =>
              PANJANG.includes(butir.kunci) ? (
                <AreaTeks
                  key={butir.kunci}
                  label={butir.label ?? judulKan(butir.kunci)}
                  rows={butir.kunci === 'misi' ? 6 : 4}
                  value={suntingan[butir.kunci] ?? butir.nilai ?? ''}
                  onChange={(e) => setSuntingan((s) => ({ ...s, [butir.kunci]: e.target.value }))}
                  petunjuk={butir.kunci === 'misi' ? 'Satu poin misi per baris.' : undefined}
                />
              ) : (
                <Isian
                  key={butir.kunci}
                  label={butir.label ?? judulKan(butir.kunci)}
                  value={suntingan[butir.kunci] ?? butir.nilai ?? ''}
                  onChange={(e) => setSuntingan((s) => ({ ...s, [butir.kunci]: e.target.value }))}
                />
              ),
            )}
          </IsiKartu>
        </Kartu>
      ))}

      <Tombol
        ukuran="besar"
        disabled={Object.keys(suntingan).length === 0}
        memuat={simpan.isPending}
        onClick={() => simpan.mutate()}
      >
        Simpan Pengaturan
      </Tombol>
    </div>
  )
}
