<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nada se borra: una venta anulada se queda con su fecha de anulación (deja de contar como venta,
 * pero sigue en el cuadre del turno en que se cobró), y los movimientos de caja, fiados y pagos
 * que se quitan quedan marcados como borrados en vez de desaparecer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->timestamp('anulada_at')->nullable()->after('vendida_at');
            // se anuló después de cuadrar su caja: su dinero sigue en ese cuadre y la devolución salió de la caja del día en que se anuló
            $table->boolean('devuelta_en_caja')->default(false)->after('anulada_at');
            $table->index(['negocio_id', 'anulada_at']);
        });
        foreach (['caja_movimientos', 'cliente_movimientos', 'pedido_pagos', 'compra_pagos'] as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->softDeletes());
        }
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex(['negocio_id', 'anulada_at']);
            $table->dropColumn(['anulada_at', 'devuelta_en_caja']);
        });
        foreach (['caja_movimientos', 'cliente_movimientos', 'pedido_pagos', 'compra_pagos'] as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->dropSoftDeletes());
        }
    }
};
