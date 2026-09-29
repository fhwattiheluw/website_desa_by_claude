import { useState, type FormEvent } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { ClipboardCheck, Copy } from 'lucide-react'
import { api, galatKolom, pesanGalat } from '@/lib/api'
import { judulKan, tanggal } from '@/lib/format'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { AreaTeks, Isian, KotakCentang, Pilihan } from '@/components/ui/Isian'
import { Tombol } from '@/components/ui/Tombol'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import { LencanaPengaduan } from '@/components/ui/Lencana'
import { useAuth } from '@/lib/auth'
import { useMeta } from '@/lib/meta'

interface HasilKirim {
  pesan: string
  nomor_tiket: string
  kode_lacak: string
  tenggat_tanggapan: string
}

export function Pengaduan() {
  const { pengguna } = useAuth()
  const [anonim, setAnonim] = useState(false)
  const [hasil, setHasil] = useState<HasilKirim | null>(null)
  const [galat, setGalat] = useState<Record<string, string>>({})
  const [pesan, setPesan] = useState('')

  useMeta({
    judul: 'Pengaduan dan Aspirasi Masyarakat',
    deskripsi: 'Sampaikan laporan, keluhan, atau usulan kepada pemerintah desa dan pantau tindak lanjutnya.',
  })

  const { data: kategori } = useQuery({
    queryKey: ['kategori-pengaduan'],
    queryFn: async () => (await api.get<{ data: string[] }>('/pengaduan/kategori')).data.data,
  })

  const { data: pengaduanPublik } = useQuery({
    queryKey: ['pengaduan-publik'],
    queryFn: async () => (await api.get('/pengaduan/publik')).data as {
      data: { nomor_tiket: string; judul: string; kategori: string; status: string; pelapor: string; dibuat_pada: string }[]
    },
  })

  const kirim = useMutation({
    mutationFn: async (formulir: FormData) => {
      const muatan = Object.fromEntries(formulir.entries())
      return (await api.post<HasilKirim>('/pengaduan', { ...muatan, anonim })).data
    },
    onSuccess: (data) => {
      setHasil(data)
      setGalat({})
      setPesan('')
    },
    onError: (kesalahan) => {
      setGalat(galatKolom(kesalahan))
      setPesan(pesanGalat(kesalahan))
    },
  })

  const padaKirim = (peristiwa: FormEvent<HTMLFormElement>) => {
    peristiwa.preventDefault()
    kirim.mutate(new FormData(peristiwa.currentTarget))
  }

  if (hasil) {
    return (
      <div className="mx-auto max-w-2xl space-y-5">
        <Pemberitahuan jenis="sukses" judul="Pengaduan berhasil dikirim">
          {hasil.pesan}
        </Pemberitahuan>

        <Kartu>
          <IsiKartu className="space-y-4">
            <div>
              <p className="text-sm text-slate-600">Nomor tiket</p>
              <p className="text-lg font-semibold">{hasil.nomor_tiket}</p>
            </div>
            <div>
              <p className="text-sm text-slate-600">Kode lacak</p>
              <div className="flex items-center gap-2">
                <p className="text-lg font-semibold tracking-wider">{hasil.kode_lacak}</p>
                <button
                  type="button"
                  onClick={() => void navigator.clipboard?.writeText(hasil.kode_lacak)}
                  className="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-slate-300 px-2.5 text-sm hover:bg-slate-50"
                >
                  <Copy aria-hidden className="size-4" /> Salin
                </button>
              </div>
              <p className="mt-1 text-sm text-slate-600">
                Simpan kode ini untuk memantau tindak lanjut pengaduan Anda.
              </p>
            </div>
            <div>
              <p className="text-sm text-slate-600">Tenggat tanggapan pertama</p>
              <p className="font-medium">{tanggal(hasil.tenggat_tanggapan, true)}</p>
            </div>
            <div className="flex flex-wrap gap-3 pt-2">
              <Link
                to={`/pengaduan/lacak?kode=${hasil.kode_lacak}`}
                className="inline-flex min-h-11 items-center rounded-lg bg-desa-700 px-4 text-sm font-medium text-white hover:bg-desa-800"
              >
                Lacak Pengaduan
              </Link>
              <Tombol ragam="garis" onClick={() => setHasil(null)}>
                Kirim Pengaduan Lain
              </Tombol>
            </div>
          </IsiKartu>
        </Kartu>
      </div>
    )
  }

  return (
    <div className="space-y-8">
      <header>
        <h1 className="text-2xl">Pengaduan dan Aspirasi Masyarakat</h1>
        <p className="mt-1 text-slate-600">
          Sampaikan laporan, keluhan, atau usulan Anda. Setiap pengaduan ditanggapi paling lambat 3 hari kerja.
        </p>
      </header>

      <div className="grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <Kartu>
          <KepalaKartu judul="Formulir Pengaduan" deskripsi="Kolom bertanda bintang wajib diisi." />
          <IsiKartu>
            {pesan && (
              <div className="mb-4">
                <Pemberitahuan jenis="bahaya">{pesan}</Pemberitahuan>
              </div>
            )}

            <form onSubmit={padaKirim} className="space-y-5">
              <KotakCentang
                label="Kirim sebagai pengaduan anonim (nama saya tidak ditampilkan kepada petugas)"
                checked={anonim}
                onChange={(e) => setAnonim(e.target.checked)}
              />

              {!anonim && (
                <Isian
                  label="Nama pelapor"
                  name="nama_pelapor"
                  defaultValue={pengguna?.nama ?? ''}
                  galat={galat['nama_pelapor']}
                  autoComplete="name"
                />
              )}

              <Isian
                label="Surel atau nomor WhatsApp"
                name="kontak_pelapor"
                required
                defaultValue={pengguna?.email ?? ''}
                petunjuk="Diperlukan agar kami dapat memberi tahu perkembangan tindak lanjut."
                galat={galat['kontak_pelapor']}
              />

              <Pilihan
                label="Kategori pengaduan"
                name="kategori"
                required
                kosong="Pilih kategori"
                pilihan={(kategori ?? []).map((k) => ({ nilai: k, teks: judulKan(k) }))}
                galat={galat['kategori']}
              />

              <Isian label="Judul laporan" name="judul" required maxLength={200} galat={galat['judul']} />

              <AreaTeks
                label="Uraian laporan"
                name="uraian"
                required
                rows={6}
                petunjuk="Jelaskan kejadian selengkap mungkin, minimal 20 karakter."
                galat={galat['uraian']}
              />

              <div className="grid gap-5 sm:grid-cols-2">
                <Isian label="Lokasi kejadian" name="lokasi" galat={galat['lokasi']} />
                <Isian label="Tanggal kejadian" name="tanggal_kejadian" type="date" galat={galat['tanggal_kejadian']} />
              </div>

              <p className="text-sm text-slate-500">
                Dengan mengirim laporan, Anda menyetujui pemrosesan data kontak untuk keperluan tindak lanjut sesuai
                kebijakan privasi. Identitas pelapor tidak pernah ditampilkan pada laman publik.
              </p>

              <Tombol type="submit" ukuran="besar" memuat={kirim.isPending}>
                Kirim Pengaduan
              </Tombol>
            </form>
          </IsiKartu>
        </Kartu>

        <div className="space-y-5">
          <Kartu>
            <KepalaKartu judul="Sudah pernah melapor?" />
            <IsiKartu>
              <p className="text-sm text-slate-600">
                Gunakan kode lacak yang Anda terima untuk memantau status pengaduan.
              </p>
              <Link
                to="/pengaduan/lacak"
                className="mt-3 inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 px-4 text-sm font-medium hover:bg-slate-50"
              >
                <ClipboardCheck aria-hidden className="size-4" /> Lacak Pengaduan
              </Link>
            </IsiKartu>
          </Kartu>

          <Kartu>
            <KepalaKartu judul="Pengaduan yang Dipublikasikan" deskripsi="Identitas pelapor disamarkan." />
            <ul className="divide-y divide-slate-100">
              {pengaduanPublik?.data.length ? (
                pengaduanPublik.data.map((item) => (
                  <li key={item.nomor_tiket} className="p-5">
                    <div className="flex items-start justify-between gap-3">
                      <p className="font-medium">{item.judul}</p>
                      <LencanaPengaduan status={item.status as never} />
                    </div>
                    <p className="mt-1 text-xs text-slate-500">
                      {judulKan(item.kategori)} · {item.pelapor} · {tanggal(item.dibuat_pada)}
                    </p>
                  </li>
                ))
              ) : (
                <li className="p-5 text-sm text-slate-600">Belum ada pengaduan yang dipublikasikan.</li>
              )}
            </ul>
          </Kartu>
        </div>
      </div>
    </div>
  )
}
