# Matriks Ketertelusuran SRS → Implementasi

Dokumen ini memetakan kebutuhan pada [SRS-WDESA-001](SRS-Website-Desa.md) ke
berkas yang mewujudkannya dan pengujian yang membuktikannya. Dipakai saat
review, audit, dan uji penerimaan (Bab 9 dan 10 SRS).

## 1. Modul fungsional

| Modul | Kebutuhan | Implementasi backend | Implementasi frontend | Pengujian |
|---|---|---|---|---|
| MOD-BRD Beranda & Profil | REQ-F-BRD-001..008 | `Api/Publik/BerandaController`, `ProfilController`, `LembagaController`, model `Pengaturan` | `pages/publik/Beranda.tsx`, `Profil.tsx` | `PortalPublikTest::test_beranda_menyajikan_profil_dan_sorotan` |
| MOD-KNT Konten | REQ-F-KNT-001..014 | `Api/Admin/KontenController`, `Api/Publik/KontenController`, model `Konten`, `KontenVersi` | `pages/admin/KelolaKonten.tsx`, `pages/publik/DaftarKonten.tsx`, `DetailKonten.tsx` | `PortalPublikTest` (tayang, kedaluwarsa, penghitung dibaca), `OtorisasiTest` (maker-checker) |
| MOD-GAL Galeri & Media | REQ-F-GAL-001..008 | `Services/MediaService`, `Api/Admin/MediaController` | `pages/publik/Halaman.tsx` (Galeri) | Uji unggahan pada `PermohonanSuratTest` (lampiran privat) |
| MOD-APB Transparansi | REQ-F-APB-001..009 | `Api/Publik/ApbdesController`, `Api/Admin/ApbdesController` | `pages/publik/Apbdes.tsx`, `pages/admin/KelolaApbdes.tsx` | `PortalPublikTest::test_apbdes_belum_dipublikasikan_tidak_dapat_diakses` |
| MOD-STA Statistik | REQ-F-STA-001..006 | `Api/Publik/StatistikController`, `Api/Admin/StatistikController` | `pages/publik/Statistik.tsx` | `PortalPublikTest::test_statistik_menyamarkan_kelompok_sangat_kecil` |
| MOD-SRT Layanan Surat | REQ-F-SRT-001..027 | `Services/PermohonanService`, `SuratService`, `NomorService`, `KalenderKerja`, `Api/Warga/PermohonanController`, `Api/Admin/PermohonanController` | `pages/warga/AjukanSurat.tsx`, `DetailPermohonan.tsx`, `pages/admin/AntreanPermohonan.tsx`, `DetailPermohonanAdmin.tsx` | `PermohonanSuratTest` (12 uji), `NomorServiceTest`, `KalenderKerjaTest` |
| MOD-ADU Pengaduan | REQ-F-ADU-001..011 | `Services/PengaduanService`, `Api/Publik/PengaduanController`, `Api/Admin/PengaduanController` | `pages/publik/Pengaduan.tsx`, `LacakPengaduan.tsx`, `pages/admin/KelolaPengaduan.tsx` | `PengaduanTest` (8 uji) |
| MOD-PID PPID & Produk Hukum | REQ-F-PID-001..007 | `Api/Publik/PustakaController`, `Api/Admin/ReferensiController` | `pages/publik/Pustaka.tsx`, `PermohonanInformasi.tsx` | `PortalPublikTest::test_bentuk_respons_berhalaman_seragam` |
| MOD-POT Potensi Desa | REQ-F-POT-001..007 | `Api/Publik/PotensiController`, `Api/Admin/ReferensiController` | `pages/publik/Potensi.tsx` | Verifikasi UMKM pada `ReferensiController` |
| MOD-LMB Lembaga | REQ-F-LMB-001..004 | `Api/Publik/LembagaController`, model `Lembaga`, `Pengurus` | `pages/publik/Profil.tsx` | Data pribadi aparatur tidak dipaparkan (lihat resource) |
| MOD-USR Pengguna & Akses | REQ-F-USR-001..015 | `Api/AuthController`, `Http/Middleware/PastikanIzin`, `Api/Admin/PenggunaController` | `lib/auth.tsx`, `pages/auth/*`, `components/layout/Terlindungi.tsx` | `AutentikasiTest` (10 uji), `OtorisasiTest` (8 uji) |
| MOD-ADM Administrasi | REQ-F-ADM-001..010 | `Api/Admin/DashboardController`, `PengaturanController`, `AuditLogController`, `Services/AuditLogger` | `pages/admin/Dasbor.tsx`, `Pengaturan.tsx`, `AuditLog.tsx` | `OtorisasiTest::test_audit_log_bersifat_hanya_baca` |
| MOD-NOT Notifikasi | REQ-F-NOT-001..006 | `Services/NotifikasiService`, model `NotifikasiLog`, perintah `sidesa:ulangi-notifikasi` | Pemberitahuan status pada halaman permohonan | Terpakai pada alur `PermohonanSuratTest` |
| MOD-SRC Pencarian & SEO | REQ-F-SRC-001..007 | `Api/Publik/PencarianController` | `pages/publik/Halaman.tsx` (Pencarian), `TidakDitemukan` | `PortalPublikTest::test_pencarian_global_menjangkau_berita_dan_layanan` |

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
| REQ-NF-SEC-013 log peristiwa keamanan | `AuditLogger` pada masuk, gagal masuk, ubah peran | `OtorisasiTest` |
| REQ-NF-PRF-003 ukuran transfer | Pemuatan malas rute berat, berkas awal 109 kB gzip | Keluaran `npm run build` |
| REQ-NF-MNT-001 cakupan uji | 58 uji, 274 asersi pada logika inti | `./vendor/bin/phpunit` |
| REQ-NF-MNT-004 migrasi bernomor | 15 migrasi idempoten | `php artisan migrate:fresh --seed` |
| REQ-NF-CMP-003 hak subjek data | Profil dapat diakses dan diperbarui pemilik akun | `AuthController::perbaruiProfil` |
| REQ-NF-CMP-004 retensi | `sidesa:bersihkan-lampiran`, audit log 24 bulan | `PermohonanService::bersihkanLampiranKedaluwarsa` |
| REQ-UI-009 aksesibilitas WCAG 2.1 AA | Tautan lewati navigasi, label terkait, fokus terlihat, target 44 px, padanan tabel pada grafik | `components/ui/*`, `components/chart/GrafikBatang.tsx` |

## 4. Kebutuhan yang belum diimplementasikan

| Kebutuhan | Prioritas | Alasan |
|---|---|---|
| REQ-F-SRT-017 (TTE tersertifikasi) | M | Menunggu keputusan OI-02 dan penerbitan sertifikat PSrE; saat ini memakai penandatanganan dalam sistem disertai QR verifikasi |
| REQ-F-SRT-023 pencetakan massal | C | Dijadwalkan setelah volume layanan stabil |
| REQ-F-KNT-013 komentar artikel | C | Memerlukan kebijakan moderasi tambahan |
| REQ-F-SRC-005, 006 data terstruktur dan RSS | S, C | Direncanakan bersama optimasi SEO Fase 4 |
| REQ-UI-011, 012 pengaturan ukuran teks dan dwibahasa | S, C | Fase 4 |
| REQ-F-ADM-006, 007 pencadangan otomatis | M | Bergantung pada penyedia hosting yang dipilih (OI-06); prosedur didokumentasikan saat penyiapan server |
| REQ-SW-004 integrasi penyedia TTE | M | Sama dengan REQ-F-SRT-017 |
