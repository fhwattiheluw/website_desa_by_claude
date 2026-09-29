import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { FileText, ShieldCheck, ShieldAlert } from 'lucide-react'
import { api, pesanGalat } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { tanggal } from '@/lib/format'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { LencanaPermohonan } from '@/components/ui/Lencana'
import { TautanTombol, Tombol } from '@/components/ui/Tombol'
import { GalatMuat, KondisiKosong, Rangka } from '@/components/ui/Status'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import type { Halaman, Permohonan } from '@/types'

export function Akun() {
  const { pengguna, keluar } = useAuth()

  const { data, isPending, error } = useQuery({
    queryKey: ['permohonan-saya'],
    queryFn: async () => (await api.get<Halaman<Permohonan>>('/permohonan')).data,
  })

  return (
    <div className="mx-auto max-w-4xl space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl">Akun Saya</h1>
          <p className="mt-1 text-slate-600">Selamat datang, {pengguna?.nama}.</p>
        </div>
        <Tombol ragam="garis" onClick={() => void keluar()}>
          Keluar
        </Tombol>
      </header>

      {pengguna?.boleh_mengajukan ? (
        <Pemberitahuan jenis="sukses" judul="Akun terverifikasi">
          <span className="inline-flex items-center gap-1.5">
            <ShieldCheck aria-hidden className="size-4" /> Anda dapat mengajukan seluruh layanan administrasi desa.
          </span>
        </Pemberitahuan>
      ) : (
        <Pemberitahuan jenis="peringatan" judul="Menunggu verifikasi NIK">
          <span className="inline-flex items-center gap-1.5">
            <ShieldAlert aria-hidden className="size-4" />
            Petugas desa sedang memvalidasi kecocokan NIK Anda dengan data kependudukan. Anda akan dapat mengajukan
            layanan setelah proses ini selesai.
          </span>
        </Pemberitahuan>
      )}

      <Kartu>
        <KepalaKartu judul="Data Diri" deskripsi="Data ini dipakai untuk mengisi otomatis formulir permohonan." />
        <IsiKartu>
          <dl className="grid gap-4 sm:grid-cols-2">
            <div>
              <dt className="text-sm text-slate-600">Nama lengkap</dt>
              <dd className="font-medium">{pengguna?.nama}</dd>
            </div>
            <div>
              <dt className="text-sm text-slate-600">NIK</dt>
              <dd className="font-medium tabular-nums">{pengguna?.nik_tersamar ?? '-'}</dd>
            </div>
            <div>
              <dt className="text-sm text-slate-600">Surel</dt>
              <dd className="font-medium">{pengguna?.email}</dd>
            </div>
            <div>
              <dt className="text-sm text-slate-600">Nomor WhatsApp</dt>
              <dd className="font-medium">{pengguna?.telepon ?? '-'}</dd>
            </div>
            <div className="sm:col-span-2">
              <dt className="text-sm text-slate-600">Alamat</dt>
              <dd className="font-medium">{pengguna?.alamat ?? '-'}</dd>
            </div>
          </dl>
          <p className="mt-4 text-xs text-slate-500">
            NIK ditampilkan sebagian demi keamanan. Anda berhak meminta koreksi atau penghapusan data pribadi melalui
            kantor desa.
          </p>
        </IsiKartu>
      </Kartu>

      <Kartu>
        <KepalaKartu
          judul="Permohonan Saya"
          deskripsi="Riwayat pengajuan layanan administrasi."
          aksi={<TautanTombol to="/layanan" ukuran="kecil">Ajukan Baru</TautanTombol>}
        />
        {error ? (
          <IsiKartu>
            <GalatMuat pesan={pesanGalat(error)} />
          </IsiKartu>
        ) : isPending ? (
          <IsiKartu>
            <Rangka baris={2} />
          </IsiKartu>
        ) : data.data.length === 0 ? (
          <IsiKartu>
            <KondisiKosong
              judul="Belum ada permohonan"
              keterangan="Ajukan surat administrasi tanpa perlu datang ke kantor desa."
              aksi={<TautanTombol to="/layanan">Lihat Katalog Layanan</TautanTombol>}
            />
          </IsiKartu>
        ) : (
          <ul className="divide-y divide-slate-100">
            {data.data.map((permohonan) => (
              <li key={permohonan.id}>
                <Link
                  to={`/akun/permohonan/${permohonan.id}`}
                  className="flex flex-wrap items-center justify-between gap-3 px-5 py-4 hover:bg-slate-50"
                >
                  <div className="flex min-w-0 gap-3">
                    <FileText aria-hidden className="mt-0.5 size-5 shrink-0 text-desa-700" />
                    <div className="min-w-0">
                      <p className="font-medium text-slate-900">{permohonan.layanan?.nama}</p>
                      <p className="mt-0.5 text-sm text-slate-600">{permohonan.nomor_tiket}</p>
                      <p className="mt-0.5 text-xs text-slate-500">
                        Diajukan {tanggal(permohonan.diajukan_pada)}
                        {permohonan.tenggat_sla && ` · Target selesai ${tanggal(permohonan.tenggat_sla)}`}
                      </p>
                    </div>
                  </div>
                  <LencanaPermohonan status={permohonan.status} />
                </Link>
              </li>
            ))}
          </ul>
        )}
      </Kartu>
    </div>
  )
}
