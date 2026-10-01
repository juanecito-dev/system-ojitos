<?php

namespace App\Models\Concerns;

use App\Models\Negocio;
use App\Support\NegocioActual;
use App\Support\SinNegocio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Separa los datos de cada negocio: al consultar solo trae los del negocio actual
 * y al crear le pone el negocio_id solo. Sin negocio elegido, se detiene (falla cerrado):
 * nunca devuelve las filas de todos los negocios por olvido.
 */
trait PerteneceANegocio
{
    public static function bootPerteneceANegocio(): void
    {
        static::addGlobalScope('negocio', function (Builder $q) {
            $actual = app(NegocioActual::class);
            if ($id = $actual->id()) {
                $q->where($q->getModel()->getTable().'.negocio_id', $id);
            } elseif (! $actual->sinFiltroPermitido()) {
                throw new SinNegocio('Consulta a «'.$q->getModel()->getTable().'» sin un negocio elegido.');
            }
        });

        static::creating(function ($m) {
            if (! $m->negocio_id) {
                $m->negocio_id = app(NegocioActual::class)->id()
                    ?? throw new SinNegocio('No se puede guardar en «'.$m->getTable().'» sin un negocio elegido.');
            }
        });
    }

    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class);
    }
}
