<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Registrasi, autentikasi, dan sesi pengguna
 * (REQ-F-USR-001..008, 013, 014).
 */
class AuthController extends Controller
{
    /** REQ-F-USR-007: penguncian sementara setelah kegagalan beruntun. */
    public const MAKS_GAGAL = 5;

    public const MENIT_KUNCI = 15;

    public function __construct(private readonly AuditLogger $audit) {}

    public function daftar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:150', 'unique:users,email'],
            'nik' => ['required', 'digits:16'],
            'telepon' => ['required', 'regex:/^(\+62|62|0)8[1-9][0-9]{6,11}$/'],
            'alamat' => ['nullable', 'string', 'max:255'],
            // REQ-F-USR-004: kombinasi huruf dan angka, minimal 8 karakter.
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/[A-Za-z]/', 'regex:/[0-9]/'],
            // REQ-F-USR-014: persetujuan pemrosesan data pribadi wajib dan tercatat.
            'persetujuan' => ['accepted'],
        ], [
            'password.regex' => 'Kata sandi harus memuat huruf dan angka.',
            'persetujuan.accepted' => 'Anda harus menyetujui kebijakan pemrosesan data pribadi.',
        ]);

        if ($this->kataSandiUmum($data['password'])) {
            throw ValidationException::withMessages([
                'password' => 'Kata sandi terlalu umum. Gunakan kombinasi yang lebih sulit ditebak.',
            ]);
        }

        // BR-12: satu NIK hanya untuk satu akun warga aktif.
        if (User::where('nik_hash', User::hashNik($data['nik']))->exists()) {
            throw ValidationException::withMessages([
                'nik' => 'NIK ini sudah terdaftar. Gunakan fitur lupa kata sandi bila Anda pemilik akun tersebut.',
            ]);
        }

        $pengguna = User::create([
            'role_id' => Role::where('kode', Role::WARGA)->value('id'),
            'name' => $data['name'],
            'email' => $data['email'],
            'nik' => $data['nik'],
            'telepon' => $data['telepon'],
            'alamat' => $data['alamat'] ?? null,
            'password' => $data['password'],
            // REQ-F-USR-003: akun menunggu validasi NIK oleh operator desa.
            'status_akun' => User::BELUM_VERIFIKASI,
            'consent_at' => now(),
        ]);

        $this->audit->catat('register', 'User', $pengguna->id, null, ['email' => $pengguna->email]);

        return response()->json([
            'pesan' => 'Pendaftaran berhasil. Akun Anda menunggu verifikasi NIK oleh petugas desa.',
            'pengguna' => $this->profil($pengguna),
        ], 201);
    }

    public function masuk(Request $request): JsonResponse
    {
        $kredensial = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $pengguna = User::with('role.permissions')->where('email', $kredensial['email'])->first();

        if ($pengguna?->terkunci_sampai?->isFuture()) {
            throw ValidationException::withMessages([
                'email' => 'Akun terkunci sementara. Coba lagi setelah '
                    .$pengguna->terkunci_sampai->diffForHumans().'.',
            ]);
        }

        if (! $pengguna || ! Hash::check($kredensial['password'], $pengguna->password)) {
            $this->catatKegagalan($pengguna, $kredensial['email']);

            throw ValidationException::withMessages(['email' => 'Surel atau kata sandi tidak cocok.']);
        }

        if ($pengguna->status_akun === User::NONAKTIF) {
            throw ValidationException::withMessages([
                'email' => 'Akun Anda dinonaktifkan. Silakan hubungi administrator desa.',
            ]);
        }

        $pengguna->forceFill([
            'gagal_masuk' => 0,
            'terkunci_sampai' => null,
            'last_login_at' => now(),
        ])->save();

        // REQ-F-USR-008: masa berlaku sesi petugas lebih pendek daripada warga.
        $kedaluwarsa = $pengguna->petugas() ? now()->addMinutes(30) : now()->addDays(7);
        $token = $pengguna->createToken('sidesa', ['*'], $kedaluwarsa);

        $this->audit->catat('login', 'User', $pengguna->id);

        return response()->json([
            'token' => $token->plainTextToken,
            'kedaluwarsa' => $kedaluwarsa->toIso8601String(),
            'pengguna' => $this->profil($pengguna),
        ]);
    }

    public function keluar(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        $this->audit->catat('logout', 'User', $request->user()->id);

        return response()->json(['pesan' => 'Anda telah keluar.']);
    }

    public function saya(Request $request): JsonResponse
    {
        return response()->json(['pengguna' => $this->profil($request->user()->load('role.permissions'))]);
    }

    /** REQ-F-USR-013: pengguna dapat memutakhirkan data pribadinya sendiri. */
    public function perbaruiProfil(Request $request): JsonResponse
    {
        $pengguna = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:150', Rule::unique('users', 'email')->ignore($pengguna->id)],
            'telepon' => ['required', 'regex:/^(\+62|62|0)8[1-9][0-9]{6,11}$/'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date', 'before_or_equal:today'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
            'pekerjaan' => ['nullable', 'string', 'max:80'],
        ]);

        $sebelum = $pengguna->only(array_keys($data));
        $pengguna->update($data);
        $this->audit->catat('update', 'User', $pengguna->id, $sebelum, $data);

        return response()->json(['pengguna' => $this->profil($pengguna->fresh('role.permissions'))]);
    }

    public function ubahKataSandi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password_lama' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/[A-Za-z]/', 'regex:/[0-9]/'],
        ]);

        if (! Hash::check($data['password_lama'], $request->user()->password)) {
            throw ValidationException::withMessages(['password_lama' => 'Kata sandi lama tidak cocok.']);
        }

        $request->user()->update(['password' => $data['password']]);
        // Seluruh sesi lain dicabut agar perubahan kredensial berlaku menyeluruh.
        $request->user()->tokens()->delete();
        $this->audit->catat('ubah_password', 'User', $request->user()->id);

        return response()->json(['pesan' => 'Kata sandi berhasil diubah. Silakan masuk kembali.']);
    }

    /** @return array<string, mixed> */
    public static function profil(User $pengguna): array
    {
        $pengguna->loadMissing('role.permissions');

        return [
            'id' => $pengguna->id,
            'nama' => $pengguna->name,
            'email' => $pengguna->email,
            'nik_tersamar' => $pengguna->nikTersamar(),
            'telepon' => $pengguna->telepon,
            'alamat' => $pengguna->alamat,
            'tempat_lahir' => $pengguna->tempat_lahir,
            'tanggal_lahir' => $pengguna->tanggal_lahir?->toDateString(),
            'jenis_kelamin' => $pengguna->jenis_kelamin,
            'pekerjaan' => $pengguna->pekerjaan,
            'status_akun' => $pengguna->status_akun,
            'nik_terverifikasi' => $pengguna->verifikasi_nik_at !== null,
            'boleh_mengajukan' => $pengguna->bolehMengajukanLayanan(),
            'peran' => $pengguna->role?->kode,
            'peran_nama' => $pengguna->role?->nama,
            'petugas' => $pengguna->petugas(),
            'izin' => $pengguna->role?->permissions->pluck('kode')->all() ?? [],
        ];
    }

    private function catatKegagalan(?User $pengguna, string $email): void
    {
        if (! $pengguna) {
            return;
        }

        $gagal = $pengguna->gagal_masuk + 1;

        $pengguna->forceFill([
            'gagal_masuk' => $gagal,
            'terkunci_sampai' => $gagal >= self::MAKS_GAGAL ? now()->addMinutes(self::MENIT_KUNCI) : null,
        ])->save();

        $this->audit->catat('login_gagal', 'User', $pengguna->id, null, ['email' => $email, 'percobaan' => $gagal]);
    }

    private function kataSandiUmum(string $kataSandi): bool
    {
        $umum = [
            'password', 'password1', '12345678', '123456789', 'qwerty123', 'admin123',
            'rahasia1', 'indonesia', 'desa1234', 'abcd1234', 'password123',
        ];

        return in_array(strtolower($kataSandi), $umum, true);
    }
}
