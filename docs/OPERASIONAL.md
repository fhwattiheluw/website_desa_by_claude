# Panduan Operasional SIDESA

Panduan bagi administrator sistem desa: penjadwalan tugas, pencadangan,
pemulihan data, retensi, penanganan insiden kebocoran data, mode pemeliharaan,
dan penempatan berkas saat produksi. Memenuhi REQ-F-ADM-006, REQ-F-ADM-007,
REQ-F-ADM-008, REQ-NF-REL-003/004, REQ-NF-CMP-004, dan REQ-NF-CMP-005.

## 1. Penjadwal tugas

Seluruh tugas berkala dijalankan oleh penjadwal Laravel. Daftarkan satu baris
cron pada server:

```cron
* * * * * cd /var/www/sidesa/backend && php artisan schedule:run >> /dev/null 2>&1
```

Tugas yang berjalan:

| Perintah | Jadwal | Tujuan |
|---|---|---|
| `sidesa:tutup-permohonan-kedaluwarsa` | Harian 01.00 | Menutup permohonan dikembalikan yang lewat 14 hari (BR-07) |
| `sidesa:bersihkan-lampiran` | Harian 01.30 | Retensi lampiran 12 bulan (REQ-F-SRT-026) |
| `sidesa:bersihkan-draf` | Harian 02.00 | Menghapus draf yang lewat 7 hari (REQ-F-SRT-007) |
| `sidesa:cadangkan --jenis=basis-data` | Harian 02.30 | Cadangan basis data (REQ-F-ADM-006) |
| `sidesa:cadangkan --jenis=media` | Minggu 03.00 | Cadangan berkas media (REQ-F-ADM-006) |
| `sidesa:segarkan-konten` | Tiap jam | Mengarsipkan pengumuman kedaluwarsa (BR-09) |
| `sidesa:ulangi-notifikasi` | Tiap 15 menit | Percobaan ulang notifikasi gagal (REQ-F-NOT-003) |
| `sidesa:pantau-anomali` | Tiap jam | Deteksi indikasi kebocoran data (REQ-NF-CMP-005) |
| `sidesa:bersihkan-audit-log` | Tanggal 1, 03.30 | Retensi jejak audit 24 bulan (REQ-NF-CMP-004) |
| `sidesa:tinjau-akun-tidak-aktif` | Tanggal 1, 04.00 | Menandai akun tidak aktif 36 bulan (REQ-NF-CMP-004) |

Periksa jadwal aktif dengan `php artisan schedule:list`.

## 2. Pencadangan

```bash
php artisan sidesa:cadangkan                      # basis data dan media sekaligus
php artisan sidesa:cadangkan --jenis=basis-data   # hanya basis data
php artisan sidesa:cadangkan --jenis=media        # hanya berkas media
php artisan sidesa:cadangkan --retensi=60         # ubah masa simpan menjadi 60 hari
```

Berkas cadangan tersimpan di `backend/storage/app/private/cadangan/` dengan pola
`basis-data-YYYYMMDD-HHMMSS.sqlite|.sql` dan `media-YYYYMMDD-HHMMSS.zip`.
Cadangan yang melewati masa retensi (bawaan 30 hari) dihapus otomatis pada
pencadangan berikutnya, dan setiap pencadangan tercatat pada audit log.

**Penting.** Cadangan yang hanya tersimpan di server yang sama tidak melindungi
dari kegagalan server tersebut. Salin berkas cadangan ke lokasi terpisah, dan
karena isinya memuat data pribadi warga, lokasi tujuan harus berada di wilayah
Indonesia serta terenkripsi (REQ-NF-CMP-007, REQ-NF-SEC-006). Contoh dengan
`rsync` melalui SSH:

```cron
15 4 * * * rsync -az --delete /var/www/sidesa/backend/storage/app/private/cadangan/ \
  cadangan@penyimpanan-desa:/cadangan/sidesa/
```

## 3. Prosedur pemulihan data

Target pemulihan: RTO ≤ 4 jam, RPO ≤ 24 jam (REQ-NF-REL-003, REQ-NF-REL-004).
Pemulihan **tidak** diotomatiskan karena bersifat merusak; ikuti langkah berikut.

1. **Aktifkan mode pemeliharaan** agar warga tidak mengirim data yang akan hilang:

   ```bash
   php artisan down --secret="kunci-rahasia-admin" --render="errors::503"
   ```

   Administrator tetap dapat mengakses situs melalui
   `https://<domain>/kunci-rahasia-admin` (REQ-F-ADM-008).

2. **Amankan keadaan saat ini** sebelum menimpa apa pun:

   ```bash
   php artisan sidesa:cadangkan --jenis=semua
   ```

3. **Pulihkan basis data** sesuai jenis koneksi:

   ```bash
   # SQLite
   cp storage/app/private/cadangan/basis-data-<cap-waktu>.sqlite database/database.sqlite

   # MySQL / MariaDB
   mysql -u <pengguna> -p <nama_basis_data> < storage/app/private/cadangan/basis-data-<cap-waktu>.sql

   # PostgreSQL
   psql -U <pengguna> -d <nama_basis_data> -f storage/app/private/cadangan/basis-data-<cap-waktu>.sql
   ```

4. **Pulihkan berkas media**:

   ```bash
   unzip -o storage/app/private/cadangan/media-<cap-waktu>.zip -d storage/
   php artisan storage:link
   ```

5. **Samakan skema** bila cadangan berasal dari versi aplikasi lebih lama:

   ```bash
   php artisan migrate --force
   ```

6. **Verifikasi** sebelum dibuka kembali:

   ```bash
   php artisan about                       # koneksi basis data terbaca
   curl -s https://<domain>/api/v1/beranda  # portal menjawab
   ```

   Periksa juga secara manual: satu permohonan terbaru masih ada, satu surat
   terbit masih dapat diverifikasi melalui kode QR-nya, dan berkas media pada
   halaman berita tampil.

7. **Nonaktifkan mode pemeliharaan**:

   ```bash
   php artisan up
   ```

8. **Catat hasilnya** pada berita acara: waktu kejadian, cadangan yang dipakai,
   durasi pemulihan, dan data yang hilang bila ada.

### Uji pemulihan berkala

REQ-F-ADM-007 mewajibkan prosedur ini diuji minimal setiap 6 bulan. Lakukan pada
server salinan, bukan produksi:

1. Siapkan lingkungan uji dengan kode versi yang sama.
2. Pulihkan cadangan produksi terbaru mengikuti langkah 3–6 di atas.
3. Ukur durasi dari mulai sampai portal berfungsi; bandingkan dengan RTO 4 jam.
4. Tandatangani berita acara uji dan simpan sebagai bukti audit.

## 4. Mode pemeliharaan

```bash
php artisan down --secret="kunci-rahasia-admin"   # tutup sementara
php artisan up                                    # buka kembali
```

Umumkan pemeliharaan terjadwal paling lambat 3 hari sebelumnya melalui
pengumuman di portal, dan laksanakan pada pukul 23.00–03.00 WIB dengan durasi
maksimal 4 jam per bulan (REQ-NF-REL-002).

## 5. Penempatan saat produksi

- **Satu domain untuk portal dan API.** Sajikan hasil `npm run build` sebagai
  berkas statis pada akar domain, dan teruskan `/api`, `/storage`, `/sitemap.xml`
  serta `/robots.txt` ke Laravel. Dengan begitu URL kanonik pada sitemap sama
  dengan URL yang diakses warga, dan tidak diperlukan konfigurasi CORS.
- **Isi `FRONTEND_URL`** pada `.env` backend dengan alamat publik portal, sebab
  nilai itu dipakai untuk tautan verifikasi surat, tautan pemulihan kata sandi,
  dan isi `sitemap.xml`.
- **Aktifkan HTTPS** dan pastikan tajuk `Strict-Transport-Security` terkirim
  (tajuk ini otomatis aktif saat permintaan berjalan di atas HTTPS).
- **Setel `APP_DEBUG=false`** dan `APP_ENV=production`, lalu jalankan
  `php artisan config:cache route:cache view:cache`.
- **Jalankan ulang cache** setiap kali `.env` berubah, sebab konfigurasi yang
  ter-cache tidak membaca berkas `.env` lagi.

## 6. Kebijakan retensi data

Retensi dijalankan otomatis oleh penjadwal (REQ-NF-CMP-004). Administrator tidak
perlu menghapus apa pun secara manual.

| Data | Masa simpan | Yang menjalankan |
|---|---|---|
| Lampiran berkas permohonan | 12 bulan setelah layanan selesai | `sidesa:bersihkan-lampiran` |
| Draf permohonan | 7 hari sejak terakhir disunting | `sidesa:bersihkan-draf` |
| Jejak audit | 24 bulan | `sidesa:bersihkan-audit-log` |
| Akun warga tidak aktif | Ditandai untuk ditinjau setelah 36 bulan | `sidesa:tinjau-akun-tidak-aktif` |
| Cadangan basis data dan media | 30 hari | `sidesa:cadangkan --retensi` |
| Hitungan kunjungan laman | Tidak dibatasi (bukan data pribadi) | — |
| Surat yang telah diterbitkan | Permanen sebagai arsip pemerintahan desa | — |

Catatan penting:

- **Akun tidak aktif tidak dinonaktifkan otomatis.** Penjadwal hanya memberi
  tanda; petugas menyaringnya pada menu Pengguna dan memutuskan sendiri. Warga
  desa memang dapat tidak memakai portal bertahun-tahun tanpa berpindah
  domisili.
- **Pemangkasan jejak audit ikut tercatat** sebagai entri `retensi_audit_log`,
  sehingga penghapusan pun tetap dapat diperiksa.
- **Surat yang sudah terbit tidak pernah dihapus**, termasuk ketika warga
  meminta penghapusan data pribadi. Dasarnya UU 27/2022 Pasal 30 ayat (2):
  penghapusan tidak berlaku atas data yang masih diperlukan untuk pelaksanaan
  kewajiban hukum dan kearsipan. Kaitan surat dengan identitas pemohon diputus
  melalui penganoniman akun.

## 7. Permintaan hak subjek data

Warga mengunduh salinan datanya sendiri kapan saja melalui menu **Akun Saya →
Hak atas Data Pribadi**. Permintaan penghapusan masuk ke panel petugas pada menu
**Hak Subjek Data** dan hanya dapat ditangani peran dengan izin
`data_pribadi.kelola` (bawaannya Sekretaris Desa dan Administrator).

Tenggat jawaban **3x24 jam** sejak permintaan masuk. Langkahnya:

1. Buka menu **Hak Subjek Data**, baca alasan yang ditulis warga.
2. Periksa apakah masih ada permohonan layanan yang berjalan atas nama warga
   tersebut. Bila ada, **tolak** disertai alasan; warga dapat mengajukan lagi
   setelah layanan selesai.
3. Bila tidak ada, **setujui**. Sistem akan:
   - menghapus NIK, nomor telepon, alamat, dan data kelahiran dari akun;
   - mengganti nama akun menjadi penanda anonim dan menonaktifkannya;
   - mencabut seluruh sesi warga tersebut;
   - menghapus draf permohonan yang belum diajukan;
   - melepas identitas pelapor dari pengaduan dan permohonan informasi publik.
4. Keputusan apa pun tercatat pada audit log beserta pelaksananya.

Tindakan ini **tidak dapat dibatalkan**. Bila ragu, tolak dahulu dengan alasan
dan minta warga menegaskan permintaannya melalui kantor desa.

## 8. Penanganan insiden kebocoran data

Dasar: UU 27/2022 Pasal 46 — pengendali data pribadi wajib memberitahukan
kebocoran kepada subjek data dan lembaga pelindungan data pribadi **paling
lambat 3x24 jam sejak kebocoran diketahui**. Tenggat itu berjalan sejak
diketahui, bukan sejak dipastikan, sehingga jam pertama menentukan.

### 8.1 Deteksi

Tiga lapis, dan ketiganya perlu ada:

| Lapis | Sumber | Yang menandakan masalah |
|---|---|---|
| Otomatis | `sidesa:pantau-anomali`, tiap jam | Kegagalan masuk beruntun dari satu IP, pengunduhan data pribadi atau ekspor laporan berlebihan, perubahan peran bertubi-tubi |
| Manual | Audit log pada panel petugas | Aksi di luar jam kerja, pelaku yang tidak biasa, entitas yang tidak lazim disentuh peran tersebut |
| Laporan luar | Kanal pengaduan, surel resmi desa | Warga atau peneliti keamanan melaporkan data desa muncul di tempat lain |

Ambang deteksi otomatis ada pada `app/Services/DeteksiInsidenService.php` dan
dapat disesuaikan dengan besar desa. Peringatan dikirim ke surel seluruh
Administrator serta surel resmi desa, dan tercatat sebagai entri audit
`insiden_terdeteksi` — entri inilah bukti kapan insiden **diketahui**.

Peringatan otomatis adalah indikasi, bukan kesimpulan. Yang menilai tetap
manusia.

### 8.2 Penanganan, jam per jam

**Jam 0–1 — amankan dan catat**

1. Catat waktu, siapa yang menemukan, dan dari mana. Mulai satu catatan
   kronologis; seluruh langkah berikutnya ditulis di sana beserta jamnya.
2. Hentikan kebocoran yang masih berjalan: cabut token akun yang diduga
   disalahgunakan (`php artisan tinker` → `$u->tokens()->delete()`), nonaktifkan
   akunnya melalui menu Pengguna, atau nyalakan mode pemeliharaan bila kebocoran
   bersumber dari portal itu sendiri.
3. **Jangan hapus apa pun**, termasuk berkas log. Audit log memang menolak
   penyuntingan dan penghapusan; jaga agar berkas log server juga tidak dirotasi
   paksa.
4. Ambil cadangan basis data saat itu juga sebagai barang bukti:
   `php artisan sidesa:cadangkan --jenis=basis-data`.

**Jam 1–24 — telusuri cakupan**

5. Tentukan **data apa** yang terbuka (NIK, kontak, alamat, berkas lampiran),
   **berapa banyak** subjek data yang terdampak, dan **sejak kapan**. Audit log
   menjadi sumber utamanya.
6. Tentukan jalan masuknya: kredensial bocor, hak akses yang terlalu luas,
   kelemahan perangkat lunak, atau kehilangan perangkat.
7. Tutup jalan masuk itu. Bila berupa kelemahan dependensi, jalankan
   `composer audit` dan `npm audit`, mutakhirkan, lalu tempatkan ulang.
8. Laporkan kepada Kepala Desa. Keputusan pemberitahuan ada padanya sebagai
   penanggung jawab pengendali data.

**Jam 24–72 — beri tahu**

9. Susun pemberitahuan tertulis kepada subjek data yang terdampak, memuat: data
   apa yang terbuka, kapan terjadi, apa akibat yang mungkin timbul, apa yang
   sudah dilakukan desa, dan apa yang sebaiknya warga lakukan (misalnya mengubah
   kata sandi). Sampaikan melalui kanal kontak terdaftar; bila jumlahnya besar,
   tambahkan pengumuman pada portal.
10. Sampaikan pemberitahuan kepada lembaga pelindungan data pribadi sesuai
    ketentuan yang berlaku, dengan tembusan kepada pemerintah kabupaten.
11. Simpan salinan seluruh pemberitahuan beserta tanggal pengirimannya.

**Setelah 72 jam — perbaiki**

12. Tulis laporan penutup: kronologi, akar penyebab, dampak, tindakan, dan
    perbaikan yang dijadwalkan.
13. Jalankan perbaikannya: penyempitan hak akses, penurunan ambang deteksi,
    penambahan uji otomatis yang menangkap kelemahan serupa.
14. Uji pemulihan data berikutnya dimajukan agar prosedur benar-benar teruji.

### 8.3 Yang tidak boleh dilakukan

- Menunggu kepastian penuh sebelum mencatat waktu diketahui. Tenggat 3x24 jam
  tidak berhenti selama penyelidikan.
- Menghapus atau menyunting log untuk "merapikan" keadaan.
- Memberi tahu sebagian subjek data saja karena sisanya sulit dihubungi.
  Pemberitahuan menyeluruh tetap wajib diupayakan dan dicatat upayanya.
- Menutup insiden tanpa menemukan akar penyebabnya.

## 9. Pilihan basis data

Sistem diuji otomatis pada **SQLite** dan **PostgreSQL**; **MySQL/MariaDB** juga
didukung oleh migrasi dan perintah pencadangan. Alur kerja CI menjalankan seluruh
uji pada SQLite dan PostgreSQL sekaligus, karena perbedaan keduanya tidak selalu
memunculkan galat — lihat catatan `LIKE` di bawah.

| Mesin | Kapan dipakai |
|---|---|
| SQLite | Pengembangan lokal dan uji otomatis. Cukup pula untuk desa kecil dengan satu server |
| MySQL/MariaDB | Pilihan paling lazim pada hosting desa di Indonesia |
| PostgreSQL | Bila penyedia menawarkannya sebagai layanan terkelola |

Berpindah mesin cukup mengubah `.env` lalu menjalankan `php artisan migrate`.
Perintah `sidesa:cadangkan` memilih sendiri `mysqldump`, `pg_dump`, atau salinan
berkas sesuai koneksi yang aktif.

### Catatan `LIKE` yang mudah terlewat

`LIKE` mengabaikan besar kecil huruf di SQLite dan MySQL, tetapi **tidak** di
PostgreSQL. Bila kueri pencarian ditulis dengan `LIKE` apa adanya, pencarian
"Jembatan" berhenti menemukan "jembatan" begitu desa berpindah ke PostgreSQL —
tanpa galat, tanpa peringatan, hanya hasil kosong.

Karena itu seluruh pencarian teks memakai makro `cariTeks` yang terdaftar pada
`AppServiceProvider`. Makro itu memilih `ILIKE` pada PostgreSQL dan `LIKE` pada
mesin lain. **Jangan menulis `->where($kolom, 'like', ...)` langsung**; pakai
`->cariTeks($kolom, $kata)`.

Hal serupa berlaku pada klausa `HAVING`: PostgreSQL tidak mengenali alias kolom
keluaran di dalamnya, sehingga agregatnya perlu diulang (`havingRaw('COUNT(*) > ?')`).

### Catatan bila memakai Supabase

Supabase Cloud **tidak memiliki region Indonesia**; yang terdekat Singapura. Itu
berbenturan dengan CON-08 dan REQ-NF-CMP-007 (PP 71/2019) yang mewajibkan data
beserta cadangannya berada di pusat data wilayah Indonesia. Supabase hanya dapat
dipakai bila dipasang sendiri (*self-hosted*) pada penyedia dalam negeri, dengan
konsekuensi pemeliharaan yang perlu dihitung terhadap CON-07.

Bila tetap dipakai, sambungkan lewat **session pooler (porta 5432)**, bukan
transaction pooler (6543): Laravel memakai *prepared statement* yang tidak
didukung penuh pada mode transaksi. Lapisan Supabase lainnya — Auth, PostgREST,
Realtime, RLS — tidak terpakai, karena alur permohonan, RBAC, audit log,
penomoran surat, dan penerbitan PDF sudah ditegakkan di Laravel.

## 10. Pemantauan minimum

| Yang dipantau | Cara | Ambang tindakan |
|---|---|---|
| Ketersediaan portal | Pemeriksaan berkala ke `/up` | Gagal 3 kali berturut-turut |
| Keberhasilan pencadangan | Audit log aksi `cadangan` | Tidak ada entri dalam 48 jam |
| Notifikasi gagal | Tabel `notifikasi_log` status `gagal` | Lebih dari 20 dalam sehari |
| Permohonan melampaui SLA | Dasbor panel petugas | Lebih dari 5 permohonan |
| Ruang penyimpanan | `df -h` pada server | Sisa di bawah 20% |
| Indikasi insiden | Audit log aksi `insiden_terdeteksi` | Satu entri saja — langsung tangani |
| Permintaan hak subjek data | Panel petugas menu Hak Subjek Data | Ada permintaan melampaui tenggat |
| Kerentanan dependensi | Alur kerja Pemindaian Berkala pada GitHub Actions | Alur kerja gagal |
