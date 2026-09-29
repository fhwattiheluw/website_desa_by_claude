# Panduan Administrator — Portal Desa

Panduan bagi petugas desa yang memakai panel administrasi (REQ-NF-MNT-006).
Untuk urusan server — penjadwal, pencadangan, pemulihan, penanganan insiden —
lihat [Panduan Operasional](OPERASIONAL.md). Untuk sisi warga, lihat
[Panduan Warga](PANDUAN-WARGA.md).

---

## 1. Peran dan kewenangan

Kewenangan ditentukan peran, bukan oleh menu yang terlihat. Setiap tindakan
diperiksa ulang di sisi server, sehingga peran yang tidak berwenang tetap ditolak
walaupun alamat halamannya diketik langsung.

| Peran | Kewenangan utama |
|---|---|
| **Operator Desa** | Konten, media, verifikasi berkas permohonan, validasi NIK, permohonan loket |
| **Verifikator (Kasi/Kaur)** | Seluruh kewenangan operator, menerbitkan konten, menyetujui permohonan, mendisposisi pengaduan |
| **Sekretaris Desa** | Menyetujui dan menandatangani surat, membatalkan surat, PPID, APBDes, BUMDes, menangani permintaan hak subjek data, audit log |
| **Kepala Desa** | Menandatangani surat, membatalkan surat, laporan kinerja, disposisi pengaduan, audit log |
| **Administrator Sistem** | Seluruh kewenangan, termasuk pengguna, peran, dan pengaturan situs |

Satu permohonan selalu melewati lebih dari satu orang: yang memverifikasi berkas
tidak boleh sekaligus menandatangani suratnya.

---

## 2. Antrean permohonan surat

Menu **Antrean Permohonan**. Saring berdasarkan status, jenis layanan, atau
permohonan yang melampaui tenggat.

### Alur kerja

1. **Verifikasi berkas** (operator/verifikator). Cocokkan isian formulir dengan
   berkas yang diunggah. Bila lengkap dan benar, tekan **Verifikasi**.
2. **Kembalikan** bila ada yang kurang. Alasan wajib ditulis **minimal 20
   karakter** dan harus menyebutkan apa persisnya yang perlu diperbaiki — warga
   membaca alasan ini apa adanya. "Berkas kurang" tidak membantu; "Foto KTP
   buram pada bagian NIK, mohon unggah ulang" membantu.
3. **Tolak** bila permohonan memang tidak dapat diproses. Alasan juga wajib.
4. **Setujui** (verifikator/sekdes/kades).
5. **Tandatangani** (sekdes/kades). Surat PDF terbit saat itu juga, lengkap
   dengan nomor surat, kode QR, dan kode verifikasi.

Warga menerima pemberitahuan pada setiap perubahan status. Seluruh perpindahan
status tercatat pada riwayat permohonan beserta pelakunya.

### Permohonan loket

Untuk warga yang datang langsung ke kantor: tekan **Buat Permohonan Loket**,
pilih warga, lalu isikan formulirnya. Permohonan tercatat berkanal `loket`
sehingga laporan dapat membedakan layanan daring dan tatap muka.

### Membatalkan surat yang sudah terbit

Menu detail permohonan → **Batalkan Surat** (sekdes/kades). Surat yang dibatalkan
tetap tersimpan, namun halaman verifikasi publik menampilkannya sebagai **tidak
berlaku** beserta alasannya. Gunakan bila surat terbit dengan data keliru.

---

## 3. Tanda tangan elektronik

Menu **Tanda Tangan** (sekdes/kades).

- Unggah **spesimen tanda tangan** Anda: foto tanda tangan di atas kertas putih,
  sebaiknya PNG berlatar transparan, maksimal 1 MB.
- Berkas disimpan pada penyimpanan privat tanpa alamat publik, dan hanya dapat
  dibuka oleh Anda sendiri.
- Bila spesimen belum dipasang, surat tetap terbit namun tanpa gambar tanda
  tangan.
- Penyimpanan dan penghapusan spesimen tercatat pada audit log.

Bila desa telah memiliki sertifikat elektronik dari penyelenggara sertifikasi
(BSrE/PSrE), administrator mengubah `TTE_DRIVER=psre` beserta kredensialnya.
Sistem otomatis kembali ke tanda tangan dalam sistem bila penyedia tidak dapat
dihubungi, agar pelayanan tidak berhenti.

---

## 4. Pengaduan masyarakat

Menu **Pengaduan**.

1. **Disposisi** pengaduan ke perangkat desa yang berwenang.
2. **Tanggapi** paling lambat 3 hari kerja. Tanggapan bertanda *internal* hanya
   terbaca petugas; tanggapan biasa terbaca pelapor.
3. **Ubah status** mengikuti kemajuan penanganan.
4. **Publikasikan** pengaduan yang layak menjadi contoh (butuh izin moderasi).

Identitas pelapor tidak pernah tampil pada halaman publik, sekalipun yang
membukanya petugas yang sedang masuk. Pengaduan anonim tidak menampilkan nama
pelapor bahkan kepada petugas.

---

## 5. Konten dan media

Menu **Konten**. Jenis: berita, artikel, pengumuman, agenda.

- **Jadwalkan terbit** dengan mengisi tanggal tayang.
- **Pengumuman berkedaluwarsa** otomatis diarsipkan pada tanggal yang Anda
  tentukan.
- Setiap penyuntingan menyimpan **versi**; versi lama dapat dipulihkan.
- Beri **teks alternatif** pada setiap gambar. Tanpa itu, pengguna pembaca layar
  kehilangan isinya.

Menu **Media** untuk galeri dan album kegiatan. Ukuran berkas dibatasi agar
halaman tetap ringan pada jaringan desa.

---

## 6. Transparansi anggaran, statistik, dan BUMDes

**APBDes.** Buat tahun anggaran, isikan pagu dan realisasi per bidang, atau impor
dari berkas CSV. Data baru tampil kepada publik setelah **dipublikasikan**
(butuh izin `apbdes.publikasi`) — susun dahulu, publikasikan setelah yakin.

**Statistik desa.** Buat periode, isikan angka kependudukan, lalu aktifkan
periode yang ditampilkan publik.

**BUMDes.** Kelola unit usaha dan kinerja tahunan. Angka kinerja juga perlu
dipublikasikan tersendiri.

Seluruh grafik pada portal selalu disertai tabel data, sehingga tetap terbaca
pengguna pembaca layar.

---

## 7. Pengguna

Menu **Pengguna**.

- **Validasi NIK** warga yang baru mendaftar. Cocokkan dengan data kependudukan.
  Warga tidak dapat mengajukan layanan sebelum langkah ini.
- **Ubah peran** hanya oleh administrator. Berikan peran sesempit mungkin.
- **Nonaktifkan akun** yang terbukti disalahgunakan.
- **Akun tidak aktif**: saring dengan **Perlu ditinjau**. Penjadwal menandai akun
  warga yang tidak dipakai 36 bulan. Sistem **tidak** menonaktifkannya sendiri —
  keputusan ada pada Anda, dan warga desa memang dapat lama tidak membuka portal.

NIK selalu ditampilkan tersamar (`3271********0003`). Yang dapat melihatnya utuh
hanya pemilik data sendiri.

---

## 8. Hak subjek data

Menu **Hak Subjek Data** (sekdes/administrator). Prosedur dan tenggatnya ada pada
[Panduan Operasional §7](OPERASIONAL.md#7-permintaan-hak-subjek-data).

Ringkasnya: jawab paling lambat 3x24 jam; tolak disertai alasan bila masih ada
permohonan berjalan; persetujuan menghapus data pribadi dan tidak dapat
dibatalkan.

---

## 9. Laporan dan statistik kunjungan

**Laporan Layanan** — jumlah permohonan per layanan, ketepatan waktu terhadap
SLA, dan kepuasan warga. Dapat diunduh sebagai CSV.

> Setiap pengunduhan CSV tercatat pada audit log beserta rentang tanggal dan
> jumlah barisnya. Berkas itu memuat data warga: simpan di tempat aman dan
> hapus setelah tidak diperlukan.

**Statistik Kunjungan** — berapa kali tiap halaman dibuka. Dihitung sendiri oleh
sistem tanpa layanan pihak ketiga, tanpa kuki, dan tanpa menyimpan alamat IP.
Angkanya menunjukkan jumlah pembukaan halaman, bukan jumlah orang. Halaman akun
dan panel petugas tidak ikut dihitung.

---

## 10. Audit log

Menu **Audit Log** (sekdes/kades/administrator). Berisi seluruh aksi tulis:
siapa, kapan, dari alamat IP mana, dan nilai sebelum serta sesudah perubahan.

- Catatan **tidak dapat diubah maupun dihapus** oleh peran mana pun.
- Nilai data pribadi disamarkan di dalam catatan.
- Entri lewat 24 bulan dipangkas otomatis, dan pemangkasannya sendiri tercatat.

Biasakan memeriksanya berkala. Entri beraksi `insiden_terdeteksi` berarti sistem
menemukan pola mencurigakan — tangani menurut
[prosedur insiden](OPERASIONAL.md#8-penanganan-insiden-kebocoran-data).

---

## 11. Pengaturan situs

Menu **Pengaturan** (administrator). Nama desa, alamat, kontak, jam pelayanan,
nama kepala desa, dan logo. Nilai ini dipakai pada seluruh halaman publik dan
pada kop surat, sehingga perubahannya langsung terasa di mana-mana.

---

## 12. Kebiasaan kerja yang dianjurkan

- **Tulis alasan yang dapat ditindaklanjuti.** Warga hanya membaca kalimat Anda,
  tanpa penjelasan tambahan.
- **Jangan berbagi akun.** Audit log mencatat nama pemilik akun, bukan orang yang
  sedang memegang papan ketik.
- **Keluar setelah selesai**, terutama pada komputer bersama di kantor desa. Sesi
  petugas memang berakhir sendiri setelah 30 menit, tetapi jangan bergantung
  padanya.
- **Publikasikan setelah diperiksa.** Angka anggaran yang telanjur tayang keliru
  lebih merepotkan daripada yang tayang terlambat sehari.
- **Laporkan hal ganjil.** Entri audit di luar jam kerja atau permohonan yang
  tidak Anda kenali lebih baik ditanyakan daripada diabaikan.
