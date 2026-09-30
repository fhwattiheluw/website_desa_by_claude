import { useMemo, useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, CheckCircle2, Clock } from 'lucide-react'
import { api, galatKolom, pesanGalat } from '@/lib/api'
import { useDetailLayanan } from '@/lib/kueri'
import { useAuth } from '@/lib/auth'
import { useMeta } from '@/lib/meta'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { AreaTeks, Berkas, Isian, KotakCentang, Pilihan } from '@/components/ui/Isian'
import { Tombol } from '@/components/ui/Tombol'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import { GalatMuat, Pemuat } from '@/components/ui/Status'
import type { KolomFormulir, Permohonan } from '@/types'

/** Nilai teks yang dianggap benar saat memulihkan kolom centang dari draf. */
const BENAR = new Set(['1', 'true', 'on', 'ya'])

/**
 * Formulir permohonan dibangun dari definisi kolom milik setiap layanan
 * (REQ-F-SRT-003), sehingga penambahan jenis surat tidak memerlukan
 * perubahan kode antarmuka.
 */
export function AjukanSurat() {
  const { slug = '' } = useParams()
  const [parameter] = useSearchParams()
  const navigasi = useNavigate()
  const { pengguna } = useAuth()
  const klien = useQueryClient()
  const { data: layanan, isPending, error } = useDetailLayanan(slug)

  // Pengisian dapat dilanjutkan dari draf yang tersimpan (REQ-F-SRT-007).
  const idDraf = parameter.get('draf')

  const { data: permohonanDraf, isPending: memuatDraf } = useQuery({
    queryKey: ['permohonan', idDraf],
    queryFn: async () => (await api.get<{ data: Permohonan }>(`/permohonan/${idDraf}`)).data.data,
    enabled: Boolean(idDraf),
  })

  const [nilai, setNilai] = useState<Record<string, string | boolean>>({})
  const [berkas, setBerkas] = useState<File[]>([])

  /*
   * Berkas ditambahkan, bukan menggantikan pilihan sebelumnya: warga dapat
   * memilih sebagian dari galeri lalu memotret sisanya (REQ-HW-002). Duplikat
   * disaring berdasarkan nama dan ukuran agar berkas yang sama tidak terunggah
   * dua kali.
   */
  const tambahBerkas = (tambahan: File[]) =>
    setBerkas((sebelumnya) => {
      const gabungan = [...sebelumnya]

      for (const satu of tambahan) {
        const sudahAda = gabungan.some((ada) => ada.name === satu.name && ada.size === satu.size)
        if (!sudahAda) gabungan.push(satu)
      }

      return gabungan.slice(0, 5)
    })
  const [galat, setGalat] = useState<Record<string, string>>({})
  const [pesan, setPesan] = useState('')
  const [mengirim, setMengirim] = useState(false)

  useMeta({ judul: layanan?.nama ?? 'Ajukan Permohonan' })

  // Nilai bawaan diambil dari draf bila ada, jika tidak dari profil pengguna
  // sehingga warga tidak perlu mengetik ulang datanya (REQ-F-SRT-005).
  const awal = useMemo<Record<string, string | boolean>>(() => {
    const dariProfil: Record<string, string | boolean> = {
      nama_lengkap: pengguna?.nama ?? '',
      alamat: pengguna?.alamat ?? '',
      tempat_lahir: pengguna?.tempat_lahir ?? '',
      tanggal_lahir: pengguna?.tanggal_lahir ?? '',
      pekerjaan: pengguna?.pekerjaan ?? '',
      jenis_kelamin: pengguna?.jenis_kelamin === 'L' ? 'Laki-laki' : pengguna?.jenis_kelamin === 'P' ? 'Perempuan' : '',
    }

    if (!permohonanDraf?.data_formulir) return dariProfil

    // Nilai draf tersimpan sebagai teks. Kolom centang harus dikembalikan ke
    // bentuk boolean, sebab teks "0" bila dibaca apa adanya akan tampil
    // sebagai tercentang dan menyesatkan pemohon.
    const tipeKolom = new Map((layanan?.kolom_formulir ?? []).map((kolom) => [kolom.nama, kolom.tipe]))

    const dariDraf = Object.fromEntries(
      Object.entries(permohonanDraf.data_formulir).map(([kunci, isi]) => [
        kunci,
        tipeKolom.get(kunci) === 'centang' ? BENAR.has(String(isi).toLowerCase()) : String(isi ?? ''),
      ]),
    )

    return { ...dariProfil, ...dariDraf }
  }, [pengguna, permohonanDraf, layanan])

  if (error) return <GalatMuat pesan={pesanGalat(error)} />
  if (isPending || !layanan || (idDraf && memuatDraf)) return <Pemuat />

  const ambil = (kolom: KolomFormulir): string | boolean =>
    nilai[kolom.nama] ?? awal[kolom.nama] ?? (kolom.tipe === 'centang' ? false : '')

  const ubah = (nama: string, isi: string | boolean) => setNilai((sebelum) => ({ ...sebelum, [nama]: isi }))

  const susunMuatan = (draf: boolean) => {
    const muatan = new FormData()
    muatan.append('layanan', layanan.slug)

    if (draf) muatan.append('draf', '1')

    for (const kolom of layanan.kolom_formulir ?? []) {
      const isi = ambil(kolom)
      muatan.append(`data_formulir[${kolom.nama}]`, typeof isi === 'boolean' ? (isi ? '1' : '0') : isi)
    }

    berkas.forEach((b, indeks) => {
      muatan.append(`lampiran[${indeks}]`, b)
      muatan.append(`label_lampiran[${indeks}]`, b.name)
    })

    return muatan
  }

  const kirim = async (peristiwa: FormEvent, draf = false) => {
    peristiwa.preventDefault()
    setMengirim(true)
    setGalat({})
    setPesan('')

    try {
      const muatan = susunMuatan(draf)
      const pengaturan = { headers: { 'Content-Type': 'multipart/form-data' } }

      let tujuan = idDraf

      if (idDraf && draf) {
        // Unggahan memakai multipart, sehingga metode PUT dititipkan lewat _method.
        muatan.append('_method', 'PUT')
        await api.post(`/permohonan/${idDraf}/draf`, muatan, pengaturan)
      } else if (idDraf) {
        await api.post(`/permohonan/${idDraf}/kirim-ulang`, muatan, pengaturan)
      } else {
        const { data } = await api.post('/permohonan', muatan, pengaturan)
        tujuan = String(data.data.id)
      }

      // Data permohonan yang tersimpan di cache sudah tidak mencerminkan
      // keadaan terbaru, sehingga dibatalkan agar halaman tujuan memuat ulang.
      await Promise.all([
        klien.invalidateQueries({ queryKey: ['permohonan', tujuan] }),
        klien.invalidateQueries({ queryKey: ['permohonan-saya'] }),
      ])

      navigasi(`/akun/permohonan/${tujuan}`, { state: { baru: true } })
    } catch (kesalahan) {
      const kolom = galatKolom(kesalahan)
      setGalat(
        Object.fromEntries(Object.entries(kolom).map(([kunci, isi]) => [kunci.replace('data_formulir.', ''), isi])),
      )
      setPesan(pesanGalat(kesalahan))
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } finally {
      setMengirim(false)
    }
  }

  if (!pengguna?.boleh_mengajukan) {
    return (
      <div className="mx-auto max-w-2xl space-y-4">
        <Pemberitahuan jenis="peringatan" judul="Akun belum dapat mengajukan layanan">
          Akun Anda belum diverifikasi petugas desa. Verifikasi NIK diperlukan sebelum Anda dapat mengajukan permohonan
          surat. Hubungi kantor desa bila proses ini memakan waktu lebih dari satu hari kerja.
        </Pemberitahuan>
        <Link to="/akun" className="text-sm font-medium text-desa-700 hover:underline">
          Kembali ke akun saya
        </Link>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <Link to="/layanan" className="inline-flex items-center gap-1.5 text-sm text-desa-700 hover:underline">
        <ArrowLeft aria-hidden className="size-4" /> Kembali ke katalog layanan
      </Link>

      <header>
        <h1 className="text-2xl">{layanan.nama}</h1>
        <p className="mt-1 text-slate-600">{layanan.deskripsi}</p>
        <div className="mt-3 flex flex-wrap gap-4 text-sm text-slate-600">
          <span className="inline-flex items-center gap-1.5">
            <Clock aria-hidden className="size-4" /> Selesai dalam {layanan.sla_hari_kerja} hari kerja
          </span>
          <span className="inline-flex items-center gap-1.5">
            <CheckCircle2 aria-hidden className="size-4" /> Tidak dipungut biaya
          </span>
        </div>
      </header>

      {pesan && <Pemberitahuan jenis="bahaya" judul="Permohonan belum dapat dikirim">{pesan}</Pemberitahuan>}

      <Kartu>
        <KepalaKartu judul="Persyaratan Berkas" deskripsi="Siapkan berkas berikut dalam bentuk foto atau pindaian." />
        <IsiKartu>
          <ul className="space-y-1.5">
            {layanan.persyaratan.map((syarat) => (
              <li key={syarat} className="flex items-start gap-2 text-sm text-slate-700">
                <CheckCircle2 aria-hidden className="mt-0.5 size-4 shrink-0 text-desa-600" />
                {syarat}
              </li>
            ))}
          </ul>
        </IsiKartu>
      </Kartu>

      <form onSubmit={kirim}>
        <Kartu>
          <KepalaKartu judul="Data Permohonan" deskripsi="Kolom bertanda bintang wajib diisi." />
          <IsiKartu className="space-y-5">
            {(layanan.kolom_formulir ?? []).map((kolom) => {
              const isi = ambil(kolom)
              const galatKolomIni = galat[kolom.nama]

              if (kolom.tipe === 'centang') {
                return (
                  <KotakCentang
                    key={kolom.nama}
                    label={kolom.label}
                    checked={Boolean(isi)}
                    onChange={(e) => ubah(kolom.nama, e.target.checked)}
                    galat={galatKolomIni}
                  />
                )
              }

              if (kolom.tipe === 'pilihan') {
                return (
                  <Pilihan
                    key={kolom.nama}
                    label={kolom.label}
                    required={kolom.wajib}
                    value={String(isi)}
                    onChange={(e) => ubah(kolom.nama, e.target.value)}
                    kosong={`Pilih ${kolom.label.toLowerCase()}`}
                    pilihan={(kolom.pilihan ?? []).map((opsi) => ({ nilai: opsi, teks: opsi }))}
                    galat={galatKolomIni}
                  />
                )
              }

              if (kolom.tipe === 'teks_panjang') {
                return (
                  <AreaTeks
                    key={kolom.nama}
                    label={kolom.label}
                    required={kolom.wajib}
                    value={String(isi)}
                    onChange={(e) => ubah(kolom.nama, e.target.value)}
                    galat={galatKolomIni}
                  />
                )
              }

              return (
                <Isian
                  key={kolom.nama}
                  label={kolom.label}
                  required={kolom.wajib}
                  value={String(isi)}
                  onChange={(e) => ubah(kolom.nama, e.target.value)}
                  type={kolom.tipe === 'tanggal' ? 'date' : kolom.tipe === 'angka' ? 'number' : 'text'}
                  inputMode={kolom.tipe === 'nik' || kolom.tipe === 'kk' ? 'numeric' : undefined}
                  maxLength={kolom.tipe === 'nik' || kolom.tipe === 'kk' ? 16 : undefined}
                  petunjuk={
                    kolom.tipe === 'nik' || kolom.tipe === 'kk' ? 'Masukkan 16 digit angka tanpa spasi.' : undefined
                  }
                  galat={galatKolomIni}
                />
              )
            })}

            <Berkas
              label="Unggah berkas persyaratan"
              multiple
              kamera
              accept="image/jpeg,image/png,application/pdf"
              petunjuk="Maksimal 5 berkas, masing-masing 5 MB. Format JPG, PNG, atau PDF."
              galat={galat['lampiran']}
              onChange={(e) => tambahBerkas(Array.from(e.target.files ?? []))}
            />

            {berkas.length > 0 && (
              <ul className="space-y-1 text-sm text-slate-600">
                {berkas.map((b) => (
                  <li key={`${b.name}-${b.size}`} className="flex items-center justify-between gap-3">
                    <span className="min-w-0 truncate">• {b.name}</span>
                    <button
                      type="button"
                      onClick={() => setBerkas((sebelumnya) => sebelumnya.filter((satu) => satu !== b))}
                      className="shrink-0 text-xs font-medium text-red-700 underline underline-offset-2"
                    >
                      Hapus
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </IsiKartu>
        </Kartu>

        <div className="mt-5 flex flex-wrap gap-3">
          <Tombol type="submit" ukuran="besar" memuat={mengirim}>
            Kirim Permohonan
          </Tombol>
          {/* REQ-F-SRT-007: pengisian dapat dijeda dan dilanjutkan dalam 7 hari.
              Permohonan yang dikembalikan petugas tidak dapat dikembalikan
              menjadi draf, sehingga tombol ini hanya tampil saat relevan. */}
          {(!idDraf || permohonanDraf?.status === 'draf') && (
            <Tombol
              type="button"
              ragam="halus"
              ukuran="besar"
              memuat={mengirim}
              onClick={(peristiwa) => void kirim(peristiwa, true)}
            >
              Simpan Draf
            </Tombol>
          )}
          <Tombol type="button" ragam="garis" ukuran="besar" onClick={() => navigasi('/layanan')}>
            Batal
          </Tombol>
        </div>

        <p className="mt-3 text-sm text-slate-500">
          Dengan mengirim permohonan, Anda menyatakan data yang diisikan benar dan dapat dipertanggungjawabkan.
          Draf yang disimpan dapat dilanjutkan dalam 7 hari dan belum masuk antrean petugas.
        </p>
      </form>
    </div>
  )
}
