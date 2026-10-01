<?php

namespace App\Models;

use App\Casts\Fecha;
use App\Models\Concerns\HeredaNegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PedidoPago extends Model
{
    use HeredaNegocio, SoftDeletes;   // borrar = marcar como borrado: nada desaparece

    public const PADRE = ['pedidos', 'pedido_id'];

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
