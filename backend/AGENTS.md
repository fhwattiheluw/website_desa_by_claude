# Panduan Kerja — Backend SIDESA

API Laravel 13 untuk Sistem Informasi Desa Terpadu. Kebutuhan mengikat ada pada
`../docs/SRS-Website-Desa.md`; pemetaan ke kode ada pada `../docs/KETERTELUSURAN.md`.

## Konvensi

- **Bahasa.** Nama domain, komentar, dan pesan ke pengguna ditulis dalam bahasa
  Indonesia. Nama kerangka kerja (controller, middleware, request) tetap Inggris.
- **Aturan bisnis** tinggal di `app/Services`, bukan di controller. Controller
  hanya memvalidasi masukan, memanggil layanan, lalu membentuk respons.
- **Setiap aksi tulis** dicatat melalui `App\Services\AuditLogger`.
- **Otorisasi** selalu ditegakkan di sisi server lewat middleware `izin:<kode>`.
  Menyembunyikan menu di antarmuka tidak pernah dianggap memadai.
- **Data pribadi** (NIK, kontak pelapor) disimpan terenkripsi; pencarian memakai
  kolom hash terpisah. Jangan menambah kolom data pribadi tanpa pola yang sama.
- **Respons daftar berhalaman** memakai bentuk seragam: kunci `data` beserta
  `current_page`, `last_page`, `per_page`, `total` di tingkat atas. Resource baru
  yang dipakai untuk daftar wajib memakai trait `KoleksiSeragam`.

## Sebelum menyerahkan perubahan

```bash
./vendor/bin/pint            # gaya kode
./vendor/bin/phpunit         # seluruh uji harus lulus
```

Perubahan pada alur permohonan, aturan bisnis, atau hak akses wajib disertai uji
yang membuktikannya. Bila menambah kebutuhan baru, perbarui pula matriks
ketertelusuran.
