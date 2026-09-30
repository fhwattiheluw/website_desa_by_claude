<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** REQ-F-USR-003, 010, 011; REQ-F-ADM-004. */
class PenggunaController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $data = User::with('role')
            ->when($request->filled('peran'), fn ($q) => $q->whereHas(
                'role', fn ($r) => $r->where('kode', $request->string('peran'))
            ))
            ->when($request->filled('status'), fn ($q) => $q->where('status_akun', $request->string('status')))
            // REQ-NF-CMP-004: akun yang ditandai penjadwal karena lama tidak dipakai.
            ->when($request->boolean('perlu_ditinjau'), fn ($q) => $q->whereNotNull('tinjauan_akun_pada'))
            ->cariTeks(['name', 'email'], $request->string('q')->toString())
            ->latest()
            ->paginate(20)
            ->through(fn (User $u) => [
                'id' => $u->id,
                'nama' => $u->name,
                'email' => $u->email,
                'telepon' => $u->telepon,
                'nik_tersamar' => $u->nikTersamar(),
                'peran' => $u->role?->kode,
                'status_akun' => $u->status_akun,
                'nik_terverifikasi' => $u->verifikasi_nik_at !== null,
                'terdaftar_pada' => $u->created_at?->toIso8601String(),
                'masuk_terakhir' => $u->last_login_at?->toIso8601String(),
                'ditandai_untuk_ditinjau_pada' => $u->tinjauan_akun_pada?->toIso8601String(),
            ]);

        return response()->json($data);
    }

    /** REQ-F-USR-003: operator memvalidasi kecocokan NIK dengan data kependudukan. */
    public function verifikasiNik(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'disetujui' => ['required', 'boolean'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $user->update($data['disetujui']
            ? ['status_akun' => User::AKTIF, 'verifikasi_nik_at' => now()]
            : ['status_akun' => User::BELUM_VERIFIKASI, 'verifikasi_nik_at' => null]);

        $this->audit->catat('verifikasi_nik', 'User', $user->id, null, [
            'disetujui' => $data['disetujui'],
            'catatan' => $data['catatan'] ?? null,
        ]);

        return response()->json([
            'pesan' => $data['disetujui']
                ? 'Akun warga diaktifkan dan dapat mengajukan layanan.'
                : 'Verifikasi ditolak; akun tetap berstatus belum terverifikasi.',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'unique:users,email'],
            'telepon' => ['nullable', 'regex:/^(\+62|62|0)8[1-9][0-9]{6,11}$/'],
            'role' => ['required', 'exists:roles,kode'],
        ]);

        $sandiSementara = Str::password(12);

        $pengguna = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'telepon' => $data['telepon'] ?? null,
            'role_id' => Role::where('kode', $data['role'])->value('id'),
            'password' => $sandiSementara,
            'status_akun' => User::AKTIF,
            'verifikasi_nik_at' => now(),
            'consent_at' => now(),
        ]);

        $this->audit->catat('create', 'User', $pengguna->id, null, ['email' => $data['email'], 'peran' => $data['role']]);

        return response()->json([
            'pesan' => 'Akun petugas dibuat. Sampaikan kata sandi sementara melalui kanal aman dan minta segera diganti.',
            'kata_sandi_sementara' => $sandiSementara,
            'data' => AuthController::profil($pengguna),
        ], 201);
    }

    public function ubahPeran(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['role' => ['required', 'exists:roles,kode']]);
        $sebelum = ['peran' => $user->role?->kode];

        $user->update(['role_id' => Role::where('kode', $data['role'])->value('id')]);
        // BR-13: perubahan peran selalu tercatat pada audit log.
        $this->audit->catat('ubah_peran', 'User', $user->id, $sebelum, ['peran' => $data['role']]);

        return response()->json(['pesan' => "Peran pengguna diubah menjadi {$data['role']}."]);
    }

    public function ubahStatus(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['status_akun' => ['required', 'in:aktif,nonaktif,belum_verifikasi']]);

        abort_if($user->id === $request->user()->id, 422, 'Anda tidak dapat mengubah status akun sendiri.');

        $user->update($data);

        if ($data['status_akun'] === User::NONAKTIF) {
            $user->tokens()->delete();
        }

        $this->audit->catat('ubah_status_akun', 'User', $user->id, null, $data);

        return response()->json(['pesan' => "Status akun diubah menjadi {$data['status_akun']}."]);
    }

    public function aturUlangKataSandi(User $user): JsonResponse
    {
        $sandiSementara = Str::password(12);

        $user->update(['password' => $sandiSementara]);
        $user->tokens()->delete();

        $this->audit->catat('reset_password', 'User', $user->id);

        return response()->json([
            'pesan' => 'Kata sandi diatur ulang. Sampaikan melalui kanal aman.',
            'kata_sandi_sementara' => $sandiSementara,
        ]);
    }

    public function peran(): JsonResponse
    {
        return response()->json(['data' => Role::with('permissions:id,kode,nama,grup')->get()]);
    }
}
