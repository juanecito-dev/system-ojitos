<?php

namespace App\Console\Commands;

use App\Redaccion\Catalogo;
use Illuminate\Console\Command;

/** Copia los modelos de redacción de database/modelos a la base de datos. */
class SincronizarModelos extends Command
{
    protected $signature = 'ojitos:modelos';

    protected $description = 'Actualiza los modelos de redacción (contratos, cartas, declaraciones, CV) desde database/modelos';

    public function handle(): int
    {
        $r = Catalogo::sincronizar();
        $this->info('Modelos al día: '.$r['nuevos'].' nuevos, '.$r['actualizados'].' actualizados, '.$r['apagados'].' retirados.');

        return self::SUCCESS;
    }
}
