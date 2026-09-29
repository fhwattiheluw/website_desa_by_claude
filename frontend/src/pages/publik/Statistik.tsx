import { useStatistik } from '@/lib/kueri'
import { pesanGalat } from '@/lib/api'
import { angka, judulKan } from '@/lib/format'
import { GrafikBatang, WARNA_TUNGGAL } from '@/components/chart/GrafikBatang'
import { BatangProporsi } from '@/components/chart/BatangProporsi'
import { Kartu, KartuStatistik, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { GalatMuat, Pemuat } from '@/components/ui/Status'

const JUDUL_KELOMPOK: Record<string, string> = {
  usia: 'Penduduk menurut Kelompok Usia',
  pendidikan: 'Penduduk menurut Tingkat Pendidikan',
  pekerjaan: 'Penduduk menurut Jenis Pekerjaan',
  agama: 'Penduduk menurut Agama',
  dusun: 'Penduduk menurut Dusun',
}

export function Statistik() {
  const { data, isPending, error } = useStatistik()

  if (error) return <GalatMuat pesan={pesanGalat(error)} />
  if (isPending || !data) return <Pemuat />

  const jenisKelamin = (data.kelompok.jenis_kelamin ?? []).map((item) => ({
    label: item.label,
    jumlah: item.jumlah ?? 0,
  }))

  const kelompokLain = Object.entries(data.kelompok).filter(
    ([kunci]) => kunci !== 'jenis_kelamin' && kunci !== 'kepala_keluarga',
  )

  return (
    <div className="space-y-8">
      <header>
        <h1 className="text-2xl">Statistik Desa</h1>
        <p className="mt-1 text-slate-600">
          Data agregat kependudukan periode {data.periode.nama}. Sumber: {data.periode.sumber_data ?? 'Pendataan desa'}.
        </p>
      </header>

      <section className="grid gap-4 sm:grid-cols-3">
        <KartuStatistik label="Total penduduk" nilai={angka(data.total_penduduk)} keterangan="jiwa" nada="positif" />
        <KartuStatistik label="Kepala keluarga" nilai={angka(data.total_kk)} keterangan="KK" />
        <KartuStatistik label="Periode data" nilai={<span className="text-lg">{data.periode.nama}</span>} />
      </section>

      {jenisKelamin.length > 0 && (
        <Kartu>
          <KepalaKartu judul="Komposisi Penduduk menurut Jenis Kelamin" />
          <IsiKartu>
            <BatangProporsi judul="" butir={jenisKelamin} />
          </IsiKartu>
        </Kartu>
      )}

      <div className="space-y-6">
        {kelompokLain.map(([kunci, butir]) => (
          <GrafikBatang
            key={kunci}
            judul={JUDUL_KELOMPOK[kunci] ?? judulKan(kunci)}
            data={butir.map((item) => ({ label: item.label, jumlah: item.jumlah ?? 0 }))}
            kunciLabel="label"
            seri={[{ kunci: 'jumlah', label: 'Jumlah jiwa', warna: WARNA_TUNGGAL }]}
            format={(nilai) => angka(nilai)}
            catatan={butir.some((item) => item.disamarkan) ? data.catatan_privasi : undefined}
          />
        ))}
      </div>

      <p className="text-sm text-slate-500">{data.catatan_privasi}</p>
    </div>
  )
}
