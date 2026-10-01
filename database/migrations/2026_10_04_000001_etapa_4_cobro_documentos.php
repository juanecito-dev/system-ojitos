<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 4, parte 2: la línea de la venta recuerda de qué documento es (l.doc del sistema anterior),
 * para que el documento pase a «cobrado» al vender y vuelva a «sin cobrar» si la venta se anula.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_items', fn (Blueprint $t) => $t->string('documento_uid', 60)->nullable()->after('producto_uid'));
    }

    public function down(): void
    {
        Schema::table('venta_items', fn (Blueprint $t) => $t->dropColumn('documento_uid'));
    }
};
