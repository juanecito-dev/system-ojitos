<?php

namespace App\Models;

use App\Casts\Fecha;
use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClienteMovimiento extends Model
{
    use PerteneceANegocio, SoftDeletes;   // borrar = marcar como borrado: nada desaparece

    protected $table = 'cliente_movimientos';

    /** cada fiado suma a lo que debe el cliente y cada pago resta (suma atómica: dos cajas a la vez no se pisan) */
    protected static function booted(): void
    {
        $efecto = fn (string $tipo, int $monto) => $tipo === 'fiado' ? $monto : -$monto;
        $aplicar = function (?int $clienteId, int $delta) {
            if ($clienteId && $delta) {
                Cliente::whereKey($clienteId)->increment('saldo', $delta);
            }
        };
        static::created(fn (ClienteMovimiento $m) => $aplicar($m->cliente_id, $efecto($m->tipo, (int) $m->monto)));
        static::deleted(fn (ClienteMovimiento $m) => $aplicar($m->cliente_id, -$efecto($m->tipo, (int) $m->monto)));
        static::restored(fn (ClienteMovimiento $m) => $aplicar($m->cliente_id, $efecto($m->tipo, (int) $m->monto)));
        static::updated(function (ClienteMovimiento $m) use ($efecto, $aplicar) {
            if ($m->trashed() || ! $m->wasChanged(['cliente_id', 'tipo', 'monto'])) {
                return;
            }
            $aplicar($m->getOriginal('cliente_id'), -$efecto((string) $m->getOriginal('tipo'), (int) $m->getOriginal('monto')));
            $aplicar($m->cliente_id, $efecto($m->tipo, (int) $m->monto));
        });
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha' => Fecha::class,
            'ocurrido_at' => 'datetime',
            'extra' => 'array',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
