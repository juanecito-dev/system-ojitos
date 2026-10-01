<?php

namespace App\Models;

use App\Models\Concerns\HeredaNegocio;
use Illuminate\Database\Eloquent\Model;

class PedidoItem extends Model
{
    use HeredaNegocio;

    public const PADRE = ['pedidos', 'pedido_id'];

    protected $table = 'pedido_items';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cantidad' => 'float',
        ];
    }
}
