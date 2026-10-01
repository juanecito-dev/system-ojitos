<?php

namespace App\Events;

use App\Models\Cliente;

/** Dos fichas eran la misma persona: todo lo de $otro pasa a $queda (dentro de la misma transacción). */
class ClientesUnidos
{
    public function __construct(public Cliente $queda, public Cliente $otro) {}
}
