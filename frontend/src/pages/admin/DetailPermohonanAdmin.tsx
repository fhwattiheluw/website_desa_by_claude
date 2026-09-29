import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Paperclip, PenLine } from 'lucide-react'
import { api, pesanGalat } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { judulKan, tanggal, ukuranBerkas } from '@/lib/format'
import { Kartu, IsiKartu, KepalaKartu } from '@/components/ui/Kartu'
import { Lencana, LencanaPermohonan } from '@/components/ui/Lencana'
import { Tombol } from '@/components/ui/Tombol'
import { AreaTeks } from '@/components/ui/Isian'
import { Dialog } from '@/components/ui/Dialog'
import { Pemberitahuan } from '@/components/ui/Pemberitahuan'
import { GalatMuat, Pemuat } from '@/components/ui/Status'
import type { Permohonan } from '@/types'

type Aksi = 'verifikasi' | 'kembalikan' | 'tolak' | 'setujui' | 'tanda-tangani' | 'batalkan-surat'

const JUDUL_AKSI: Record<Aksi, { judul: string; deskripsi: string; wajibAlasan: boolean; ragam: 'utama' | 'bahaya' }> = {
  verifikasi: {
    judul: 'Verifikasi berkas',
    deskripsi: 'Nyatakan berkas permohonan lengkap dan sesuai persyaratan.',
    wajibAlasan: false,
    ragam: 'utama',
  },
  kembalikan: {
    judul: 'Kembalikan untuk perbaikan',
    deskripsi: 'Pemohon akan menerima alasan ini agar dapat memperbaiki permohonannya.',
    wajibAlasan: true,
    ragam: 'bahaya',
  },
  tolak: {
    judul: 'Tolak permohonan',
    deskripsi: 'Penolakan bersifat final dan wajib disertai alasan yang jelas.',
    wajibAlasan: true,
    ragam: 'bahaya',
  },
  setujui: {
    judul: 'Setujui permohonan',
    deskripsi: 'Permohonan akan masuk antrean penandatanganan.',
    wajibAlasan: false,
    ragam: 'utama',
  },
  'tanda-tangani': {
    judul: 'Tanda tangani dan terbitkan surat',
    deskripsi: 'Nomor surat akan diterbitkan dan dokumen PDF dibuat otomatis.',
    wajibAlasan: false,
    ragam: 'utama',
  },
  'batalkan-surat': {
    judul: 'Batalkan surat',
    deskripsi: 'Surat dinyatakan tidak berlaku. Nomor surat tidak digunakan ulang.',
    wajibAlasan: true,
    ragam: 'bahaya',
  },
}

export function DetailPermohonanAdmin() {
  const { id = '' } = useParams()
  const klien = useQueryClient()
  const { punyaIzin } = useAuth()
  const [aksi, setAksi] = useState<Aksi | null>(null)
  const [catatan, setCatatan] = useState('')
  const [pesan, setPesan] = useState('')
  const [sukses, setSukses] = useState('')

  const { data, isPending, error } = useQuery({
    queryKey: ['admin-permohonan', id],
    queryFn: async () => (await api.get<{ data: Permohonan }>(`/admin/permohonan/${id}`)).data.data,
  })

  const jalankan = useMutation({
    mutationFn: async (pilihan: Aksi) => {
      const muatan = JUDUL_AKSI[pilihan].wajibAlasan ? { alasan: catatan } : { catatan }
      return (await api.post(`/admin/permohonan/${id}/${pilihan}`, muatan)).data
    },
    onSuccess: (hasil: { pesan?: string }) => {
      setSukses(hasil.pesan ?? 'Tindakan berhasil disimpan.')
      setPesan('')
      setAksi(null)
      setCatatan('')
      void klien.invalidateQueries({ queryKey: ['admin-permohonan', id] })
      void klien.invalidateQueries({ queryKey: ['antrean-permohonan'] })
      void klien.invalidateQueries({ queryKey: ['dasbor'] })
    },
    onError: (kesalahan) => setPesan(pesanGalat(kesalahan)),
  })

  if (error) return <GalatMuat pesan={pesanGalat(error)} />
  if (isPending || !data) return <Pemuat />

  const kolom = data.layanan?.kolom_formulir ?? []

  const aksiTersedia: Aksi[] = []
  if (data.status === 'diajukan' && punyaIzin('permohonan.verifikasi')) aksiTersedia.push('verifikasi', 'kembalikan', 'tolak')
  if (data.status === 'diverifikasi') {
    if (punyaIzin('permohonan.setujui')) aksiTersedia.push('setujui')
    if (punyaIzin('permohonan.verifikasi')) aksiTersedia.push('kembalikan', 'tolak')
  }
  if (data.status === 'disetujui' && punyaIzin('permohonan.tanda_tangan')) aksiTersedia.push('tanda-tangani')
  if (data.surat?.status_keabsahan === 'sah' && punyaIzin('surat.batalkan')) aksiTersedia.push('batalkan-surat')

  const rincian = aksi ? JUDUL_AKSI[aksi] : null
  const alasanKurang = Boolean(rincian?.wajibAlasan && catatan.trim().length < 20)

  return (
    <div className="mx-auto max-w-4xl space-y-6">
      <Link to="/admin/permohonan" className="inline-flex items-center gap-1.5 text-sm text-desa-700 hover:underline">
        <ArrowLeft aria-hidden className="size-4" /> Kembali ke antrean
      </Link>

      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl">{data.layanan?.nama}</h1>
          <p className="mt-1 text-slate-600">
            {data.nomor_tiket} · Diajukan {tanggal(data.diajukan_pada, true)}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <LencanaPermohonan status={data.status} />
          {data.melampaui_sla && <Lencana nada="bahaya">Melampaui SLA</Lencana>}
          {data.kanal === 'loket' && <Lencana>Kanal loket</Lencana>}
        </div>
      </header>

      {sukses && <Pemberitahuan jenis="sukses">{sukses}</Pemberitahuan>}
      {pesan && <Pemberitahuan jenis="bahaya" judul="Tindakan gagal">{pesan}</Pemberitahuan>}

      {data.alasan && (
        <Pemberitahuan jenis="peringatan" judul="Catatan terakhir">
          {data.alasan}
        </Pemberitahuan>
      )}

      {aksiTersedia.length > 0 && (
        <Kartu>
          <KepalaKartu judul="Tindakan" deskripsi="Setiap tindakan tercatat pada jejak audit sistem." />
          <IsiKartu>
            <div className="flex flex-wrap gap-3">
              {aksiTersedia.map((pilihan) => (
                <Tombol
                  key={pilihan}
                  ragam={JUDUL_AKSI[pilihan].ragam === 'bahaya' ? 'bahaya' : 'utama'}
                  onClick={() => { setAksi(pilihan); setCatatan(''); setPesan('') }}
                >
                  {pilihan === 'tanda-tangani' && <PenLine aria-hidden className="size-4" />}
                  {JUDUL_AKSI[pilihan].judul}
                </Tombol>
              ))}
            </div>
          </IsiKartu>
        </Kartu>
      )}

      {data.surat && (
        <Kartu className={data.surat.status_keabsahan === 'sah' ? 'border-desa-300' : 'border-red-300'}>
          <KepalaKartu
            judul="Surat Terbit"
            deskripsi={`Nomor ${data.surat.nomor_surat} · ${tanggal(data.surat.tanggal_terbit)}`}
            aksi={
              <Lencana nada={data.surat.status_keabsahan === 'sah' ? 'sukses' : 'bahaya'}>
                {judulKan(data.surat.status_keabsahan)}
              </Lencana>
            }
          />
          <IsiKartu>
            <p className="text-sm text-slate-600">
              Kode verifikasi publik: <span className="font-mono font-medium">{data.surat.kode_verifikasi}</span>
            </p>
          </IsiKartu>
        </Kartu>
      )}

      <div className="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <Kartu>
          <KepalaKartu judul="Data Permohonan" />
          <IsiKartu>
            <dl className="grid gap-4 sm:grid-cols-2">
              {kolom
                .filter((k) => k.tipe !== 'centang')
                .map((k) => (
                  <div key={k.nama}>
                    <dt className="text-sm text-slate-600">{k.label}</dt>
                    <dd className="font-medium break-words">{String(data.data_formulir[k.nama] ?? '-')}</dd>
                  </div>
                ))}
            </dl>

            <div className="mt-5 border-t border-slate-100 pt-4">
              <h3 className="text-sm font-medium text-slate-700">Lampiran</h3>
              {(data.lampiran?.length ?? 0) > 0 ? (
                <ul className="mt-2 space-y-1.5">
                  {data.lampiran?.map((lampiran) => (
                    <li key={lampiran.id} className="flex items-center gap-2 text-sm text-slate-600">
                      <Paperclip aria-hidden className="size-4 shrink-0" />
                      {lampiran.label ?? lampiran.nama} · {ukuranBerkas(lampiran.ukuran)}
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="mt-1 text-sm text-slate-600">Tidak ada lampiran.</p>
              )}
            </div>
          </IsiKartu>
        </Kartu>

        <div className="space-y-6">
          <Kartu>
            <KepalaKartu judul="Pemohon" />
            <IsiKartu>
              <dl className="space-y-3">
                <div>
                  <dt className="text-sm text-slate-600">Nama</dt>
                  <dd className="font-medium">{data.pemohon?.nama}</dd>
                </div>
                <div>
                  <dt className="text-sm text-slate-600">NIK</dt>
                  <dd className="font-medium tabular-nums">{data.pemohon?.nik_tersamar ?? '-'}</dd>
                </div>
                <div>
                  <dt className="text-sm text-slate-600">Kontak</dt>
                  <dd className="font-medium">{data.pemohon?.telepon ?? '-'}</dd>
                </div>
              </dl>
            </IsiKartu>
          </Kartu>

          <Kartu>
            <KepalaKartu judul="Riwayat Status" />
            <IsiKartu>
              <ol className="space-y-4">
                {(data.riwayat ?? []).map((langkah, indeks) => (
                  <li key={indeks} className="flex gap-3">
                    <span aria-hidden className="mt-1.5 size-2.5 shrink-0 rounded-full bg-desa-600" />
                    <div className="min-w-0">
                      <p className="text-sm font-medium">{judulKan(langkah.ke)}</p>
                      {langkah.catatan && <p className="mt-0.5 text-sm text-slate-600">{langkah.catatan}</p>}
                      <p className="mt-0.5 text-xs text-slate-500">
                        {tanggal(langkah.waktu, true)}
                        {langkah.aktor && ` · ${langkah.aktor}`}
                      </p>
                    </div>
                  </li>
                ))}
              </ol>
            </IsiKartu>
          </Kartu>
        </div>
      </div>

      <Dialog
        terbuka={aksi !== null}
        judul={rincian?.judul ?? ''}
        deskripsi={rincian?.deskripsi}
        onTutup={() => setAksi(null)}
        aksi={
          <>
            <Tombol ragam="garis" onClick={() => setAksi(null)}>
              Batal
            </Tombol>
            <Tombol
              ragam={rincian?.ragam === 'bahaya' ? 'bahaya' : 'utama'}
              disabled={alasanKurang}
              memuat={jalankan.isPending}
              onClick={() => aksi && jalankan.mutate(aksi)}
            >
              {rincian?.judul}
            </Tombol>
          </>
        }
      >
        <AreaTeks
          label={rincian?.wajibAlasan ? 'Alasan (wajib)' : 'Catatan (opsional)'}
          required={rincian?.wajibAlasan}
          value={catatan}
          onChange={(e) => setCatatan(e.target.value)}
          rows={4}
          petunjuk={
            rincian?.wajibAlasan
              ? `Minimal 20 karakter agar pemohon memahami langkah perbaikan. Saat ini ${catatan.trim().length} karakter.`
              : undefined
          }
          galat={alasanKurang && catatan.length > 0 ? 'Alasan masih kurang dari 20 karakter.' : undefined}
        />
      </Dialog>
    </div>
  )
}
