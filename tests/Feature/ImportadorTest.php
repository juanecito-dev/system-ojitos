<?php

use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Services\AccesoPin;
use App\Services\ErrorNegocio;
use App\Services\ImportadorCopia;
use App\Services\Stock;
use App\Support\NegocioActual;

function importarFixture(?Negocio $neg = null): array
{
    $imp = app(ImportadorCopia::class);

    return $imp->importar($imp->leerArchivo(base_path('tests/fixtures/copia-v3.json')), $neg, null, 'ojitos');
}

it('importa la copia v3 completa', function () {
    $res = importarFixture();
    app(NegocioActual::class)->set($res['negocio']);

    expect($res['negocio']->nombre)->toBe('Ojitos')
        ->and($res['negocio']->ajuste('ult_EB01'))->toBe('445')
        ->and($res['negocio']->moduloActivo('compras'))->toBeFalse()
        ->and(Venta::count())->toBe(3)
        ->and(Usuario::count())->toBe(3)
        ->and(Producto::count())->toBe(14)
        ->and(Comprobante::first()->etiqueta())->toBe('EB01-440');

    $v = Venta::with('items')->where('uid', 'v2')->first();
    expect($v->vendida_at->format('Y-m-d H:i'))->toBe('2026-09-20 10:05')   // hora de Lima
        ->and($v->propio())->toBe(200)                                       // sin la tasa de terceros
        ->and($v->terceros())->toBe(5000)
        ->and($v->cliente->nombre)->toBe('Rosa Díaz');
});

it('no duplica nada si se importa dos veces', function () {
    importarFixture();
    $res = importarFixture(Negocio::first());
    app(NegocioActual::class)->set($res['negocio']);

    expect(Negocio::count())->toBe(1)->and(Venta::count())->toBe(3)->and(Cliente::count())->toBe(1)
        ->and(VentaItem::count())->toBe(4);
});

it('calcula el stock y la deuda igual que el sistema anterior', function () {
    $res = importarFixture();
    app(NegocioActual::class)->set($res['negocio']);
    $st = app(Stock::class)->todos();

    // lapicero: conteo 24 + compra 12 − 2 vendidos; hoja bond: 500 − 20 copias B/N (insumo)
    expect($st[producto('c_lk1')->id])->toBe(34.0)
        ->and($st[producto('u_hoja_bond')->id])->toBe(480.0)
        // 120 fiado + 50 de su ficha duplicada (alias)
        ->and(Cliente::first()->saldo())->toBe(170);
});

it('los usuarios entran con su PIN antiguo y se actualiza al formato nuevo', function () {
    $res = importarFixture();
    app(NegocioActual::class)->set($res['negocio']);
    $acceso = app(AccesoPin::class);

    $alex = usuario('alex');
    expect($acceso->verificar($alex, '1111'))->toBeFalse()
        ->and($acceso->verificar($alex, '2580'))->toBeTrue()
        ->and($alex->fresh()->pin_legado)->toBeNull()
        ->and($acceso->verificar($alex->fresh(), '2580'))->toBeTrue()
        ->and($acceso->verificar(usuario('jeremy'), '1470'))->toBeTrue()
        ->and(usuario('carlos')->activo)->toBeFalse();
});

it('un número de comprobante repetido en la copia no frena la importación ni se pierde', function () {
    $imp = app(ImportadorCopia::class);
    $d = $imp->leerArchivo(base_path('tests/fixtures/copia-v3.json'));
    $d['dias']['2026-09-20']['cpes']['cp9'] = ['id' => 'cp9', 't' => 1789916820000, 'tipo' => '03', 'serie' => 'EB01', 'num' => '00440', 'total' => 100, 'estado' => 'emitido'];
    $res = $imp->importar($d, null, null, 'ojitos');
    app(NegocioActual::class)->set($res['negocio']);

    $rep = Comprobante::where('uid', 'cp9')->first();
    expect(Comprobante::where('uid', 'cp1')->value('numero'))->toBe('440')
        ->and($rep->numero)->toBeNull()->and($rep->extra['numeroRepetido'])->toBe('440');
});

it('rechaza archivos que no son copias', function () {
    app(ImportadorCopia::class)->validar(['app' => 'otra']);
})->throws(ErrorNegocio::class);
