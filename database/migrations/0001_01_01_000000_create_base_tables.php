<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base de la plataforma: negocios (cada cliente de la plataforma), sus módulos,
 * los permisos, los roles y los usuarios que entran con rol, usuario y PIN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('negocios', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('nombre');
            $table->string('giro')->nullable();
            $table->string('titular')->nullable();
            $table->string('ruc', 11)->nullable();
            $table->string('direccion')->nullable();
            $table->string('celular', 20)->nullable();
            $table->string('ciudad')->nullable();
            $table->string('rubro', 20)->default('imprenta');   // imprenta, libreria, bodega, servicios, otro
            $table->json('ajustes')->nullable();                // meta, ticket, cobros, facturación, seguridad…
            $table->longText('logo')->nullable();               // imagen en data URL (como en el sistema actual)
            $table->longText('qr_yape')->nullable();
            $table->longText('qr_plin')->nullable();
            $table->timestamp('ultima_copia_at')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('modulos_negocio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->string('modulo', 30);
            $table->boolean('activo')->default(true);
            $table->unique(['negocio_id', 'modulo']);
        });

        Schema::create('permisos', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 30)->unique();
            $table->string('nombre');
            $table->string('tipo', 10);   // modulo | accion
            $table->unsignedSmallInteger('orden')->default(0);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->string('uid', 40);                       // id del sistema anterior (admin, vendedor, r_xxx)
            $table->string('nombre');
            $table->boolean('es_admin')->default(false);     // puede todo, no se edita
            $table->unsignedInteger('desc_max')->nullable(); // tope de descuento sin autorización, en céntimos
            $table->unsignedTinyInteger('desc_pct')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
            $table->unique(['negocio_id', 'uid']);
        });

        Schema::create('permiso_rol', function (Blueprint $table) {
            $table->foreignId('rol_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permiso_id')->constrained('permisos')->cascadeOnDelete();
            $table->primary(['rol_id', 'permiso_id']);
        });

        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rol_id')->constrained('roles');
            $table->string('uid', 40);
            $table->string('nombre');
            $table->string('usuario', 40);                 // con lo que entra: alex, jeremy…
            $table->string('pin')->nullable();             // hash de Laravel (bcrypt)
            $table->string('pin_legado')->nullable();      // hash PBKDF2 o SHA-256 del sistema anterior: se cambia al entrar
            $table->string('pin_sal', 64)->nullable();
            $table->unsignedTinyInteger('pin_largo')->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('intentos_fallidos')->default(0);
            $table->timestamp('bloqueado_hasta')->nullable();
            $table->timestamp('ultimo_ingreso_at')->nullable();
            $table->rememberToken();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
            $table->unique(['negocio_id', 'usuario']);
            $table->unique(['negocio_id', 'uid']);
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('permiso_rol');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permisos');
        Schema::dropIfExists('modulos_negocio');
        Schema::dropIfExists('negocios');
    }
};
