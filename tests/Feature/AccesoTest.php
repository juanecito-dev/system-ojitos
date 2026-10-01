<?php

use App\Livewire\Ajustes;
use App\Livewire\Usuarios;
use App\Models\ModuloNegocio;
use App\Models\Producto;
use App\Models\Usuario;
use App\Models\Venta;
use App\Services\Ventas;
use App\Support\NegocioActual;
use Livewire\Livewire;

it('cada negocio ve solo sus datos', function () {
    $a = negocioDePrueba('ojitos');
    app(Ventas::class)->registrar(usuario('alex'), ['lineas' => [['producto_id' => producto('bn_a4')->id, 'nombre' => 'B/N A4', 'cantidad' => 2, 'precio' => 15]], 'metodo' => 'efectivo']);
    $b = negocioDePrueba('libreria');   // queda como negocio actual

    expect(Venta::count())->toBe(0)
        ->and(Producto::count())->toBe(Producto::withoutGlobalScopes()->where('negocio_id', $b->id)->count())
        ->and(Usuario::where('usuario', 'alex')->count())->toBe(1);

    app(NegocioActual::class)->set($a);
    expect(Venta::count())->toBe(1);
});

it('un vendedor no entra a Usuarios y roles', function () {
    negocioDePrueba();
    entrarComo('jeremy');
    $this->get('/usuarios')->assertRedirect(route('inicio'));
    $this->get('/ajustes')->assertRedirect(route('inicio'));
});

it('un módulo apagado desaparece aunque el rol lo tenga', function () {
    $neg = negocioDePrueba();
    ModuloNegocio::where('negocio_id', $neg->id)->where('modulo', 'caja')->update(['activo' => false]);
    entrarComo('jeremy');
    $this->get('/caja')->assertRedirect(route('inicio'));
});

it('el vendedor solo ve sus ventas si no tiene «ver todo»', function () {
    negocioDePrueba();
    $j = usuario('jeremy');
    $j->rol->sincronizarPermisos(['vender', 'ventas']);
    $v = app(Ventas::class)->registrar(usuario('alex'), ['lineas' => [['producto_id' => producto('bn_a4')->id, 'nombre' => 'B/N A4', 'cantidad' => 2, 'precio' => 15]], 'metodo' => 'efectivo']);
    $this->actingAs($j);
    $this->get('/ventas')->assertOk()->assertDontSee('Alex Simon Santiago');
    $this->get(route('ticket.pdf', $v->uid))->assertForbidden();
});

it('el administrador crea un usuario y no puede quedarse sin administradores', function () {
    negocioDePrueba();
    $alex = entrarComo('alex');
    Livewire::test(Usuarios::class)->call('nuevoUsuario')->set('u.nombre', 'Carlos Ríos')->set('u.pin', '4826')->set('u.pin2', '4826')
        ->call('guardarUsuario')->assertSet('error', '');
    expect(usuario('carlos')->rol->uid)->toBe('vendedor');

    Livewire::test(Usuarios::class)->call('nuevoUsuario')->set('u.nombre', 'Otro')->set('u.pin', '1234')->set('u.pin2', '1234')
        ->call('guardarUsuario')->assertSet('error', 'Ese PIN es muy fácil de adivinar (como 1111 o 1234). Elige otro.');

    Livewire::test(Usuarios::class)->call('pedirDesactivar', $alex->id)->assertSet('desactivando', null);
    expect($alex->fresh()->activo)->toBeTrue();
});

it('en Configuración no se puede abrir una pestaña sin permiso', function () {
    negocioDePrueba();
    $j = entrarComo('jeremy');
    $j->rol->sincronizarPermisos(['vender', 'ajustes', 'precios']);
    Livewire::test(Ajustes::class)->assertSet('pestana', 'productos')
        ->set('pestana', 'datos')->assertSet('pestana', 'productos')
        ->call('fijar', 'nombre', 'Otro')->assertForbidden();
});
