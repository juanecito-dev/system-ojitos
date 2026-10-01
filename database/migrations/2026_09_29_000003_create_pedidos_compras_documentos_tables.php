<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas de las etapas 2 a 4 (pedidos, compras, redacción). Ya se crean ahora
 * para que el importador guarde todo lo de la copia; «datos» conserva el objeto
 * original completo para no perder ningún detalle hasta que se haga cada módulo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->string('uid', 60);
            $table->unsignedInteger('numero')->nullable();
            $table->string('etapa', 12)->default('cotizado'); // cotizado, proceso, listo, entregado, rechazado
            $table->foreignId('cliente_id')->nullable()->constrained()->nullOnDelete();
            $table->json('cliente')->nullable();           // {nombre, doc, cel, dir, inst}
            $table->text('detalle')->nullable();
            $table->integer('total')->default(0);
            $table->integer('descuento')->default(0);
            $table->date('fecha');
            $table->date('fecha_entrega')->nullable();
            $table->string('hora_entrega', 10)->nullable();
            $table->timestamp('entregado_at')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('vendedor')->nullable();
            $table->timestamp('creado_at');
            $table->json('datos')->nullable();
            $table->timestamps();
            $table->unique(['negocio_id', 'uid']);
            $table->index(['negocio_id', 'etapa']);
        });

        Schema::create('pedido_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained()->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained()->nullOnDelete();
            $table->string('producto_uid', 60)->nullable();
            $table->string('nombre');
            $table->string('detalle')->nullable();
            $table->decimal('cantidad', 12, 3);
            $table->integer('precio');
            $table->integer('subtotal');
            $table->unsignedSmallInteger('orden')->default(0);
        });

        Schema::create('pedido_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained()->cascadeOnDelete();
            $table->string('uid', 60);
            $table->integer('monto');
            $table->string('metodo', 20)->nullable();
            $table->string('tipo', 20)->nullable();        // Adelanto, Saldo, Venta
            $table->string('venta_uid', 60)->nullable();
            $table->date('fecha')->nullable();
            $table->string('vendedor')->nullable();
            $table->timestamp('pagado_at');
        });

        Schema::create('plantillas_utiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->string('uid', 60);
            $table->string('nombre');
            $table->string('institucion')->nullable();
            $table->json('items');
            $table->timestamps();
            $table->unique(['negocio_id', 'uid']);
        });

        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->string('uid', 60);
            $table->string('nombre');
            $table->string('ruc', 11)->nullable();
            $table->string('celular', 20)->nullable();
            $table->json('extra')->nullable();
            $table->timestamps();
            $table->unique(['negocio_id', 'uid']);
        });

        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->string('uid', 60);
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->string('numero', 40)->nullable();
            $table->date('fecha');
            $table->integer('total');
            $table->string('condicion', 10)->default('contado'); // contado | credito
            $table->date('vence')->nullable();
            $table->string('nota')->nullable();
            $table->json('extra')->nullable();
            $table->timestamps();
            $table->unique(['negocio_id', 'uid']);
            $table->index(['negocio_id', 'fecha']);
        });

        Schema::create('compra_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained()->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained()->nullOnDelete();
            $table->string('producto_uid', 60)->nullable();
            $table->string('nombre');
            $table->decimal('cantidad', 12, 3);
            $table->string('unidad', 20)->nullable();
            $table->decimal('factor', 12, 3)->default(1);  // unidades por empaque
            $table->integer('costo_unitario');
            $table->json('extra')->nullable();
        });

        Schema::create('compra_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained()->cascadeOnDelete();
            $table->integer('monto');
            $table->string('metodo', 20)->nullable();
            $table->string('caja_mov_uid', 60)->nullable();
            $table->timestamp('pagado_at');
        });

        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->string('uid', 60);
            $table->string('plantilla', 60)->nullable();
            $table->string('titulo')->nullable();
            $table->string('estado', 12)->default('borrador'); // borrador (sin cobrar), cobrado, entregado
            $table->foreignId('cliente_id')->nullable()->constrained()->nullOnDelete();
            $table->string('venta_uid', 60)->nullable();
            $table->json('datos')->nullable();
            $table->longText('html')->nullable();
            $table->timestamps();
            $table->unique(['negocio_id', 'uid']);
        });

        Schema::create('modelos_documento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->string('uid', 60);
            $table->string('nombre');
            $table->json('datos');
            $table->timestamps();
            $table->unique(['negocio_id', 'uid']);
        });
    }

    public function down(): void
    {
        foreach (['modelos_documento', 'documentos', 'compra_pagos', 'compra_items', 'compras', 'proveedores',
            'plantillas_utiles', 'pedido_pagos', 'pedido_items', 'pedidos'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
