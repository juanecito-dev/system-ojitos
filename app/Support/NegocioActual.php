<?php

namespace App\Support;

use App\Models\Negocio;

/**
 * El negocio con el que se está trabajando en esta petición (el del usuario que entró,
 * o el que eligió el importador). Todas las tablas con negocio_id se filtran por él.
 *
 * Sin negocio elegido, una consulta a esas tablas se detiene (SinNegocio) en vez de traer
 * las filas de todos los negocios. El código de la plataforma que de verdad necesita ver
 * todos los negocios lo pide de forma explícita con plataforma().
 */
class NegocioActual
{
    private ?Negocio $negocio = null;

    private int $plataforma = 0;

    public function set(?Negocio $negocio): void
    {
        $this->negocio = $negocio;
    }

    public function get(): ?Negocio
    {
        return $this->negocio;
    }

    public function id(): ?int
    {
        return $this->negocio?->id;
    }

    public function obligatorio(): Negocio
    {
        return $this->negocio ?? throw new SinNegocio('No hay un negocio elegido.');
    }

    /**
     * Ejecuta $fn sin el filtro por negocio (solo para tareas de la plataforma: revisar todos los negocios,
     * migraciones, mantenimiento). Si hay un negocio elegido, se sigue filtrando por él.
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    public function plataforma(callable $fn): mixed
    {
        $this->plataforma++;
        try {
            return $fn();
        } finally {
            $this->plataforma--;
        }
    }

    public function sinFiltroPermitido(): bool
    {
        return $this->plataforma > 0;
    }
}
