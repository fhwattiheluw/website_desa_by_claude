<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrutanNomor extends Model
{
    protected $table = 'urutan_nomor';

    protected $fillable = ['kunci', 'urut'];
}
