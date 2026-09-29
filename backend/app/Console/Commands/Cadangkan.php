<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Pencadangan basis data dan berkas media (REQ-F-ADM-006).
 *
 * Basis data dicadangkan harian, berkas media mingguan, dan cadangan yang
 * lebih tua dari masa retensi dihapus otomatis. Prosedur pemulihannya
 * didokumentasikan pada docs/OPERASIONAL.md (REQ-F-ADM-007).
 */
class Cadangkan extends Command
{
    protected $signature = 'sidesa:cadangkan
        {--jenis=semua : Jenis cadangan yang dibuat: basis-data, media, atau semua}
        {--retensi=30 : Jumlah hari cadangan disimpan sebelum dihapus}';

    protected $description = 'Membuat cadangan basis data dan berkas media desa';

    public function handle(AuditLogger $audit): int
    {
        $jenis = (string) $this->option('jenis');
        $retensi = max((int) $this->option('retensi'), 1);
        $direktori = storage_path('app/private/cadangan');

        File::ensureDirectoryExists($direktori);

        $hasil = [];

        try {
            if (in_array($jenis, ['semua', 'basis-data'], true)) {
                $hasil['basis_data'] = $this->cadangkanBasisData($direktori);
            }

            if (in_array($jenis, ['semua', 'media'], true)) {
                $hasil['media'] = $this->cadangkanMedia($direktori);
            }
        } catch (\Throwable $galat) {
            $this->error('Pencadangan gagal: '.$galat->getMessage());
            $audit->catat('cadangan_gagal', 'Sistem', null, null, ['galat' => $galat->getMessage()]);

            return self::FAILURE;
        }

        $dihapus = $this->bersihkanKedaluwarsa($direktori, $retensi);

        foreach ($hasil as $nama => $berkas) {
            $this->info(sprintf(
                'Cadangan %s: %s (%s)',
                str_replace('_', ' ', $nama),
                basename($berkas),
                $this->ukuran(filesize($berkas) ?: 0),
            ));
        }

        if ($dihapus > 0) {
            $this->line("Cadangan melewati retensi {$retensi} hari dihapus: {$dihapus} berkas.");
        }

        $audit->catat('cadangan', 'Sistem', null, null, [
            'jenis' => $jenis,
            'berkas' => array_map('basename', $hasil),
            'dihapus' => $dihapus,
        ]);

        return self::SUCCESS;
    }

    private function cadangkanBasisData(string $direktori): string
    {
        $koneksi = config('database.default');
        $konfigurasi = config("database.connections.{$koneksi}");
        $cap = now()->format('Ymd-His');

        return match ($koneksi) {
            'sqlite' => tap("{$direktori}/basis-data-{$cap}.sqlite", function (string $tujuan) use ($konfigurasi) {
                $sumber = $konfigurasi['database'];

                if (! is_file($sumber)) {
                    throw new \RuntimeException("Berkas basis data tidak ditemukan: {$sumber}");
                }

                File::copy($sumber, $tujuan);
            }),
            'mysql', 'mariadb' => $this->jalankanDump(
                [
                    'mysqldump',
                    '--host='.$konfigurasi['host'],
                    '--port='.$konfigurasi['port'],
                    '--user='.$konfigurasi['username'],
                    '--single-transaction',
                    '--quick',
                    $konfigurasi['database'],
                ],
                "{$direktori}/basis-data-{$cap}.sql",
                ['MYSQL_PWD' => (string) $konfigurasi['password']],
            ),
            'pgsql' => $this->jalankanDump(
                [
                    'pg_dump',
                    '--host='.$konfigurasi['host'],
                    '--port='.$konfigurasi['port'],
                    '--username='.$konfigurasi['username'],
                    '--no-password',
                    $konfigurasi['database'],
                ],
                "{$direktori}/basis-data-{$cap}.sql",
                ['PGPASSWORD' => (string) $konfigurasi['password']],
            ),
            default => throw new \RuntimeException("Pencadangan untuk koneksi {$koneksi} belum didukung."),
        };
    }

    /** @param array<int, string> $perintah */
    private function jalankanDump(array $perintah, string $tujuan, array $lingkungan): string
    {
        $proses = new Process($perintah, env: $lingkungan, timeout: 900);
        $proses->run();

        if (! $proses->isSuccessful()) {
            throw new \RuntimeException(trim($proses->getErrorOutput()) ?: 'Perintah dump gagal dijalankan.');
        }

        File::put($tujuan, $proses->getOutput());

        return $tujuan;
    }

    private function cadangkanMedia(string $direktori): string
    {
        $tujuan = "{$direktori}/media-".now()->format('Ymd-His').'.zip';
        $arsip = new ZipArchive;

        if ($arsip->open($tujuan, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Arsip media tidak dapat dibuat.');
        }

        foreach (['app/public', 'app/private'] as $bagian) {
            $akar = storage_path($bagian);

            if (! is_dir($akar)) {
                continue;
            }

            foreach (File::allFiles($akar) as $berkas) {
                // Direktori cadangan sendiri tidak ikut diarsipkan agar tidak beranak-pinak.
                if (str_contains($berkas->getPathname(), DIRECTORY_SEPARATOR.'cadangan'.DIRECTORY_SEPARATOR)) {
                    continue;
                }

                $arsip->addFile($berkas->getPathname(), $bagian.'/'.$berkas->getRelativePathname());
            }
        }

        $arsip->close();

        return $tujuan;
    }

    private function bersihkanKedaluwarsa(string $direktori, int $retensiHari): int
    {
        $batas = now()->subDays($retensiHari)->getTimestamp();
        $dihapus = 0;

        foreach (File::files($direktori) as $berkas) {
            if ($berkas->getMTime() < $batas) {
                File::delete($berkas->getPathname());
                $dihapus++;
            }
        }

        return $dihapus;
    }

    private function ukuran(int $bita): string
    {
        if ($bita < 1024 * 1024) {
            return round($bita / 1024).' KB';
        }

        return round($bita / 1024 / 1024, 1).' MB';
    }
}
