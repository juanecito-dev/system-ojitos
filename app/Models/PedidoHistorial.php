<?php

namespace App\Models;

use App\Models\Concerns\HeredaNegocio;
use Illuminate\Database\Eloquent\Model;

class PedidoHistorial extends Model
{
    use HeredaNegocio;

    public const PADRE = ['pedidos', 'pedido_id'];

    protected $table = 'pedido_historial';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['ocurrido_at' => 'datetime'];
    }
}
