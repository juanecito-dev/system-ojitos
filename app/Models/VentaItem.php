<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $venta_id
 * @property int|null $producto_id
 * @property string|null $producto_uid
 * @property string $nombre
 * @property float $cantidad
 * @property int $precio
 * @property int $subtotal
 * @property bool $tercero
 * @property string|null $origen_tipo de dónde viene la línea: documento (Redacción)…
 * @property string|null $origen_uid
 */
class VentaItem extends Model
{
    protected $table = 'venta_items';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cantidad' => 'float',
            'costo' => 'float',
            'tercero' => 'boolean',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }
}
