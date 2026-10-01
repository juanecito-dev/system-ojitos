<?php

use App\Livewire\Caja;
use App\Livewire\Ventas as PantallaVentas;
use App\Models\CajaMovimiento;
use App\Models\Cliente;
use App\Models\ClienteMovimiento;
use App\Models\Comprobante;
use App\Models\TurnoCaja;
use App\Models\Venta;
use App\Models\VentaAnulada;
use App\Services\CajaDia;
use App\Services\Clientes;
use App\Services\Compras;
use App\Services\ErrorNegocio;
use App\Services\Ventas;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-10-01 09:00:00');
    negocioDePrueba();
});

function venderBN(string $usuario = 'jeremy', int $cant = 20, array $extra = []): Venta
{
    return app(Ventas::class)->registrar(usuario($usuario), ['metodo' => 'efectivo',
        'lineas' => [['producto_id' => producto('bn_a4')->id, 'nombre' => 'B/N A4', 'cantidad' => $cant, 'precio' => 15]]] + $extra);
}

function abrirTurnoDe(string $usuario, int $inicial = 5000): TurnoCaja
{
    $u = usuario($usuario);

    return TurnoCaja::create(['uid' => uniqid('t'), 'fecha' => today()->toDateString(), 'usuario_id' => $u->id, 'vendedor' => $u->nombre, 'abre_at' => now(), 'inicial' => $inicial]);
}

function cerrarTurnoDe(TurnoCaja $t): array
{
    $c = (new CajaDia($t->fecha->toDateString()))->turno($t);
    $t->update(['cierra_at' => now(), 'contado' => $c['esperado'], 'esperado' => $c['esperado']]);

    return $c;
}

it('anular una venta no la borra: deja de contar y pasa al historial de anuladas', function () {
    $v = venderBN();
    app(Ventas::class)->anular($v, usuario('alex'), 'Se equivocó de cantidad');

    expect(Venta::count())->toBe(0)
        ->and(Venta::conAnuladas()->where('uid', $v->uid)->first()->anulada_at)->not->toBeNull()
        ->and(VentaAnulada::where('venta_uid', $v->uid)->value('motivo'))->toBe('Se equivocó de cantidad');
});

it('las anuladas no salen en la lista de ventas, sino en su historial, de la más reciente a la más antigua', function () {
    $a = venderBN('alex', 10);
    $b = venderBN('alex', 30);
    venderBN('alex', 40);
    app(Ventas::class)->anular($a, usuario('alex'), 'primera');
    $this->travel(5)->minutes();
    app(Ventas::class)->anular($b, usuario('alex'), 'segunda');
    entrarComo('alex');

    $c = Livewire::test(PantallaVentas::class)->assertSee('Ver el historial de anuladas (2)')->assertDontSee('Motivo: primera');
    $c->set('verAnuladas', true)->assertSeeInOrder(['Historial de anuladas', 'Motivo: segunda', 'Motivo: primera']);
    expect($c->viewData('V'))->toHaveCount(1);
});

it('quien no puede ver todo solo ve sus anuladas', function () {
    $mia = venderBN('jeremy');
    $otra = venderBN('alex');
    app(Ventas::class)->anular($mia, usuario('alex'), 'de Jeremy');
    app(Ventas::class)->anular($otra, usuario('alex'), 'de Alex');
    $j = entrarComo('jeremy');
    $j->rol->sincronizarPermisos(['vender', 'ventas', 'caja']);

    Livewire::test(PantallaVentas::class)->set('verAnuladas', true)->assertSee('Motivo: de Jeremy')->assertDontSee('Motivo: de Alex');
});

it('anular en la misma caja abierta: la venta sale del cuadre y no hace falta devolución', function () {
    $t = abrirTurnoDe('jeremy');
    $this->travel(1)->minutes();
    $v = venderBN();
    expect((new CajaDia(today()->toDateString()))->turno($t)['esperado'])->toBe(5000 + 300);

    app(Ventas::class)->anular($v, usuario('jeremy'));
    expect((new CajaDia(today()->toDateString()))->turno($t->fresh())['esperado'])->toBe(5000)
        ->and(CajaMovimiento::count())->toBe(0);
});

it('anular una venta de una caja ya cerrada no cambia ese cuadre: la devolución sale de la caja de hoy', function () {
    $t1 = abrirTurnoDe('jeremy');
    $this->travel(1)->minutes();
    $v = venderBN();
    $this->travel(1)->hours();
    $antes = cerrarTurnoDe($t1);
    expect($antes['esperado'])->toBe(5300);

    $this->travel(1)->hours();
    $t2 = abrirTurnoDe('alex', 2000);
    $this->travel(1)->minutes();
    app(Ventas::class)->anular($v, usuario('alex'), 'Devolvió las copias');

    expect((new CajaDia(today()->toDateString()))->turno($t1->fresh())['esperado'])->toBe(5300)   // el cuadre cerrado no cambia
        ->and((new CajaDia(today()->toDateString()))->turno($t2)['esperado'])->toBe(2000 - 300)      // la devolución sale de la caja abierta
        ->and(CajaMovimiento::first()->concepto)->toBe('Devolución de venta anulada')
        ->and(Venta::conAnuladas()->first()->devuelta_en_caja)->toBeTrue()
        ->and(Venta::count())->toBe(0);   // y deja de contar como venta
});

it('no deja borrar un movimiento de una caja cerrada; en una caja abierta queda marcado como borrado', function () {
    $t = abrirTurnoDe('alex');
    $this->travel(1)->minutes();
    entrarComo('alex');
    $c = Livewire::test(Caja::class)->call('abrirMovimiento', 'gasto')->set('monto', '5')->call('guardarMovimiento');
    $m = CajaMovimiento::first();

    $c->call('borrarMovimiento', $m->id);
    expect(CajaMovimiento::count())->toBe(0)->and(CajaMovimiento::withTrashed()->count())->toBe(1);

    CajaMovimiento::withTrashed()->find($m->id)->restore();
    $this->travel(1)->minutes();
    cerrarTurnoDe($t);
    $c->call('borrarMovimiento', $m->id)->assertDispatched('toast', texto: 'Esa caja ya se cerró y cuadró: no se puede borrar. Si fue un error, registra un movimiento de corrección hoy.');
    expect(CajaMovimiento::count())->toBe(1);
});

it('borrar un pago de fiado de una caja cerrada se corrige desde la caja de hoy', function () {
    entrarComo('alex');
    $cli = Cliente::create(['uid' => 'c1', 'nombre' => 'Rosa']);
    $srv = app(Clientes::class);
    $srv->anotarDeuda($cli, 1000, usuario('alex'));
    $t = abrirTurnoDe('alex');
    $this->travel(1)->minutes();
    $pago = $srv->registrarPago($cli, 400, 'efectivo', usuario('alex'));
    $this->travel(1)->minutes();
    cerrarTurnoDe($t);

    $this->travelTo('2026-10-02 09:00:00');
    $srv->borrarMovimiento($pago);
    expect(ClienteMovimiento::where('tipo', 'abono')->count())->toBe(0)
        ->and(ClienteMovimiento::withTrashed()->where('tipo', 'abono')->count())->toBe(1)
        ->and(CajaMovimiento::where('fecha', '2026-10-02')->first()?->concepto)->toBe('Corrección: Pago de fiado')
        ->and(CajaMovimiento::where('fecha', '2026-10-01')->count())->toBe(1);   // el del día del cuadre sigue ahí
});

it('deshacer una venta con boleta emitida la deja anulada en el registro, no la borra', function () {
    $v = venderBN('jeremy', 20, ['cpe' => ['tipo' => '03', 'emitida' => true, 'serie' => 'EB01', 'numero' => '77']]);
    $aviso = app(Ventas::class)->anular($v->fresh(), usuario('jeremy'), 'Deshecha al momento', 'deshecha');

    expect(Comprobante::count())->toBe(1)->and(Comprobante::first()->estado)->toBe('anulado')->and($aviso)->toContain('portal de SUNAT');
});

it('la misma venta anulada dos veces a la vez no se resta dos veces', function () {
    $cli = Cliente::create(['uid' => 'c1', 'nombre' => 'Rosa']);
    $v = venderBN('jeremy', 20, ['cliente_id' => $cli->id]);
    expect($cli->fresh()->visitas)->toBe(1);
    $copia = Venta::find($v->id);

    app(Ventas::class)->anular($v, usuario('alex'));
    expect(fn () => app(Ventas::class)->anular($copia, usuario('alex')))->toThrow(ErrorNegocio::class, 'ya se anuló');
    expect($cli->fresh()->visitas)->toBe(0)->and(VentaAnulada::count())->toBe(1);
});

it('una caja abierta que pasa la medianoche se puede cerrar, con la fecha en que se abrió y las ventas de después de las 12', function () {
    $this->travelTo('2026-10-01 20:00:00');
    $t = abrirTurnoDe('jeremy');
    $this->travel(1)->minutes();
    venderBN();                                  // 20:01, S/ 3.00
    $this->travelTo('2026-10-02 00:30:00');
    venderBN('jeremy', 10);                      // 00:30 del día siguiente, S/ 1.50

    entrarComo('jeremy');
    $c = Livewire::test(Caja::class);
    $c->assertSee('Abierta desde el 01/10 a las 20:00');
    expect((new CajaDia('2026-10-02'))->sinTurno()['total'])->toBe(0);   // la venta de las 00:30 sí tiene caja

    // no puede abrir otra mientras esa siga abierta
    $c->set('inicial', '10')->call('abrirCaja')->assertDispatched('toast', texto: 'Primero cierra tu caja abierta el 01/10 a las 20:00');
    expect(TurnoCaja::count())->toBe(1);

    $c->set('contado.'.$t->id, '54.50')->call('pedirCierre', $t->id)->call('cerrarCaja');
    $t->refresh();
    expect($t->cierra_at)->not->toBeNull()->and($t->fecha->toDateString())->toBe('2026-10-01')
        ->and($t->esperado)->toBe(5000 + 300 + 150)->and($t->contado)->toBe(5450);
});

it('eliminar una compra pagada desde una caja cerrada devuelve el dinero en la caja de hoy', function () {
    entrarComo('alex');
    $t = abrirTurnoDe('alex');
    $this->travel(1)->minutes();
    $p = producto('u_lapicero');
    $prov = app(Compras::class)->guardarProveedor(null, 'Librería Central', '', '962506202');
    $compra = app(Compras::class)->guardar(null, ['proveedor_id' => $prov->id, 'numero' => 'F001-1', 'condicion' => 'credito', 'vence' => today()->addDays(30)->toDateString()],
        [['producto_id' => $p->id, 'cantidad' => 1, 'unidad' => 'Caja', 'factor' => 12, 'costo_unitario' => 1200]], usuario('alex'));
    app(Compras::class)->pagar($compra, 1200, 'efectivo', true, usuario('alex'));
    $this->travel(1)->minutes();
    cerrarTurnoDe($t);

    $this->travelTo('2026-10-02 09:00:00');
    app(Compras::class)->eliminar($compra->fresh(), usuario('alex'));
    $hoy = CajaMovimiento::where('fecha', '2026-10-02')->first();
    expect($hoy->tipo)->toBe('ingreso')->and($hoy->concepto)->toBe('Corrección: Pago a proveedor')->and($hoy->monto)->toBe(1200)
        ->and(CajaMovimiento::where('fecha', '2026-10-01')->count())->toBe(1);
});
