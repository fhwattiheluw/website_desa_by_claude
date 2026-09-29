# SIDESA — Sistem Informasi Desa Terpadu

Portal resmi pemerintah desa: informasi publik, transparansi anggaran, layanan
administrasi surat daring, dan kanal pengaduan masyarakat.

Implementasi mengacu pada [Software Requirements Specification](docs/SRS-Website-Desa.md)
(SRS-WDESA-001). Pemetaan tiap kebutuhan ke kode tersedia pada
[matriks ketertelusuran](docs/KETERTELUSURAN.md), sedangkan penjadwalan tugas,
pencadangan, dan prosedur pemulihan data ada pada
[panduan operasional](docs/OPERASIONAL.md).

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
cd backend && ./vendor/bin/phpunit      # 89 uji, 405 asersi
cd backend && ./vendor/bin/pint --test  # gaya kode
cd frontend && npm run build            # typecheck + bundel produksi
cd frontend && npm run lint
```

Cakupan uji otomatis meliputi autentikasi dan penguncian akun, pemulihan kata
sandi dan verifikasi surel, alur permohonan surat ujung-ke-ujung sampai PDF
terbit, draf permohonan, penegakan hak akses antarperan, aturan bisnis (BR-01
sampai BR-15), portal publik, pengaduan, sitemap dan robots, pencadangan,
ekspor laporan, perhitungan SLA hari kerja, serta keunikan penomoran surat.

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
- Sebelum peluncuran, kerjakan daftar periksa prarilis pada Lampiran C SRS.

## Status

| Fase SRS | Lingkup | Status |
|---|---|---|
| Fase 1 | Informasi dan transparansi | Terimplementasi |
| Fase 2 | Layanan surat daring | Terimplementasi (TTE tersertifikasi belum, lihat OI-02) |
| — | Pemulihan akun, SEO, pencadangan | Terimplementasi |
| Fase 3 | Partisipasi dan keterbukaan | Terimplementasi |
| Fase 4 | Ekonomi desa dan penyempurnaan | Terimplementasi: BUMDes, pendaftaran UMKM mandiri, PWA, dwibahasa, API data terbuka, dan lapisan TTE yang siap disambungkan ke penyedia tersertifikasi |
