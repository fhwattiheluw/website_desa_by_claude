<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const AKTIF = 'aktif';

    public const BELUM_VERIFIKASI = 'belum_verifikasi';

    public const NONAKTIF = 'nonaktif';

    protected $fillable = [
        'role_id', 'name', 'email', 'password', 'nik', 'nik_hash', 'telepon', 'alamat',
        'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'pekerjaan', 'status_akun',
        'verifikasi_nik_at', 'consent_at',
    ];

    protected $hidden = ['password', 'remember_token', 'nik_hash'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verifikasi_nik_at' => 'datetime',
            'consent_at' => 'datetime',
            'last_login_at' => 'datetime',
            'terkunci_sampai' => 'datetime',
            'tanggal_lahir' => 'date',
            'password' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function permohonan(): HasMany
    {
        return $this->hasMany(Permohonan::class, 'pemohon_id');
    }

    /** NIK disimpan terenkripsi (REQ-NF-SEC-006) dengan hash pencarian (BR-12). */
    public function setNikAttribute(?string $nilai): void
    {
        if (blank($nilai)) {
            $this->attributes['nik'] = null;
            $this->attributes['nik_hash'] = null;

            return;
        }

        $this->attributes['nik'] = Crypt::encryptString($nilai);
        $this->attributes['nik_hash'] = hash('sha256', $nilai);
    }

    public function getNikAttribute(?string $nilai): ?string
    {
        if (blank($nilai)) {
            return null;
        }

        try {
            return Crypt::decryptString($nilai);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function hashNik(string $nik): string
    {
        return hash('sha256', $nik);
    }

    /** Menyamarkan NIK untuk tampilan petugas: 3271********0003. */
    public function nikTersamar(): ?string
    {
        $nik = $this->nik;

        return $nik ? substr($nik, 0, 4).str_repeat('*', 8).substr($nik, -4) : null;
    }

    public function punyaIzin(string $kode): bool
    {
        return $this->relationLoaded('role') && $this->role?->relationLoaded('permissions')
            ? $this->role->permissions->contains('kode', $kode)
            : (bool) $this->role?->permissions()->where('kode', $kode)->exists();
    }

    public function berperan(string ...$kode): bool
    {
        return in_array($this->role?->kode, $kode, true);
    }

    public function petugas(): bool
    {
        return $this->role !== null && $this->role->kode !== Role::WARGA;
    }

    /** BR-01: hanya warga aktif ber-NIK terverifikasi yang boleh mengajukan layanan. */
    public function bolehMengajukanLayanan(): bool
    {
        return $this->status_akun === self::AKTIF && $this->verifikasi_nik_at !== null;
    }
}
