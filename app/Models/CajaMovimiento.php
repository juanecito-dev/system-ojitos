<?php

namespace App\Models;

use App\Casts\Fecha;
use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $uid
 * @property \Illuminate\Support\Carbon $fecha
 * @property string $tipo ingreso | gasto | retiro
 * @property string $concepto
 * @property int $monto
 * @property string|null $metodo
 * @property string|null $referencia
 * @property int|null $usuario_id
 * @property \Illuminate\Support\Carbon $ocurrido_at
 */
class CajaMovimiento extends Model
{
    use PerteneceANegocio, SoftDeletes;   // borrar = marcar como borrado: nada desaparece

    protected $table = 'caja_movimientos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha' => Fecha::class,
            'ocurrido_at' => 'datetime',
            'extra' => 'array',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
