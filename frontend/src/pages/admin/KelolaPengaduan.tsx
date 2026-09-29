import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, pesanGalat } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { judulKan, tanggal } from '@/lib/format'
import { Kartu, IsiKartu } from '@/components/ui/Kartu'
import { AreaTeks, KotakCentang, Pilihan } from '@/components/ui/Isian'
import { Lencana, LencanaPengaduan } from '@/components/ui/Lencana'
import { Tombol } from '@/components/ui/Tombol'
import { Dialog } from '@/components/ui/Dialog'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import { GalatMuat, KondisiKosong, Rangka } from '@/components/ui/Status'
import type { Halaman, Pengaduan, StatusPengaduan } from '@/types'

const LANJUTAN: Record<StatusPengaduan, StatusPengaduan[]> = {
  baru: ['diverifikasi', 'ditolak'],
  diverifikasi: ['didisposisi', 'ditolak'],
  didisposisi: ['proses'],
  proses: ['selesai'],
  selesai: [],
  ditolak: [],
}

export function KelolaPengaduan() {
  const klien = useQueryClient()
  const { punyaIzin } = useAuth()
  const [status, setStatus] = useState('')
  const [dipilih, setDipilih] = useState<Pengaduan | null>(null)
  const [tanggapan, setTanggapan] = useState('')
  const [internal, setInternal] = useState(false)
  const [statusBaru, setStatusBaru] = useState<StatusPengaduan | ''>('')
  const [pesan, setPesan] = useState('')

  const { data, isPending, error } = useQuery({
    queryKey: ['admin-pengaduan', status],
    queryFn: async () =>
      (await api.get<Halaman<Pengaduan>>('/admin/pengaduan', { params: { status: status || undefined } })).data,
  })

  const detail = useQuery({
    queryKey: ['admin-pengaduan-detail', dipilih?.id],
    queryFn: async () => (await api.get<{ data: Pengaduan }>(`/admin/pengaduan/${dipilih?.id}`)).data.data,
    enabled: Boolean(dipilih),
  })

  const segarkan = () => {
    void klien.invalidateQueries({ queryKey: ['admin-pengaduan'] })
    void klien.invalidateQueries({ queryKey: ['admin-pengaduan-detail'] })
    void klien.invalidateQueries({ queryKey: ['dasbor'] })
  }

  const kirimTanggapan = useMutation({
    mutationFn: async () => api.post(`/admin/pengaduan/${dipilih?.id}/tanggapan`, { isi: tanggapan, internal }),
    onSuccess: () => { setTanggapan(''); setPesan(''); segarkan() },
    onError: (kesalahan) => setPesan(pesanGalat(kesalahan)),
  })

  const ubahStatus = useMutation({
    mutationFn: async () =>
      api.post(`/admin/pengaduan/${dipilih?.id}/status`, { status: statusBaru, catatan: tanggapan || undefined }),
    onSuccess: () => { setStatusBaru(''); setTanggapan(''); setPesan(''); segarkan() },
    onError: (kesalahan) => setPesan(pesanGalat(kesalahan)),
  })

  const ubahPublikasi = useMutation({
    mutationFn: async (tampil: boolean) =>
      api.post(`/admin/pengaduan/${dipilih?.id}/publikasi`, { tampil_publik: tampil }),
    onSuccess: segarkan,
    onError: (kesalahan) => setPesan(pesanGalat(kesalahan)),
  })

  const aktif = detail.data ?? dipilih

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl">Pengaduan Masyarakat</h1>
        <p className="mt-1 text-slate-600">Tanggapi laporan warga paling lambat 3 hari kerja sejak diterima.</p>
      </header>

      <div className="max-w-xs">
        <Pilihan
          label="Saring status"
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          kosong="Semua status"
          pilihan={['baru', 'diverifikasi', 'didisposisi', 'proses', 'selesai', 'ditolak'].map((s) => ({
            nilai: s,
            teks: judulKan(s),
          }))}
        />
      </div>

      {error ? (
        <GalatMuat pesan={pesanGalat(error)} />
      ) : isPending ? (
        <Rangka baris={4} />
      ) : data.data.length === 0 ? (
        <KondisiKosong judul="Tidak ada pengaduan" keterangan="Belum ada laporan pada saringan ini." />
      ) : (
        <ul className="space-y-3">
          {data.data.map((pengaduan) => (
            <li key={pengaduan.id}>
              <Kartu>
                <IsiKartu>
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                      <div className="flex flex-wrap items-center gap-2">
                        <LencanaPengaduan status={pengaduan.status} />
                        <Lencana>{judulKan(pengaduan.kategori)}</Lencana>
                        {pengaduan.melampaui_sla && <Lencana nada="bahaya">Lewat tenggat</Lencana>}
                        {pengaduan.tampil_publik && <Lencana nada="info">Tampil publik</Lencana>}
                      </div>
                      <p className="mt-2 font-medium">{pengaduan.judul}</p>
                      <p className="mt-0.5 line-clamp-2 text-sm text-slate-600">{pengaduan.uraian}</p>
                      <p className="mt-1 text-xs text-slate-500">
                        {pengaduan.nomor_tiket} · {pengaduan.pelapor} · {tanggal(pengaduan.dibuat_pada)}
                      </p>
                    </div>
                    <Tombol ragam="garis" ukuran="kecil" onClick={() => { setDipilih(pengaduan); setPesan('') }}>
                      Kelola
                    </Tombol>
                  </div>
                </IsiKartu>
              </Kartu>
            </li>
          ))}
        </ul>
      )}

      <Dialog
        terbuka={aktif !== null}
        judul={aktif?.judul ?? ''}
        deskripsi={aktif ? `${aktif.nomor_tiket} · ${judulKan(aktif.kategori)}` : undefined}
        onTutup={() => { setDipilih(null); setTanggapan(''); setStatusBaru(''); setPesan('') }}
      >
        {aktif && (
          <div className="space-y-5">
            {pesan && <Pemberitahuan jenis="bahaya">{pesan}</Pemberitahuan>}

            <div>
              <h3 className="text-sm font-medium text-slate-700">Uraian laporan</h3>
              <p className="mt-1 whitespace-pre-line text-sm text-slate-700">{aktif.uraian}</p>
              <p className="mt-2 text-xs text-slate-500">
                Kontak pelapor: {aktif.kontak_pelapor ?? 'tidak tersedia'} · Tenggat{' '}
                {tanggal(aktif.tenggat_tanggapan, true)}
              </p>
            </div>

            {(detail.data?.tanggapan?.length ?? 0) > 0 && (
              <div>
                <h3 className="text-sm font-medium text-slate-700">Tanggapan sebelumnya</h3>
                <ul className="mt-2 space-y-2">
                  {detail.data?.tanggapan?.map((item, indeks) => (
                    <li key={indeks} className="rounded-lg bg-slate-50 p-3 text-sm">
                      <p className="text-slate-700">{item.isi}</p>
                      <p className="mt-1 text-xs text-slate-500">
                        {item.oleh} · {tanggal(item.waktu, true)}
                        {item.internal && ' · catatan internal'}
                      </p>
                    </li>
                  ))}
                </ul>
              </div>
            )}

            <AreaTeks
              label="Tanggapan"
              value={tanggapan}
              onChange={(e) => setTanggapan(e.target.value)}
              rows={4}
              petunjuk="Tanggapan publik akan dikirimkan kepada pelapor melalui kanal kontaknya."
            />

            <KotakCentang
              label="Simpan sebagai catatan internal (tidak dikirim ke pelapor)"
              checked={internal}
              onChange={(e) => setInternal(e.target.checked)}
            />

            <div className="flex flex-wrap gap-2">
              <Tombol
                ukuran="kecil"
                disabled={tanggapan.trim().length < 10}
                memuat={kirimTanggapan.isPending}
                onClick={() => kirimTanggapan.mutate()}
              >
                Kirim Tanggapan
              </Tombol>

              {punyaIzin('pengaduan.moderasi') && (
                <Tombol
                  ukuran="kecil"
                  ragam="garis"
                  memuat={ubahPublikasi.isPending}
                  onClick={() => ubahPublikasi.mutate(!aktif.tampil_publik)}
                >
                  {aktif.tampil_publik ? 'Sembunyikan dari publik' : 'Tampilkan ke publik'}
                </Tombol>
              )}
            </div>

            {LANJUTAN[aktif.status].length > 0 && (
              <div className="border-t border-slate-200 pt-4">
                <Pilihan
                  label="Ubah status"
                  value={statusBaru}
                  onChange={(e) => setStatusBaru(e.target.value as StatusPengaduan)}
                  kosong="Pilih status berikutnya"
                  pilihan={LANJUTAN[aktif.status].map((s) => ({ nilai: s, teks: judulKan(s) }))}
                />
                <Tombol
                  className="mt-3"
                  ukuran="kecil"
                  disabled={!statusBaru || (statusBaru === 'ditolak' && tanggapan.trim().length < 20)}
                  memuat={ubahStatus.isPending}
                  onClick={() => ubahStatus.mutate()}
                >
                  Simpan Status
                </Tombol>
                {statusBaru === 'ditolak' && (
                  <p className="mt-2 text-xs text-slate-500">
                    Penolakan wajib disertai alasan minimal 20 karakter pada kolom tanggapan.
                  </p>
                )}
              </div>
            )}
          </div>
        )}
      </Dialog>
    </div>
  )
}
