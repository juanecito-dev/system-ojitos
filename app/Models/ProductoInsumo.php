<?php

namespace App\Models;

use App\Models\Concerns\HeredaNegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoInsumo extends Model
{
    use HeredaNegocio;

    public const PADRE = ['productos', 'producto_id'];

    protected $table = 'producto_insumos';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cantidad' => 'float',
        ];
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'insumo_id')->withTrashed();
    }
}
