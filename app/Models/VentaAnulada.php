<?php

namespace App\Models;

use App\Casts\Fecha;
use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;

class VentaAnulada extends Model
{
    use PerteneceANegocio;

    protected $table = 'ventas_anuladas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha' => Fecha::class,
            'venta_at' => 'datetime',
            'anulada_at' => 'datetime',
            'venta' => 'array',
        ];
    }
}
