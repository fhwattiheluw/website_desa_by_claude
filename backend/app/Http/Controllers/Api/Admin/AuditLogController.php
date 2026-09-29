<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** REQ-F-ADM-004, 005: audit log hanya dapat dibaca, tidak dapat diubah. */
class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = AuditLog::with('aktor:id,name')
            ->when($request->filled('aksi'), fn ($q) => $q->where('aksi', $request->string('aksi')))
            ->when($request->filled('entitas'), fn ($q) => $q->where('entitas', $request->string('entitas')))
            ->when($request->filled('aktor_id'), fn ($q) => $q->where('aktor_id', $request->integer('aktor_id')))
            ->when($request->filled('dari'), fn ($q) => $q->where('created_at', '>=', $request->date('dari')))
            ->when($request->filled('sampai'), fn ($q) => $q->where('created_at', '<=', $request->date('sampai')))
            ->latest('created_at')
            ->paginate(30)
            ->through(fn (AuditLog $l) => [
                'id' => $l->id,
                'waktu' => $l->created_at?->toIso8601String(),
                'aktor' => $l->aktor?->name ?? 'Sistem',
                'aksi' => $l->aksi,
                'entitas' => $l->entitas,
                'entitas_id' => $l->entitas_id,
                'sebelum' => $l->sebelum,
                'sesudah' => $l->sesudah,
                'alamat_ip' => $l->alamat_ip,
            ]);

        return response()->json($data);
    }
}
