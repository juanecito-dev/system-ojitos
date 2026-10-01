<?php

namespace App\Listeners;

use App\Events\VentaAnulada;
use App\Models\Pedido;
use App\Services\Pedidos;
use App\Support\Dinero;

/** Pedidos: anular un adelanto, un pago o la entrega deshace ese pago (y la entrega, con su stock). */
class PedidosAlAnularVenta
{
    public function __construct(private Pedidos $pedidos) {}

    public function handle(VentaAnulada $e): void
    {
        $v = $e->venta;
        if ($v->origen_tipo !== 'pedido' || ! ($p = Pedido::where('uid', $v->origen_uid)->first())) {
            return;
        }
        $p->pagos()->where('venta_uid', $v->uid)->delete();   // queda marcado como borrado
        if ($p->etapa === 'entregado') {
            $this->pedidos->quitarStock($p, $e->por);
            $p->update($p->directa ? ['etapa' => 'cotizado', 'directa' => false, 'entregado_at' => null] : ['etapa' => 'listo', 'entregado_at' => null]);
        }
        $this->pedidos->anotar($p, 'Se anuló un pago de '.Dinero::s($v->total), $e->por);
    }
}
