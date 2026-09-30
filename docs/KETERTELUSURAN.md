# Matriks Ketertelusuran SRS → Implementasi

Dokumen ini memetakan kebutuhan pada [SRS-WDESA-001](SRS-Website-Desa.md) ke
berkas yang mewujudkannya dan pengujian yang membuktikannya. Dipakai saat
review, audit, dan uji penerimaan (Bab 9 dan 10 SRS).

## 1. Modul fungsional

| Modul | Kebutuhan | Implementasi backend | Implementasi frontend | Pengujian |
|---|---|---|---|---|
| MOD-BRD Beranda & Profil | REQ-F-BRD-001..008 | `Api/Publik/BerandaController`, `ProfilController`, `LembagaController`, model `Pengaturan`, `FasilitasUmum` | `pages/publik/Beranda.tsx`, `Profil.tsx`, `components/ui/BaganOrganisasi.tsx`, `components/ui/Peta.tsx` | `PortalPublikTest::test_beranda_menyajikan_profil_dan_sorotan`, `BaganOrganisasiTest` (5 uji) |
| MOD-KNT Konten | REQ-F-KNT-001..014 | `Api/Admin/KontenController`, `Api/Publik/KontenController`, model `Konten`, `KontenVersi` | `pages/admin/KelolaKonten.tsx`, `pages/publik/DaftarKonten.tsx`, `DetailKonten.tsx` | `PortalPublikTest` (tayang, kedaluwarsa, penghitung dibaca), `OtorisasiTest` (maker-checker) |
| MOD-GAL Galeri & Media | REQ-F-GAL-001..008 | `Services/MediaService`, `Services/Pemindai/*`, `Services/PenyematVideo`, `Api/Admin/MediaController`, `Api/Admin/VideoAlbumController`, `Api/Publik/GaleriController` | `pages/publik/Halaman.tsx` (Galeri), `pages/publik/AlbumGaleri.tsx`, `components/ui/SematVideo.tsx`, `pages/admin/KelolaGaleri.tsx` | `VideoAlbumTest` (9 uji), uji unggahan pada `PermohonanSuratTest` (lampiran privat) |
| MOD-APB Transparansi | REQ-F-APB-001..009 | `Api/Publik/ApbdesController`, `Api/Admin/ApbdesController` | `pages/publik/Apbdes.tsx`, `pages/admin/KelolaApbdes.tsx` | `PortalPublikTest::test_apbdes_belum_dipublikasikan_tidak_dapat_diakses` |
| MOD-STA Statistik | REQ-F-STA-001..006 | `Api/Publik/StatistikController`, `Api/Admin/StatistikController` | `pages/publik/Statistik.tsx` | `PortalPublikTest::test_statistik_menyamarkan_kelompok_sangat_kecil` |
| MOD-SRT Layanan Surat | REQ-F-SRT-001..027 | `Services/PermohonanService`, `SuratService`, `NomorService`, `KalenderKerja`, `Services/TandaTangan/*`, `Api/Warga/PermohonanController`, `Api/Admin/PermohonanController`, `Api/Admin/TandaTanganController` | `pages/warga/AjukanSurat.tsx`, `DetailPermohonan.tsx`, `pages/admin/AntreanPermohonan.tsx`, `DetailPermohonanAdmin.tsx`, `TandaTangan.tsx` | `PermohonanSuratTest` (16 uji), `Fase4Test` (TTE), `NomorServiceTest`, `KalenderKerjaTest` |
| MOD-ADU Pengaduan | REQ-F-ADU-001..011 | `Services/PengaduanService`, `Api/Publik/PengaduanController`, `Api/Admin/PengaduanController` | `pages/publik/Pengaduan.tsx`, `LacakPengaduan.tsx`, `pages/admin/KelolaPengaduan.tsx` | `PengaduanTest` (8 uji) |
| MOD-PID PPID & Produk Hukum | REQ-F-PID-001..007 | `Api/Publik/PustakaController`, `Api/Admin/ReferensiController` | `pages/publik/Pustaka.tsx`, `PermohonanInformasi.tsx` | `PortalPublikTest::test_bentuk_respons_berhalaman_seragam` |
| MOD-POT Potensi Desa | REQ-F-POT-001..007 | `Api/Publik/PotensiController`, `Api/Publik/BumdesController`, `Api/Admin/ReferensiController`, `Api/Admin/BumdesController` | `pages/publik/Potensi.tsx`, `DaftarUmkm.tsx`, `Bumdes.tsx`, `pages/admin/KelolaBumdes.tsx` | `Fase4Test::test_laman_bumdes_...`, `test_kinerja_bumdes_baru_tampil_...` |
| MOD-LMB Lembaga | REQ-F-LMB-001..004 | `Api/Publik/LembagaController`, model `Lembaga`, `Pengurus` | `pages/publik/Profil.tsx` | Data pribadi aparatur tidak dipaparkan (lihat resource) |
| MOD-USR Pengguna & Akses | REQ-F-USR-001..015 | `Api/AuthController`, `Services/OtpService`, `Api/KataSandiController`, `Api/VerifikasiSurelController`, `Http/Middleware/PastikanIzin`, `Api/Admin/PenggunaController` | `lib/auth.ts`, `components/layout/PenyediaAuth.tsx`, `pages/auth/*`, `pages/warga/RiwayatMasuk.tsx`, `components/layout/Terlindungi.tsx` | `AutentikasiTest` (12 uji), `DuaFaktorTest` (9 uji), `PemulihanAkunTest` (9 uji), `OtorisasiTest` (8 uji) |
| MOD-ADM Administrasi | REQ-F-ADM-001..010 | `Api/Admin/DashboardController`, `PengaturanController`, `Api/Admin/MenuController`, `Api/Publik/MenuController`, `AuditLogController`, `Services/AuditLogger`, `Console/Commands/Cadangkan` | `pages/admin/Dasbor.tsx`, `Pengaturan.tsx`, `KelolaMenu.tsx`, `components/layout/MenuUtama.tsx`, `AuditLog.tsx`, `Laporan.tsx` | `OtorisasiTest::test_audit_log_bersifat_hanya_baca`, `OperasionalTest::test_perintah_pencadangan_...`, `test_ekspor_laporan_...` |
| MOD-NOT Notifikasi | REQ-F-NOT-001..006 | `Services/NotifikasiService`, `NotifikasiPetugasService`, model `NotifikasiLog`, `NotifikasiPetugas`, `Api/Admin/NotifikasiController`, `Api/PreferensiNotifikasiController` | `components/layout/LonceNotifikasi.tsx`, `pages/warga/PreferensiNotifikasi.tsx` | `NotifikasiTest` (9 uji), alur `PermohonanSuratTest` |
| MOD-SRC Pencarian & SEO | REQ-F-SRC-001..007 | `Api/Publik/PencarianController`, `PetaSitusController` (sitemap, robots, RSS), `KerangkaAplikasiController`, `Services/MetadataHalaman` | `pages/publik/Halaman.tsx` (Pencarian), `TidakDitemukan`, `lib/meta.ts` (metadata dan data terstruktur) | `PortalPublikTest::test_pencarian_global_...`, `OperasionalTest::test_sitemap_...`, `Fase4Test::test_umpan_rss_...` |

## 2. Aturan bisnis

| Aturan | Penegakan | Pengujian |
|---|---|---|
| BR-01 hanya warga aktif ber-NIK terverifikasi | `User::bolehMengajukanLayanan`, `PermohonanService::buat` | `test_warga_belum_terverifikasi_tidak_dapat_mengajukan` |
| BR-02 urutan verifikasi sebelum persetujuan | `PermohonanService::TRANSISI` | `test_persetujuan_tanpa_verifikasi_ditolak` |
| BR-03 pembuat loket bukan penyetuju | `PermohonanService::transisi` | `test_pembuat_permohonan_loket_tidak_dapat_menyetujuinya` |
| BR-04 nomor surat terbit saat penandatanganan | `SuratService::terbitkan` | `test_alur_lengkap_sampai_surat_terbit` |
| BR-05 pembatalan surat beralasan | `SuratService::batalkan` | `test_surat_yang_dibatalkan_ditandai_tidak_berlaku` |
| BR-06 SLA dalam hari kerja | `Services/KalenderKerja` | `KalenderKerjaTest` (4 uji) |
| BR-07 kedaluwarsa perbaikan 14 hari | `PermohonanService::tutupYangKedaluwarsa` | Perintah `sidesa:tutup-permohonan-kedaluwarsa` |
| BR-08 APBDes tampil setelah disetujui | `TahunAnggaran::dipublikasikan`, `ApbdesController` | `test_apbdes_belum_dipublikasikan_tidak_dapat_diakses` |
| BR-09 pengumuman kedaluwarsa diarsipkan | `Konten::scopeTayang`, perintah `sidesa:segarkan-konten` | `test_pengumuman_kedaluwarsa_tidak_tampil` |
| BR-10 penolakan pengaduan beralasan | `PengaduanService::ubahStatus` | `test_penolakan_pengaduan_wajib_beralasan` |
| BR-11 identitas pelapor disamarkan | `Pengaduan::pelaporTersamar`, `PengaduanController::publik` | `test_pengaduan_publik_menyamarkan_pelapor` |
| BR-12 satu NIK satu akun | Kolom `nik_hash` unik, validasi registrasi | `test_satu_nik_hanya_untuk_satu_akun` |
| BR-13 perubahan peran tercatat | `PenggunaController::ubahPeran` + `AuditLogger` | `test_perubahan_peran_hanya_oleh_admin_dan_tercatat_di_audit_log` |
| BR-14 produk hukum dicabut tetap diakses | `PustakaController::produkHukum` | Ditampilkan dengan penanda "Tidak berlaku" |
| BR-15 ambang k-anonimitas 5 jiwa | `StatistikController::AMBANG_ANONIMITAS` | `test_statistik_menyamarkan_kelompok_sangat_kecil` |
| BR-16 kontak UMKM atas persetujuan | `Umkm::teleponPublik` | Dipakai pada `PotensiController::umkm` |
| BR-17 unggahan berbahaya ditolak | `MediaService::pastikanAman` | Validasi magic number dan pemindaian konten |

## 3. Kebutuhan non-fungsional

| Kebutuhan | Implementasi | Bukti |
|---|---|---|
| REQ-NF-SEC-002..004 validasi, XSS, SQL, CSRF | Validasi sisi server pada seluruh controller, Eloquent berparameter, Sanctum berbasis token | Uji validasi pada `PermohonanSuratTest`, `AutentikasiTest` |
| REQ-NF-SEC-005 tajuk keamanan | `Http/Middleware/TajukKeamanan` | `test_tajuk_keamanan_terpasang_pada_respons` |
| REQ-NF-SEC-006 enkripsi data pribadi | Mutator terenkripsi pada `User::nik`, `Pengaduan::kontak_pelapor` | `test_warga_baru_terdaftar_...`, `test_warga_dapat_mengirim_pengaduan_...` |
| REQ-NF-SEC-007 berkas di luar akar web | `MediaService` disk privat, unduhan bertanda tangan | `test_tautan_unduh_surat_berbatas_waktu_dan_bertanda_tangan` |
| REQ-NF-SEC-009 pembatasan laju | `AppServiceProvider::daftarkanPembatasLaju` | `test_pembatas_laju_per_akun_...`, `test_formulir_publik_dibatasi_lajunya` |
| REQ-NF-SEC-013 log peristiwa keamanan | `AuditLogger` pada masuk, gagal masuk, ubah peran, ekspor laporan | `OtorisasiTest`, `DeteksiInsidenTest::test_ekspor_laporan_massal_...` |
| REQ-NF-CMP-005 deteksi dan pelaporan insiden | `Services/DeteksiInsidenService`, `sidesa:pantau-anomali` tiap jam, prosedur 3x24 jam | `DeteksiInsidenTest` (6 uji), `docs/OPERASIONAL.md` bagian 8 |
| REQ-NF-MNT-002 integrasi berkelanjutan | Pint, PHPUnit, tsc, oxlint, dan build pada setiap perubahan | `.github/workflows/ci.yml` |
| REQ-NF-SEC-012 pemindaian dependensi | `composer audit` dan `npm audit --audit-level=high` pada CI dan mingguan | `.github/workflows/ci.yml`, `pemindaian-berkala.yml` |
| REQ-NF-MNT-006 dokumentasi | Panduan instalasi (README), operasional, administrator, dan warga | `README.md`, `docs/OPERASIONAL.md`, `docs/PANDUAN-ADMINISTRATOR.md`, `docs/PANDUAN-WARGA.md` |
| REQ-NF-CMP-001 kebijakan dan syarat | Kebijakan Privasi dan Syarat Penggunaan, tertaut pada kaki setiap halaman | `pages/publik/Halaman.tsx`, `components/layout/TataLetakPublik.tsx` |
| REQ-SW-006 analitik menghormati privasi | Penghitung sendiri tanpa kuki, alamat IP, maupun pengenal pengunjung | `Services/AnalitikService`, `pages/admin/Analitik.tsx`, `AnalitikTest` (8 uji) |
| REQ-SW-007, REQ-F-ADU-010 anti-penyalahgunaan | Pembatasan laju ditambah CAPTCHA yang diverifikasi di sisi server | `Services/Captcha/*`, `Http/Middleware/PeriksaCaptcha`, `CaptchaTest` (10 uji) |
| REQ-NF-PRF-003 ukuran transfer | Pemuatan malas rute berat, berkas awal 115 kB gzip | Keluaran `npm run build` |
| REQ-F-BRD-003 bagan struktur organisasi | Kolom `pengurus.atasan_id` membentuk pohon; ditampilkan sebagai daftar bersarang dengan garis penghubung CSS | `Api/Publik/LembagaController::bagan`, `components/ui/BaganOrganisasi.tsx`, `BaganOrganisasiTest` |
| REQ-F-BRD-004, REQ-SW-003 peta wilayah | Peta tersemat berpenanda kantor desa dan fasilitas umum, pustaka petanya diimpor hanya saat peta terlihat | `Api/Publik/ProfilController`, `components/ui/Peta.tsx`, `BaganOrganisasiTest::test_fasilitas_umum_...` |
| REQ-NF-MNT-001 cakupan uji | 207 uji, 893 asersi pada logika inti, dijalankan pada SQLite dan PostgreSQL | `./vendor/bin/phpunit` |
| REQ-F-KNT-009 terpopuler | Dibatasi 90 hari terakhir agar satu tulisan lama tidak menempatinya selamanya | `Api/Publik/BerandaController` |
| REQ-F-KNT-013 komentar bermoderasi | Seluruh komentar menunggu tinjauan petugas; tidak ada jalur yang membuat tulisan orang lain langsung tayang | `Api/Publik/KomentarController`, `Api/Admin/KomentarController`, `components/ui/Komentar.tsx`, `PartisipasiTest` |
| REQ-F-PID-007 keberatan informasi | Kode lacak memberi pemohon jalan memeriksa status dan mengajukan keberatan tanpa berakun; tenggat 30 hari kerja (UU 14/2008 Pasal 36) | `Api/Publik/KeberatanController`, `Api/Admin/KeberatanController`, `pages/publik/LacakInformasi.tsx` |
| REQ-F-POT-007 produk unggulan | Pergiliran berhenti saat gerakan diminta dikurangi, saat penunjuk di atasnya, atau saat fokus masuk | `components/ui/Bergilir.tsx` |
| REQ-F-STA-004 pembanding periode | Kelompok yang disamarkan tidak ikut dibandingkan; selisihnya dapat membocorkan angka yang disembunyikan | `Api/Publik/StatistikController::pembanding` |
| REQ-F-BRD-008 penunjuk arah | Diserahkan ke layanan peta di perangkat warga; rute menuntut data jalan mutakhir | `components/ui/Peta.tsx` |
| REQ-F-KNT-012 kalender agenda | Kalender bulanan berupa tabel — bukan kisi div — agar pembaca layar mengumumkan tanggal beserta harinya; tersedia bersama tampilan daftar | `components/ui/KalenderAgenda.tsx`, `Api/Publik/KontenController::kalender` |
| REQ-API-006, REQ-NF-MNT-007 kode galat dan korelasi | Setiap respons galat memuat kode stabil, pesan ringkas, dan pengenal korelasi yang sama dengan tajuk `X-Request-Id` dan baris log | `Http/Middleware/PengenalKorelasi`, `Exceptions/KodeGalat`, kanal log `terstruktur`, `GalatApiTest` (7 uji) |
| REQ-UI-004 remah roti | Disusun dari alamat halaman sehingga tidak perlu didaftarkan ulang tiap kali rute bertambah | `components/layout/RemahRoti.tsx` |
| REQ-F-SRC-002 penyorotan kata kunci | Ditandai dengan elemen `<mark>`, bukan sekadar warna | `components/ui/Sorot.tsx` |
| REQ-HW-002 pengambilan lewat kamera | Isian kedua ber-`capture`, sehingga memilih dari galeri tetap mungkin | `components/ui/Isian.tsx`, `pages/warga/AjukanSurat.tsx` |
| REQ-F-BRD-007 sambutan kepala desa | Penyambut diambil dari puncak bagan pemerintah desa, bukan pencocokan teks jabatan | `Api/Publik/BerandaController::sambutan`, `BaganOrganisasiTest` |
| REQ-F-SRC-004, REQ-F-SRC-005 metadata halaman | Kerangka aplikasi disajikan Laravel dengan judul, deskripsi, kanonik, Open Graph, dan data terstruktur terisi; sisi klien memperbaruinya saat berpindah halaman | `Services/MetadataHalaman`, `Http/Controllers/KerangkaAplikasiController`, `lib/meta.ts`, `MetadataHalamanTest` (9 uji) |
| REQ-NF-MNT-004 migrasi bernomor | 26 migrasi idempoten | `php artisan migrate:fresh --seed` |
| REQ-NF-CMP-003, REQ-F-USR-013 hak subjek data | Akses, koreksi, unduhan salinan, dan pengajuan penghapusan oleh pemilik akun | `AuthController::perbaruiProfil`, `Services/DataPribadiService`, `HakSubjekDataTest` (10 uji) |
| REQ-NF-CMP-004 retensi | Lampiran 12 bulan, draf 7 hari, jejak audit 24 bulan, akun tidak aktif ditandai setelah 36 bulan | `Services/RetensiService`, `sidesa:bersihkan-audit-log`, `sidesa:tinjau-akun-tidak-aktif`, `HakSubjekDataTest::test_jejak_audit_melewati_24_bulan_dihapus` |
| REQ-NF-REL-003, 004 RTO dan RPO | Cadangan harian dan mingguan, prosedur pemulihan berurut | `docs/OPERASIONAL.md` bagian 3, `OperasionalTest::test_perintah_pencadangan_...` |
| REQ-UI-007 formulir bertahap | Formulir 10–14 kolom dipecah menjadi langkah berisi paling banyak lima kolom, masing-masing diperiksa sebelum lanjut; galat dari server memindahkan pengisi ke langkah yang bermasalah | `pages/warga/AjukanSurat.tsx`, `components/ui/Langkah.tsx`, `lib/validasiFormulir.ts` |
| REQ-UI-009 aksesibilitas WCAG 2.1 AA | Tautan lewati navigasi, label terkait, fokus terlihat, target 44 px, padanan tabel pada grafik | `components/ui/*`, `components/chart/GrafikBatang.tsx` |
| REQ-UI-011 ukuran teks dan kontras | Pengaturan tersimpan per perangkat, diterapkan lewat atribut pada elemen akar | `lib/preferensi.ts`, `components/ui/PengaturanTampilan.tsx`, `index.css` |
| REQ-UI-012 dwibahasa | Halaman profil dan wisata tersedia dalam bahasa Indonesia dan Inggris | `lib/bahasa.ts`, `components/layout/PenyediaBahasa.tsx` |
| REQ-API-004 dokumentasi OpenAPI | Dibangkitkan dari tabel rute aplikasi, bukan ditulis terpisah; uji menggagalkan rakitan begitu ada rute yang tidak terwakili, sehingga dokumentasi tidak dapat tertinggal dari kodenya | `Services/DokumentasiOpenApi`, `Api/Publik/DokumentasiController`, `pages/publik/DokumentasiApi.tsx`, `DokumentasiApiTest` (6 uji) |
| REQ-API-005 API data terbuka | Titik akhir hanya-baca berlisensi terbuka dengan kuota per alamat IP | `Api/Publik/DataTerbukaController`, `Fase4Test::test_api_data_terbuka_...` |
| REQ-F-ADM-003 pengelola menu | Penyarangan dibatasi dua tingkat dan butir tingkat pertama dibatasi tujuh, ditegakkan di server; alamat tautan disaring dengan daftar putih skema agar `javascript:` tidak pernah menjadi atribut href | `Api/Admin/MenuController`, `components/layout/MenuUtama.tsx`, `MenuNavigasiTest` (10 uji) |
| REQ-F-GAL-006 penyematan video | Hanya pengenal video pada penyedia yang dikenali yang disimpan; alamat sematan dibentuk ulang, memakai ranah tanpa kuki, dan bingkainya baru dipasang setelah warga menekan putar — membuka galeri tidak menghubungi penyedia sama sekali | `Services/PenyematVideo`, `components/ui/SematVideo.tsx`, `VideoAlbumTest` (9 uji) |
| CON-02 koneksi tidak stabil | Aplikasi web progresif: kerangka aplikasi dan data publik tersinggah, halaman luring menjelaskan keadaan | `vite.config.ts` (VitePWA), `public/luring.html` |

## 4. Status pemenuhan kebutuhan

Hasil audit menyeluruh terhadap 218 kebutuhan pada SRS, diverifikasi dengan
menelusuri kode dan bukan sekadar mencocokkan anotasi.

| Status | Jumlah | Rincian prioritas |
|---|---:|---|
| Terimplementasi | 189 | M 125 · S 52 · C 12 |
| Terimplementasi sebagian | 4 | M 2 · S 2 |
| Belum diimplementasikan | 0 | — |
| Perlu pengukuran atau penyiapan server | 22 | M 21 · S 1 |
| Tidak berlaku pada arsitektur ini | 3 | M 2 · W 1 |

**Seluruh kebutuhan Must yang dapat dikerjakan sudah dikerjakan.** Dua butir
Must yang tersisa berstatus sebagian — REQ-F-SRT-017 dan REQ-SW-004 — dan
keduanya tertahan pada terbitnya sertifikat elektronik (OI-02), bukan pada kode:
integrasi penyedianya sudah lengkap dan teruji, tinggal menunggu kredensial
sungguhan. Sisanya menunggu pengukuran pada lingkungan setara produksi.

### 4.1 Terimplementasi sebagian

| Kebutuhan | Prioritas | Keadaan saat ini |
|---|:---:|---|
| REQ-F-ADM-010 | S | Halaman populer dan jumlah pembukaan laman sudah ada; jumlah pengunjung unik sengaja tidak dihitung karena memerlukan pengenalan pengunjung berulang, yang bertentangan dengan REQ-SW-006. |
| REQ-F-SRT-017 | M | Jalur spesimen berjalan; penyedia tersertifikasi siap tetapi menunggu sertifikat (OI-02). |
| REQ-NF-SEC-008 | S | Validasi tipe asli, penolakan berkas berisi skrip, dan pemindaian antivirus sudah ada. Pemindai berjalan bila `PEMINDAI_BERKAS=clamav` diarahkan ke daemon ClamAV; tanpa daemon, pemindaian dilewati dan sisa lapisan tetap berlaku. Butir ini menjadi utuh setelah daemon itu terpasang pada server desa. |
| REQ-SW-004 | M | Integrasi penyedia TTE lengkap dan teruji, menunggu kredensial sungguhan. |

### 4.2 Belum diimplementasikan

Tidak ada. Seluruh kebutuhan yang dapat diwujudkan dengan menulis kode sudah
dikerjakan; yang tersisa menunggu sertifikat elektronik (OI-02), pemasangan
daemon pemindai pada server desa, atau pengukuran pada lingkungan setara
produksi.

### 4.3 Perlu pengukuran, pengujian, atau penyiapan server

Kebutuhan berikut bukan sesuatu yang diwujudkan dengan menulis kode. Pemenuhannya
dibuktikan melalui pengukuran, uji penerimaan, atau konfigurasi saat sistem
dipasang, sebagaimana diatur pada Bab 10 dan Lampiran C SRS:

`REQ-API-001`, `REQ-NF-CMP-006`, `REQ-NF-CMP-007`, `REQ-NF-CMP-008`, `REQ-NF-MNT-005`, `REQ-NF-PRF-001`, `REQ-NF-PRF-002`, `REQ-NF-PRF-004`, `REQ-NF-PRF-005`, `REQ-NF-PRF-006`, `REQ-NF-PRF-007`, `REQ-NF-REL-001`, `REQ-NF-REL-002`, `REQ-NF-REL-003`, `REQ-NF-REL-004`, `REQ-NF-REL-007`, `REQ-NF-SEC-001`, `REQ-NF-SEC-011`, `REQ-NF-USA-001`, `REQ-NF-USA-002`, `REQ-NF-USA-003`, `REQ-NF-USA-004`.

Yang sudah terukur sejauh ini: ukuran berkas awal 115 kB terkompresi
(REQ-NF-PRF-003) dan cakupan uji otomatis 207 uji dengan 893 asersi pada logika
bisnis inti (REQ-NF-MNT-001), dijalankan pada SQLite maupun PostgreSQL. Sisanya menunggu lingkungan setara produksi,
uji beban, uji penetrasi, dan uji penerimaan bersama perangkat desa.

### 4.4 Tidak berlaku pada arsitektur ini

| Kebutuhan | Alasan |
|---|---|
| REQ-HW-003 | Dinyatakan di luar lingkup oleh SRS sendiri (prioritas W). |
| REQ-NF-SEC-004 | API memakai token tanpa sesi, sehingga tidak ada operasi tulis berbasis sesi. |
| REQ-NF-SEC-015 | Tidak ada kuki sesi; token disimpan klien dan dikirim lewat tajuk Authorization. |

### 4.5 Catatan koreksi

Versi sebelumnya dokumen ini hanya mencantumkan tujuh butir sebagai belum
dikerjakan. Audit ulang menemukan daftar itu tidak lengkap: sejumlah kebutuhan
tertutup oleh anotasi rentang seperti `REQ-F-USR-001..015` sehingga tampak
terpenuhi padahal butir tertentu di dalamnya belum ada. Tabel di atas menggantikan
daftar lama dan disusun dari penelusuran bukti per butir.

Pemutakhiran berikutnya menutup seluruh butir Must yang terbuka: CAPTCHA
(REQ-SW-007, REQ-F-ADU-010), Syarat Penggunaan (REQ-NF-CMP-001), hak subjek data
(REQ-F-USR-013), retensi jejak audit dan peninjauan akun tidak aktif
(REQ-NF-CMP-004), deteksi serta prosedur insiden kebocoran (REQ-NF-CMP-005),
pipeline integrasi berkelanjutan (REQ-NF-MNT-002), pemindaian kerentanan
dependensi (REQ-NF-SEC-012), analitik yang menghormati privasi (REQ-SW-006), dan
panduan administrator serta warga yang terpisah (REQ-NF-MNT-006).
