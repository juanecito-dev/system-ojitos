<?php

use App\Livewire\Entrar;
use App\Models\Actividad;
use Livewire\Livewire;

beforeEach(fn () => negocioDePrueba());

it('muestra la pantalla de inicio de sesión', function () {
    $this->withUnencryptedCookie('negocio', 'ojitos')->get('/entrar')->assertOk()->assertSee('Iniciar sesión')->assertSee('Rol')->assertSee('PIN');
});

it('con varios negocios posibles, la entrada pide el código; en la PC de un solo negocio va directo', function () {
    config(['ojitos.negocio_unico' => false]);
    $this->get('/entrar')->assertOk()->assertSee('Código del negocio')->assertDontSee('Rol');

    config(['ojitos.negocio_unico' => true]);
    $this->get('/entrar')->assertOk()->assertSee('Rol')->assertSee('PIN');
});

it('entra con rol, usuario y PIN correctos', function () {
    Livewire::test(Entrar::class)->set('rol', 'admin')->set('usuario', 'Alex')->set('pin', '2580')
        ->call('ingresar')->assertRedirect(route('inicio'));
    $this->assertAuthenticatedAs(usuario('alex'));
    expect(Actividad::where('tipo', 'entrada')->count())->toBe(1);
});

it('no entra con el rol equivocado', function () {
    Livewire::test(Entrar::class)->set('rol', 'vendedor')->set('usuario', 'alex')->set('pin', '2580')
        ->call('ingresar')->assertSet('error', 'Usuario, rol o PIN incorrectos.');
    $this->assertGuest();
});

it('bloquea el PIN después de 5 intentos fallidos', function () {
    $c = Livewire::test(Entrar::class)->set('rol', 'vendedor');
    foreach (range(1, 5) as $i) {
        $c->set('usuario', 'jeremy')->set('pin', '9999')->call('ingresar');
    }
    expect($c->get('error'))->toContain('Demasiados intentos');
    // ni con el PIN correcto mientras dure el bloqueo
    $c->set('pin', '1470')->call('ingresar');
    $this->assertGuest();
    expect(usuario('jeremy')->bloqueado_hasta)->not->toBeNull()
        ->and(Actividad::where('tipo', 'bloqueo')->count())->toBe(1);
});

it('un usuario desactivado no puede entrar', function () {
    usuario('jeremy')->update(['activo' => false]);
    Livewire::test(Entrar::class)->set('rol', 'vendedor')->set('usuario', 'jeremy')->set('pin', '1470')->call('ingresar');
    $this->assertGuest();
});

it('pide entrar para ver el sistema', function () {
    $this->get('/')->assertRedirect(route('login'));
});
