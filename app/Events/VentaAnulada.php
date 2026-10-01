<?php

namespace App\Events;

use App\Models\Usuario;
use App\Models\Venta;

/**
 * Se anuló (o se deshizo) una venta. Se avisa dentro de la misma transacción, antes de marcarla como anulada,
 * para que cada módulo deshaga lo suyo. Lo que el cajero deba saber se agrega a $avisos.
 */
class VentaAnulada
{
    /** @var list<string> */
    public array $avisos = [];

    public function __construct(public Venta $venta, public Usuario $por, public string $tipo = 'anulada') {}
}
