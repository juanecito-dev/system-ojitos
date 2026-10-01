<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;

/**
 * El stock de un producto con control de stock, guardado: se ajusta con cada venta y movimiento
 * (Stock::mover) y la revisión diaria lo compara con el historial (Stock::recalcular).
 *
 * @property int $producto_id
 * @property float $cantidad
 */
class StockSaldo extends Model
{
    use PerteneceANegocio;

    protected $table = 'stock_saldos';

    const CREATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cantidad' => 'float'];
    }
}
