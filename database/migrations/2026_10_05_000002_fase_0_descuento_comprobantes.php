<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Descuento global del comprobante (el descuento de las ventas): las líneas van a su precio y
 * líneas − descuento = total, como lo pide SUNAT. Los comprobantes que ya existen se completan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->integer('descuento')->default(0)->after('total');
        });

        DB::table('comprobantes')->whereIn('id', DB::table('comprobante_items')->select('comprobante_id'))
            ->orderBy('id')->each(function ($c) {
                $lineas = (int) DB::table('comprobante_items')->where('comprobante_id', $c->id)->sum('subtotal');
                if ($lineas > abs($c->total)) {
                    DB::table('comprobantes')->where('id', $c->id)->update(['descuento' => $lineas - abs($c->total)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropColumn('descuento');
        });
    }
};
