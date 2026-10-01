<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompraPago extends Model
{
    use SoftDeletes;   // borrar = marcar como borrado: nada desaparece

    protected $table = 'compra_pagos';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'pagado_at' => 'datetime',
        ];
    }
}
