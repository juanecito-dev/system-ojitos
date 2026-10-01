<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stock guardado:
 * - stock_consumos: lo que gastó cada venta de sus insumos (las hojas de cada copia), con la receta de ese
 *   momento. Antes se calculaba con la receta actual, así que cambiar una receta cambiaba el stock hacia atrás.
 *   Las ventas que ya existían se anotan con la receta de hoy: el stock queda igual que antes.
 * - stock_saldos: el stock de cada producto, que se actualiza con cada venta y movimiento, en vez de
 *   recalcularse con todo el historial en cada página. Una revisión (ojitos:stock-revisar) lo compara
 *   con el historial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_consumos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venta_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venta_item_id')->nullable()->constrained('venta_items')->cascadeOnDelete();   // la línea que lo gastó
            $table->foreignId('producto_id')->constrained()->cascadeOnDelete();   // el insumo que se gastó
            $table->decimal('cantidad', 20, 10);
            $table->timestamp('ocurrido_at');
            $table->index(['negocio_id', 'producto_id', 'ocurrido_at']);
            $table->index('venta_id');
        });
        DB::statement('INSERT INTO stock_consumos (negocio_id, venta_id, venta_item_id, producto_id, cantidad, ocurrido_at)
            SELECT v.negocio_id, v.id, i.id, pi.insumo_id, i.cantidad * pi.cantidad, v.vendida_at
            FROM venta_items i JOIN ventas v ON v.id = i.venta_id JOIN producto_insumos pi ON pi.producto_id = i.producto_id
            WHERE i.tercero = ? AND i.producto_id IS NOT NULL', [false]);

        Schema::create('stock_saldos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained()->cascadeOnDelete();
            $table->decimal('cantidad', 20, 10);
            $table->timestamp('updated_at')->nullable();
            $table->unique(['negocio_id', 'producto_id']);
        });
        foreach (DB::table('stock_bases')->get() as $b) {
            DB::table('stock_saldos')->insert(['negocio_id' => $b->negocio_id, 'producto_id' => $b->producto_id,
                'cantidad' => $this->calcular($b), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_saldos');
        Schema::dropIfExists('stock_consumos');
    }

    /** el mismo cálculo que Stock::calcular(), para un producto (aquí sin depender del código de la aplicación) */
    private function calcular(object $b): float
    {
        $mov = (float) DB::table('stock_movimientos')->where('producto_id', $b->producto_id)->where('ocurrido_at', '>', $b->desde)
            ->whereIn('tipo', ['entrada', 'salida'])->sum(DB::raw("CASE WHEN tipo = 'entrada' THEN cantidad ELSE -cantidad END"));
        $vend = (float) DB::table('venta_items as i')->join('ventas as v', 'v.id', '=', 'i.venta_id')->whereNull('v.anulada_at')
            ->where('i.producto_id', $b->producto_id)->where('i.tercero', false)->where('v.vendida_at', '>', $b->desde)->sum('i.cantidad');
        $gast = (float) DB::table('stock_consumos as c')->join('ventas as v', 'v.id', '=', 'c.venta_id')->whereNull('v.anulada_at')
            ->where('c.producto_id', $b->producto_id)->where('c.ocurrido_at', '>', $b->desde)->sum('c.cantidad');

        return (float) $b->cantidad + $mov - $vend - $gast;
    }
};
