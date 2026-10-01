<?php

namespace App\Events;

use App\Models\Usuario;
use App\Models\Venta;

/**
 * Se guardó una venta. Se avisa dentro de la misma transacción: si un módulo falla, no se guarda nada.
 * Cada módulo mira el origen de la venta o de sus líneas (origen_tipo / origen_uid) y actualiza lo suyo.
 */
class VentaRegistrada
{
    public function __construct(public Venta $venta, public Usuario $por) {}
}
