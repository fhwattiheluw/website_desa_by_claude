<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    public const WARGA = 'warga';

    public const OPERATOR = 'operator';

    public const VERIFIKATOR = 'verifikator';

    public const SEKDES = 'sekdes';

    public const KADES = 'kades';

    public const ADMIN = 'admin';

    protected $fillable = ['kode', 'nama', 'deskripsi', 'bawaan'];

    protected function casts(): array
    {
        return ['bawaan' => 'boolean'];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
