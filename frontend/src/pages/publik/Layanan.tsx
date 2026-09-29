import { Link } from 'react-router-dom'
import { CheckCircle2, Clock, FileText, Wallet } from 'lucide-react'
import { useLayanan } from '@/lib/kueri'
import { pesanGalat } from '@/lib/api'
import { Kartu } from '@/components/ui/Kartu'
import { GalatMuat, Rangka } from '@/components/ui/Status'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'

/** REQ-F-SRT-001: katalog layanan lengkap dengan persyaratan, biaya, dan SLA. */
export function Layanan() {
  const { data, isPending, error } = useLayanan()

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl">Layanan Administrasi Desa</h1>
        <p className="mt-1 text-slate-600">
          Ajukan surat keterangan secara daring tanpa perlu datang ke kantor desa. Seluruh layanan tidak dipungut biaya.
        </p>
      </header>

      <Pemberitahuan jenis="info" judul="Sebelum mengajukan">
        Anda perlu memiliki akun warga yang telah diverifikasi petugas desa. Siapkan berkas persyaratan dalam bentuk
        foto atau pindaian (JPG, PNG, atau PDF, maksimal 5 MB per berkas).
      </Pemberitahuan>

      {error ? (
        <GalatMuat pesan={pesanGalat(error)} />
      ) : isPending ? (
        <Rangka baris={5} />
      ) : (
        <ul className="grid gap-5 md:grid-cols-2">
          {data?.map((layanan) => (
            <li key={layanan.kode}>
              <Kartu className="h-full transition-colors hover:border-desa-300">
                <div className="flex h-full flex-col p-5">
                  <div className="flex items-start gap-3">
                    <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-desa-50 text-desa-700">
                      <FileText aria-hidden className="size-5" />
                    </span>
                    <div className="min-w-0">
                      <h2 className="text-base leading-snug">
                        <Link to={`/layanan/${layanan.slug}`} className="hover:text-desa-700">
                          {layanan.nama}
                        </Link>
                      </h2>
                      <p className="mt-1 text-sm text-slate-600">{layanan.deskripsi}</p>
                    </div>
                  </div>

                  <dl className="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <div className="flex items-center gap-1.5">
                      <Clock aria-hidden className="size-4 text-slate-400" />
                      <dt className="sr-only">Waktu penyelesaian</dt>
                      <dd>{layanan.sla_hari_kerja} hari kerja</dd>
                    </div>
                    <div className="flex items-center gap-1.5">
                      <Wallet aria-hidden className="size-4 text-slate-400" />
                      <dt className="sr-only">Biaya</dt>
                      <dd>Gratis</dd>
                    </div>
                  </dl>

                  <div className="mt-4 flex-1">
                    <p className="text-sm font-medium text-slate-700">Persyaratan:</p>
                    <ul className="mt-1.5 space-y-1">
                      {layanan.persyaratan.map((syarat) => (
                        <li key={syarat} className="flex items-start gap-2 text-sm text-slate-600">
                          <CheckCircle2 aria-hidden className="mt-0.5 size-4 shrink-0 text-desa-600" />
                          {syarat}
                        </li>
                      ))}
                    </ul>
                  </div>

                  <Link
                    to={`/layanan/${layanan.slug}`}
                    className="mt-5 inline-flex min-h-11 items-center justify-center rounded-lg bg-desa-700 px-4 text-sm font-medium text-white hover:bg-desa-800"
                  >
                    Ajukan Permohonan
                  </Link>
                </div>
              </Kartu>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
