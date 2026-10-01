<?php

namespace App\Models\Concerns;

use App\Models\Negocio;
use App\Support\NegocioActual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Separa los datos de cada negocio: al consultar solo trae los del negocio actual
 * y al crear le pone el negocio_id solo.
 */
trait PerteneceANegocio
{
    public static function bootPerteneceANegocio(): void
    {
        static::addGlobalScope('negocio', function (Builder $q) {
            $id = app(NegocioActual::class)->id();
            if ($id) {
                $q->where($q->getModel()->getTable().'.negocio_id', $id);
            }
        });

        static::creating(function ($m) {
            if (! $m->negocio_id) {
                $m->negocio_id = app(NegocioActual::class)->id();
            }
        });
    }

    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class);
    }
}
