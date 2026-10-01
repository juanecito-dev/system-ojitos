<?php

use App\Livewire\Caja;
use App\Models\CajaMovimiento;
use App\Models\TurnoCaja;
use App\Services\CajaDia;
use App\Services\Ventas;
use Livewire\Livewire;

beforeEach(fn () => negocioDePrueba());

it('cada usuario abre y cierra su caja, y el cuadre sale bien', function () {
    entrarComo('jeremy');
    $c = Livewire::test(Caja::class)->set('inicial', '50')->call('abrirCaja');
    $t = TurnoCaja::first();
    expect($t->inicial)->toBe(5000);

    $this->travel(1)->seconds();
    app(Ventas::class)->registrar(usuario('jeremy'), ['lineas' => [['producto_id' => producto('bn_a4')->id, 'nombre' => 'B/N A4', 'cantidad' => 20, 'precio' => 15]], 'metodo' => 'efectivo']);
    app(Ventas::class)->registrar(usuario('jeremy'), ['lineas' => [['producto_id' => producto('bn_a4')->id, 'nombre' => 'B/N A4', 'cantidad' => 20, 'precio' => 15]], 'metodo' => 'yape']);
    $c->call('abrirMovimiento', 'gasto')->set('monto', '1.00')->set('concepto', 'Papel')->call('guardarMovimiento');
    expect(CajaMovimiento::first()->monto)->toBe(100);

    $calc = (new CajaDia(today()->toDateString()))->turno($t->fresh());
    expect($calc['esperado'])->toBe(5000 + 300 - 100);   // la venta por Yape no entra al efectivo

    $c->set('contado.'.$t->id, '52.00')->call('pedirCierre', $t->id)->call('cerrarCaja');
    expect($t->fresh()->contado)->toBe(5200)->and($t->fresh()->esperado)->toBe(5200);
});

it('registrar un gasto sin permiso pide autorización', function () {
    $j = entrarComo('jeremy');
    $j->rol->sincronizarPermisos(['vender', 'caja']);
    $c = Livewire::test(Caja::class)->call('abrirMovimiento', 'gasto')->set('monto', '5')->call('guardarMovimiento');
    expect($c->get('autz')['permiso'])->toBe('gastos')->and(CajaMovimiento::count())->toBe(0);
    $c->set('autzPin', '2580')->call('confirmarAutorizacion');
    expect(CajaMovimiento::count())->toBe(1)->and(CajaMovimiento::first()->vendedor)->toBe('Jeremy');
});

it('avisa del efectivo vendido sin caja abierta', function () {
    entrarComo('alex');
    app(Ventas::class)->registrar(usuario('jeremy'), ['lineas' => [['producto_id' => producto('bn_a4')->id, 'nombre' => 'B/N A4', 'cantidad' => 20, 'precio' => 15]], 'metodo' => 'efectivo']);
    $f = (new CajaDia(today()->toDateString()))->sinTurno();
    expect($f['total'])->toBe(300)->and($f['quien'])->toBe(['Jeremy']);
    Livewire::test(Caja::class)->assertSee('sin caja abierta');
});
