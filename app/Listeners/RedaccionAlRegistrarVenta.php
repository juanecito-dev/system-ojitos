<?php

namespace App\Listeners;

use App\Events\VentaRegistrada;
use App\Models\Documento;

/** Redacción: los documentos cobrados en la venta quedan «cobrados» (docsVenta del sistema anterior). */
class RedaccionAlRegistrarVenta
{
    public function handle(VentaRegistrada $e): void
    {
        $v = $e->venta;
        $docs = $v->items->where('origen_tipo', 'documento')->pluck('origen_uid')->filter()->unique()->values()->all();
        if ($docs) {
            Documento::whereIn('uid', $docs)->update(['estado' => 'cobrado', 'venta_uid' => $v->uid, 'cobrado_at' => $v->vendida_at, 'entregado_at' => null]);
        }
    }
}
