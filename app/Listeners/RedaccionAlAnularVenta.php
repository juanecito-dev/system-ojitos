<?php

namespace App\Listeners;

use App\Events\VentaAnulada;
use App\Models\Documento;

/** Redacción: sus documentos vuelven a «sin cobrar» (solo si esta era la venta que los cobró). */
class RedaccionAlAnularVenta
{
    public function handle(VentaAnulada $e): void
    {
        Documento::where('venta_uid', $e->venta->uid)->update(['estado' => 'borrador', 'venta_uid' => null, 'cobrado_at' => null, 'entregado_at' => null]);
    }
}
