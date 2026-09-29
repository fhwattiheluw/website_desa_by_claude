<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $layanan->nama }} — {{ $surat->nomor_surat }}</title>
    <style>
        @page { margin: 2cm 2.2cm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11pt; color: #111; line-height: 1.55; }
        .kop { border-bottom: 3px double #111; padding-bottom: 8px; margin-bottom: 18px; text-align: center; }
        .kop .pemerintah { font-size: 12pt; margin: 0; letter-spacing: .5px; }
        .kop .desa { font-size: 15pt; font-weight: bold; margin: 2px 0; text-transform: uppercase; }
        .kop .alamat { font-size: 9pt; margin: 2px 0 0; }
        .judul { text-align: center; margin: 22px 0 4px; }
        .judul h1 { font-size: 12.5pt; text-transform: uppercase; text-decoration: underline; margin: 0 0 4px; }
        .judul .nomor { font-size: 10.5pt; margin: 0; }
        table.data { width: 100%; margin: 14px 0 14px 18px; border-collapse: collapse; }
        table.data td { vertical-align: top; padding: 2px 0; font-size: 10.5pt; }
        table.data td.label { width: 34%; }
        table.data td.pemisah { width: 3%; }
        .ttd { width: 100%; margin-top: 26px; }
        .ttd td { vertical-align: top; font-size: 10.5pt; }
        .ttd .kanan { width: 45%; text-align: center; }
        .ttd .nama { font-weight: bold; text-decoration: underline; margin-top: 62px; }
        .qr { width: 92px; }
        .verifikasi { font-size: 7.5pt; color: #444; line-height: 1.3; }
        .catatan-kaki { margin-top: 26px; border-top: 1px solid #ccc; padding-top: 6px; font-size: 7.5pt; color: #555; }
    </style>
</head>
<body>
    <div class="kop">
        <p class="pemerintah">PEMERINTAH KABUPATEN {{ strtoupper($desa['kabupaten'] ?? '-') }}</p>
        <p class="pemerintah">KECAMATAN {{ strtoupper($desa['kecamatan'] ?? '-') }}</p>
        <p class="desa">Desa {{ $desa['nama_desa'] ?? '-' }}</p>
        <p class="alamat">{{ $desa['alamat'] ?? '' }} · Telp. {{ $desa['telepon'] ?? '-' }} · {{ $desa['email'] ?? '' }}</p>
    </div>

    <div class="judul">
        <h1>{{ $layanan->nama }}</h1>
        <p class="nomor">Nomor: {{ $surat->nomor_surat }}</p>
    </div>

    <p>Yang bertanda tangan di bawah ini Kepala Desa {{ $desa['nama_desa'] ?? '-' }},
       Kecamatan {{ $desa['kecamatan'] ?? '-' }}, Kabupaten {{ $desa['kabupaten'] ?? '-' }},
       dengan ini menerangkan bahwa:</p>

    <table class="data">
        @foreach ($layanan->kolom_formulir as $kolom)
            @continue(($kolom['tipe'] ?? 'teks') === 'centang' || ($kolom['di_surat'] ?? true) === false)
            <tr>
                <td class="label">{{ $kolom['label'] }}</td>
                <td class="pemisah">:</td>
                <td>{{ $data[$kolom['nama']] ?? '-' }}</td>
            </tr>
        @endforeach
    </table>

    <p>Orang tersebut di atas adalah benar warga Desa {{ $desa['nama_desa'] ?? '-' }}.
       Surat keterangan ini dibuat untuk keperluan
       <strong>{{ $data['keperluan'] ?? $data['keperluan_surat'] ?? 'sebagaimana mestinya' }}</strong>.</p>

    <p>Demikian surat keterangan ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.</p>

    <table class="ttd">
        <tr>
            <td>
                <img class="qr" src="{{ $qr }}" alt="Kode QR verifikasi surat">
                <p class="verifikasi">
                    Pindai untuk memverifikasi keaslian surat.<br>
                    Kode verifikasi: <strong>{{ $surat->kode_verifikasi }}</strong>
                </p>
            </td>
            <td class="kanan">
                {{ $desa['nama_desa'] ?? '-' }}, {{ $surat->tanggal_terbit->translatedFormat('d F Y') }}<br>
                Kepala Desa {{ $desa['nama_desa'] ?? '-' }}
                <div class="nama">{{ $penandatangan->name }}</div>
            </td>
        </tr>
    </table>

    <div class="catatan-kaki">
        Dokumen ini diterbitkan melalui sistem informasi desa dan sah tanpa memerlukan cap basah.
        Keaslian dokumen dapat diperiksa pada laman verifikasi surat dengan kode di atas.
        Nomor tiket permohonan: {{ $permohonan->nomor_tiket }}.
    </div>
</body>
</html>
