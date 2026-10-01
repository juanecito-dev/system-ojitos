<?php

use App\Livewire\Facturacion;
use App\Models\Comprobante;
use App\Models\Dia;
use App\Models\Venta;
use App\Services\Comprobantes;
use App\Services\ErrorNegocio;
use App\Services\Ventas;
use Illuminate\Database\UniqueConstraintViolationException;
use Livewire\Livewire;

beforeEach(fn () => negocioDePrueba('ojitos', ['serieB' => 'EB01', 'serieF' => 'E001']));

function vender(string $uid, int $cant, ?int $precio = null, array $extra = []): Venta
{
    $p = producto($uid);

    return app(Ventas::class)->registrar(usuario('jeremy'), ['metodo' => 'efectivo', 'lineas' => [
        ['producto_id' => $p->id, 'producto_uid' => $p->uid, 'nombre' => $p->nombre, 'cantidad' => $cant, 'precio' => $precio ?? $p->opciones->first()->precio],
    ]] + $extra);
}

function pendiente(string $kind): array
{
    return collect(app(Comprobantes::class)->pendientes())->firstWhere('kind', $kind);
}

it('arma lo que falta emitir: la boleta de cierre del día y las ventas que necesitan la suya', function () {
    vender('bn_a4', 20);                 // S/ 3.00 → cierre
    vender('bn_a4', 10);                 // S/ 1.50 → cierre
    $grande = vender('s_empastado', 1, 2000);
    $pidio = vender('bn_a4', 2, null, ['cpe' => ['pide' => true]]);

    $P = app(Comprobantes::class)->pendientes();
    expect($P)->toHaveCount(3)
        ->and($P[0]['kind'])->toBe('cierre')->and($P[0]['total'])->toBe(450)->and($P[0]['n'])->toBe(2)
        ->and(collect($P)->where('kind', 'venta')->pluck('ids.0')->all())->toBe([$grande->uid, $pidio->uid]);
});

it('registra la boleta de cierre con el detalle de productos y la puede anular', function () {
    vender('bn_a4', 20);
    vender('bn_a4', 10);
    $srv = app(Comprobantes::class);

    $c = $srv->emitirPendiente(pendiente('cierre'), 'eb01', '130', null);
    expect($c->etiqueta())->toBe('EB01-130')->and($c->total)->toBe(450)
        ->and(Venta::where('boleta', true)->count())->toBe(2)
        ->and(Dia::first()->cierre)->toBeTrue()
        ->and($c->items)->toHaveCount(1)->and($c->items->first()->cantidad)->toEqual(30)
        ->and($srv->pendientes())->toBeEmpty()
        ->and($srv->ultimoNumero('EB01'))->toBe(130);

    $srv->anular($c, 'Error en el monto', usuario('alex'));
    expect($c->fresh()->estado)->toBe('anulado')->and(Venta::where('boleta', true)->count())->toBe(0)
        ->and(Dia::first()->cierre)->toBeFalse()
        ->and(pendiente('cierre')['total'])->toBe(450);
});

it('no deja repetir un número ni registrar dos veces la misma venta', function () {
    vender('s_empastado', 1, 2000);
    vender('s_empastado', 1, 2500);
    $srv = app(Comprobantes::class);
    [$a, $b] = $srv->pendientes();
    $srv->emitirPendiente($a, 'EB01', '200', null);

    expect(fn () => $srv->emitirPendiente($b, 'EB01', '200', null))->toThrow(ErrorNegocio::class, 'Ya registraste EB01-200')
        ->and(fn () => $srv->emitirPendiente($a, 'EB01', '201', null))->toThrow(ErrorNegocio::class, 'otro equipo');
});

it('la factura pide RUC válido y razón social; la boleta grande pide documento', function () {
    vender('s_empastado', 1, 80000, ['cpe' => ['tipo' => '01', 'pide' => true, 'doc' => ['td' => '6', 'nd' => '20100070970', 'nom' => 'Supermercados Peruanos SA']]]);
    $srv = app(Comprobantes::class);
    $x = pendiente('venta');

    expect(fn () => $srv->emitirPendiente($x, 'E001', '5', ['td' => '6', 'nd' => '20100070970', 'nom' => '']))->toThrow(ErrorNegocio::class, 'RUC válido');
    $c = $srv->emitirPendiente($x, 'E001', '5', ['td' => '6', 'nd' => '20100070970', 'nom' => 'Supermercados Peruanos SA']);
    expect($c->tipo)->toBe('01')->and($c->cliente['nom'])->toBe('Supermercados Peruanos SA');

    vender('s_empastado', 1, 75000);
    expect(fn () => $srv->emitirPendiente(pendiente('venta'), 'EB01', '9', null))->toThrow(ErrorNegocio::class, 'S/ 700');
});

it('nota de crédito: no pasa el total y sigue al comprobante si se corrige su número', function () {
    $v = vender('s_empastado', 1, 2000);
    $srv = app(Comprobantes::class);
    $c = $srv->emitirPendiente(pendiente('venta'), 'EB01', '300', null);

    $srv->notaCredito($c, 1500, 'Devolución parcial', 'BB01-1', usuario('alex'));
    expect($srv->notasPrevias($c))->toBe(1500)
        ->and(fn () => $srv->notaCredito($c, 600, 'Otra', '', usuario('alex')))->toThrow(ErrorNegocio::class, 'no pasar el total');

    $srv->cambiarNumero($c, 'EB01', '301', usuario('alex'));
    expect($c->fresh()->etiqueta())->toBe('EB01-301')->and($v->fresh()->comprobante_numero)->toBe('EB01-301')
        ->and(Comprobante::where('tipo', '07')->value('referencia'))->toBe('EB01-301')
        ->and($srv->notasPrevias($c->fresh()))->toBe(1500);
});

it('avisa los números que faltan en la serie del mes', function () {
    foreach ([10, 11, 14] as $n) {
        vender('s_empastado', 1, 1000 + $n);
        app(Comprobantes::class)->emitirPendiente(pendiente('venta'), 'EB01', (string) $n, null);
    }

    // la nota de crédito lleva su propio correlativo: no cuenta como salto ni choca con la boleta 1
    $srv = app(Comprobantes::class);
    $srv->notaCredito(Comprobante::first(), 100, 'Descuento', 'EB01-1', usuario('alex'));
    expect($srv->saltos(today()->format('Y-m')))->toBe(['EB01' => [12, 13]])
        ->and($srv->ultimoNumero('EB01'))->toBe(14)->and($srv->ultimoNumero('EB01', true))->toBe(1)
        ->and(fn () => $srv->notaCredito(Comprobante::first(), 100, 'Otra', 'EB01-1', usuario('alex')))->toThrow(ErrorNegocio::class, 'ya está registrado');
});

it('la guía registra uno tras otro y se puede saltar', function () {
    entrarComo('jeremy');
    vender('s_empastado', 1, 2000);
    vender('s_empastado', 1, 3000);
    vender('s_empastado', 1, 4000);

    $lw = Livewire::test(Facturacion::class)->call('empezarGuia')->assertSet('numero', '1')
        ->set('numero', '50')->call('registrar')
        ->call('saltar')
        ->set('numero', '51')->call('registrar')
        ->assertSet('guia', false)->assertSet('emitiendo', null);

    expect(Comprobante::pluck('total')->all())->toBe([2000, 4000]);
});

it('corregir un número ya puesto pide el PIN de un administrador, aunque sea admin', function () {
    entrarComo('alex');
    vender('s_empastado', 1, 2000);
    $c = app(Comprobantes::class)->emitirPendiente(pendiente('venta'), 'EB01', '7', null);

    $lw = Livewire::test(Facturacion::class, ['tab' => 'emit'])->call('pedirAccion', 'numero', $c->uid);
    expect($lw->get('autz')['soloAdmin'])->toBeTrue()->and($lw->get('accion'))->toBeNull();

    $lw->set('autzPin', '2580')->call('confirmarAutorizacion')->assertSet('accion', 'numero')
        ->set('numero', '8')->call('confirmarAccion');
    expect($c->fresh()->numero)->toBe('8');
});

it('no confirma una acción que no se pidió antes', function () {
    entrarComo('alex');
    vender('s_empastado', 1, 2000);
    $c = app(Comprobantes::class)->emitirPendiente(pendiente('venta'), 'EB01', '7', null);

    Livewire::test(Facturacion::class)->call('confirmarAccion');
    expect($c->fresh()->estado)->toBe('emitido');
});

it('descarga el registro de ventas del mes: los anulados en 0 y las notas restan', function () {
    entrarComo('alex');
    vender('s_empastado', 1, 2000);
    vender('s_empastado', 1, 3000);
    $srv = app(Comprobantes::class);
    $a = $srv->emitirPendiente(pendiente('venta'), 'EB01', '1', null);
    $b = $srv->emitirPendiente(pendiente('venta'), 'EB01', '2', null);
    $srv->anular($b, 'Error', usuario('alex'));
    $srv->notaCredito($a, 500, 'Descuento', 'BB01-1', usuario('alex'));

    $r = $this->get(route('facturacion.csv', today()->format('Y-m')))->assertOk();
    $filas = explode("\r\n", ltrim($r->getContent(), "\u{FEFF}"));
    expect($filas)->toHaveCount(4)
        ->and($filas[1])->toContain(';EB01;1;')->toContain(';20.00;emitido;')
        ->and($filas[2])->toContain(';0.00;anulado;')
        ->and($filas[3])->toContain('Nota de crédito;BB01;1;')->toContain(';-5.00;emitido;"EB01-1"');
});

it('la pantalla abre en sus tres pestañas', function () {
    entrarComo('jeremy');
    vender('bn_a4', 20);
    foreach (['pend', 'emit', 'reg'] as $t) {
        $this->get(route('facturacion', ['t' => $t]))->assertOk()->assertSee('Facturación');
    }
});

it('la base de datos no deja registrar dos veces el mismo número, aunque se salte las revisiones', function () {
    $base = ['fecha' => today(), 'emitido_at' => now(), 'tipo' => '03', 'serie' => 'EB01', 'total' => 100, 'estado' => 'emitido'];
    Comprobante::create($base + ['uid' => 'a', 'numero' => '50']);

    expect(fn () => Comprobante::create($base + ['uid' => 'b', 'numero' => '50']))
        ->toThrow(UniqueConstraintViolationException::class);

    // la nota de crédito lleva su propio correlativo en la misma serie, y un anulado libera su número
    Comprobante::create(['tipo' => '07', 'uid' => 'c', 'numero' => '50'] + $base);
    Comprobante::where('uid', 'a')->update(['estado' => 'anulado']);
    Comprobante::create($base + ['uid' => 'd', 'numero' => '50']);
    expect(Comprobante::count())->toBe(3);
});

it('al registrar un número ya usado avisa y no guarda nada', function () {
    $v1 = vender('s_empastado', 1, 2000);
    $v2 = vender('s_empastado', 1, 2000);
    $srv = app(Comprobantes::class);
    $srv->registrar(['tipo' => '03', 'serie' => 'EB01', 'numero' => '7', 'total' => 2000, 'ventas' => [['k' => today()->toDateString(), 'id' => $v1->uid]]]);

    expect(fn () => $srv->registrar(['tipo' => '03', 'serie' => 'EB01', 'numero' => '007', 'total' => 2000, 'ventas' => [['k' => today()->toDateString(), 'id' => $v2->uid]]]))
        ->toThrow(ErrorNegocio::class, 'Ya registraste EB01-7');
    expect(Comprobante::count())->toBe(1)->and($v2->fresh()->boleta)->toBeFalse();

    // y la misma venta no recibe un segundo comprobante desde otro equipo
    expect(fn () => $srv->registrar(['tipo' => '03', 'serie' => 'EB01', 'numero' => '8', 'total' => 2000, 'ventas' => [['k' => today()->toDateString(), 'id' => $v1->uid]]]))
        ->toThrow(ErrorNegocio::class, 'desde otro equipo');
});

it('no deja subir el sistema si ya hay números repetidos', function () {
    // «9» y «09» pasan el índice (son textos distintos), pero son el mismo número: la revisión de la migración lo detecta
    $base = ['fecha' => today(), 'emitido_at' => now(), 'tipo' => '03', 'serie' => 'EB01', 'total' => 100, 'estado' => 'emitido'];
    Comprobante::create($base + ['uid' => 'a', 'numero' => '9']);
    Comprobante::create($base + ['uid' => 'b', 'numero' => '09']);
    $m = require database_path('migrations/2026_10_05_000001_fase_0_numeros_unicos.php');

    expect(fn () => (fn () => $this->revisarRepetidos())->call($m))->toThrow(RuntimeException::class, 'EB01-9');
});

it('con descuento, las líneas del comprobante menos el descuento dan el total', function () {
    // S/ 10.00 de anillado con S/ 1.00 de descuento, emitida al cobrar
    $v = vender('s_empastado', 1, 1000, ['descuento' => 100, 'cpe' => ['tipo' => '03', 'emitida' => true, 'serie' => 'EB01', 'numero' => '30']]);
    $c = Comprobante::with('items')->where('uid', $v->fresh()->comprobante_uid)->first();
    expect($c->total)->toBe(900)->and($c->items->sum('subtotal'))->toBe(1000)->and($c->descuento)->toBe(100);

    // en la boleta de cierre del día también
    vender('bn_a4', 20, null, ['descuento' => 50]);   // S/ 3.00 − 0.50
    vender('bn_a4', 10);                              // S/ 1.50
    $x = pendiente('cierre');
    $cierre = app(Comprobantes::class)->emitirPendiente($x, 'EB01', '31', null);
    expect($cierre->total)->toBe(400)->and($cierre->items->sum('subtotal') - $cierre->descuento)->toBe(400);
});
