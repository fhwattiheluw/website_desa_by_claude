import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useSearchParams } from 'react-router-dom'
import { api, pesanGalat } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { judulKan, tanggal } from '@/lib/format'
import { Kartu } from '@/components/ui/Kartu'
import { Isian, Pilihan } from '@/components/ui/Isian'
import { Lencana } from '@/components/ui/Lencana'
import { Tombol } from '@/components/ui/Tombol'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import { GalatMuat, KondisiKosong, Rangka } from '@/components/ui/Status'
import { Paginasi } from '@/components/ui/Paginasi'
import type { Halaman } from '@/types'

interface BarisPengguna {
  id: number
  nama: string
  email: string
  telepon: string | null
  nik_tersamar: string | null
  peran: string | null
  status_akun: string
  nik_terverifikasi: boolean
  terdaftar_pada: string
  masuk_terakhir: string | null
}

export function KelolaPengguna() {
  const klien = useQueryClient()
  const { punyaIzin, pengguna: sendiri } = useAuth()
  const [parameter, setParameter] = useSearchParams()
  const [halaman, setHalaman] = useState(1)
  const [q, setQ] = useState('')
  const [pesan, setPesan] = useState('')
  const [sukses, setSukses] = useState('')

  const status = parameter.get('status') ?? ''
  const peran = parameter.get('peran') ?? ''

  const { data, isPending, error } = useQuery({
    queryKey: ['admin-pengguna', status, peran, q, halaman],
    queryFn: async () =>
      (
        await api.get<Halaman<BarisPengguna>>('/admin/pengguna', {
          params: { page: halaman, status: status || undefined, peran: peran || undefined, q: q || undefined },
        })
      ).data,
  })

  const segarkan = () => {
    void klien.invalidateQueries({ queryKey: ['admin-pengguna'] })
    void klien.invalidateQueries({ queryKey: ['dasbor'] })
  }

  const verifikasi = useMutation({
    mutationFn: async ({ id, disetujui }: { id: number; disetujui: boolean }) =>
      (await api.post(`/admin/pengguna/${id}/verifikasi-nik`, { disetujui })).data,
    onSuccess: (hasil: { pesan?: string }) => { setSukses(hasil.pesan ?? 'Tersimpan.'); setPesan(''); segarkan() },
    onError: (kesalahan) => setPesan(pesanGalat(kesalahan)),
  })

  const ubahStatus = useMutation({
    mutationFn: async ({ id, statusBaru }: { id: number; statusBaru: string }) =>
      (await api.post(`/admin/pengguna/${id}/status`, { status_akun: statusBaru })).data,
    onSuccess: (hasil: { pesan?: string }) => { setSukses(hasil.pesan ?? 'Tersimpan.'); setPesan(''); segarkan() },
    onError: (kesalahan) => setPesan(pesanGalat(kesalahan)),
  })

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl">Manajemen Pengguna</h1>
        <p className="mt-1 text-slate-600">
          Verifikasi NIK warga sebelum mereka dapat mengajukan layanan administrasi.
        </p>
      </header>

      {sukses && <Pemberitahuan jenis="sukses">{sukses}</Pemberitahuan>}
      {pesan && <Pemberitahuan jenis="bahaya">{pesan}</Pemberitahuan>}

      <div className="grid max-w-3xl gap-4 sm:grid-cols-3">
        <Pilihan
          label="Status akun"
          value={status}
          onChange={(e) => { setParameter({ status: e.target.value, peran }); setHalaman(1) }}
          kosong="Semua status"
          pilihan={['belum_verifikasi', 'aktif', 'nonaktif'].map((s) => ({ nilai: s, teks: judulKan(s) }))}
        />
        <Pilihan
          label="Peran"
          value={peran}
          onChange={(e) => { setParameter({ status, peran: e.target.value }); setHalaman(1) }}
          kosong="Semua peran"
          pilihan={['warga', 'operator', 'verifikator', 'sekdes', 'kades', 'admin'].map((p) => ({
            nilai: p,
            teks: judulKan(p),
          }))}
        />
        <Isian label="Cari" value={q} onChange={(e) => { setQ(e.target.value); setHalaman(1) }} placeholder="Nama atau surel" />
      </div>

      {error ? (
        <GalatMuat pesan={pesanGalat(error)} />
      ) : isPending ? (
        <Rangka baris={4} />
      ) : data.data.length === 0 ? (
        <KondisiKosong judul="Pengguna tidak ditemukan" />
      ) : (
        <>
          <Kartu>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <caption className="sr-only">Daftar pengguna sistem</caption>
                <thead>
                  <tr className="border-b border-slate-200 bg-slate-50 text-left text-slate-600">
                    <th scope="col" className="px-4 py-3 font-medium">Nama</th>
                    <th scope="col" className="px-4 py-3 font-medium">NIK</th>
                    <th scope="col" className="px-4 py-3 font-medium">Peran</th>
                    <th scope="col" className="px-4 py-3 font-medium">Status</th>
                    <th scope="col" className="px-4 py-3 font-medium">Tindakan</th>
                  </tr>
                </thead>
                <tbody>
                  {data.data.map((baris) => (
                    <tr key={baris.id} className="border-b border-slate-100 last:border-0">
                      <td className="px-4 py-3">
                        <p className="font-medium text-slate-900">{baris.nama}</p>
                        <p className="text-xs text-slate-500">{baris.email}</p>
                        <p className="text-xs text-slate-500">Terdaftar {tanggal(baris.terdaftar_pada)}</p>
                      </td>
                      <td className="px-4 py-3 tabular-nums">{baris.nik_tersamar ?? '-'}</td>
                      <td className="px-4 py-3">{judulKan(baris.peran ?? '-')}</td>
                      <td className="px-4 py-3">
                        <Lencana
                          nada={
                            baris.status_akun === 'aktif'
                              ? 'sukses'
                              : baris.status_akun === 'nonaktif'
                                ? 'bahaya'
                                : 'peringatan'
                          }
                        >
                          {judulKan(baris.status_akun)}
                        </Lencana>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex flex-wrap gap-2">
                          {punyaIzin('pengguna.verifikasi') && !baris.nik_terverifikasi && baris.nik_tersamar && (
                            <Tombol
                              ukuran="kecil"
                              onClick={() => verifikasi.mutate({ id: baris.id, disetujui: true })}
                            >
                              Verifikasi NIK
                            </Tombol>
                          )}
                          {punyaIzin('pengguna.kelola') && baris.id !== sendiri?.id && (
                            <Tombol
                              ukuran="kecil"
                              ragam={baris.status_akun === 'nonaktif' ? 'halus' : 'garis'}
                              onClick={() =>
                                ubahStatus.mutate({
                                  id: baris.id,
                                  statusBaru: baris.status_akun === 'nonaktif' ? 'aktif' : 'nonaktif',
                                })
                              }
                            >
                              {baris.status_akun === 'nonaktif' ? 'Aktifkan' : 'Nonaktifkan'}
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

          <Paginasi halaman={data.current_page} totalHalaman={data.last_page} total={data.total} onPindah={setHalaman} />
        </>
      )}
    </div>
  )
}
