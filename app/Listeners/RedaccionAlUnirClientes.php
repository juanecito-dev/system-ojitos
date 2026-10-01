<?php

namespace App\Listeners;

use App\Events\ClientesUnidos;
use App\Models\Documento;

class RedaccionAlUnirClientes
{
    public function handle(ClientesUnidos $e): void
    {
        Documento::where('cliente_id', $e->otro->id)->update(['cliente_id' => $e->queda->id]);
    }
}
