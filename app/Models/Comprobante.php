<?php

namespace App\Models;

use App\Casts\Fecha;
use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comprobante extends Model
{
    use PerteneceANegocio;

    protected $table = 'comprobantes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha' => Fecha::class,
            'cierre_de' => Fecha::class,
            'cliente' => 'array',
            'ventas' => 'array',
            'extra' => 'array',
            'emitido_at' => 'datetime',
            'anulado_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ComprobanteItem::class);
    }

    public function etiqueta(): string
    {
        return $this->serie ? $this->serie.'-'.($this->numero ?: '?') : 'Sin número';
    }
}
