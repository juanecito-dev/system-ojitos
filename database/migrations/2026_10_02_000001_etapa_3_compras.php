<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 3, compras: el costo de cada línea acepta fracciones de céntimo, cada línea guarda su subtotal
 * y el costo del producto antes y después (para deshacer la compra), y se anota quién registró cada compra y pago.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compra_items', function (Blueprint $t) {
            $t->decimal('costo_unitario', 14, 4)->change();                 // por presentación (caja, millar…)
            $t->integer('subtotal')->default(0)->after('costo_unitario');
            $t->decimal('costo_antes', 14, 4)->nullable()->after('subtotal');
            $t->decimal('costo_nuevo', 14, 4)->nullable()->after('costo_antes');
            $t->unsignedSmallInteger('orden')->default(0)->after('costo_nuevo');
        });
        Schema::table('compras', function (Blueprint $t) {
            $t->foreignId('usuario_id')->nullable()->after('nota')->constrained('usuarios')->nullOnDelete();
            $t->string('vendedor')->nullable()->after('usuario_id');
        });
        Schema::table('compra_pagos', function (Blueprint $t) {
            $t->foreignId('usuario_id')->nullable()->after('caja_mov_uid')->constrained('usuarios')->nullOnDelete();
            $t->string('vendedor')->nullable()->after('usuario_id');
        });
        // las líneas que vinieron de la copia: subtotal = cantidad × costo
        foreach (DB::table('compra_items')->get() as $l) {
            $extra = json_decode($l->extra ?? 'null', true) ?: [];
            DB::table('compra_items')->where('id', $l->id)->update([
                'subtotal' => (int) round($extra['sub'] ?? $l->cantidad * $l->costo_unitario),
                'costo_antes' => $extra['costoAnt'] ?? null, 'costo_nuevo' => $extra['costoNuevo'] ?? null,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('compra_pagos', fn (Blueprint $t) => $t->dropConstrainedForeignId('usuario_id'));
        Schema::table('compra_pagos', fn (Blueprint $t) => $t->dropColumn('vendedor'));
        Schema::table('compras', fn (Blueprint $t) => $t->dropConstrainedForeignId('usuario_id'));
        Schema::table('compras', fn (Blueprint $t) => $t->dropColumn('vendedor'));
        Schema::table('compra_items', fn (Blueprint $t) => $t->dropColumn(['subtotal', 'costo_antes', 'costo_nuevo', 'orden']));
        Schema::table('compra_items', fn (Blueprint $t) => $t->integer('costo_unitario')->change());
    }
};
