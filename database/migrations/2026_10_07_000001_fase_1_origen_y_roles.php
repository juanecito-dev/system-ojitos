<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La venta deja de conocer a los módulos de la imprenta:
 * - «de dónde viene» una venta o una línea es un origen genérico (origen_tipo + origen_uid),
 *   en vez de columnas propias de Pedidos (pedido_uid) y de Redacción (documento_uid);
 * - los productos que un módulo usa se reconocen por su «rol», no por códigos fijos (s_contrato, bn_a4…).
 * Los datos que ya existen se pasan solos.
 */
return new class extends Migration
{
    /** productos del catálogo de la imprenta y el rol que cumplen en Redacción */
    public const ROLES_LEGADO = [
        's_contrato' => 'redaccion_pagina', 's_solicitud' => 'redaccion_documento', 's_cv' => 'redaccion_cv', 'bn_a4' => 'redaccion_ejemplar',
    ];

    public function up(): void
    {
        foreach (['ventas', 'venta_items'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->string('origen_tipo', 20)->nullable();   // pedido, documento… (lo que el módulo necesite)
                $table->string('origen_uid', 60)->nullable();
            });
        }
        DB::table('ventas')->whereNotNull('pedido_uid')->update(['origen_tipo' => 'pedido', 'origen_uid' => DB::raw('pedido_uid')]);
        DB::table('venta_items')->whereNotNull('documento_uid')->update(['origen_tipo' => 'documento', 'origen_uid' => DB::raw('documento_uid')]);
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('pedido_uid');
            $table->index(['negocio_id', 'origen_tipo', 'origen_uid']);
        });
        Schema::table('venta_items', fn (Blueprint $table) => $table->dropColumn('documento_uid'));

        Schema::table('productos', fn (Blueprint $table) => $table->string('rol', 30)->nullable()->after('uid'));
        foreach (self::ROLES_LEGADO as $uid => $rol) {
            DB::table('productos')->where('uid', $uid)->update(['rol' => $rol]);
        }
    }

    public function down(): void
    {
        Schema::table('ventas', fn (Blueprint $table) => $table->string('pedido_uid', 60)->nullable());
        Schema::table('venta_items', fn (Blueprint $table) => $table->string('documento_uid', 60)->nullable());
        DB::table('ventas')->where('origen_tipo', 'pedido')->update(['pedido_uid' => DB::raw('origen_uid')]);
        DB::table('venta_items')->where('origen_tipo', 'documento')->update(['documento_uid' => DB::raw('origen_uid')]);
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex(['negocio_id', 'origen_tipo', 'origen_uid']);
            $table->dropColumn(['origen_tipo', 'origen_uid']);
        });
        Schema::table('venta_items', fn (Blueprint $table) => $table->dropColumn(['origen_tipo', 'origen_uid']));
        Schema::table('productos', fn (Blueprint $table) => $table->dropColumn('rol'));
    }
};
