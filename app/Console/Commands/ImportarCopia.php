<?php

namespace App\Console\Commands;

use App\Models\Negocio;
use App\Services\ErrorNegocio;
use App\Services\ImportadorCopia;
use Illuminate\Console\Command;

class ImportarCopia extends Command
{
    protected $signature = 'ojitos:importar
        {archivo : Ruta del archivo de copia (ojitos-copia-AAAA-MM-DD.json)}
        {--negocio=ojitos : Código del negocio. Si no existe, se crea con los datos de la copia}';

    protected $description = 'Importa la copia de seguridad v3 del sistema anterior (caja-rapida.html)';

    public function handle(ImportadorCopia $imp): int
    {
        $ruta = $this->argument('archivo');
        if (! is_file($ruta)) {
            $this->error('No encuentro el archivo '.$ruta);

            return self::FAILURE;
        }
        try {
            $d = $imp->leerArchivo($ruta);
        } catch (ErrorNegocio $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $slug = (string) $this->option('negocio');
        $neg = Negocio::where('slug', $slug)->first();
        $this->info(($neg ? 'Actualizando el negocio «'.$neg->nombre.'»' : 'Creando el negocio «'.$slug.'»').' con la copia del '.substr($d['creado'] ?? '', 0, 10).'…');
        $res = $imp->importar($d, $neg, fn ($t) => $this->line('  · '.$t), $slug);
        $this->newLine();
        $this->table(['Qué', 'Cuántos'], collect($res)->except('negocio')->map(fn ($n, $k) => [$k, $n])->values()->all());
        $this->info('Listo. Los usuarios entran con su mismo usuario y PIN de siempre.');

        return self::SUCCESS;
    }
}
