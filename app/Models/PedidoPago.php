<?php

namespace App\Models;

use App\Casts\Fecha;
use Illuminate\Database\Eloquent\Model;

class PedidoPago extends Model
{
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
