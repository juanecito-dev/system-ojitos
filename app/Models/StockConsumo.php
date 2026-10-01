<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Lo que gastó una venta de un insumo (las hojas de cada copia), con la receta de ese momento.
 * Cuenta mientras su venta no esté anulada.
 *
 * @property int $id
 * @property int $venta_id
 * @property int|null $venta_item_id la línea que lo gastó
 * @property int $producto_id el insumo
 * @property float $cantidad
 * @property Carbon $ocurrido_at
 */
class StockConsumo extends Model
{
    use PerteneceANegocio;

    protected $table = 'stock_consumos';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cantidad' => 'float', 'ocurrido_at' => 'datetime'];
    }
}
