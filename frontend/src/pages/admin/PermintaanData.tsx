import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertTriangle, ShieldOff } from 'lucide-react'
import { api, galatKolom, pesanGalat } from '@/lib/api'
import { tanggal } from '@/lib/format'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { AreaTeks } from '@/components/ui/Isian'
import { Tombol } from '@/components/ui/Tombol'
import { Lencana } from '@/components/ui/Lencana'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import { Paginasi } from '@/components/ui/Paginasi'
import { GalatMuat, KondisiKosong, Rangka } from '@/components/ui/Status'
import type { Halaman } from '@/types'

interface Permintaan {
  id: number
  jenis: string
  status: 'menunggu' | 'disetujui' | 'ditolak'
  alasan: string | null
  catatan_petugas: string | null
  pemohon: { id: number; nama: string; surel: string; status_akun: string }
  petugas: string | null
  diajukan_pada: string | null
  tenggat_jawaban: string | null
  melampaui_tenggat: boolean
  ditindak_pada: string | null
}

const NADA = { menunggu: 'peringatan', disetujui: 'sukses', ditolak: 'bahaya' } as const
const TEKS = { menunggu: 'Menunggu', disetujui: 'Disetujui', ditolak: 'Ditolak' } as const

/** REQ-F-USR-013: penanganan permintaan hak subjek data oleh pengendali data desa. */
export function PermintaanData() {
  const klien = useQueryClient()
  const [halaman, setHalaman] = useState(1)
  const [dibuka, setDibuka] = useState<number | null>(null)
  const [catatan, setCatatan] = useState('')
  const [galat, setGalat] = useState<Record<string, string>>({})
  const [pesan, setPesan] = useState('')
  const [sukses, setSukses] = useState('')

  const { data, isPending, error } = useQuery({
    queryKey: ['permintaan-data', halaman],
    queryFn: async () => (await api.get<Halaman<Permintaan>>('/admin/permintaan-data', {
      params: { page: halaman },
    })).data,
  })

  const tindak = useMutation({
    mutationFn: async ({ id, keputusan }: { id: number; keputusan: 'setujui' | 'tolak' }) =>
      (await api.post<{ pesan: string }>(`/admin/permintaan-data/${id}/tindak`, { keputusan, catatan })).data,
    onSuccess: (hasil) => {
      setSukses(hasil.pesan)
      setPesan('')
      setGalat({})
      setCatatan('')
      setDibuka(null)
      void klien.invalidateQueries({ queryKey: ['permintaan-data'] })
    },
    onError: (kesalahan) => {
      setGalat(galatKolom(kesalahan))
      setPesan(pesanGalat(kesalahan))
    },
  })

  if (error) return <GalatMuat pesan={pesanGalat(error)} />

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl">Permintaan Hak Subjek Data</h1>
        <p className="mt-1 text-slate-600">
          Permintaan penghapusan data pribadi dari warga. Setiap keputusan wajib dijawab paling lambat tiga hari dan
          tercatat pada audit log.
        </p>
      </header>

      {sukses && <Pemberitahuan jenis="sukses">{sukses}</Pemberitahuan>}
      {pesan && <Pemberitahuan jenis="bahaya">{pesan}</Pemberitahuan>}

      <Pemberitahuan jenis="peringatan" judul="Persetujuan tidak dapat dibatalkan">
        Menyetujui permintaan akan menghapus data pribadi warga, menganonimkan pengaduannya, dan menonaktifkan akunnya.
        Surat yang telah diterbitkan tetap disimpan sebagai arsip. Tolak permintaan bila masih ada permohonan layanan
        berjalan, dan tuliskan alasannya.
      </Pemberitahuan>

      {isPending ? (
        <Rangka baris={3} />
      ) : data.data.length === 0 ? (
        <KondisiKosong
          judul="Belum ada permintaan"
          keterangan="Permintaan penghapusan data pribadi dari warga akan tampil di sini."
        />
      ) : (
        <>
          <ul className="space-y-4">
            {data.data.map((permintaan) => (
              <li key={permintaan.id}>
                <Kartu>
                  <KepalaKartu
                    judul={permintaan.pemohon.nama}
                    deskripsi={`${permintaan.pemohon.surel} · diajukan ${tanggal(permintaan.diajukan_pada)}`}
                    aksi={<Lencana nada={NADA[permintaan.status]}>{TEKS[permintaan.status]}</Lencana>}
                  />
                  <IsiKartu className="space-y-3">
                    {permintaan.melampaui_tenggat && (
                      <p className="flex items-center gap-2 text-sm font-medium text-red-700">
                        <AlertTriangle aria-hidden className="size-4" />
                        Melampaui tenggat jawaban {tanggal(permintaan.tenggat_jawaban)}.
                      </p>
                    )}

                    <p className="text-sm text-slate-700">
                      Alasan warga: {permintaan.alasan ?? 'Tidak dituliskan.'}
                    </p>

                    {permintaan.status === 'menunggu' ? (
                      dibuka === permintaan.id ? (
                        <div className="space-y-3 border-t border-slate-100 pt-3">
                          <AreaTeks
                            label="Catatan keputusan"
                            rows={3}
                            value={catatan}
                            onChange={(e) => setCatatan(e.target.value)}
                            petunjuk="Wajib bila menolak, minimal 10 karakter. Catatan disampaikan kepada warga."
                            galat={galat['catatan']}
                          />
                          <div className="flex flex-wrap gap-2">
                            <Tombol
                              ragam="bahaya"
                              ukuran="kecil"
                              memuat={tindak.isPending}
                              onClick={() => tindak.mutate({ id: permintaan.id, keputusan: 'setujui' })}
                            >
                              <ShieldOff aria-hidden className="size-4" /> Setujui dan Hapus Data
                            </Tombol>
                            <Tombol
                              ragam="garis"
                              ukuran="kecil"
                              memuat={tindak.isPending}
                              onClick={() => tindak.mutate({ id: permintaan.id, keputusan: 'tolak' })}
                            >
                              Tolak Permintaan
                            </Tombol>
                            <Tombol ragam="halus" ukuran="kecil" onClick={() => setDibuka(null)}>
                              Batal
                            </Tombol>
                          </div>
                        </div>
                      ) : (
                        <Tombol
                          ukuran="kecil"
                          onClick={() => {
                            setDibuka(permintaan.id)
                            setCatatan('')
                            setGalat({})
                          }}
                        >
                          Tindak Permintaan
                        </Tombol>
                      )
                    ) : (
                      <p className="text-sm text-slate-600">
                        Ditindak {tanggal(permintaan.ditindak_pada, true)} oleh {permintaan.petugas ?? 'petugas'}.
                        {permintaan.catatan_petugas && ` Catatan: ${permintaan.catatan_petugas}`}
                      </p>
                    )}
                  </IsiKartu>
                </Kartu>
              </li>
            ))}
          </ul>

          <Paginasi halaman={data.current_page} totalHalaman={data.last_page} onPindah={setHalaman} />
        </>
      )}
    </div>
  )
}
