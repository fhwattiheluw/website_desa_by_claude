# Panduan Operasional SIDESA

Panduan bagi administrator sistem desa: penjadwalan tugas, pencadangan,
pemulihan data, mode pemeliharaan, dan penempatan berkas saat produksi.
Memenuhi REQ-F-ADM-006, REQ-F-ADM-007, REQ-F-ADM-008, dan REQ-NF-REL-003/004.

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

## 6. Pemantauan minimum

| Yang dipantau | Cara | Ambang tindakan |
|---|---|---|
| Ketersediaan portal | Pemeriksaan berkala ke `/up` | Gagal 3 kali berturut-turut |
| Keberhasilan pencadangan | Audit log aksi `cadangan` | Tidak ada entri dalam 48 jam |
| Notifikasi gagal | Tabel `notifikasi_log` status `gagal` | Lebih dari 20 dalam sehari |
| Permohonan melampaui SLA | Dasbor panel petugas | Lebih dari 5 permohonan |
| Ruang penyimpanan | `df -h` pada server | Sisa di bawah 20% |
