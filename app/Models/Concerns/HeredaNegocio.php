<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Para las tablas hijas (líneas, pagos, historial, opciones): se filtran por negocio como las demás,
 * y al crearlas sin un negocio elegido (por ejemplo, al dar de alta un negocio nuevo) toman el de su fila padre.
 * Cada modelo define PADRE = [tabla padre, columna que la apunta].
 */
trait HeredaNegocio
{
    use PerteneceANegocio;

    public function negocioDelPadre(): ?int
    {
        [$tabla, $fk] = static::PADRE;
        $id = $this->getAttribute($fk);

        return $id ? DB::table($tabla)->where('id', $id)->value('negocio_id') : null;
    }
}
