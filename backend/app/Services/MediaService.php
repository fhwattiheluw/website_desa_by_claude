<?php

namespace App\Services;

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Pengelolaan unggahan berkas (REQ-F-GAL-002..004, 008; REQ-NF-SEC-007, 008).
 */
class MediaService
{
    public const MAKS_GAMBAR = 5 * 1024 * 1024;    // 5 MB

    public const MAKS_DOKUMEN = 10 * 1024 * 1024;  // 10 MB

    /** Varian lebar gambar yang dibuat otomatis (REQ-F-GAL-003). */
    public const VARIAN = ['kecil' => 320, 'sedang' => 768, 'besar' => 1600];

    public const MIME_GAMBAR = ['image/jpeg', 'image/png', 'image/webp'];

    public const MIME_DOKUMEN = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    public function simpan(
        UploadedFile $berkas,
        string $koleksi = 'umum',
        ?User $pengunggah = null,
        bool $privat = false,
        ?string $alt = null,
    ): Media {
        $mime = $this->mimeAsli($berkas);
        $gambar = in_array($mime, self::MIME_GAMBAR, true);

        $this->pastikanAman($berkas, $mime, $gambar);

        $disk = $privat ? 'local' : 'public';
        $nama = Str::uuid()->toString().'.'.$this->ekstensi($mime);
        $path = trim($koleksi, '/')."/{$nama}";

        // Gambar diproses ulang oleh GD: metadata EXIF (termasuk lokasi) ikut
        // terbuang dan berkas tersimpan bersih (REQ-F-GAL-008).
        $isi = $gambar
            ? $this->prosesGambar($berkas, $mime, max(self::VARIAN))
            : file_get_contents($berkas->getRealPath());

        Storage::disk($disk)->put($path, $isi);

        if ($gambar && ! $privat) {
            $this->buatVarian($berkas, $mime, $disk, $koleksi, $nama);
        }

        $media = Media::create([
            'uploader_id' => $pengunggah?->id,
            'nama_asli' => substr($berkas->getClientOriginalName(), 0, 255),
            'path' => $path,
            'disk' => $disk,
            'mime' => $mime,
            'ukuran' => strlen($isi),
            'alt' => $alt,
            'koleksi' => $koleksi,
            'privat' => $privat,
        ]);

        $this->audit->catat('upload', 'Media', $media->id, null, [
            'nama' => $media->nama_asli,
            'mime' => $mime,
            'koleksi' => $koleksi,
        ]);

        return $media;
    }

    public function hapus(Media $media): void
    {
        Storage::disk($media->disk)->delete($media->path);

        foreach (array_keys(self::VARIAN) as $varian) {
            Storage::disk($media->disk)->delete($this->pathVarian($media->path, $varian));
        }

        $this->audit->catat('delete', 'Media', $media->id, $media->attributesToArray(), null);
        $media->delete();
    }

    public function pathVarian(string $path, string $varian): string
    {
        $info = pathinfo($path);

        return ($info['dirname'] === '.' ? '' : $info['dirname'].'/')."{$info['filename']}-{$varian}.{$info['extension']}";
    }

    /**
     * Tipe berkas ditentukan dari isi berkas (magic number), bukan dari
     * ekstensi yang mudah dipalsukan (REQ-F-GAL-004).
     */
    private function mimeAsli(UploadedFile $berkas): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        return (string) $finfo->file($berkas->getRealPath());
    }

    private function pastikanAman(UploadedFile $berkas, string $mime, bool $gambar): void
    {
        $diizinkan = array_merge(self::MIME_GAMBAR, self::MIME_DOKUMEN);

        if (! in_array($mime, $diizinkan, true)) {
            throw ValidationException::withMessages([
                'berkas' => "Tipe berkas {$mime} tidak diizinkan. Gunakan JPG, PNG, WEBP, PDF, DOCX, atau XLSX.",
            ]);
        }

        $maks = $gambar ? self::MAKS_GAMBAR : self::MAKS_DOKUMEN;

        if ($berkas->getSize() > $maks) {
            throw ValidationException::withMessages([
                'berkas' => 'Ukuran berkas melebihi batas '.($maks / 1024 / 1024).' MB.',
            ]);
        }

        // Tolak berkas yang menyamar sebagai gambar namun memuat skrip.
        $cuplikan = (string) file_get_contents($berkas->getRealPath(), false, null, 0, 1024);

        if (preg_match('/<\?php|<script\b/i', $cuplikan)) {
            throw ValidationException::withMessages([
                'berkas' => 'Berkas ditolak karena memuat konten yang dapat dieksekusi.',
            ]);
        }
    }

    private function ekstensi(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            default => 'xlsx',
        };
    }

    private function buatVarian(UploadedFile $berkas, string $mime, string $disk, string $koleksi, string $nama): void
    {
        foreach (self::VARIAN as $label => $lebar) {
            $isi = $this->prosesGambar($berkas, $mime, $lebar);
            Storage::disk($disk)->put($this->pathVarian(trim($koleksi, '/')."/{$nama}", $label), $isi);
        }
    }

    private function prosesGambar(UploadedFile $berkas, string $mime, int $lebarMaks): string
    {
        $sumber = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($berkas->getRealPath()),
            'image/png' => @imagecreatefrompng($berkas->getRealPath()),
            'image/webp' => @imagecreatefromwebp($berkas->getRealPath()),
            default => false,
        };

        if ($sumber === false) {
            return (string) file_get_contents($berkas->getRealPath());
        }

        $lebarAsli = imagesx($sumber);
        $tinggiAsli = imagesy($sumber);
        $lebar = min($lebarMaks, $lebarAsli);
        $tinggi = (int) round($tinggiAsli * ($lebar / $lebarAsli));

        $tujuan = imagecreatetruecolor($lebar, $tinggi);

        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($tujuan, false);
            imagesavealpha($tujuan, true);
        }

        imagecopyresampled($tujuan, $sumber, 0, 0, 0, 0, $lebar, $tinggi, $lebarAsli, $tinggiAsli);

        ob_start();
        match ($mime) {
            'image/png' => imagepng($tujuan, null, 8),
            'image/webp' => imagewebp($tujuan, null, 82),
            default => imagejpeg($tujuan, null, 82),
        };
        $isi = (string) ob_get_clean();

        imagedestroy($sumber);
        imagedestroy($tujuan);

        return $isi;
    }
}
