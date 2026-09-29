import { Link, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Clock, Mail, MapPin, Phone } from 'lucide-react'
import { api, pesanGalat } from '@/lib/api'
import { useProfilDesa } from '@/lib/kueri'
import { useMeta } from '@/lib/meta'
import { judulKan, tanggal } from '@/lib/format'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { GalatMuat, KondisiKosong, Pemuat } from '@/components/ui/Status'
import { TautanTombol } from '@/components/ui/Tombol'

export function Galeri() {
  const { data, isPending, error } = useQuery({
    queryKey: ['galeri'],
    queryFn: async () =>
      (await api.get<{ data: { id: number; nama: string; slug: string; deskripsi: string | null; tanggal_kegiatan: string | null; media_count: number }[] }>('/galeri')).data,
  })

  if (error) return <GalatMuat pesan={pesanGalat(error)} />
  if (isPending) return <Pemuat />

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl">Galeri Kegiatan</h1>
        <p className="mt-1 text-slate-600">Dokumentasi kegiatan pemerintah desa dan masyarakat.</p>
      </header>

      {data.data.length === 0 ? (
        <KondisiKosong judul="Belum ada album" keterangan="Dokumentasi kegiatan akan ditampilkan di sini." />
      ) : (
        <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {data.data.map((album) => (
            <li key={album.id}>
              <Kartu className="h-full">
                <IsiKartu>
                  <h2 className="text-base">{album.nama}</h2>
                  <p className="mt-1 text-sm text-slate-600">{album.deskripsi}</p>
                  <p className="mt-2 text-xs text-slate-500">
                    {album.media_count} foto · {tanggal(album.tanggal_kegiatan)}
                  </p>
                </IsiKartu>
              </Kartu>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

export function Pencarian() {
  const [parameter] = useSearchParams()
  const q = parameter.get('q') ?? ''

  const { data, isPending, error } = useQuery({
    queryKey: ['pencarian', q],
    queryFn: async () =>
      (await api.get<{ kata_kunci: string; jumlah: number; data: { jenis: string; judul: string; cuplikan: string; tautan: string; tanggal: string | null }[] }>(
        '/pencarian',
        { params: { q } },
      )).data,
    enabled: q.length >= 3,
  })

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl">Hasil Pencarian</h1>
        <p className="mt-1 text-slate-600">
          {q ? `Kata kunci: “${q}”` : 'Masukkan kata kunci pada kotak pencarian di bagian atas halaman.'}
        </p>
      </header>

      {error ? (
        <GalatMuat pesan={pesanGalat(error)} />
      ) : isPending && q.length >= 3 ? (
        <Pemuat />
      ) : data && data.jumlah === 0 ? (
        <KondisiKosong
          judul="Tidak ada hasil"
          keterangan="Coba gunakan kata kunci lain atau telusuri menu navigasi."
          aksi={<TautanTombol to="/">Kembali ke Beranda</TautanTombol>}
        />
      ) : data ? (
        <>
          <p className="text-sm text-slate-600" aria-live="polite">
            Ditemukan {data.jumlah} hasil.
          </p>
          <ul className="space-y-3">
            {data.data.map((hasil, indeks) => (
              <li key={indeks}>
                <Kartu>
                  <IsiKartu>
                    <span className="text-xs font-medium uppercase tracking-wide text-desa-700">
                      {judulKan(hasil.jenis)}
                    </span>
                    <h2 className="mt-1 text-base">
                      <Link to={hasil.tautan} className="hover:text-desa-700">
                        {hasil.judul}
                      </Link>
                    </h2>
                    <p className="mt-1 text-sm text-slate-600">{hasil.cuplikan}</p>
                    {hasil.tanggal && <p className="mt-2 text-xs text-slate-500">{tanggal(hasil.tanggal)}</p>}
                  </IsiKartu>
                </Kartu>
              </li>
            ))}
          </ul>
        </>
      ) : null}
    </div>
  )
}

export function Kontak() {
  const { data: desa } = useProfilDesa()

  const kontak = [
    { ikon: MapPin, label: 'Alamat kantor', nilai: desa?.alamat },
    { ikon: Phone, label: 'Telepon', nilai: desa?.telepon },
    { ikon: Mail, label: 'Surel', nilai: desa?.email },
    { ikon: Clock, label: 'Jam pelayanan', nilai: desa?.jam_pelayanan },
  ]

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <header>
        <h1 className="text-2xl">Kontak Pemerintah Desa</h1>
        <p className="mt-1 text-slate-600">Hubungi kami melalui kanal resmi berikut.</p>
      </header>

      <Kartu>
        <IsiKartu>
          <dl className="space-y-5">
            {kontak.map((butir) => (
              <div key={butir.label} className="flex gap-3">
                <butir.ikon aria-hidden className="mt-0.5 size-5 shrink-0 text-desa-700" />
                <div>
                  <dt className="text-sm text-slate-600">{butir.label}</dt>
                  <dd className="font-medium">{butir.nilai ?? '-'}</dd>
                </div>
              </div>
            ))}
          </dl>
        </IsiKartu>
      </Kartu>

      <Kartu>
        <KepalaKartu judul="Kanal Pengaduan" deskripsi="Untuk laporan dan aspirasi, gunakan kanal resmi berikut." />
        <IsiKartu>
          <TautanTombol to="/pengaduan">Kirim Pengaduan</TautanTombol>
        </IsiKartu>
      </Kartu>
    </div>
  )
}

/** Diperbarui bersamaan dengan perubahan isi kebijakan, bukan mengikuti tanggal hari ini. */
const TANGGAL_KEBIJAKAN = '2026-09-29'

export function KebijakanPrivasi() {
  const { data: desa } = useProfilDesa()

  useMeta({
    judul: 'Kebijakan Privasi',
    deskripsi: 'Bagaimana pemerintah desa mengumpulkan, melindungi, menyimpan, dan menghapus data pribadi warga.',
  })

  return (
    <article className="prose-desa mx-auto max-w-3xl text-slate-700">
      <h1 className="text-2xl">Kebijakan Privasi</h1>
      <p className="mt-2 text-sm text-slate-500">Terakhir diperbarui: {tanggal(TANGGAL_KEBIJAKAN)}</p>

      <h2>Data yang kami kumpulkan</h2>
      <p>
        Pemerintah Desa {desa?.nama_desa ?? ''} mengumpulkan data pribadi sebatas yang diperlukan untuk memberikan
        layanan administrasi: nama, Nomor Induk Kependudukan, nomor kontak, alamat, serta berkas persyaratan yang Anda
        unggah.
      </p>

      <h2>Dasar dan tujuan pemrosesan</h2>
      <p>
        Pemrosesan dilakukan atas dasar persetujuan Anda dan pelaksanaan kewenangan pemerintahan desa, semata-mata untuk
        memproses permohonan layanan, menindaklanjuti pengaduan, dan menyusun laporan agregat.
      </p>

      <h2>Perlindungan dan retensi</h2>
      <ul>
        <li>Nomor Induk Kependudukan dan kontak disimpan dalam bentuk terenkripsi.</li>
        <li>Berkas lampiran layanan hanya dapat diakses petugas berwenang dan dihapus 12 bulan setelah layanan selesai.</li>
        <li>Seluruh akses dan perubahan data tercatat pada jejak audit sistem.</li>
        <li>Data statistik yang ditampilkan publik bersifat agregat dan tidak dapat dikaitkan dengan individu.</li>
      </ul>

      <h2>Hak Anda sebagai subjek data</h2>
      <p>
        Anda berhak mengakses, memperbaiki, dan meminta penghapusan data pribadi Anda, serta menarik persetujuan
        pemrosesan. Permintaan dapat disampaikan melalui kanal kontak resmi desa.
      </p>

      <h2>Kontak</h2>
      <p>
        Pertanyaan mengenai kebijakan ini dapat disampaikan ke {desa?.email ?? 'kantor desa'} atau datang langsung pada
        jam pelayanan.
      </p>
    </article>
  )
}

/**
 * REQ-NF-CMP-001: Syarat Penggunaan, dapat dicapai dari kaki setiap halaman
 * bersama Kebijakan Privasi.
 */
export function SyaratPenggunaan() {
  const { data: desa } = useProfilDesa()
  const namaDesa = desa?.nama_desa ? `Desa ${desa.nama_desa}` : 'pemerintah desa'

  useMeta({
    judul: 'Syarat Penggunaan',
    deskripsi: 'Ketentuan penggunaan portal desa: hak, kewajiban, dan batasan bagi pengguna layanan daring.',
  })

  return (
    <article className="prose-desa mx-auto max-w-3xl text-slate-700">
      <h1 className="text-2xl">Syarat Penggunaan</h1>
      <p className="mt-2 text-sm text-slate-500">Terakhir diperbarui: {tanggal(TANGGAL_KEBIJAKAN)}</p>

      <p className="mt-3">
        Portal ini disediakan oleh Pemerintah {namaDesa} sebagai kanal resmi informasi dan layanan administrasi. Dengan
        mengakses atau menggunakan portal, Anda dianggap membaca dan menyetujui syarat berikut.
      </p>

      <h2>Penggunaan yang diperkenankan</h2>
      <ul>
        <li>Membaca informasi publik, dokumen anggaran, dan produk hukum yang ditayangkan.</li>
        <li>Mengajukan layanan administrasi atas nama diri sendiri atau anggota keluarga dalam satu kartu keluarga.</li>
        <li>Menyampaikan pengaduan, aspirasi, dan permohonan informasi publik secara jujur.</li>
        <li>Menggunakan kembali data terbuka yang kami sediakan dengan mencantumkan sumbernya.</li>
      </ul>

      <h2>Yang tidak diperkenankan</h2>
      <ul>
        <li>Memberikan identitas atau dokumen palsu, termasuk mengajukan layanan atas nama orang lain tanpa hak.</li>
        <li>Mengirim pengaduan yang memuat hinaan, ancaman, ujaran kebencian, atau tuduhan tanpa dasar.</li>
        <li>Mengunggah berkas yang memuat perangkat perusak atau materi yang melanggar hukum.</li>
        <li>Mencoba menembus pembatasan akses, mengambil data secara massal, atau mengganggu ketersediaan layanan.</li>
      </ul>

      <h2>Kewajiban Anda atas akun</h2>
      <p>
        Kata sandi bersifat pribadi dan menjadi tanggung jawab pemilik akun. Segala pengajuan yang masuk melalui akun
        Anda dianggap berasal dari Anda. Bila Anda menduga akun disalahgunakan, segera ubah kata sandi dan beri tahu
        petugas desa.
      </p>

      <h2>Keabsahan dokumen elektronik</h2>
      <p>
        Surat yang diterbitkan melalui portal ini sah sebagai dokumen elektronik. Keasliannya dapat diperiksa siapa pun
        melalui kode QR atau kode verifikasi pada laman{' '}
        <Link to="/layanan/verifikasi" className="underline underline-offset-2">
          verifikasi surat
        </Link>
        . Dokumen yang dibatalkan akan tampil sebagai tidak berlaku pada laman tersebut.
      </p>

      <h2>Ketersediaan dan keakuratan</h2>
      <p>
        Kami berupaya menjaga portal tetap tersedia dan isinya mutakhir. Layanan dapat terhenti sementara karena
        pemeliharaan atau gangguan di luar kendali kami. Untuk urusan yang memerlukan kepastian hukum, dokumen resmi
        yang diterbitkan kantor desa tetap menjadi acuan.
      </p>

      <h2>Konsekuensi pelanggaran</h2>
      <p>
        Pelanggaran syarat ini dapat berakibat penonaktifan akun, pembatalan surat yang telah diterbitkan, serta
        penerusan perkara kepada aparat penegak hukum bila memenuhi unsur pidana. Pemerintah desa memberi tahu alasan
        penonaktifan melalui kanal kontak yang Anda daftarkan.
      </p>

      <h2>Pelindungan data pribadi</h2>
      <p>
        Pemrosesan data pribadi diatur pada{' '}
        <Link to="/kebijakan-privasi" className="underline underline-offset-2">
          Kebijakan Privasi
        </Link>
        , termasuk hak Anda untuk mengunduh dan meminta penghapusan data.
      </p>

      <h2>Perubahan syarat</h2>
      <p>
        Syarat ini dapat diperbarui bila ketentuan hukum atau cakupan layanan berubah. Tanggal pembaruan selalu
        dicantumkan di bagian atas halaman, dan perubahan berlaku sejak ditayangkan.
      </p>
    </article>
  )
}

export function Aksesibilitas() {
  return (
    <article className="prose-desa mx-auto max-w-3xl text-slate-700">
      <h1 className="text-2xl">Pernyataan Aksesibilitas</h1>

      <p className="mt-3">
        Portal ini dirancang agar dapat digunakan oleh sebanyak mungkin warga, termasuk penyandang disabilitas, dengan
        mengacu pada Web Content Accessibility Guidelines (WCAG) 2.1 tingkat AA.
      </p>

      <h2>Langkah yang telah kami terapkan</h2>
      <ul>
        <li>Seluruh fungsi dapat dioperasikan menggunakan papan ketik, dengan indikator fokus yang terlihat jelas.</li>
        <li>Rasio kontras teks terhadap latar minimal 4,5:1.</li>
        <li>Setiap gambar konten dilengkapi teks alternatif.</li>
        <li>Grafik selalu disertai padanan tabel data.</li>
        <li>Struktur judul dan penanda wilayah halaman konsisten untuk pembaca layar.</li>
        <li>Tautan lewati navigasi tersedia di awal setiap halaman.</li>
      </ul>

      <h2>Menemui hambatan?</h2>
      <p>
        Apabila Anda menemui bagian yang sulit diakses, sampaikan melalui kanal pengaduan atau hubungi kantor desa. Kami
        menindaklanjuti laporan hambatan akses paling lambat 3 hari kerja.
      </p>
    </article>
  )
}

export function TidakDitemukan() {
  return (
    <div className="mx-auto max-w-lg py-16 text-center">
      <p className="text-6xl font-bold text-desa-700">404</p>
      <h1 className="mt-4 text-2xl">Halaman tidak ditemukan</h1>
      <p className="mt-2 text-slate-600">
        Halaman yang Anda tuju mungkin telah dipindahkan atau alamatnya keliru.
      </p>
      <div className="mt-6 flex flex-wrap justify-center gap-3">
        <TautanTombol to="/">Kembali ke Beranda</TautanTombol>
        <TautanTombol to="/pencarian" ragam="garis">
          Cari Informasi
        </TautanTombol>
      </div>
    </div>
  )
}
