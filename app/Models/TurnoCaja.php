<?php

namespace App\Models;

use App\Casts\Fecha;
use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $usuario_id
 * @property string $vendedor
 * @property \Illuminate\Support\Carbon $fecha
 * @property \Illuminate\Support\Carbon $abre_at
 * @property \Illuminate\Support\Carbon|null $cierra_at
 * @property int $inicial
 * @property int|null $contado
 * @property int|null $esperado
 */
class TurnoCaja extends Model
{
    use PerteneceANegocio;

    protected $table = 'turnos_caja';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha' => Fecha::class,
            'abre_at' => 'datetime',
            'cierra_at' => 'datetime',
            'correcciones' => 'array',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
