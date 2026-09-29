import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { AlertTriangle, CheckCircle2, Clock, FileText, Inbox, MessageSquareWarning, UserCheck } from 'lucide-react'
import { api, pesanGalat } from '@/lib/api'
import { angka, judulKan } from '@/lib/format'
import { useAuth } from '@/lib/auth'
import { Kartu, KartuStatistik, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { GalatMuat, Rangka } from '@/components/ui/Status'

interface DataDasbor {
  permohonan: {
    per_status: Record<string, number>
    total: number
    melampaui_sla: number
    perlu_tindakan: number
  }
  kinerja_30_hari: { selesai: number; rata_hari_kerja: number; kepatuhan_sla_persen: number }
  pengaduan: { terbuka: number; melampaui_sla: number; per_kategori: Record<string, number> }
  konten: { menunggu_review: number; terbit_30_hari: number }
  pengguna: { warga_menunggu_verifikasi: number; total_warga: number }
}

export function Dasbor() {
  const { pengguna } = useAuth()

  const { data, isPending, error } = useQuery({
    queryKey: ['dasbor'],
    queryFn: async () => (await api.get<DataDasbor>('/admin/dashboard')).data,
    refetchInterval: 60_000,
  })

  if (error) return <GalatMuat pesan={pesanGalat(error)} />
  if (isPending) return <Rangka baris={4} />

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl">Dasbor</h1>
        <p className="mt-1 text-slate-600">
          Ringkasan kinerja layanan desa. Selamat bertugas, {pengguna?.nama}.
        </p>
      </header>

      <section aria-labelledby="ringkasan-layanan" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <h2 id="ringkasan-layanan" className="sr-only">Ringkasan layanan</h2>
        <KartuStatistik
          label="Perlu tindakan"
          nilai={angka(data.permohonan.perlu_tindakan)}
          keterangan="Permohonan menunggu diproses"
          ikon={<Inbox aria-hidden className="size-5" />}
          nada={data.permohonan.perlu_tindakan > 0 ? 'peringatan' : 'netral'}
        />
        <KartuStatistik
          label="Melampaui SLA"
          nilai={angka(data.permohonan.melampaui_sla)}
          keterangan="Segera tindak lanjuti"
          ikon={<AlertTriangle aria-hidden className="size-5" />}
          nada={data.permohonan.melampaui_sla > 0 ? 'bahaya' : 'positif'}
        />
        <KartuStatistik
          label="Kepatuhan SLA"
          nilai={`${data.kinerja_30_hari.kepatuhan_sla_persen}%`}
          keterangan="30 hari terakhir"
          ikon={<CheckCircle2 aria-hidden className="size-5" />}
          nada={data.kinerja_30_hari.kepatuhan_sla_persen >= 95 ? 'positif' : 'peringatan'}
        />
        <KartuStatistik
          label="Rata-rata proses"
          nilai={`${data.kinerja_30_hari.rata_hari_kerja} hari`}
          keterangan={`${data.kinerja_30_hari.selesai} permohonan selesai`}
          ikon={<Clock aria-hidden className="size-5" />}
        />
      </section>

      <div className="grid gap-6 lg:grid-cols-2">
        <Kartu>
          <KepalaKartu
            judul="Permohonan menurut Status"
            aksi={
              <Link to="/admin/permohonan" className="text-sm font-medium text-desa-700 hover:underline">
                Buka antrean
              </Link>
            }
          />
          <IsiKartu>
            <ul className="space-y-2">
              {Object.entries(data.permohonan.per_status).map(([status, jumlah]) => (
                <li key={status} className="flex items-center justify-between gap-4">
                  <Link
                    to={`/admin/permohonan?status=${status}`}
                    className="text-sm text-slate-700 hover:text-desa-700"
                  >
                    {judulKan(status)}
                  </Link>
                  <span className="font-medium tabular-nums">{angka(jumlah)}</span>
                </li>
              ))}
              {Object.keys(data.permohonan.per_status).length === 0 && (
                <li className="text-sm text-slate-600">Belum ada permohonan masuk.</li>
              )}
            </ul>
          </IsiKartu>
        </Kartu>

        <Kartu>
          <KepalaKartu
            judul="Pengaduan Masyarakat"
            aksi={
              <Link to="/admin/pengaduan" className="text-sm font-medium text-desa-700 hover:underline">
                Kelola pengaduan
              </Link>
            }
          />
          <IsiKartu className="space-y-4">
            <div className="flex gap-4">
              <div className="flex items-center gap-2">
                <MessageSquareWarning aria-hidden className="size-5 text-desa-700" />
                <div>
                  <p className="text-sm text-slate-600">Terbuka</p>
                  <p className="text-lg font-semibold">{angka(data.pengaduan.terbuka)}</p>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <AlertTriangle aria-hidden className="size-5 text-amber-600" />
                <div>
                  <p className="text-sm text-slate-600">Lewat tenggat</p>
                  <p className="text-lg font-semibold">{angka(data.pengaduan.melampaui_sla)}</p>
                </div>
              </div>
            </div>

            <ul className="space-y-1.5">
              {Object.entries(data.pengaduan.per_kategori).map(([kategori, jumlah]) => (
                <li key={kategori} className="flex items-center justify-between text-sm">
                  <span className="text-slate-700">{judulKan(kategori)}</span>
                  <span className="tabular-nums text-slate-600">{angka(jumlah)}</span>
                </li>
              ))}
            </ul>
          </IsiKartu>
        </Kartu>
      </div>

      <div className="grid gap-6 sm:grid-cols-2">
        <Kartu>
          <IsiKartu className="flex items-center gap-4">
            <UserCheck aria-hidden className="size-8 text-desa-700" />
            <div>
              <p className="text-sm text-slate-600">Warga menunggu verifikasi NIK</p>
              <p className="text-2xl font-semibold">{angka(data.pengguna.warga_menunggu_verifikasi)}</p>
              <Link to="/admin/pengguna?status=belum_verifikasi" className="text-sm font-medium text-desa-700 hover:underline">
                Verifikasi sekarang
              </Link>
            </div>
          </IsiKartu>
        </Kartu>

        <Kartu>
          <IsiKartu className="flex items-center gap-4">
            <FileText aria-hidden className="size-8 text-desa-700" />
            <div>
              <p className="text-sm text-slate-600">Konten menunggu review</p>
              <p className="text-2xl font-semibold">{angka(data.konten.menunggu_review)}</p>
              <Link to="/admin/konten?status=review" className="text-sm font-medium text-desa-700 hover:underline">
                Tinjau konten
              </Link>
            </div>
          </IsiKartu>
        </Kartu>
      </div>
    </div>
  )
}
