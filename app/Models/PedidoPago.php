<?php

namespace App\Models;

use App\Casts\Fecha;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PedidoPago extends Model
{
    use SoftDeletes;   // borrar = marcar como borrado: nada desaparece

    protected $table = 'pedido_pagos';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha' => Fecha::class,
            'pagado_at' => 'datetime',
        ];
    }
}
