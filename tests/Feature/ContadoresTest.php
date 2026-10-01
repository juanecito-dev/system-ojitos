<?php

use App\Livewire\AjustesMaquinas;
use App\Livewire\CajaContadores;
use App\Livewire\Inicio;
use App\Models\LecturaContador;
use App\Models\Maquina;
use App\Services\Contadores;
use App\Services\ErrorNegocio;
use App\Services\Ventas;
use Livewire\Livewire;

beforeEach(fn () => negocioDePrueba());

/** una máquina con un contador B/N, configurada desde Configuración › Máquinas */
function unaMaquina(): Maquina
{
    entrarComo('alex');
    Livewire::test(AjustesMaquinas::class)->call('agregar');

    return Maquina::first();
}

function venderCopias(int $copias, int $precio = 15): void
{
    app(Ventas::class)->registrar(usuario('jeremy'), ['lineas' => [['producto_id' => producto('bn_a4')->id, 'nombre' => 'B/N A4', 'cantidad' => $copias, 'precio' => $precio]], 'metodo' => 'efectivo']);
}

it('agregar una máquina pone los productos que cuentan por defecto', function () {
    $m = unaMaquina();
    expect($m->nombre)->toBe('Máquina 1')->and($m->contadores[0]['tipo'])->toBe('bn')
        ->and(app(Contadores::class)->prods())->toHaveKeys(['bn_a4', 'bn_a3', 'col_a4', 'col_a3']);
});

it('el cuadre compara el contador con las copias cobradas y las de prueba', function () {
    $m = unaMaquina();
    $c = $m->contadores[0]['id'];
    $cont = app(Contadores::class);
    $cont->anotar(today()->subDay()->toDateString(), $m->uid, $c, 1000);

    venderCopias(80);
    $cont->merma(today()->toDateString(), 'bn', 5);
    $cont->anotar(today()->toDateString(), $m->uid, $c, 1100);

    $o = app(Contadores::class)->cuadre(today()->toDateString())['bn'];
    expect($o['usado'])->toBe(100)->and($o['esperado'])->toBe(80)->and($o['merma'])->toBe(5)
        ->and($o['dif'])->toBe(15)->and($o['listo'])->toBeTrue()
        ->and($o['monto'])->toBe(225);   // 15 copias a S/ 0.15
});

it('la primera lectura no se compara y falta la de los otros contadores', function () {
    $m = unaMaquina();
    $o = app(Contadores::class)->cuadre(today()->toDateString())['bn'];
    expect($o['listo'])->toBeFalse()->and($o['falta'])->toBe(['Máquina 1']);

    app(Contadores::class)->anotar(today()->toDateString(), $m->uid, $m->contadores[0]['id'], 500);
    $o = app(Contadores::class)->cuadre(today()->toDateString())['bn'];
    expect($o['primero'])->toBe(['Máquina 1'])->and($o['listo'])->toBeFalse();
});

it('no acepta una lectura menor y pide confirmar un salto grande', function () {
    $m = unaMaquina();
    $c = $m->contadores[0]['id'];
    app(Contadores::class)->anotar(today()->subDay()->toDateString(), $m->uid, $c, 1000);
    $key = $m->uid.':'.$c;

    $w = Livewire::test(CajaContadores::class, ['dia' => today()->toDateString()])->set('lectura.'.$key, '900')->call('guardar', $key);
    expect(LecturaContador::where('fecha', today()->toDateString())->count())->toBe(0);

    $w->set('lectura.'.$key, '50.000')->call('guardar', $key);
    expect($w->get('confirmar')['valor'])->toBe('50000')->and(LecturaContador::where('fecha', today()->toDateString())->count())->toBe(0);
    $w->call('guardar', $key, true);
    expect(LecturaContador::where('fecha', today()->toDateString())->first()->valor)->toBe(50000);

    expect(fn () => app(Contadores::class)->anotar(today()->toDateString(), $m->uid, $c, 999))->toThrow(ErrorNegocio::class);
});

it('corregir la lectura del día usa la misma lectura anterior', function () {
    $m = unaMaquina();
    $c = $m->contadores[0]['id'];
    $cont = app(Contadores::class);
    $cont->anotar(today()->subDay()->toDateString(), $m->uid, $c, 1000);
    $cont->anotar(today()->toDateString(), $m->uid, $c, 1200);
    $this->travel(1)->minutes();
    $cont->anotar(today()->toDateString(), $m->uid, $c, 1150);

    expect(app(Contadores::class)->cuadre(today()->toDateString())['bn']['usado'])->toBe(150);
});

it('solo el administrador ve el cuadre y las copias sin cobrar en Inicio', function () {
    $m = unaMaquina();
    $c = $m->contadores[0]['id'];
    app(Contadores::class)->anotar(today()->subDay()->toDateString(), $m->uid, $c, 1000);
    app(Contadores::class)->anotar(today()->toDateString(), $m->uid, $c, 1040);
    venderCopias(30);

    Livewire::test(CajaContadores::class, ['dia' => today()->toDateString()])->assertSee('10 sin cobrar');
    Livewire::test(Inicio::class)->assertSee('Copias sin cobrar (7 días)')->assertSee('S/ 1.50');

    entrarComo('jeremy');
    Livewire::test(CajaContadores::class, ['dia' => today()->toDateString()])->assertDontSee('sin cobrar')->assertSee('Corregir lectura');
    Livewire::test(Inicio::class)->assertDontSee('Copias sin cobrar');
});

it('los días pasados solo se miran y el historial muestra las copias de cada día', function () {
    $m = unaMaquina();
    $c = $m->contadores[0]['id'];
    $cont = app(Contadores::class);
    $cont->anotar(today()->subDays(2)->toDateString(), $m->uid, $c, 1000);
    $cont->anotar(today()->subDay()->toDateString(), $m->uid, $c, 1300);

    $key = $m->uid.':'.$c;
    Livewire::test(CajaContadores::class, ['dia' => today()->subDay()->toDateString()])
        ->assertDontSee('Lectura al cerrar')->set('lectura.'.$key, '1400')->call('guardar', $key);
    expect(LecturaContador::count())->toBe(2);

    $h = app(Contadores::class)->historial($m);
    expect($h[0]['total'])->toBe(300)->and($h[1]['conts'][$c]['copias'])->toBeNull();
});

it('con un contador total, todos los productos cuentan juntos', function () {
    $m = unaMaquina();
    Livewire::test(AjustesMaquinas::class)->call('contador', $m->id, $m->contadores[0]['id'], 'tipo', 'total');
    expect(app(Contadores::class)->grupos())->toBe(['total'])
        ->and(collect(app(Contadores::class)->prods())->pluck('g')->unique()->all())->toBe(['total']);

    Livewire::test(AjustesMaquinas::class)->call('contador', $m->id, $m->contadores[0]['id'], 'tipo', 'bn');
    expect(app(Contadores::class)->prods()['col_a4']['g'])->toBe('color');
});

it('un vendedor no puede cambiar las máquinas', function () {
    entrarComo('jeremy');
    Livewire::test(AjustesMaquinas::class)->assertForbidden();
});
