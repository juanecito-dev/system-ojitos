<?php

namespace App\Console\Commands;

use App\Models\Negocio;
use App\Models\Producto;
use App\Services\Stock;
use App\Support\NegocioActual;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/** Compara el stock guardado de cada negocio con su historial y lo corrige (se programa cada madrugada). */
class RevisarStock extends Command
{
    protected $signature = 'ojitos:stock-revisar {--negocio= : Código de un solo negocio (si no, todos los activos)}';

    protected $description = 'Revisa el stock guardado contra el historial de ventas y movimientos, y lo corrige';

    public function handle(Stock $stock, NegocioActual $actual): int
    {
        $negocios = Negocio::where('activo', true)->when($this->option('negocio'), fn ($q, $s) => $q->where('slug', $s))->get();
        $corregidos = 0;
        $antes = $actual->get();
        foreach ($negocios as $neg) {
            $actual->set($neg);
            $mal = $stock->recalcular();
            $corregidos += count($mal);
            foreach ($mal as $id => $x) {
                $nombre = Producto::withTrashed()->whereKey($id)->value('nombre');
                Log::warning('Stock corregido', ['negocio' => $neg->slug, 'producto' => $nombre, 'guardado' => $x['guardado'], 'real' => $x['real']]);
                $this->line("  · {$neg->slug}: {$nombre} decía ".($x['guardado'] ?? 'nada').", el historial dice {$x['real']}");
            }
        }
        $actual->set($antes);
        $this->info($corregidos ? "Listo: se corrigieron {$corregidos} productos." : 'Listo: todo el stock cuadra con su historial.');

        return self::SUCCESS;
    }
}
