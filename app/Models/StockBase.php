<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use App\Services\Stock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBase extends Model
{
    use PerteneceANegocio;

    protected $table = 'stock_bases';

    /** un conteo nuevo (o el primer control de stock) recalcula el stock guardado de ese producto */
    protected static function booted(): void
    {
        static::saved(fn (StockBase $b) => app(Stock::class)->recalcular([$b->producto_id]));
    }

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cantidad' => 'float',
            'desde' => 'datetime',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class)->withTrashed();
    }
}
