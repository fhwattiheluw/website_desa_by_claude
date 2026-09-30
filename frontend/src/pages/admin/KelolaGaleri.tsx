import { useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Images, Plus, Trash2, Upload, Video } from 'lucide-react'
import { api, galatKolom, pesanGalat } from '@/lib/api'
import { tanggal } from '@/lib/format'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { AreaTeks, Isian } from '@/components/ui/Isian'
import { Tombol } from '@/components/ui/Tombol'
import { Lencana } from '@/components/ui/Lencana'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import { GalatMuat, KondisiKosong, Rangka } from '@/components/ui/Status'

interface Album {
  id: number
  nama: string
  slug: string
  deskripsi: string | null
  tanggal_kegiatan: string | null
  media_count: number
}

interface Video {
  id: number
  judul: string
  penyedia: string
  url_asli: string
  url_sematan: string
  thumbnail: string | null
}

/** REQ-F-GAL-005, REQ-F-GAL-006: album galeri beserta video tersemat. */
export function KelolaGaleri() {
  const klien = useQueryClient()
  const [terpilih, setTerpilih] = useState<Album | null>(null)
  const [albumBaru, setAlbumBaru] = useState({ nama: '', deskripsi: '', tanggal_kegiatan: '' })
  const [videoBaru, setVideoBaru] = useState({ judul: '', url: '' })
  const [galat, setGalat] = useState<Record<string, string>>({})
  const [pesan, setPesan] = useState('')
  const [sukses, setSukses] = useState('')
  const berkas = useRef<HTMLInputElement>(null)

  const album = useQuery({
    queryKey: ['album-admin'],
    queryFn: async () => (await api.get<{ data: Album[] }>('/admin/media/album/daftar')).data.data,
  })

  const video = useQuery({
    queryKey: ['video-album', terpilih?.id],
    queryFn: async () =>
      (await api.get<{ data: Video[] }>(`/admin/media/album/${terpilih!.id}/video`)).data.data,
    enabled: terpilih !== null,
  })

  const berhasil = (kabar: string) => {
    setSukses(kabar)
    setPesan('')
    setGalat({})
    void klien.invalidateQueries({ queryKey: ['album-admin'] })
    void klien.invalidateQueries({ queryKey: ['video-album'] })
    void klien.invalidateQueries({ queryKey: ['galeri'] })
  }

  const gagal = (kesalahan: unknown) => {
    setGalat(galatKolom(kesalahan))
    setPesan(pesanGalat(kesalahan))
    setSukses('')
  }

  const buatAlbum = useMutation({
    mutationFn: async () =>
      (await api.post('/admin/media/album/daftar', {
        nama: albumBaru.nama,
        deskripsi: albumBaru.deskripsi || null,
        tanggal_kegiatan: albumBaru.tanggal_kegiatan || null,
      })).data,
    onSuccess: () => {
      setAlbumBaru({ nama: '', deskripsi: '', tanggal_kegiatan: '' })
      berhasil('Album dibuat.')
    },
    onError: gagal,
  })

  const sematkan = useMutation({
    mutationFn: async () =>
      (await api.post(`/admin/media/album/${terpilih!.id}/video`, videoBaru)).data,
    onSuccess: () => {
      setVideoBaru({ judul: '', url: '' })
      berhasil('Video tersemat pada album.')
    },
    onError: gagal,
  })

  const lepaskan = useMutation({
    mutationFn: async (id: number) =>
      (await api.delete(`/admin/media/album/${terpilih!.id}/video/${id}`)).data,
    onSuccess: () => berhasil('Video dilepas dari album.'),
    onError: gagal,
  })

  const unggah = useMutation({
    mutationFn: async (daftar: FileList) => {
      for (const satu of Array.from(daftar)) {
        const muatan = new FormData()

        muatan.append('berkas', satu)
        muatan.append('koleksi', 'galeri')
        muatan.append('album_id', String(terpilih!.id))
        muatan.append('alt', `Dokumentasi ${terpilih!.nama}`)

        await api.post('/admin/media', muatan)
      }
    },
    onSuccess: () => {
      if (berkas.current) berkas.current.value = ''
      berhasil('Foto terunggah ke album.')
    },
    onError: gagal,
  })

  if (album.error) return <GalatMuat pesan={pesanGalat(album.error)} />
  if (album.isPending) return <Rangka baris={5} />

  return (
    <div className="max-w-4xl space-y-6">
      <header>
        <h1 className="text-2xl">Galeri Kegiatan</h1>
        <p className="mt-1 text-slate-600">
          Album dokumentasi beserta foto dan video kegiatan desa.
        </p>
      </header>

      {sukses && <Pemberitahuan jenis="sukses">{sukses}</Pemberitahuan>}
      {pesan && <Pemberitahuan jenis="bahaya">{pesan}</Pemberitahuan>}

      <Kartu>
        <KepalaKartu judul="Album Baru" />
        <IsiKartu>
          <form
            className="space-y-4"
            onSubmit={(peristiwa) => {
              peristiwa.preventDefault()
              buatAlbum.mutate()
            }}
          >
            <Isian
              label="Nama album"
              required
              maxLength={150}
              value={albumBaru.nama}
              onChange={(e) => setAlbumBaru({ ...albumBaru, nama: e.target.value })}
              galat={galat['nama']}
            />
            <AreaTeks
              label="Keterangan"
              rows={2}
              value={albumBaru.deskripsi}
              onChange={(e) => setAlbumBaru({ ...albumBaru, deskripsi: e.target.value })}
              galat={galat['deskripsi']}
            />
            <Isian
              label="Tanggal kegiatan"
              type="date"
              value={albumBaru.tanggal_kegiatan}
              onChange={(e) => setAlbumBaru({ ...albumBaru, tanggal_kegiatan: e.target.value })}
              galat={galat['tanggal_kegiatan']}
            />
            <Tombol type="submit" memuat={buatAlbum.isPending}>
              <Plus aria-hidden className="size-4" /> Buat Album
            </Tombol>
          </form>
        </IsiKartu>
      </Kartu>

      <Kartu>
        <KepalaKartu judul="Album Tersedia" deskripsi="Pilih album untuk menambah foto atau menyematkan video." />
        {album.data.length === 0 ? (
          <IsiKartu>
            <KondisiKosong judul="Belum ada album" keterangan="Buat album lebih dulu untuk mulai mengunggah." />
          </IsiKartu>
        ) : (
          <ul className="divide-y divide-slate-100">
            {album.data.map((satu) => (
              <li key={satu.id}>
                <button
                  type="button"
                  onClick={() => { setTerpilih(satu); setSukses(''); setPesan('') }}
                  aria-pressed={terpilih?.id === satu.id}
                  className={`flex w-full items-center justify-between gap-3 px-5 py-4 text-left hover:bg-slate-50 ${
                    terpilih?.id === satu.id ? 'bg-desa-50' : ''
                  }`}
                >
                  <span className="min-w-0">
                    <span className="block font-medium text-slate-900">{satu.nama}</span>
                    <span className="mt-0.5 block text-xs text-slate-500">
                      {satu.media_count} foto · {tanggal(satu.tanggal_kegiatan)}
                    </span>
                  </span>
                  {terpilih?.id === satu.id && <Lencana nada="sukses">Dipilih</Lencana>}
                </button>
              </li>
            ))}
          </ul>
        )}
      </Kartu>

      {terpilih && (
        <>
          <Kartu>
            <KepalaKartu
              judul={`Foto — ${terpilih.nama}`}
              deskripsi="Berkas gambar dipindai dan diperkecil otomatis setelah diunggah."
            />
            <IsiKartu>
              <label className="mb-2 block text-sm font-medium text-slate-800" htmlFor="unggah-foto">
                Pilih berkas
              </label>
              <input
                id="unggah-foto"
                ref={berkas}
                type="file"
                accept="image/*"
                multiple
                onChange={(e) => e.target.files?.length && unggah.mutate(e.target.files)}
                className="block w-full text-sm text-slate-600 file:me-3 file:min-h-11 file:rounded-lg file:border-0 file:bg-desa-700 file:px-4 file:text-sm file:font-medium file:text-white hover:file:bg-desa-800"
              />
              {unggah.isPending && (
                <p className="mt-2 inline-flex items-center gap-2 text-sm text-slate-600">
                  <Upload aria-hidden className="size-4" /> Mengunggah…
                </p>
              )}
              <p className="mt-2 text-xs text-slate-500">
                <Images aria-hidden className="me-1 inline size-3" />
                Album ini memuat {terpilih.media_count} foto. Ukuran maksimal 10 MB per berkas.
              </p>
            </IsiKartu>
          </Kartu>

          <Kartu>
            <KepalaKartu
              judul={`Video — ${terpilih.nama}`}
              deskripsi="Tempelkan alamat video dari YouTube atau Vimeo."
            />
            <IsiKartu>
              <form
                className="space-y-4"
                onSubmit={(peristiwa) => {
                  peristiwa.preventDefault()
                  sematkan.mutate()
                }}
              >
                <Isian
                  label="Judul video"
                  required
                  maxLength={160}
                  value={videoBaru.judul}
                  onChange={(e) => setVideoBaru({ ...videoBaru, judul: e.target.value })}
                  galat={galat['judul']}
                />
                <Isian
                  label="Alamat video"
                  required
                  value={videoBaru.url}
                  onChange={(e) => setVideoBaru({ ...videoBaru, url: e.target.value })}
                  placeholder="https://www.youtube.com/watch?v=…"
                  petunjuk="Video diputar hanya setelah pengunjung menekan tombol putar, sehingga membuka galeri tidak menghubungi penyedia."
                  galat={galat['url']}
                />
                <Tombol type="submit" memuat={sematkan.isPending}>
                  <Video aria-hidden className="size-4" /> Sematkan Video
                </Tombol>
              </form>
            </IsiKartu>

            {video.data && video.data.length > 0 && (
              <ul className="divide-y divide-slate-100 border-t border-slate-100">
                {video.data.map((satu) => (
                  <li key={satu.id} className="flex items-center gap-3 px-5 py-3">
                    {satu.thumbnail ? (
                      <img src={satu.thumbnail} alt="" className="h-12 w-20 shrink-0 rounded object-cover" />
                    ) : (
                      <span className="grid h-12 w-20 shrink-0 place-items-center rounded bg-slate-100 text-slate-400">
                        <Video aria-hidden className="size-5" />
                      </span>
                    )}
                    <div className="min-w-0 flex-1">
                      <p className="font-medium text-slate-900">{satu.judul}</p>
                      <p className="mt-0.5 truncate text-xs text-slate-500">{satu.url_asli}</p>
                    </div>
                    <button
                      type="button"
                      onClick={() => lepaskan.mutate(satu.id)}
                      aria-label={`Lepas video ${satu.judul}`}
                      className="grid size-11 place-items-center rounded-lg text-red-700 hover:bg-red-50"
                    >
                      <Trash2 aria-hidden className="size-4" />
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </Kartu>
        </>
      )}
    </div>
  )
}
