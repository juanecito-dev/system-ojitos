<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El día a día: clientes y fiados, ventas, caja (turnos y movimientos),
 * comprobantes, lecturas de contadores, actividad y numeraciones.
 * «fecha» es el día del negocio (hora de Lima); «uid» es el id del sistema anterior
 * o uno nuevo, y sirve para que el importador no duplique nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->string('uid', 60);
            $table->string('nombre');
            $table->string('celular', 20)->nullable();
            $table->string('documento', 15)->nullable();   // DNI o RUC
            $table->string('direccion')->nullable();
            $table->string('nota')->nullable();
            $table->unsignedInteger('visitas')->default(0);
            $table->unsignedBigInteger('gastado')->default(0);
            $table->timestamp('ultima_visita_at')->nullable();
            $table->json('alias')->nullable();             // ids de clientes unidos a este
            $table->json('extra')->nullable();
            $table->timestamps();
            $table->unique(['negocio_id', 'uid']);
            $table->index(['negocio_id', 'celular']);
        });

        Schema::create('dias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->date('fecha');
            $table->boolean('cierre')->default(false);     // boleta de cierre de ventas menores ya emitida
            $table->string('cierre_comprobante_uid', 60)->nullable();
            $table->integer('caja_inicial')->nullable();   // forma antigua: una sola caja para todos
            $table->json('caja_arqueo')->nullable();
            $table->timestamps();
            $table->unique(['negocio_id', 'fecha']);
        });

        Schema::create('turnos_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->string('uid', 60);
            $table->date('fecha');
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('vendedor')->nullable();
            $table->timestamp('abre_at');
            $table->timestamp('cierra_at')->nullable();
            $table->integer('inicial')->default(0);
            $table->integer('contado')->nullable();
            $table->integer('esperado')->nullable();
            $table->string('cerro_por')->nullable();
            $table->json('correcciones')->nullable();      // cambios del sencillo inicial
            $table->timestamps();
            $table->unique(['negocio_id', 'uid']);
            $table->index(['negocio_id', 'fecha']);
        });

        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->string('uid', 60);
            $table->date('fecha');
            $table->string('numero', 20)->nullable();      // nota de venta por usuario: A1-001
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('vendedor')->nullable();
            $table->foreignId('cliente_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('total');                      // lo cobrado por la venta (sin la deuda que pagó)
            $table->integer('descuento')->default(0);
            $table->string('metodo', 20)->default('efectivo');
            $table->integer('pago')->nullable();           // con cuánto pagó (para el vuelto)
            $table->integer('abono')->default(0);          // deuda anterior que pagó junto con esta venta
            $table->boolean('boleta')->default(false);     // ya tiene comprobante emitido
            $table->string('comprobante_uid', 60)->nullable();
            $table->date('comprobante_fecha')->nullable();
            $table->string('comprobante_numero', 30)->nullable();
            $table->json('comprobante_pedido')->nullable(); // {tipo, pide, doc:{td, nd, nom, dir}}
            $table->boolean('al_cierre')->default(false);   // regularizada en la boleta de cierre
            $table->string('pedido_uid', 60)->nullable();
            $table->timestamp('vendida_at');
            $table->json('extra')->nullable();
            $table->timestamps();
            $table->unique(['negocio_id', 'uid']);
            $table->index(['negocio_id', 'fecha']);
            $table->index(['negocio_id', 'usuario_id', 'fecha']);
        });

        Schema::create('venta_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained()->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained()->nullOnDelete();
            $table->string('producto_uid', 60)->nullable();
            $table->string('nombre');
            $table->string('detalle')->nullable();
            $table->decimal('cantidad', 12, 3);
            $table->integer('precio');
            $table->integer('precio_lista')->nullable();   // precio normal si se cambió al cobrar
            $table->integer('subtotal');
            $table->integer('costo')->nullable();
            $table->boolean('tercero')->default(false);    // tasa pagada a terceros: no es ingreso propio
            $table->unsignedSmallInteger('orden')->default(0);
        });

        Schema::create('ventas_anuladas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->string('uid', 60);
            $table->date('fecha');
            $table->string('venta_uid', 60)->nullable();
            $table->string('numero', 20)->nullable();
            $table->timestamp('venta_at')->nullable();
            $table->integer('total')->default(0);
            $table->string('detalle')->nullable();
            $table->string('motivo')->nullable();
            $table->string('tipo', 10)->default('anulada'); // anulada | deshecha
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('por')->nullable();
            $table->string('vendedor_original')->nullable();
            $table->timestamp('anulada_at');
            $table->json('venta')->nullable();             // copia completa de la venta
            $table->timestamps();
            $table->unique(['negocio_id', 'uid']);
            $table->index(['negocio_id', 'fecha']);
        });

        Schema::create('caja_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->string('uid', 60);
            $table->date('fecha');
            $table->string('tipo', 10);                    // gasto, ingreso, retiro
            $table->string('concepto');
            $table->string('nota')->nullable();
            $table->integer('monto');
            $table->string('metodo', 20)->default('efectivo');
            $table->string('referencia', 60)->nullable();  // venta o compra relacionada
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('vendedor')->nullable();
            $table->timestamp('ocurrido_at');
            $table->json('extra')->nullable();
            $table->timestamps();
            $table->unique(['negocio_id', 'uid']);
            $table->index(['negocio_id', 'fecha']);
        });

        Schema::create('cliente_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->string('uid', 60);
            $table->foreignId('cliente_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 10);                    // fiado | abono
            $table->integer('monto');
            $table->string('metodo', 20)->nullable();
            $table->string('detalle')->nullable();
            $table->string('venta_uid', 60)->nullable();
            $table->date('fecha')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('vendedor')->nullable();
            $table->timestamp('ocurrido_at');
            $table->json('extra')->nullable();
            $table->timestamps();
            $table->unique(['negocio_id', 'uid']);
            $table->index(['cliente_id', 'ocurrido_at']);
        });

        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->string('uid', 60);
            $table->date('fecha');
            $table->string('tipo', 2);                     // 03 boleta, 01 factura, 07 NC, 08 ND
            $table->string('serie', 4);
            $table->string('numero', 10)->nullable();
            $table->json('cliente')->nullable();           // {td, nd, nom, dir}
            $table->integer('total');
            $table->integer('gravado')->default(0);
            $table->integer('exonerado')->default(0);
            $table->integer('inafecto')->default(0);
            $table->integer('igv')->default(0);
            $table->string('descripcion', 250)->nullable();
            $table->string('estado', 12)->default('emitido'); // emitido | anulado
            $table->string('modo', 10)->default('manual');
            $table->string('referencia', 60)->nullable();  // comprobante que corrige una nota
            $table->string('motivo')->nullable();
            $table->date('cierre_de')->nullable();         // boleta de cierre de ese día
            $table->json('ventas')->nullable();            // [{k: fecha, id: venta_uid}]
            $table->timestamp('anulado_at')->nullable();
            $table->string('anulado_por')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('vendedor')->nullable();
            $table->timestamp('emitido_at');
            $table->json('extra')->nullable();
            $table->timestamps();
            $table->unique(['negocio_id', 'uid']);
            $table->index(['negocio_id', 'serie', 'numero']);
            $table->index(['negocio_id', 'fecha']);
        });

        Schema::create('lecturas_contador', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->string('uid', 60);
            $table->date('fecha');
            $table->string('tipo', 10);                    // fin | merma
            $table->string('maquina_uid', 60)->nullable();
            $table->string('contador_uid', 60)->nullable();
            $table->string('grupo', 10)->nullable();       // bn, color, total (en mermas)
            $table->unsignedBigInteger('valor');
            $table->unsignedBigInteger('desde')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('vendedor')->nullable();
            $table->timestamp('ocurrido_at');
            $table->timestamps();
            $table->unique(['negocio_id', 'uid']);
            $table->index(['negocio_id', 'fecha']);
        });

        Schema::create('actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->string('uid', 60);
            $table->date('fecha');
            $table->string('tipo', 20);
            $table->string('detalle', 240);
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('vendedor')->nullable();
            $table->string('equipo', 30)->nullable();
            $table->timestamp('ocurrido_at');
            $table->timestamps();
            $table->unique(['negocio_id', 'uid']);
            $table->index(['negocio_id', 'fecha']);
        });

        Schema::create('secuencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->string('clave', 60);                   // pedido, ticket:2026-09-29:A1, ult:EB01…
            $table->unsignedBigInteger('valor')->default(0);
            $table->timestamps();
            $table->unique(['negocio_id', 'clave']);
        });
    }

    public function down(): void
    {
        foreach (['secuencias', 'actividades', 'lecturas_contador', 'comprobantes', 'cliente_movimientos', 'caja_movimientos',
            'ventas_anuladas', 'venta_items', 'ventas', 'turnos_caja', 'dias', 'clientes'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
