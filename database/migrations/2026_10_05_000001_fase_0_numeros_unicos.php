<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un número de comprobante no se puede registrar dos veces, aunque dos equipos lo intenten a la vez.
 * Los anulados no cuentan (su número se puede volver a usar, como hasta ahora) y las notas de crédito
 * llevan su propio correlativo dentro de la misma serie: por eso la llave es una columna calculada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->revisarRepetidos();

        $driver = DB::connection()->getDriverName();
        $pegar = fn (string ...$p) => in_array($driver, ['mysql', 'mariadb'], true) ? 'CONCAT('.implode(', ', $p).')' : implode(' || ', $p);
        $llave = "CASE WHEN numero IS NOT NULL AND estado <> 'anulado' THEN "
            .$pegar("CASE WHEN tipo = '07' THEN 'N' ELSE 'C' END", 'serie', "'-'", 'numero').' END';

        Schema::table('comprobantes', function (Blueprint $table) use ($driver, $llave) {
            $col = $table->string('llave_numero', 20)->nullable();
            $driver === 'pgsql' ? $col->storedAs($llave) : $col->virtualAs($llave);
        });
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->unique(['negocio_id', 'llave_numero']);
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropUnique(['negocio_id', 'llave_numero']);
        });
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropColumn('llave_numero');
        });
    }

    /** si ya hay números repetidos, no se toca nada: hay que corregirlos antes en Facturación › Emitidos */
    private function revisarRepetidos(): void
    {
        $rep = DB::table('comprobantes')->whereNotNull('numero')->where('estado', '<>', 'anulado')
            ->get(['negocio_id', 'tipo', 'serie', 'numero'])
            ->groupBy(fn ($c) => $c->negocio_id.'|'.($c->tipo === '07' ? 'Nota de crédito ' : '').$c->serie.'-'.(int) $c->numero)
            ->filter(fn ($g) => $g->count() > 1)->keys()
            ->map(fn ($k) => explode('|', $k, 2)[1]);
        if ($rep->isNotEmpty()) {
            throw new RuntimeException('Hay comprobantes con el número repetido: '.$rep->join(', ')
                .'. Corrígelos en Facturación › Emitidos («Corregir número» o anular el que sobra) y vuelve a abrir el sistema.');
        }
    }
};
