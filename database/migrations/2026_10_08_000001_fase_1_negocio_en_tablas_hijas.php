<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada fila sabe de qué negocio es, también las de las tablas hijas (líneas, pagos, historial, opciones):
 * así los reportes, la exportación y la baja de un negocio no dependen de cruzar con la tabla padre.
 * Además, índices para las búsquedas que más se repiten.
 */
return new class extends Migration
{
    /** tabla hija => [tabla padre, columna que apunta al padre] */
    public const HIJAS = [
        'venta_items' => ['ventas', 'venta_id'],
        'pedido_items' => ['pedidos', 'pedido_id'],
        'pedido_pagos' => ['pedidos', 'pedido_id'],
        'pedido_historial' => ['pedidos', 'pedido_id'],
        'compra_items' => ['compras', 'compra_id'],
        'compra_pagos' => ['compras', 'compra_id'],
        'comprobante_items' => ['comprobantes', 'comprobante_id'],
        'producto_opciones' => ['productos', 'producto_id'],
        'producto_insumos' => ['productos', 'producto_id'],
    ];

    public function up(): void
    {
        foreach (self::HIJAS as $hija => [$padre, $fk]) {
            Schema::table($hija, fn (Blueprint $table) => $table->foreignId('negocio_id')->nullable()->after('id')->constrained()->cascadeOnDelete());
            DB::table($hija)->update(['negocio_id' => DB::raw("(SELECT negocio_id FROM $padre WHERE $padre.id = $hija.$fk)")]);
            Schema::table($hija, fn (Blueprint $table) => $table->index(['negocio_id', $fk]));
        }

        Schema::table('ventas', fn (Blueprint $table) => $table->index(['negocio_id', 'vendida_at']));
        Schema::table('cliente_movimientos', function (Blueprint $table) {
            $table->index(['negocio_id', 'cliente_id']);
            $table->index(['negocio_id', 'venta_uid']);
        });
        Schema::table('stock_movimientos', fn (Blueprint $table) => $table->index(['negocio_id', 'producto_id', 'ocurrido_at']));
        Schema::table('caja_movimientos', fn (Blueprint $table) => $table->index(['negocio_id', 'referencia']));
        Schema::table('documentos', fn (Blueprint $table) => $table->index(['negocio_id', 'venta_uid']));
        Schema::table('pedido_pagos', fn (Blueprint $table) => $table->index(['negocio_id', 'venta_uid']));
    }

    public function down(): void
    {
        Schema::table('pedido_pagos', fn (Blueprint $table) => $table->dropIndex(['negocio_id', 'venta_uid']));
        Schema::table('documentos', fn (Blueprint $table) => $table->dropIndex(['negocio_id', 'venta_uid']));
        Schema::table('caja_movimientos', fn (Blueprint $table) => $table->dropIndex(['negocio_id', 'referencia']));
        Schema::table('stock_movimientos', fn (Blueprint $table) => $table->dropIndex(['negocio_id', 'producto_id', 'ocurrido_at']));
        Schema::table('cliente_movimientos', function (Blueprint $table) {
            $table->dropIndex(['negocio_id', 'cliente_id']);
            $table->dropIndex(['negocio_id', 'venta_uid']);
        });
        Schema::table('ventas', fn (Blueprint $table) => $table->dropIndex(['negocio_id', 'vendida_at']));
        foreach (self::HIJAS as $hija => [, $fk]) {
            Schema::table($hija, function (Blueprint $table) use ($fk) {
                $table->dropIndex(['negocio_id', $fk]);
                $table->dropConstrainedForeignId('negocio_id');
            });
        }
    }
};
