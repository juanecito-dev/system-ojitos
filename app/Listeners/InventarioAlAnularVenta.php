<?php

namespace App\Listeners;

use App\Events\VentaAnulada;
use App\Services\Stock;

/** Inventario: lo de una venta anulada vuelve al stock. */
class InventarioAlAnularVenta
{
    public function __construct(private Stock $stock) {}

    public function handle(VentaAnulada $e): void
    {
        $this->stock->alAnular($e->venta, $e->por);
    }
}
