import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Download, Trash2 } from 'lucide-react'
import { api, galatKolom, pesanGalat, unduhBerkas } from '@/lib/api'
import { tanggal } from '@/lib/format'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { AreaTeks, KotakCentang } from '@/components/ui/Isian'
import { Tombol } from '@/components/ui/Tombol'
import { Lencana } from '@/components/ui/Lencana'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'

interface Permintaan {
  id: number
  jenis: string
  status: 'menunggu' | 'disetujui' | 'ditolak'
  alasan: string | null
  catatan_petugas: string | null
  diajukan_pada: string | null
  tenggat_jawaban: string | null
  ditindak_pada: string | null
}

interface RingkasanHak {
  tenggat_jawaban_hari: number
  ada_permintaan_tertunda: boolean
  data: Permintaan[]
}

const NADA: Record<Permintaan['status'], 'peringatan' | 'sukses' | 'bahaya'> = {
  menunggu: 'peringatan',
  disetujui: 'sukses',
  ditolak: 'bahaya',
}

const TEKS_STATUS: Record<Permintaan['status'], string> = {
  menunggu: 'Sedang ditinjau',
  disetujui: 'Disetujui',
  ditolak: 'Ditolak',
}

/**
 * REQ-F-USR-013: hak subjek data — memperoleh salinan data pribadi dan
 * mengajukan penghapusannya.
 */
export function HakDataPribadi() {
  const klien = useQueryClient()
  const [alasan, setAlasan] = useState('')
  const [setuju, setSetuju] = useState(false)
  const [galat, setGalat] = useState<Record<string, string>>({})
  const [pesan, setPesan] = useState('')
  const [sukses, setSukses] = useState('')
  const [mengunduh, setMengunduh] = useState(false)

  const { data } = useQuery({
    queryKey: ['hak-data-pribadi'],
    queryFn: async () => (await api.get<RingkasanHak>('/auth/data-pribadi')).data,
  })

  const unduh = async () => {
    setMengunduh(true)
    setPesan('')

    try {
      // Berkas hanya dapat diambil dengan token, jadi tidak bisa memakai
      // tautan biasa.
      await unduhBerkas('/auth/data-pribadi/unduh', `data-pribadi-${new Date().toISOString().slice(0, 10)}.json`)
    } catch (kesalahan) {
      setPesan(pesanGalat(kesalahan))
    } finally {
      setMengunduh(false)
    }
  }

  const ajukan = useMutation({
    mutationFn: async () =>
      (await api.post<{ pesan: string }>('/auth/data-pribadi/penghapusan', { alasan, konfirmasi: setuju })).data,
    onSuccess: (hasil) => {
      setSukses(hasil.pesan)
      setGalat({})
      setPesan('')
      setAlasan('')
      setSetuju(false)
      void klien.invalidateQueries({ queryKey: ['hak-data-pribadi'] })
    },
    onError: (kesalahan) => {
      setGalat(galatKolom(kesalahan))
      setPesan(pesanGalat(kesalahan))
    },
  })

  const kirim = (peristiwa: FormEvent) => {
    peristiwa.preventDefault()
    ajukan.mutate()
  }

  const riwayat = data?.data ?? []

  return (
    <Kartu>
      <KepalaKartu
        judul="Hak atas Data Pribadi Anda"
        deskripsi="Sesuai Undang-Undang Pelindungan Data Pribadi, Anda berhak memperoleh salinan data yang kami simpan dan meminta penghapusannya."
      />
      <IsiKartu className="space-y-5">
        {sukses && <Pemberitahuan jenis="sukses">{sukses}</Pemberitahuan>}
        {pesan && <Pemberitahuan jenis="bahaya">{pesan}</Pemberitahuan>}

        <div>
          <h3 className="text-base font-medium">Unduh salinan data</h3>
          <p className="mt-1 text-sm text-slate-600">
            Berisi data diri, riwayat permohonan layanan, pengaduan, dan permohonan informasi publik Anda dalam format
            JSON yang dapat dibaca aplikasi lain.
          </p>
          <Tombol ragam="garis" ukuran="kecil" className="mt-3" memuat={mengunduh} onClick={() => void unduh()}>
            <Download aria-hidden className="size-4" /> Unduh Data Pribadi
          </Tombol>
        </div>

        <div className="border-t border-slate-100 pt-5">
          <h3 className="text-base font-medium">Ajukan penghapusan data</h3>

          {riwayat.length > 0 && (
            <ul className="mt-3 space-y-3">
              {riwayat.map((permintaan) => (
                <li key={permintaan.id} className="rounded-lg border border-slate-200 p-4">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-sm font-medium">Diajukan {tanggal(permintaan.diajukan_pada)}</p>
                    <Lencana nada={NADA[permintaan.status]}>{TEKS_STATUS[permintaan.status]}</Lencana>
                  </div>
                  {permintaan.alasan && <p className="mt-2 text-sm text-slate-600">Alasan Anda: {permintaan.alasan}</p>}
                  {permintaan.status === 'menunggu' && permintaan.tenggat_jawaban && (
                    <p className="mt-2 text-sm text-slate-600">
                      Dijawab paling lambat {tanggal(permintaan.tenggat_jawaban)}.
                    </p>
                  )}
                  {permintaan.catatan_petugas && (
                    <p className="mt-2 text-sm text-slate-700">Catatan petugas: {permintaan.catatan_petugas}</p>
                  )}
                </li>
              ))}
            </ul>
          )}

          {data?.ada_permintaan_tertunda ? (
            <p className="mt-3 text-sm text-slate-600">
              Permintaan Anda sedang ditinjau petugas desa. Anda akan dihubungi melalui kanal kontak yang terdaftar.
            </p>
          ) : (
            <form onSubmit={kirim} className="mt-3 space-y-4">
              <AreaTeks
                label="Alasan permintaan (opsional)"
                name="alasan"
                rows={3}
                value={alasan}
                onChange={(e) => setAlasan(e.target.value)}
                petunjuk="Misalnya pindah domisili atau menarik persetujuan pemrosesan data."
                galat={galat['alasan']}
              />
              <KotakCentang
                checked={setuju}
                onChange={(e) => setSetuju(e.target.checked)}
                label="Saya memahami bahwa akun saya akan dinonaktifkan dan data pribadi saya dihapus atau dianonimkan, serta tindakan ini tidak dapat dibatalkan."
                galat={galat['konfirmasi']}
              />
              <p className="text-sm text-slate-500">
                Surat yang telah diterbitkan atas nama Anda tetap disimpan sebagai arsip pemerintahan desa karena
                diwajibkan peraturan, dan tidak lagi dapat dikaitkan dengan akun Anda.
              </p>
              <Tombol ragam="bahaya" disabled={!setuju} memuat={ajukan.isPending} type="submit">
                <Trash2 aria-hidden className="size-4" /> Ajukan Penghapusan Data
              </Tombol>
            </form>
          )}
        </div>
      </IsiKartu>
    </Kartu>
  )
}
