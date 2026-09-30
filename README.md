# SIDESA — Sistem Informasi Desa Terpadu

Portal resmi pemerintah desa: informasi publik, transparansi anggaran, layanan
administrasi surat daring, dan kanal pengaduan masyarakat.

Implementasi mengacu pada [Software Requirements Specification](docs/SRS-Website-Desa.md)
(SRS-WDESA-001). Pemetaan tiap kebutuhan ke kode tersedia pada
[matriks ketertelusuran](docs/KETERTELUSURAN.md).

| Dokumen | Untuk siapa |
|---|---|
| [SRS](docs/SRS-Website-Desa.md) | Perancang dan pemeriksa kebutuhan |
| [Matriks ketertelusuran](docs/KETERTELUSURAN.md) | Peninjau dan auditor |
| [Panduan operasional](docs/OPERASIONAL.md) | Administrator server: penjadwal, pencadangan, pemulihan, retensi, penanganan insiden |
| [Panduan administrator](docs/PANDUAN-ADMINISTRATOR.md) | Petugas desa pemakai panel administrasi |
| [Panduan warga](docs/PANDUAN-WARGA.md) | Warga pemakai portal |

## Arsitektur

| Lapisan | Teknologi | Lokasi |
|---|---|---|
| API | Laravel 13 (PHP 8.3+), Sanctum, SQLite/MySQL/PostgreSQL | [`backend/`](backend) |
| Antarmuka | React 19, TypeScript, Vite, Tailwind CSS 4, TanStack Query, PWA | [`frontend/`](frontend) |
| Dokumen | dompdf (PDF surat), bacon/bacon-qr-code (QR verifikasi) | `backend/app/Services` |

Backend berperan sebagai API murni; frontend adalah aplikasi satu halaman yang
mengonsumsinya melalui `/api/v1`. Keduanya dapat dipasang pada satu server
maupun terpisah.

## Menjalankan secara lokal

### 1. Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan storage:link
php artisan serve                     # http://127.0.0.1:8000
```

### 2. Frontend

```bash
cd frontend
npm install
npm run dev                           # http://127.0.0.1:5173
```

Server pengembangan Vite meneruskan `/api` dan `/storage` ke Laravel, sehingga
tidak diperlukan konfigurasi CORS saat pengembangan.

### 3. Akun demonstrasi

Seluruh akun hasil seeder memakai kata sandi `sidesa2026`.
**Ganti seluruh kata sandi sebelum sistem dipakai sungguhan.**

| Peran | Surel | Dapat melakukan |
|---|---|---|
| Kepala Desa | `kades@sukamaju.desa.id` | Menandatangani surat, memantau dasbor |
| Sekretaris Desa | `sekdes@sukamaju.desa.id` | Menyetujui, menandatangani, publikasi APBDes |
| Verifikator | `kasi@sukamaju.desa.id` | Verifikasi berkas, disposisi pengaduan |
| Operator | `operator@sukamaju.desa.id` | Kelola konten, verifikasi berkas dan NIK |
| Administrator | `admin@sukamaju.desa.id` | Pengguna, peran, pengaturan, audit |
| Warga (aktif) | `sari.wulandari@contoh.id` | Mengajukan layanan surat |
| Warga (menunggu) | `rina.pertiwi@contoh.id` | Menunggu verifikasi NIK oleh operator |

## Pengujian

```bash
cd backend && ./vendor/bin/phpunit      # 124 uji, 562 asersi
cd backend && ./vendor/bin/pint --test  # gaya kode
cd frontend && npm run build            # typecheck + bundel produksi
cd frontend && npm run lint
```

Cakupan uji otomatis meliputi autentikasi dan penguncian akun, pemulihan kata
sandi dan verifikasi surel, alur permohonan surat ujung-ke-ujung sampai PDF
terbit, draf permohonan, penegakan hak akses antarperan, aturan bisnis (BR-01
sampai BR-15), portal publik, pengaduan, sitemap dan robots, pencadangan,
ekspor laporan, perhitungan SLA hari kerja, keunikan penomoran surat, tantangan
CAPTCHA pada formulir publik, hak subjek data beserta penganoniman akun,
kebijakan retensi, deteksi indikasi insiden, dan analitik tanpa data pribadi.

Seluruh pemeriksaan di atas juga dijalankan otomatis pada setiap perubahan
melalui [alur kerja CI](.github/workflows/ci.yml), bersama pemindaian kerentanan
dependensi (`composer audit` dan `npm audit`) yang diulang tiap pekan. Uji
backend dijalankan pada **SQLite dan PostgreSQL** sekaligus, karena perbedaan
keduanya tidak selalu memunculkan galat — lihat
[catatan pilihan basis data](docs/OPERASIONAL.md#9-pilihan-basis-data).

## Struktur

```
backend/
├─ app/Http/Controllers/Api/   Publik, Warga, Admin
├─ app/Http/Middleware/        PastikanIzin (RBAC), TajukKeamanan
├─ app/Http/Resources/         Bentuk respons seragam
├─ app/Models/                 32 model domain
├─ app/Services/               Alur permohonan, surat, SLA, notifikasi, audit
├─ database/migrations/        15 migrasi
├─ database/seeders/           Peran, izin, layanan, data contoh
├─ resources/views/surat/      Templat dokumen surat
├─ routes/api.php              68 rute /api/v1
└─ tests/                      Uji fitur dan unit

frontend/src/
├─ components/chart/           Grafik dengan padanan tabel data
├─ components/layout/          Tata letak publik, panel, penjaga rute
├─ components/ui/              Komponen dasar yang dapat diakses
├─ lib/                        Klien API, autentikasi, kueri, format
├─ pages/publik|auth|warga|admin
└─ types/                      Kontrak data bersama
```

## Operasional

Tugas terjadwal (daftarkan `php artisan schedule:run` pada cron server):

| Perintah | Jadwal | Tujuan |
|---|---|---|
| `sidesa:tutup-permohonan-kedaluwarsa` | Harian 01.00 | Menutup permohonan yang tidak diperbaiki (BR-07) |
| `sidesa:bersihkan-lampiran` | Harian 01.30 | Retensi lampiran 12 bulan (REQ-F-SRT-026) |
| `sidesa:segarkan-konten` | Tiap jam | Mengarsipkan pengumuman kedaluwarsa (BR-09) |
| `sidesa:bersihkan-draf` | Harian 02.00 | Menghapus draf permohonan lewat 7 hari (REQ-F-SRT-007) |
| `sidesa:cadangkan --jenis=basis-data` | Harian 02.30 | Cadangan basis data, retensi 30 hari (REQ-F-ADM-006) |
| `sidesa:cadangkan --jenis=media` | Mingguan | Cadangan berkas media (REQ-F-ADM-006) |
| `sidesa:ulangi-notifikasi` | Tiap 15 menit | Percobaan ulang notifikasi gagal (REQ-F-NOT-003) |
| `sidesa:pantau-anomali` | Tiap jam | Deteksi indikasi kebocoran data (REQ-NF-CMP-005) |
| `sidesa:bersihkan-audit-log` | Bulanan | Retensi jejak audit 24 bulan (REQ-NF-CMP-004) |
| `sidesa:tinjau-akun-tidak-aktif` | Bulanan | Menandai akun tidak aktif 36 bulan (REQ-NF-CMP-004) |

Gateway WhatsApp bersifat opsional. Bila `WHATSAPP_GATEWAY_TOKEN` kosong,
notifikasi tetap terkirim melalui surel dan alur layanan tidak terganggu.

## Aplikasi web progresif

Portal dapat dipasang di layar utama ponsel dan tetap terbuka saat jaringan
terputus: kerangka aplikasi serta data publik yang pernah dibuka disajikan dari
singgahan peramban, sedangkan halaman yang belum pernah dibuka menampilkan
pemberitahuan luring. Pengajuan surat dan pengaduan tetap memerlukan koneksi.

## Tanda tangan elektronik

Metode penandatanganan dipilih lewat `TTE_DRIVER` pada `.env`:

| Nilai | Perilaku |
|---|---|
| `internal` (bawaan) | Dokumen memuat spesimen tanda tangan pejabat dan diverifikasi lewat kode QR |
| `psre` | Dokumen dikirim ke penyelenggara sertifikasi elektronik untuk ditandatangani |

Bila `psre` dipilih namun kredensialnya belum lengkap, atau penyedia sedang
gangguan, sistem otomatis kembali ke metode internal agar pelayanan surat tidak
terhenti, dan kegagalannya tercatat pada audit log.

## Anti-penyalahgunaan formulir publik

Formulir pengaduan, permohonan informasi, dan pendaftaran UMKM dilindungi dua
lapis: pembatasan laju pengiriman dan tantangan CAPTCHA yang selalu diverifikasi
di sisi server. Penyedianya dipilih lewat `CAPTCHA_DRIVER`:

| Nilai | Perilaku |
|---|---|
| `bawaan` (anjuran) | Soal aritmatika dalam kata, tanpa layanan luar, dapat dibacakan pembaca layar |
| `turnstile` | Cloudflare Turnstile; token diverifikasi ke titik akhir resmi penyedia |
| `nihil` | Dimatikan — hanya untuk pengembangan dan uji otomatis |

Bila `turnstile` dipilih namun kuncinya belum lengkap, sistem kembali ke
tantangan bawaan alih-alih membiarkan formulir tanpa pelindung.

## Hak subjek data dan retensi

Warga mengunduh salinan lengkap data pribadinya sendiri melalui **Akun Saya →
Hak atas Data Pribadi**, dan dapat mengajukan penghapusan. Permintaan ditinjau
petugas berizin `data_pribadi.kelola` dengan tenggat jawaban 3x24 jam.
Persetujuan menghapus data pribadi, menganonimkan pengaduan, dan menonaktifkan
akun; surat yang telah terbit tetap disimpan sebagai arsip dan hal itu
dinyatakan terbuka kepada warga sebelum ia mengajukan permintaan.

Retensi berjalan otomatis: lampiran 12 bulan, draf 7 hari, jejak audit 24 bulan,
cadangan 30 hari, dan akun tidak aktif ditandai untuk ditinjau setelah 36 bulan
tanpa dinonaktifkan sendiri oleh sistem.

## Analitik

Kunjungan dihitung sendiri oleh sistem, tanpa skrip pihak ketiga, tanpa kuki,
dan tanpa menyimpan alamat IP maupun pengenal pengunjung. Yang tersimpan hanya
jumlah pembukaan laman per jalur per hari; parameter kueri dipangkas dan laman
akun serta panel petugas tidak dihitung. Angkanya tampil pada menu **Statistik
Kunjungan**, dan penghitungan dapat dimatikan sepenuhnya lewat `ANALITIK_AKTIF`.

## Basis data

| Mesin | Kapan dipakai |
|---|---|
| SQLite | Pengembangan lokal, uji otomatis, dan desa kecil bersatu server |
| MySQL/MariaDB | Pilihan paling lazim pada hosting desa di Indonesia |
| PostgreSQL | Bila penyedia menawarkannya sebagai layanan terkelola |

Berpindah mesin cukup mengubah `.env` lalu `php artisan migrate`; perintah
`sidesa:cadangkan` memilih sendiri `mysqldump`, `pg_dump`, atau salinan berkas.

Perhatikan: Supabase Cloud tidak punya region Indonesia, sehingga berbenturan
dengan CON-08 dan REQ-NF-CMP-007 (PP 71/2019). Rinciannya beserta jalur yang
patuh ada pada [panduan operasional bagian 9](docs/OPERASIONAL.md#9-pilihan-basis-data).

## Catatan keamanan dan perlindungan data

- NIK warga dan kontak pelapor pengaduan disimpan terenkripsi; pencarian
  memakai kolom hash terpisah.
- Lampiran identitas disimpan pada disk privat dan hanya dapat diunduh melalui
  tautan bertanda tangan digital yang kedaluwarsa dalam 30 hari.
- Seluruh aksi tulis tercatat pada audit log yang tidak dapat diubah.
- Statistik publik menyamarkan kelompok dengan jumlah di bawah 5 jiwa.
- Pemulihan kata sandi memakai tautan sekali pakai berumur 60 menit dan
  mencabut seluruh sesi lama; jawaban permintaan dibuat seragam agar tidak dapat
  dipakai memetakan surel yang terdaftar.
- Cadangan memuat data pribadi: simpan pada lokasi terenkripsi di wilayah
  Indonesia, lihat panduan operasional.
- Indikasi kebocoran data dipantau tiap jam dan diperingatkan ke administrator;
  prosedur penanganan 3x24 jam ada pada
  [panduan operasional bagian 8](docs/OPERASIONAL.md#8-penanganan-insiden-kebocoran-data).
- Sebelum peluncuran, kerjakan daftar periksa prarilis pada Lampiran C SRS.

## Status

| Fase SRS | Lingkup | Status |
|---|---|---|
| Fase 1 | Informasi dan transparansi | Terimplementasi |
| Fase 2 | Layanan surat daring | Terimplementasi, termasuk penandatanganan dengan spesimen tanda tangan; sertifikat PSrE menunggu OI-02 |
| — | Pemulihan akun, SEO, pencadangan | Terimplementasi |
| Fase 3 | Partisipasi dan keterbukaan | Terimplementasi |
| Fase 4 | Ekonomi desa dan penyempurnaan | Terimplementasi: BUMDes, pendaftaran UMKM mandiri, PWA, dwibahasa, API data terbuka, dan lapisan TTE yang siap disambungkan ke penyedia tersertifikasi |
| — | Kepatuhan UU PDP, CI, dan analitik | Terimplementasi: CAPTCHA, Syarat Penggunaan, hak subjek data, retensi jejak audit, deteksi insiden, pipeline CI beserta pemindaian dependensi, dan analitik tanpa data pribadi |

Dari 218 kebutuhan pada SRS: 163 terimplementasi, 10 terimplementasi sebagian,
20 belum dikerjakan, 22 menunggu pengukuran atau penyiapan server, dan 3 tidak
berlaku pada arsitektur yang dipilih. **Tidak ada lagi kebutuhan berprioritas
Must yang belum dikerjakan**; lima butir Must yang tersisa berstatus sebagian,
dua di antaranya tertahan pada terbitnya sertifikat elektronik (OI-02). Rincian
per butir beserta alasannya ada pada
[bagian 4 matriks ketertelusuran](docs/KETERTELUSURAN.md#4-status-pemenuhan-kebutuhan).
