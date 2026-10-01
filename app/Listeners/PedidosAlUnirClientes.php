<?php

namespace App\Listeners;

use App\Events\ClientesUnidos;
use App\Models\Pedido;

class PedidosAlUnirClientes
{
    public function handle(ClientesUnidos $e): void
    {
        Pedido::where('cliente_id', $e->otro->id)->update(['cliente_id' => $e->queda->id]);
    }
}
