<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use App\Services\Stock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uid
 * @property int|null $producto_id
 * @property string|null $producto_uid
 * @property string|null $nombre
 * @property string $tipo entrada | salida | conteo
 * @property float $cantidad
 * @property float|null $diferencia
 * @property string|null $nota
 * @property string|null $compra_uid
 * @property string|null $pedido_uid
 * @property Carbon $ocurrido_at
 */
class StockMovimiento extends Model
{
    use PerteneceANegocio;

    protected $table = 'stock_movimientos';

    /** cada entrada o salida ajusta el stock guardado (si cuenta: después del último conteo) */
    protected static function booted(): void
    {
        $ajustar = function (StockMovimiento $m, int $signo) {
            if ($m->producto_id && in_array($m->tipo, ['entrada', 'salida'], true) && app(Stock::class)->cuenta($m->producto_id, $m->ocurrido_at)) {
                app(Stock::class)->mover($m->producto_id, $signo * ($m->tipo === 'entrada' ? 1 : -1) * (float) $m->cantidad);
            }
        };
        static::created(fn (StockMovimiento $m) => $ajustar($m, 1));
        static::deleted(fn (StockMovimiento $m) => $ajustar($m, -1));
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cantidad' => 'float',
            'diferencia' => 'float',
            'costo' => 'float',
            'ocurrido_at' => 'datetime',
            'extra' => 'array',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class)->withTrashed();
    }
}
