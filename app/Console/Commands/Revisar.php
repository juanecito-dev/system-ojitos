<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Negocio;
use App\Models\Producto;
use App\Services\Clientes;
use App\Services\Stock;
use App\Support\NegocioActual;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Revisa lo que se guarda para no recalcularlo en cada pantalla (el stock de cada producto y lo que debe
 * cada cliente) contra su historial, y lo corrige. Se programa cada madrugada.
 */
class Revisar extends Command
{
    protected $signature = 'ojitos:revisar {--negocio= : Código de un solo negocio (si no, todos los activos)}';

    protected $description = 'Revisa el stock y las deudas guardadas contra su historial, y los corrige';

    public function handle(Stock $stock, Clientes $clientes, NegocioActual $actual): int
    {
        $negocios = Negocio::where('activo', true)->when($this->option('negocio'), fn ($q, $s) => $q->where('slug', $s))->get();
        $corregidos = 0;
        $antes = $actual->get();
        try {
            foreach ($negocios as $neg) {
                $actual->set($neg);
                foreach ($stock->recalcular() as $id => $x) {
                    $corregidos++;
                    $this->avisar($neg, 'stock', Producto::withTrashed()->whereKey($id)->value('nombre'), $x);
                }
                foreach ($clientes->recalcularSaldos() as $id => $x) {
                    $corregidos++;
                    $this->avisar($neg, 'deuda', Cliente::whereKey($id)->value('nombre'), $x);
                }
            }
        } finally {
            $actual->set($antes);
        }
        $this->info($corregidos ? "Listo: se corrigieron {$corregidos} saldos." : 'Listo: el stock y las deudas cuadran con su historial.');

        return self::SUCCESS;
    }

    private function avisar(Negocio $neg, string $que, ?string $nombre, array $x): void
    {
        Log::warning(ucfirst($que).' corregido', ['negocio' => $neg->slug, 'de' => $nombre, 'guardado' => $x['guardado'], 'real' => $x['real']]);
        $this->line("  · {$neg->slug}: {$que} de {$nombre} decía ".($x['guardado'] ?? 'nada').", el historial dice {$x['real']}");
    }
}
