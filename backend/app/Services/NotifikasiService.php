<?php

namespace App\Services;

use App\Models\NotifikasiLog;
use App\Models\Permohonan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Pengiriman notifikasi lintas kanal (REQ-F-NOT-001..004).
 *
 * Kegagalan gateway eksternal tidak boleh menggagalkan transaksi inti
 * (REQ-NF-REL-005): setiap kegagalan dicatat dan diantre untuk dicoba ulang.
 */
class NotifikasiService
{
    public const MAKS_PERCOBAAN = 3;

    public function permohonanBerubah(Permohonan $permohonan, string $templat): void
    {
        $permohonan->loadMissing('pemohon', 'jenisLayanan');

        $data = [
            'nama' => $permohonan->pemohon->name,
            'nomor_tiket' => $permohonan->nomor_tiket,
            'layanan' => $permohonan->jenisLayanan->nama,
            'status' => $permohonan->status,
            'alasan' => $permohonan->alasan,
        ];

        // REQ-F-NOT-006: kanal yang dimatikan warga tidak dipakai lagi.
        $pemohon = $permohonan->pemohon;

        if (filled($pemohon->email) && $pemohon->menerimaLewat('email')) {
            $this->kirim('email', $pemohon->email, $templat, $data);
        }

        if (filled($pemohon->telepon) && $pemohon->menerimaLewat('whatsapp')) {
            $this->kirim('whatsapp', $pemohon->telepon, $templat, $data);
        }
    }

    /** @param array<string, mixed> $data */
    public function kirim(string $kanal, string $tujuan, string $templat, array $data = []): NotifikasiLog
    {
        $log = NotifikasiLog::create([
            'kanal' => $kanal,
            'tujuan' => $tujuan,
            'templat' => $templat,
            'data' => $data,
            'status' => 'antre',
        ]);

        try {
            $this->kirimKeGateway($log);

            $log->update(['status' => 'terkirim', 'percobaan' => 1, 'terkirim_pada' => now()]);
        } catch (\Throwable $e) {
            $log->update(['status' => 'gagal', 'percobaan' => 1, 'galat' => $e->getMessage()]);
            Log::warning('Notifikasi gagal dikirim', ['id' => $log->id, 'kanal' => $kanal, 'galat' => $e->getMessage()]);
        }

        return $log;
    }

    /** Percobaan ulang bertingkat untuk notifikasi yang gagal (REQ-F-NOT-003). */
    public function cobaUlangYangGagal(): int
    {
        $berhasil = 0;

        NotifikasiLog::query()
            ->where('status', 'gagal')
            ->where('percobaan', '<', self::MAKS_PERCOBAAN)
            ->limit(100)
            ->get()
            ->each(function (NotifikasiLog $log) use (&$berhasil) {
                try {
                    $this->kirimKeGateway($log);
                    $log->update([
                        'status' => 'terkirim',
                        'percobaan' => $log->percobaan + 1,
                        'terkirim_pada' => now(),
                        'galat' => null,
                    ]);
                    $berhasil++;
                } catch (\Throwable $e) {
                    $log->update(['percobaan' => $log->percobaan + 1, 'galat' => $e->getMessage()]);
                }
            });

        return $berhasil;
    }

    private function kirimKeGateway(NotifikasiLog $log): void
    {
        match ($log->kanal) {
            'email' => $this->kirimEmail($log),
            'whatsapp' => $this->kirimWhatsapp($log),
            default => null,
        };
    }

    private function kirimEmail(NotifikasiLog $log): void
    {
        $isi = $this->susunPesan($log);

        Mail::raw($isi, function ($pesan) use ($log) {
            $pesan->to($log->tujuan)->subject($this->judul($log->templat));
        });
    }

    /**
     * Gateway WhatsApp bersifat opsional (DEP-02). Selama kredensial belum
     * dikonfigurasi, pesan hanya dicatat sehingga alur layanan tetap berjalan.
     */
    private function kirimWhatsapp(NotifikasiLog $log): void
    {
        $token = config('services.whatsapp.token');

        if (blank($token)) {
            Log::info('Gateway WhatsApp belum dikonfigurasi, notifikasi dilewati', ['id' => $log->id]);

            return;
        }

        $respons = Http::withToken($token)
            ->timeout(10)
            ->post(config('services.whatsapp.url'), [
                'to' => $log->tujuan,
                'template' => $log->templat,
                'params' => $log->data,
            ]);

        $respons->throw();
    }

    private function judul(string $templat): string
    {
        return match ($templat) {
            'permohonan_diterima' => 'Permohonan Anda telah diterima',
            'permohonan_dikembalikan' => 'Permohonan perlu diperbaiki',
            'permohonan_ditolak' => 'Permohonan tidak dapat diproses',
            'permohonan_selesai' => 'Surat Anda telah selesai',
            'pemulihan_kata_sandi' => 'Pemulihan kata sandi akun desa',
            'verifikasi_surel' => 'Verifikasi alamat surel Anda',
            'pengaduan_diterima' => 'Pengaduan Anda telah diterima',
            'pengaduan_ditanggapi' => 'Pengaduan Anda telah ditanggapi',
            'insiden_keamanan' => 'PENTING: indikasi insiden keamanan pada portal desa',
            default => 'Pemberitahuan layanan desa',
        };
    }

    private function susunPesan(NotifikasiLog $log): string
    {
        $data = $log->data ?? [];
        $baris = ['Yth. '.($data['nama'] ?? 'Warga').',', ''];

        $baris[] = match ($log->templat) {
            'permohonan_diterima' => "Permohonan {$data['layanan']} Anda telah kami terima dengan nomor tiket {$data['nomor_tiket']}.",
            'permohonan_dikembalikan' => "Permohonan {$data['nomor_tiket']} perlu diperbaiki. Alasan: {$data['alasan']}",
            'permohonan_ditolak' => "Permohonan {$data['nomor_tiket']} tidak dapat diproses. Alasan: {$data['alasan']}",
            'permohonan_selesai' => "Surat untuk permohonan {$data['nomor_tiket']} telah selesai dan dapat diunduh melalui akun Anda.",
            'pemulihan_kata_sandi' => "Kami menerima permintaan pemulihan kata sandi untuk akun Anda.\n\n"
                ."Buka tautan berikut untuk membuat kata sandi baru (berlaku {$data['berlaku_menit']} menit):\n{$data['tautan']}\n\n"
                .'Abaikan pesan ini bila Anda tidak merasa mengajukan permintaan tersebut.',
            'verifikasi_surel' => 'Silakan verifikasi alamat surel Anda dengan membuka tautan berikut '
                ."(berlaku {$data['berlaku_jam']} jam):\n{$data['tautan']}",
            // REQ-NF-CMP-005: peringatan dini agar tenggat pelaporan 3x24 jam
            // masih dapat dipenuhi.
            'insiden_keamanan' => "Sistem mendeteksi pola yang perlu segera diperiksa: {$data['keterangan']}.\n\n"
                ."Terjadi {$data['jumlah']} kali dalam {$data['jendela_jam']} jam terakhir, "
                ."melewati batas kewajaran {$data['batas']} kali.\n\n"
                .'Periksa audit log pada panel administrasi dan ikuti prosedur penanganan insiden pada '
                .'dokumen operasional. Bila terbukti terjadi kebocoran data pribadi, pemberitahuan kepada subjek '
                .'data dan lembaga berwenang wajib disampaikan paling lambat 3x24 jam sejak diketahui.',
            default => 'Terdapat pembaruan pada layanan yang Anda ajukan.',
        };

        $baris[] = '';
        $baris[] = 'Pesan ini dikirim otomatis oleh sistem informasi desa.';

        return implode("\n", $baris);
    }
}
