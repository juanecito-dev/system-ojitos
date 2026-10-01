<?php

namespace App\Models;

use App\Models\Concerns\HeredaNegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompraPago extends Model
{
    use HeredaNegocio, SoftDeletes;   // borrar = marcar como borrado: nada desaparece

    public const PADRE = ['compras', 'compra_id'];

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
