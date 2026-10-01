<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que debe cada cliente, guardado (en céntimos): se ajusta con cada fiado o pago, en vez de sumar
 * todos sus movimientos cada vez. La revisión diaria (ojitos:revisar) lo compara con sus movimientos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->bigInteger('saldo')->default(0)->after('gastado');
            $table->index(['negocio_id', 'saldo']);
        });
        DB::table('clientes')->update(['saldo' => DB::raw("COALESCE((SELECT SUM(CASE WHEN m.tipo = 'fiado' THEN m.monto ELSE -m.monto END)
            FROM cliente_movimientos m WHERE m.cliente_id = clientes.id AND m.deleted_at IS NULL), 0)")]);
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex(['negocio_id', 'saldo']);
            $table->dropColumn('saldo');
        });
    }
};
