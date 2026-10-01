<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 4, redacción: los modelos del sistema se guardan como datos (vienen de database/modelos y se
 * sincronizan con «php artisan ojitos:modelos»), y cada documento guarda su texto, sus partes y su estado.
 * El texto usa un formato simple por líneas: «[title] …», «[p] …», «[li] …», «[firma] NOMBRE | DNI | rol».
 */
return new class extends Migration
{
    public function up(): void
    {
        // modelos del sistema, iguales para todos los negocios (los propios de cada negocio van en modelos_documento)
        Schema::create('modelos_redaccion', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 60)->unique();          // solicitud, carta_poder, cv_terreno…
            $table->string('paquete', 40);                 // grupo por rubro: tramites, inmobiliario…
            $table->string('seccion', 20);                 // contratos, cartas, declaraciones, laboral, cv
            $table->string('nombre');
            $table->string('icono', 16)->nullable();
            $table->string('descripcion')->nullable();
            $table->text('nota')->nullable();
            $table->string('cobro', 10)->default('doc');   // pagina | doc | cv
            $table->boolean('niveles')->default(false);    // contrato con nivel simple, intermedio o avanzado
            $table->unsignedSmallInteger('orden')->default(0);
            $table->json('definicion');                    // campos, cláusulas y plantilla
            $table->string('version', 40);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('documentos', function (Blueprint $table) {
            $table->longText('texto')->nullable()->after('html');        // el documento en el formato por líneas
            $table->string('partes')->nullable()->after('titulo');
            $table->string('busqueda', 400)->nullable()->after('partes');
            $table->unsignedTinyInteger('nivel')->nullable()->after('plantilla');
            $table->unsignedSmallInteger('paginas')->nullable()->after('nivel');
            $table->string('encargo', 12)->nullable()->after('estado');  // pendiente (por redactar) | listo (para revisar)
            $table->text('contexto')->nullable()->after('encargo');      // lo que contó el cliente, para «Encargar a Claude»
            $table->foreignId('usuario_id')->nullable()->after('venta_uid')->constrained('usuarios')->nullOnDelete();
            $table->string('vendedor')->nullable()->after('usuario_id');
            $table->timestamp('cobrado_at')->nullable()->after('vendedor');
            $table->timestamp('entregado_at')->nullable()->after('cobrado_at');
            $table->index(['negocio_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropIndex(['negocio_id', 'estado']);
            $table->dropConstrainedForeignId('usuario_id');
            $table->dropColumn(['texto', 'partes', 'busqueda', 'nivel', 'paginas', 'encargo', 'contexto', 'vendedor', 'cobrado_at', 'entregado_at']);
        });
        Schema::dropIfExists('modelos_redaccion');
    }
};
