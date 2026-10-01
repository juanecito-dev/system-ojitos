<?php

use App\Models\CajaMovimiento;
use App\Models\Cliente;
use App\Models\ClienteMovimiento;
use App\Models\Comprobante;
use App\Models\ProductoInsumo;
use App\Models\StockMovimiento;
use App\Models\Venta;
use App\Models\VentaAnulada;
use App\Services\ErrorNegocio;
use App\Services\Stock;
use App\Services\Ventas;
use App\Support\Texto;

beforeEach(fn () => negocioDePrueba('ojitos', ['metOff' => ['tarjeta', 'transferencia'], 'serieB' => 'EB01']));

function linea(string $uid, int $cant, ?int $precio = null, array $extra = []): array
{
    $p = producto($uid);

    return ['producto_id' => $p->id, 'producto_uid' => $p->uid, 'nombre' => $p->nombre, 'cantidad' => $cant, 'precio' => $precio ?? $p->opciones->first()->precio] + $extra;
}

function clienteNuevo(string $nombre = 'Rosa Díaz'): Cliente
{
    return Cliente::create(['uid' => Texto::nuevoUid(), 'nombre' => $nombre, 'celular' => '987654321']);
}

it('registra una venta con número de nota por usuario y vuelto', function () {
    $s = app(Ventas::class);
    $v1 = $s->registrar(usuario('jeremy'), ['lineas' => [linea('bn_a4', 20)], 'metodo' => 'efectivo', 'recibido' => 500]);
    $v2 = $s->registrar(usuario('jeremy'), ['lineas' => [linea('u_lapicero', 1)], 'metodo' => 'yape']);
    $code = usuario('jeremy')->codigoTicket();

    expect($v1->total)->toBe(300)->and($v1->pago)->toBe(500)
        ->and($v1->numero)->toBe($code.'-001')->and($v2->numero)->toBe($code.'-002')
        ->and($v1->items->first()->costo)->toBeNull();
});

it('no deja fiar sin cliente', function () {
    app(Ventas::class)->registrar(usuario('jeremy'), ['lineas' => [linea('bn_a4', 2)], 'metodo' => 'fiado']);
})->throws(ErrorNegocio::class, 'Para fiar');

it('no acepta métodos de pago apagados', function () {
    app(Ventas::class)->registrar(usuario('jeremy'), ['lineas' => [linea('bn_a4', 2)], 'metodo' => 'tarjeta']);
})->throws(ErrorNegocio::class);

it('el descuento no puede pasar lo que es propio (sin tasas de terceros)', function () {
    app(Ventas::class)->registrar(usuario('alex'), ['descuento' => 500, 'metodo' => 'efectivo', 'lineas' => [
        linea('s_tramite', 1), ['producto_uid' => 'tasa', 'nombre' => 'Tasa', 'cantidad' => 1, 'precio' => 5000, 'tercero' => true]]]);
})->throws(ErrorNegocio::class, 'descuento');

it('fiado: anota la deuda y suma la visita del cliente', function () {
    $c = clienteNuevo();
    app(Ventas::class)->registrar(usuario('jeremy'), ['lineas' => [linea('col_a4', 3, 40)], 'metodo' => 'fiado', 'cliente_id' => $c->id]);

    expect($c->fresh()->saldo())->toBe(120)->and($c->fresh()->visitas)->toBe(1);
});

it('cobra la deuda junto con la venta y la pone en caja', function () {
    $c = clienteNuevo();
    $s = app(Ventas::class);
    $s->registrar(usuario('jeremy'), ['lineas' => [linea('col_a4', 3, 40)], 'metodo' => 'fiado', 'cliente_id' => $c->id]);
    $v = $s->registrar(usuario('jeremy'), ['lineas' => [linea('bn_a4', 10)], 'metodo' => 'efectivo', 'cliente_id' => $c->id, 'abono' => 999, 'recibido' => 1000]);

    expect($v->abono)->toBe(120)                     // no más de lo que debe
        ->and($c->fresh()->saldo())->toBe(0)
        ->and($v->pago)->toBe(1000 - 120)
        ->and(CajaMovimiento::where('concepto', 'Pago de fiado')->value('monto'))->toBe(120);
});

it('boleta de más de S/ 700 necesita DNI', function () {
    app(Ventas::class)->registrar(usuario('alex'), ['lineas' => [linea('s_empastado', 30, 3000)], 'metodo' => 'efectivo',
        'cpe' => ['tipo' => '03', 'pide' => true, 'emitida' => true, 'numero' => '446']]);
})->throws(ErrorNegocio::class, 'S/ 700');

it('registra la boleta emitida y no deja repetir su número', function () {
    $s = app(Ventas::class);
    $v = $s->registrar(usuario('alex'), ['lineas' => [linea('bn_a4', 50)], 'metodo' => 'efectivo',
        'cpe' => ['tipo' => '03', 'pide' => true, 'emitida' => true, 'serie' => '', 'numero' => '446']]);

    expect($v->fresh()->boleta)->toBeTrue()->and($v->fresh()->comprobante_numero)->toBe('EB01-446')
        ->and(Comprobante::first()->exonerado)->toBe(750);
    $s->registrar(usuario('alex'), ['lineas' => [linea('bn_a4', 50)], 'metodo' => 'efectivo',
        'cpe' => ['tipo' => '03', 'pide' => true, 'emitida' => true, 'serie' => 'EB01', 'numero' => '446']]);
})->throws(ErrorNegocio::class, 'ya está registrado');

it('factura exige RUC válido', function () {
    app(Ventas::class)->registrar(usuario('alex'), ['lineas' => [linea('bn_a4', 50)], 'metodo' => 'efectivo',
        'cpe' => ['tipo' => '01', 'doc' => ['td' => '6', 'nd' => '20123456789', 'nom' => 'Empresa SAC']]]);
})->throws(ErrorNegocio::class, 'RUC');

it('sabe qué va a la boleta de cierre y qué necesita boleta propia', function () {
    $s = app(Ventas::class);
    $chica = $s->registrar(usuario('jeremy'), ['lineas' => [linea('bn_a4', 20)], 'metodo' => 'efectivo']);
    $grande = $s->registrar(usuario('jeremy'), ['lineas' => [linea('s_empastado', 1, 2000)], 'metodo' => 'efectivo']);
    $pidio = $s->registrar(usuario('jeremy'), ['lineas' => [linea('bn_a4', 2)], 'metodo' => 'efectivo', 'cpe' => ['pide' => true]]);

    expect($chica->vaAlCierre())->toBeTrue()->and($chica->faltaComprobante())->toBeFalse()
        ->and($grande->faltaComprobante())->toBeTrue()
        ->and($pidio->faltaComprobante())->toBeTrue()->and($pidio->vaAlCierre())->toBeFalse();
});

it('el stock baja con las ventas y los insumos, y vuelve al anular', function () {
    $stock = app(Stock::class);
    $lap = producto('u_lapicero');
    $hoja = producto('u_hoja_bond');
    ProductoInsumo::create(['producto_id' => producto('bn_a4')->id, 'insumo_id' => $hoja->id, 'cantidad' => 1]);
    $stock->fijar($lap->id, 10);
    $stock->fijar($hoja->id, 500);
    $this->travel(1)->seconds();

    $v = app(Ventas::class)->registrar(usuario('jeremy'), ['lineas' => [linea('u_lapicero', 3), linea('bn_a4', 20)], 'metodo' => 'efectivo']);
    expect($stock->de($lap->id))->toBe(7.0)->and($stock->de($hoja->id))->toBe(480.0);

    app(Ventas::class)->anular($v, usuario('alex'), 'Se equivocó');
    expect($stock->de($lap->id))->toBe(10.0)->and($stock->de($hoja->id))->toBe(500.0)
        ->and(VentaAnulada::first()->motivo)->toBe('Se equivocó')
        ->and(Venta::count())->toBe(0);
});

it('si ya se contó el stock después de la venta, al anular la devolución entra como movimiento', function () {
    $stock = app(Stock::class);
    $lap = producto('u_lapicero');
    $stock->fijar($lap->id, 10);
    $this->travel(1)->seconds();
    $v = app(Ventas::class)->registrar(usuario('jeremy'), ['lineas' => [linea('u_lapicero', 3)], 'metodo' => 'efectivo']);
    $this->travel(1)->seconds();
    $stock->fijar($lap->id, 7);   // conteo después de la venta
    $this->travel(1)->seconds();

    app(Ventas::class)->anular($v, usuario('alex'));
    expect($stock->de($lap->id))->toBe(10.0)
        ->and(StockMovimiento::first()->nota)->toBe('Devolución por venta anulada');
});

it('anular una venta fiada borra la deuda y descuenta la visita', function () {
    $c = clienteNuevo();
    $v = app(Ventas::class)->registrar(usuario('jeremy'), ['lineas' => [linea('col_a4', 3, 40)], 'metodo' => 'fiado', 'cliente_id' => $c->id]);
    app(Ventas::class)->anular($v, usuario('alex'), 'Error');

    expect($c->fresh()->saldo())->toBe(0)->and($c->fresh()->visitas)->toBe(0)->and(ClienteMovimiento::count())->toBe(0);
});

it('anular marca la boleta como anulada y avisa para SUNAT', function () {
    $v = app(Ventas::class)->registrar(usuario('alex'), ['lineas' => [linea('bn_a4', 50)], 'metodo' => 'efectivo',
        'cpe' => ['tipo' => '03', 'pide' => true, 'emitida' => true, 'numero' => '500']]);
    $aviso = app(Ventas::class)->anular($v->fresh(), usuario('alex'), 'Error');

    expect(Comprobante::first()->estado)->toBe('anulado')->and($aviso)->toContain('SUNAT');
});
