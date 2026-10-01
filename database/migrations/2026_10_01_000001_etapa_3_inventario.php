<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 3, inventario: los costos aceptan fracciones de céntimo (una hoja de un millar de S/ 25
 * cuesta 2.5 céntimos, como en el sistema anterior) y la toma de inventario se guarda en el servidor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', fn (Blueprint $t) => $t->decimal('costo', 14, 4)->nullable()->change());
        Schema::table('venta_items', fn (Blueprint $t) => $t->decimal('costo', 14, 4)->nullable()->change());
        Schema::table('stock_movimientos', fn (Blueprint $t) => $t->decimal('costo', 14, 4)->nullable()->change());
        // «1 tóner rinde 7000 copias» necesita más decimales para volver a mostrar 7000
        Schema::table('producto_insumos', fn (Blueprint $t) => $t->decimal('cantidad', 20, 10)->change());

        // lo contado en la toma de inventario que todavía no se aplica (sirve desde cualquier equipo)
        Schema::create('toma_conteos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained()->cascadeOnDelete();
            $table->decimal('cantidad', 14, 3);
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('vendedor')->nullable();
            $table->timestamps();
            $table->unique('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('toma_conteos');
        Schema::table('producto_insumos', fn (Blueprint $t) => $t->decimal('cantidad', 14, 6)->change());
        Schema::table('stock_movimientos', fn (Blueprint $t) => $t->unsignedInteger('costo')->nullable()->change());
        Schema::table('venta_items', fn (Blueprint $t) => $t->integer('costo')->nullable()->change());
        Schema::table('productos', fn (Blueprint $t) => $t->unsignedInteger('costo')->nullable()->change());
    }
};
