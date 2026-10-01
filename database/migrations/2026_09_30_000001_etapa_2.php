<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 2: límite de fiado por cliente, datos de pedidos que antes iban sueltos,
 * historial completo de cada pedido y detalle de productos de cada comprobante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->unsignedInteger('limite_fiado')->nullable()->after('gastado');   // céntimos; null = sin límite
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->unsignedSmallInteger('validez')->default(7)->after('descuento');   // días de la cotización
            $table->string('cond_entrega')->nullable()->after('validez');
            $table->string('cond_pago')->nullable()->after('cond_entrega');
            $table->text('notas')->nullable()->after('cond_pago');
            $table->string('responsable', 40)->nullable()->after('notas');
            $table->boolean('cotizada')->default(false)->after('responsable');
            $table->boolean('directa')->default(false)->after('cotizada');   // se cobró todo de una vez en la caja
            $table->timestamp('aceptado_at')->nullable()->after('entregado_at');
            $table->timestamp('listo_at')->nullable()->after('aceptado_at');
            $table->timestamp('cerrado_at')->nullable()->after('listo_at');
        });

        Schema::create('pedido_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained()->cascadeOnDelete();
            $table->string('que');
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('vendedor')->nullable();
            $table->timestamp('ocurrido_at');
        });

        Schema::create('comprobante_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comprobante_id')->constrained()->cascadeOnDelete();
            $table->string('producto_uid', 60)->nullable();
            $table->string('descripcion');
            $table->decimal('cantidad', 12, 3);
            $table->integer('precio');
            $table->integer('subtotal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobante_items');
        Schema::dropIfExists('pedido_historial');
        Schema::table('pedidos', fn (Blueprint $t) => $t->dropColumn(['validez', 'cond_entrega', 'cond_pago', 'notas', 'responsable', 'cotizada', 'directa', 'aceptado_at', 'listo_at', 'cerrado_at']));
        Schema::table('clientes', fn (Blueprint $t) => $t->dropColumn('limite_fiado'));
    }
};
