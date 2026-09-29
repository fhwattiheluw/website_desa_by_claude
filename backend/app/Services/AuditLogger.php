<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Pencatat jejak audit untuk seluruh aksi tulis (REQ-F-ADM-004, REQ-NF-SEC-013).
 */
class AuditLogger
{
    /** Kunci yang nilainya tidak boleh masuk audit log apa adanya (REQ-NF-CMP-002). */
    private const RAHASIA = ['password', 'password_confirmation', 'nik', 'nik_hash', 'kontak_pelapor', 'token'];

    public function catat(
        string $aksi,
        string $entitas,
        string|int|null $entitasId = null,
        ?array $sebelum = null,
        ?array $sesudah = null,
    ): AuditLog {
        return AuditLog::create([
            'aktor_id' => Auth::id(),
            'aksi' => $aksi,
            'entitas' => $entitas,
            'entitas_id' => $entitasId === null ? null : (string) $entitasId,
            'sebelum' => $sebelum ? $this->samarkan($sebelum) : null,
            'sesudah' => $sesudah ? $this->samarkan($sesudah) : null,
            'alamat_ip' => Request::ip(),
            'agen' => substr((string) Request::userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }

    public function catatModel(string $aksi, Model $model, ?array $sebelum = null): AuditLog
    {
        return $this->catat(
            $aksi,
            class_basename($model),
            $model->getKey(),
            $sebelum,
            $model->attributesToArray(),
        );
    }

    /** @param array<string, mixed> $data */
    private function samarkan(array $data): array
    {
        foreach ($data as $kunci => $nilai) {
            if (in_array($kunci, self::RAHASIA, true)) {
                $data[$kunci] = '[disamarkan]';
            } elseif (is_array($nilai)) {
                $data[$kunci] = $this->samarkan($nilai);
            }
        }

        return $data;
    }
}
