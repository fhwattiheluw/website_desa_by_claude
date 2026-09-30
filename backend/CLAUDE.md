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
- **Formulir publik** memperoleh pelindungnya dari grup rute
  (`throttle:formulir-publik` dan `captcha`), bukan dari masing-masing
  pengendali. Tambahkan rute formulir publik baru ke grup itu.
- **Pencarian teks** memakai makro `->cariTeks($kolom, $kata)`, bukan
  `->where($kolom, 'like', ...)`. `LIKE` mengabaikan besar kecil huruf di SQLite
  dan MySQL tetapi tidak di PostgreSQL, sehingga pencarian gagal diam-diam saat
  mesin basis data berganti.
- **Respons daftar berhalaman** memakai bentuk seragam: kunci `data` beserta
  `current_page`, `last_page`, `per_page`, `total` di tingkat atas. Resource baru
  yang dipakai untuk daftar wajib memakai trait `KoleksiSeragam`.

## Sebelum menyerahkan perubahan

```bash
./vendor/bin/pint            # gaya kode
./vendor/bin/phpunit         # seluruh uji harus lulus (SQLite)

# Uji juga pada PostgreSQL bila menyentuh kueri, agregasi, atau pencarian:
DB_CONNECTION=pgsql DB_URL= DB_HOST=127.0.0.1 DB_DATABASE=sidesa_uji \
  DB_USERNAME=sidesa DB_PASSWORD=sidesa ./vendor/bin/phpunit
```

Perubahan pada alur permohonan, aturan bisnis, atau hak akses wajib disertai uji
yang membuktikannya. Bila menambah kebutuhan baru, perbarui pula matriks
ketertelusuran.
