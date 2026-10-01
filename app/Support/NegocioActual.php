<?php

namespace App\Support;

use App\Models\Negocio;

/**
 * El negocio con el que se está trabajando en esta petición (el del usuario que entró,
 * o el que eligió el importador). Todas las tablas con negocio_id se filtran por él.
 */
class NegocioActual
{
    private ?Negocio $negocio = null;

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
        return $this->negocio ?? throw new \RuntimeException('No hay un negocio elegido.');
    }
}
