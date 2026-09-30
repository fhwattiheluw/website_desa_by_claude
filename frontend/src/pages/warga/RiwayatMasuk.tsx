import { useQuery } from '@tanstack/react-query'
import { Check, X } from 'lucide-react'
import { api, pesanGalat } from '@/lib/api'
import { tanggal } from '@/lib/format'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { GalatMuat, KondisiKosong, Rangka } from '@/components/ui/Status'

interface Upaya {
  berhasil: boolean
  aksi: string
  waktu: string | null
  alamat_ip: string | null
  agen: string | null
}

const KETERANGAN: Record<string, string> = {
  login: 'Berhasil masuk',
  login_gagal: 'Kata sandi salah',
  otp_gagal: 'Kode masuk salah',
}

/**
 * Menyederhanakan agen pengguna menjadi keterangan perangkat yang dapat dikenali
 * warga. Tujuannya bukan ketepatan teknis, melainkan agar pemilik akun dapat
 * menjawab satu pertanyaan: apakah ini saya?
 */
function perangkat(agen: string | null): string {
  if (!agen) return 'Perangkat tidak tercatat'

  const sistem = /Android/i.test(agen)
    ? 'Android'
    : /iPhone|iPad|iOS/i.test(agen)
      ? 'iOS'
      : /Windows/i.test(agen)
        ? 'Windows'
        : /Mac OS X|Macintosh/i.test(agen)
          ? 'macOS'
          : /Linux/i.test(agen)
            ? 'Linux'
            : 'Perangkat lain'

  const peramban = /Edg\//i.test(agen)
    ? 'Edge'
    : /OPR\/|Opera/i.test(agen)
      ? 'Opera'
      : /Chrome\//i.test(agen)
        ? 'Chrome'
        : /Firefox\//i.test(agen)
          ? 'Firefox'
          : /Safari\//i.test(agen)
            ? 'Safari'
            : 'peramban lain'

  return `${peramban} di ${sistem}`
}

/** REQ-F-USR-015: pemilik akun dapat memeriksa sendiri riwayat upaya masuk. */
export function RiwayatMasuk() {
  const { data, isPending, error } = useQuery({
    queryKey: ['riwayat-masuk'],
    queryFn: async () =>
      (await api.get<{ data: Upaya[]; catatan: string }>('/auth/riwayat-masuk')).data,
  })

  return (
    <Kartu>
      <KepalaKartu
        judul="Riwayat Masuk"
        deskripsi="Dua puluh upaya masuk terakhir pada akun Anda, berhasil maupun gagal."
      />
      {error ? (
        <IsiKartu>
          <GalatMuat pesan={pesanGalat(error)} />
        </IsiKartu>
      ) : isPending ? (
        <IsiKartu>
          <Rangka baris={3} />
        </IsiKartu>
      ) : data.data.length === 0 ? (
        <IsiKartu>
          <KondisiKosong
            judul="Belum ada catatan"
            keterangan="Riwayat akan terisi setiap kali ada upaya masuk ke akun ini."
          />
        </IsiKartu>
      ) : (
        <>
          <ul className="divide-y divide-slate-100">
            {data.data.map((upaya, urutan) => (
              <li key={`${upaya.waktu}-${urutan}`} className="flex items-start gap-3 px-5 py-3">
                <span
                  aria-hidden
                  className={`mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full ${
                    upaya.berhasil ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'
                  }`}
                >
                  {upaya.berhasil ? <Check className="size-3.5" /> : <X className="size-3.5" />}
                </span>
                <div className="min-w-0">
                  <p className="text-sm font-medium text-slate-900">
                    {KETERANGAN[upaya.aksi] ?? 'Upaya masuk gagal'}
                    <span className="sr-only">{upaya.berhasil ? '' : ' (gagal)'}</span>
                  </p>
                  <p className="mt-0.5 text-xs text-slate-600">
                    {tanggal(upaya.waktu, true)} · {perangkat(upaya.agen)}
                  </p>
                  <p className="mt-0.5 text-xs text-slate-500 tabular-nums">
                    Alamat IP {upaya.alamat_ip ?? 'tidak tercatat'}
                  </p>
                </div>
              </li>
            ))}
          </ul>
          <IsiKartu>
            <p className="text-xs text-slate-500">{data.catatan}</p>
          </IsiKartu>
        </>
      )}
    </Kartu>
  )
}
