<?php

namespace App\Listeners;

use App\Events\VentaRegistrada;
use App\Services\Stock;

/** Inventario: lo vendido y lo que gastó en insumos sale del stock guardado. */
class InventarioAlRegistrarVenta
{
    public function __construct(private Stock $stock) {}

    public function handle(VentaRegistrada $e): void
    {
        $this->stock->alVender($e->venta);
    }
}
