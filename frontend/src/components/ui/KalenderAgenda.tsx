import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { ChevronLeft, ChevronRight, Clock, MapPin, Users } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { jam, tanggal } from '@/lib/format'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { Rangka } from '@/components/ui/Status'
import type { Konten } from '@/types'

const NAMA_BULAN = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]

/** Pekan dimulai Senin, mengikuti kelaziman kalender di Indonesia. */
const NAMA_HARI = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min']

function kunciTanggal(nilai: Date) {
  return `${nilai.getFullYear()}-${String(nilai.getMonth() + 1).padStart(2, '0')}-${String(nilai.getDate()).padStart(2, '0')}`
}

/**
 * Tampilan kalender bulanan untuk agenda kegiatan (REQ-F-KNT-012).
 *
 * Kalender adalah tabel: barisnya pekan, kolomnya hari. Menuliskannya sebagai
 * `<table>` membuat pembaca layar mengumumkan tanggal beserta hari yang benar,
 * yang tidak terjadi bila memakai kisi div.
 */
export function KalenderAgenda() {
  // Tanggal hari ini ditetapkan sekali saat komponen dibuat; membacanya ulang
  // tiap render membuat hasilnya berubah-ubah tanpa sebab.
  const [hariIni] = useState(() => new Date())
  const [bulan, setBulan] = useState(() => hariIni.getMonth())
  const [tahun, setTahun] = useState(() => hariIni.getFullYear())
  const [dipilih, setDipilih] = useState<string | null>(null)

  const { data, isPending } = useQuery({
    queryKey: ['agenda-kalender', tahun, bulan],
    queryFn: async () =>
      (await api.get<{ data: Konten[] }>('/agenda/kalender', { params: { bulan: bulan + 1, tahun } })).data.data,
  })

  const perTanggal = useMemo(() => {
    const peta = new Map<string, Konten[]>()

    for (const agenda of data ?? []) {
      if (!agenda.mulai_pada) continue

      const kunci = kunciTanggal(new Date(agenda.mulai_pada))
      peta.set(kunci, [...(peta.get(kunci) ?? []), agenda])
    }

    return peta
  }, [data])

  // Kisi selalu dimulai pada Senin pekan pertama dan berakhir pada Minggu
  // pekan terakhir, supaya setiap baris berisi tujuh sel penuh.
  const pekan = useMemo(() => {
    const awal = new Date(tahun, bulan, 1)
    const geser = (awal.getDay() + 6) % 7
    const mulai = new Date(tahun, bulan, 1 - geser)
    const baris: Date[][] = []

    for (let p = 0; p < 6; p++) {
      const hari = Array.from({ length: 7 }, (_, h) => new Date(mulai.getFullYear(), mulai.getMonth(), mulai.getDate() + p * 7 + h))

      // Baris yang seluruhnya di luar bulan ini tidak perlu digambar.
      if (p >= 4 && hari.every((satu) => satu.getMonth() !== bulan)) break

      baris.push(hari)
    }

    return baris
  }, [bulan, tahun])

  const geserBulan = (arah: number) => {
    const sasaran = new Date(tahun, bulan + arah, 1)
    setBulan(sasaran.getMonth())
    setTahun(sasaran.getFullYear())
    setDipilih(null)
  }

  const agendaDipilih = dipilih ? (perTanggal.get(dipilih) ?? []) : (data ?? [])

  return (
    <Kartu>
      <KepalaKartu
        judul={`${NAMA_BULAN[bulan]} ${tahun}`}
        deskripsi="Pilih tanggal bertanda untuk melihat kegiatannya."
        aksi={
          <span className="flex gap-1">
            <button
              type="button"
              onClick={() => geserBulan(-1)}
              aria-label="Bulan sebelumnya"
              className="grid size-11 place-items-center rounded-lg border border-slate-300 hover:bg-slate-50"
            >
              <ChevronLeft aria-hidden className="size-4" />
            </button>
            <button
              type="button"
              onClick={() => geserBulan(1)}
              aria-label="Bulan berikutnya"
              className="grid size-11 place-items-center rounded-lg border border-slate-300 hover:bg-slate-50"
            >
              <ChevronRight aria-hidden className="size-4" />
            </button>
          </span>
        }
      />

      <IsiKartu>
        {isPending ? (
          <Rangka baris={4} />
        ) : (
          <table className="w-full table-fixed border-collapse text-sm">
            <caption className="sr-only">
              Agenda kegiatan desa bulan {NAMA_BULAN[bulan]} {tahun}
            </caption>
            <thead>
              <tr>
                {NAMA_HARI.map((hari) => (
                  <th key={hari} scope="col" className="pb-2 text-center text-xs font-medium text-slate-600">
                    {hari}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {pekan.map((baris) => (
                <tr key={kunciTanggal(baris[0])}>
                  {baris.map((hari) => {
                    const kunci = kunciTanggal(hari)
                    const bulanIni = hari.getMonth() === bulan
                    const kegiatan = perTanggal.get(kunci) ?? []
                    const iniHariIni = kunci === kunciTanggal(hariIni)

                    return (
                      <td key={kunci} className="p-0.5 align-top">
                        <button
                          type="button"
                          disabled={kegiatan.length === 0}
                          onClick={() => setDipilih(dipilih === kunci ? null : kunci)}
                          aria-label={`${hari.getDate()} ${NAMA_BULAN[hari.getMonth()]} ${hari.getFullYear()}, ${kegiatan.length} kegiatan`}
                          aria-pressed={dipilih === kunci}
                          className={[
                            'flex min-h-12 w-full flex-col items-center justify-start gap-1 rounded-lg p-1.5',
                            bulanIni ? 'text-slate-900' : 'text-slate-400',
                            iniHariIni ? 'ring-1 ring-desa-600' : '',
                            kegiatan.length > 0 ? 'cursor-pointer hover:bg-desa-50' : 'cursor-default',
                            dipilih === kunci ? 'bg-desa-50' : '',
                          ].join(' ')}
                        >
                          <span className={iniHariIni ? 'font-semibold text-desa-800' : undefined}>
                            {hari.getDate()}
                          </span>
                          {kegiatan.length > 0 && (
                            <span aria-hidden className="flex gap-0.5">
                              {kegiatan.slice(0, 3).map((satu) => (
                                <span key={satu.id} className="size-1.5 rounded-full bg-desa-700" />
                              ))}
                            </span>
                          )}
                        </button>
                      </td>
                    )
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </IsiKartu>

      <div className="border-t border-slate-100">
        <h3 className="px-5 pt-4 text-sm font-semibold">
          {dipilih ? `Kegiatan ${tanggal(dipilih)}` : `Seluruh kegiatan ${NAMA_BULAN[bulan]}`}
        </h3>

        {agendaDipilih.length === 0 ? (
          <p className="px-5 pb-5 pt-2 text-sm text-slate-600">Tidak ada kegiatan terjadwal.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {agendaDipilih.map((agenda) => (
              <li key={agenda.id} className="px-5 py-4">
                <Link to={`/agenda/${agenda.slug}`} className="font-medium hover:text-desa-700">
                  {agenda.judul}
                </Link>
                <dl className="mt-1 flex flex-wrap gap-x-5 gap-y-1 text-sm text-slate-600">
                  <div className="flex items-center gap-1.5">
                    <dt className="sr-only">Waktu</dt>
                    <Clock aria-hidden className="size-4 shrink-0" />
                    <dd>
                      {tanggal(agenda.mulai_pada)}
                      {agenda.mulai_pada && `, ${jam(agenda.mulai_pada)}`}
                      {agenda.selesai_pada && `–${jam(agenda.selesai_pada)}`}
                    </dd>
                  </div>
                  {agenda.lokasi && (
                    <div className="flex items-center gap-1.5">
                      <dt className="sr-only">Lokasi</dt>
                      <MapPin aria-hidden className="size-4 shrink-0" />
                      <dd>{agenda.lokasi}</dd>
                    </div>
                  )}
                  {agenda.penyelenggara && (
                    <div className="flex items-center gap-1.5">
                      <dt className="sr-only">Penyelenggara</dt>
                      <Users aria-hidden className="size-4 shrink-0" />
                      <dd>{agenda.penyelenggara}</dd>
                    </div>
                  )}
                </dl>
              </li>
            ))}
          </ul>
        )}
      </div>
    </Kartu>
  )
}
