<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoInsumo extends Model
{
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
