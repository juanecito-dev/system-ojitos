<?php

namespace App\Models;

use App\Models\Concerns\HeredaNegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompraItem extends Model
{
    use HeredaNegocio;

    public const PADRE = ['compras', 'compra_id'];

    protected $table = 'compra_items';

    public $timestamps = false;

    protected $guarded = ['id'];

    /** unidades que suma al stock (cantidad × lo que trae cada presentación) */
    public function unidades(): float
    {
        return round($this->cantidad * max(1, $this->factor), 3);
    }

    /** costo de cada unidad suelta */
    public function costoPorUnidad(): float
    {
        return $this->unidades() > 0 ? $this->subtotal / $this->unidades() : 0.0;
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    protected function casts(): array
    {
        return [
            'cantidad' => 'float',
            'factor' => 'float',
            'costo_unitario' => 'float',
            'costo_antes' => 'float',
            'costo_nuevo' => 'float',
            'extra' => 'array',
        ];
    }
}
