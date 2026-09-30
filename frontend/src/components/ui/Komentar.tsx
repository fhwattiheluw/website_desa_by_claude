import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { MessageSquare } from 'lucide-react'
import { api, galatKolom, pesanGalat } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { tanggal } from '@/lib/format'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { AreaTeks, Isian } from '@/components/ui/Isian'
import { Tombol } from '@/components/ui/Tombol'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import { Captcha } from '@/components/ui/Captcha'
import { useCaptcha } from '@/lib/captcha'
import { KondisiKosong } from '@/components/ui/Status'

interface ButirKomentar {
  nama: string
  isi: string
  waktu: string | null
}

/**
 * Komentar pembaca pada berita dan artikel (REQ-F-KNT-013).
 *
 * Setiap kiriman menunggu moderasi; tidak ada jalur yang membuat tulisan orang
 * lain langsung tampil di laman desa.
 */
export function Komentar({ tipe, slug }: { tipe: string; slug: string }) {
  const { pengguna } = useAuth()
  const klien = useQueryClient()
  const captcha = useCaptcha()
  const [isi, setIsi] = useState('')
  const [nama, setNama] = useState('')
  const [galat, setGalat] = useState<Record<string, string>>({})
  const [pesan, setPesan] = useState('')
  const [terkirim, setTerkirim] = useState(false)

  const { data } = useQuery({
    queryKey: ['komentar', tipe, slug],
    queryFn: async () => (await api.get<{ data: ButirKomentar[] }>(`/konten/${tipe}/${slug}/komentar`)).data.data,
  })

  const kirim = useMutation({
    mutationFn: async () =>
      (
        await api.post<{ pesan: string }>(`/konten/${tipe}/${slug}/komentar`, {
          nama: pengguna ? undefined : nama,
          isi,
          ...captcha.muatan(),
        })
      ).data,
    onSuccess: () => {
      setTerkirim(true)
      setIsi('')
      setNama('')
      setGalat({})
      setPesan('')
      void klien.invalidateQueries({ queryKey: ['komentar', tipe, slug] })
    },
    onError: (kesalahan) => {
      setGalat(galatKolom(kesalahan))
      setPesan(pesanGalat(kesalahan))
      captcha.segarkan()
    },
  })

  const padaKirim = (peristiwa: FormEvent) => {
    peristiwa.preventDefault()
    kirim.mutate()
  }

  return (
    <section aria-labelledby="komentar-artikel" className="mt-10">
      <h2 id="komentar-artikel" className="mb-4 flex items-center gap-2 text-xl">
        <MessageSquare aria-hidden className="size-5 text-desa-700" />
        Komentar Pembaca
      </h2>

      {data?.length ? (
        <ul className="mb-6 space-y-4">
          {data.map((satu, indeks) => (
            <li key={`${satu.nama}-${indeks}`}>
              <Kartu>
                <IsiKartu>
                  <p className="font-medium text-slate-900">{satu.nama}</p>
                  <p className="mt-0.5 text-xs text-slate-500">{tanggal(satu.waktu)}</p>
                  <p className="mt-2 whitespace-pre-line text-slate-700">{satu.isi}</p>
                </IsiKartu>
              </Kartu>
            </li>
          ))}
        </ul>
      ) : (
        <div className="mb-6">
          <KondisiKosong
            judul="Belum ada komentar"
            keterangan="Jadilah yang pertama menanggapi tulisan ini."
          />
        </div>
      )}

      <Kartu>
        <KepalaKartu
          judul="Tulis Komentar"
          deskripsi="Komentar tayang setelah ditinjau petugas desa, umumnya dalam satu hari kerja."
        />
        <IsiKartu>
          {terkirim && (
            <div className="mb-4">
              <Pemberitahuan jenis="sukses" judul="Komentar terkirim">
                Terima kasih. Komentar Anda akan tayang setelah ditinjau petugas desa.
              </Pemberitahuan>
            </div>
          )}
          {pesan && (
            <div className="mb-4">
              <Pemberitahuan jenis="bahaya">{pesan}</Pemberitahuan>
            </div>
          )}

          <form onSubmit={padaKirim} className="space-y-4">
            {pengguna ? (
              <p className="text-sm text-slate-600">
                Berkomentar sebagai <span className="font-medium text-slate-900">{pengguna.nama}</span>.
              </p>
            ) : (
              <Isian
                label="Nama Anda"
                name="nama"
                required
                value={nama}
                onChange={(e) => setNama(e.target.value)}
                galat={galat['nama']}
              />
            )}

            <AreaTeks
              label="Komentar"
              name="isi"
              required
              rows={4}
              value={isi}
              onChange={(e) => setIsi(e.target.value)}
              petunjuk="Sampaikan tanggapan secara santun. Minimal 10 karakter."
              galat={galat['isi']}
            />

            <Captcha kendali={captcha} galat={galat['captcha_jawaban']} />

            <Tombol type="submit" memuat={kirim.isPending}>
              Kirim Komentar
            </Tombol>
          </form>
        </IsiKartu>
      </Kartu>
    </section>
  )
}
