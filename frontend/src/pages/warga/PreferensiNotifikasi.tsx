import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Mail, MessageCircle, Megaphone } from 'lucide-react'
import { api, pesanGalat } from '@/lib/api'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { KotakCentang } from '@/components/ui/Isian'
import { Tombol } from '@/components/ui/Tombol'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'

interface Preferensi {
  email: boolean
  whatsapp: boolean
  pengumuman: boolean
}

const KANAL: { kunci: keyof Preferensi; label: string; keterangan: string; ikon: typeof Mail }[] = [
  {
    kunci: 'email',
    label: 'Surel',
    keterangan: 'Pemberitahuan status permohonan dan pengaduan dikirim ke alamat surel Anda.',
    ikon: Mail,
  },
  {
    kunci: 'whatsapp',
    label: 'WhatsApp',
    keterangan: 'Pemberitahuan singkat dikirim ke nomor WhatsApp yang terdaftar.',
    ikon: MessageCircle,
  },
  {
    kunci: 'pengumuman',
    label: 'Pengumuman dan kabar desa',
    keterangan: 'Kabar yang tidak berkaitan langsung dengan permohonan Anda. Dapat dimatikan sepenuhnya.',
    ikon: Megaphone,
  },
]

/** REQ-F-NOT-006: pengaturan kanal notifikasi oleh pemilik akun. */
export function PreferensiNotifikasi() {
  const klien = useQueryClient()
  // Yang disimpan hanya suntingan pengguna; nilai tampilnya diturunkan dari
  // data tersimpan supaya keduanya tidak perlu disamakan lewat efek.
  const [suntingan, setSuntingan] = useState<Partial<Preferensi>>({})
  const [pesan, setPesan] = useState('')
  const [sukses, setSukses] = useState('')

  const { data } = useQuery({
    queryKey: ['preferensi-notifikasi'],
    queryFn: async () =>
      (await api.get<{ preferensi: Preferensi; catatan: string }>('/auth/preferensi-notifikasi')).data,
  })

  const simpan = useMutation({
    mutationFn: async (muatan: Preferensi) =>
      (await api.put<{ pesan: string }>('/auth/preferensi-notifikasi', muatan)).data,
    onSuccess: (hasil) => {
      setSukses(hasil.pesan)
      setPesan('')
      setSuntingan({})
      void klien.invalidateQueries({ queryKey: ['preferensi-notifikasi'] })
    },
    onError: (kesalahan) => setPesan(pesanGalat(kesalahan)),
  })

  if (!data) return null

  const nilai: Preferensi = { ...data.preferensi, ...suntingan }

  const ubah = (kunci: keyof Preferensi, aktif: boolean) => {
    setSuntingan((sebelumnya) => ({ ...sebelumnya, [kunci]: aktif }))
    setSukses('')
  }

  return (
    <Kartu>
      <KepalaKartu
        judul="Preferensi Notifikasi"
        deskripsi="Pilih kanal yang Anda inginkan untuk menerima pemberitahuan dari pemerintah desa."
      />
      <IsiKartu className="space-y-4">
        {sukses && <Pemberitahuan jenis="sukses">{sukses}</Pemberitahuan>}
        {pesan && <Pemberitahuan jenis="bahaya">{pesan}</Pemberitahuan>}

        {KANAL.map((kanal) => (
          <div key={kanal.kunci} className="flex gap-3">
            <kanal.ikon aria-hidden className="mt-0.5 size-5 shrink-0 text-desa-700" />
            <KotakCentang
              checked={nilai[kanal.kunci]}
              onChange={(e) => ubah(kanal.kunci, e.target.checked)}
              label={
                <span>
                  <span className="font-medium text-slate-900">{kanal.label}</span>
                  <span className="block text-slate-600">{kanal.keterangan}</span>
                </span>
              }
            />
          </div>
        ))}

        {data.catatan && <p className="border-t border-slate-100 pt-4 text-sm text-slate-500">{data.catatan}</p>}

        <Tombol memuat={simpan.isPending} onClick={() => simpan.mutate(nilai)}>
          Simpan Preferensi
        </Tombol>
      </IsiKartu>
    </Kartu>
  )
}
