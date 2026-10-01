<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Productos y precios, insumos por servicio, stock (último conteo + movimientos)
 * y máquinas con sus contadores. Los montos siempre en céntimos (enteros).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->string('uid', 60);                     // id del sistema anterior: bn_a4, u_lapicero…
            $table->string('grupo');
            $table->string('nombre');
            $table->string('color', 12)->default('t-otro'); // t-bn, t-color, t-foto, t-acab, t-util, t-otro
            $table->boolean('rapido')->default(false);      // un toque suma 1
            $table->boolean('monto_libre')->default(false); // se puede escribir el precio
            $table->boolean('pide_detalle')->default(false);
            $table->boolean('es_tasa')->default(false);     // puede llevar tasa pagada a terceros
            $table->boolean('favorito')->default(false);
            $table->boolean('oculto')->default(false);      // insumo: no se vende en la caja
            $table->unsignedInteger('costo')->nullable();
            $table->decimal('stock_minimo', 12, 3)->nullable();
            $table->string('unidad', 20)->nullable();
            $table->string('codigos_barra')->nullable();    // separados por espacio
            $table->unsignedInteger('orden')->default(0);
            $table->json('extra')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['negocio_id', 'uid']);
            $table->index(['negocio_id', 'grupo']);
        });

        Schema::create('producto_opciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained()->cascadeOnDelete();
            $table->string('etiqueta')->default('');       // Poco, Medio, Lleno, Full, Pequeño…
            $table->unsignedInteger('precio');
            $table->unsignedSmallInteger('orden')->default(0);
        });

        Schema::create('producto_insumos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insumo_id')->constrained('productos')->cascadeOnDelete();
            $table->decimal('cantidad', 14, 6);            // por cada 1 vendido
        });

        Schema::create('stock_bases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained()->cascadeOnDelete();
            $table->decimal('cantidad', 14, 3);
            $table->timestamp('desde');                    // desde cuándo vale el conteo
            $table->unique('producto_id');
        });

        Schema::create('stock_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->string('uid', 60);
            $table->foreignId('producto_id')->nullable()->constrained()->nullOnDelete();
            $table->string('producto_uid', 60)->nullable();
            $table->string('nombre')->nullable();
            $table->string('tipo', 10);                    // entrada, salida, conteo
            $table->decimal('cantidad', 14, 3);
            $table->decimal('diferencia', 14, 3)->nullable();
            $table->unsignedInteger('costo')->nullable();
            $table->string('motivo')->nullable();
            $table->string('nota')->nullable();
            $table->string('compra_uid', 60)->nullable();
            $table->string('pedido_uid', 60)->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('vendedor')->nullable();
            $table->timestamp('ocurrido_at');
            $table->json('extra')->nullable();
            $table->timestamps();
            $table->unique(['negocio_id', 'uid']);
            $table->index(['producto_id', 'ocurrido_at']);
        });

        Schema::create('maquinas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->string('uid', 60);
            $table->string('nombre');
            $table->json('contadores');                    // [{id, n, tipo: bn|color|total}]
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
            $table->unique(['negocio_id', 'uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maquinas');
        Schema::dropIfExists('stock_movimientos');
        Schema::dropIfExists('stock_bases');
        Schema::dropIfExists('producto_insumos');
        Schema::dropIfExists('producto_opciones');
        Schema::dropIfExists('productos');
    }
};
