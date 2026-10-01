<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoHistorial extends Model
{
    protected $table = 'pedido_historial';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['ocurrido_at' => 'datetime'];
    }
}
