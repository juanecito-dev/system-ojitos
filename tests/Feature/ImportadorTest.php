<?php

use App\Livewire\PrimerUso;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Services\AccesoPin;
use App\Services\Contadores;
use App\Services\ErrorNegocio;
use App\Services\ImportadorCopia;
use App\Services\Numeracion;
use App\Services\Stock;
use App\Services\Ventas;
use App\Support\NegocioActual;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

function importarFixture(?Negocio $neg = null): array
{
    $imp = app(ImportadorCopia::class);

    return $imp->importar($imp->leerArchivo(base_path('tests/fixtures/copia-v3.json')), $neg, null, 'ojitos');
}

it('importa la copia v3 completa', function () {
    $res = importarFixture();
    app(NegocioActual::class)->set($res['negocio']);

    expect($res['negocio']->nombre)->toBe('Ojitos')
        ->and(app(Numeracion::class)->valor('ult:EB01'))->toBe(445)
        ->and($res['negocio']->ajuste('ult_EB01'))->toBeNull()
        ->and(app(Contadores::class)->anterior('2026-10-01', 'mq1', 'ct1'))->toBe(['v' => 120500, 'fecha' => '2026-09-21'])
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

it('no deja volver a importar si ya se usó el sistema nuevo', function () {
    $res = importarFixture();
    app(NegocioActual::class)->set($res['negocio']);
    $this->travel(5)->seconds();
    app(Ventas::class)->registrar(Usuario::where('usuario', 'jeremy')->first(), ['metodo' => 'efectivo',
        'lineas' => [['producto_id' => null, 'nombre' => 'Copia', 'cantidad' => 1, 'precio' => 100]]]);

    expect(fn () => importarFixture($res['negocio']->fresh()))->toThrow(ErrorNegocio::class, 'ya tiene ventas hechos en el sistema nuevo');

    // desde la terminal, sabiendo lo que hace, se puede forzar
    $imp = app(ImportadorCopia::class);
    $imp->importar($imp->leerArchivo(base_path('tests/fixtures/copia-v3.json')), $res['negocio']->fresh(), null, 'ojitos', true);
    expect(Venta::count())->toBe(4);
});

it('un negocio que empezó de cero y ya vende no recibe una copia encima', function () {
    $neg = negocioDePrueba();
    app(Ventas::class)->registrar(usuario('jeremy'), ['metodo' => 'efectivo', 'lineas' => [['producto_id' => null, 'nombre' => 'Copia', 'cantidad' => 1, 'precio' => 100]]]);

    expect(fn () => importarFixture($neg))->toThrow(ErrorNegocio::class, 'ya tiene ventas');
});

it('si la copia falla a la mitad, no queda nada guardado', function () {
    $imp = app(ImportadorCopia::class);
    $d = $imp->leerArchivo(base_path('tests/fixtures/copia-v3.json'));
    $d['pedidos'] = [['id' => 'roto', 'items' => 'esto no es una lista', 'pagos' => 5]];

    expect(fn () => $imp->importar($d, null, null, 'ojitos'))->toThrow(TypeError::class);
    expect(Negocio::count())->toBe(0)->and(Usuario::withoutGlobalScopes()->count())->toBe(0);
});

it('en «Bienvenido», una copia con datos raros muestra un aviso claro y deja volver a intentar', function () {
    $d = json_decode(file_get_contents(base_path('tests/fixtures/copia-v3.json')), true);
    $d['pedidos'] = [['id' => 'roto', 'items' => 'esto no es una lista', 'pagos' => 5]];
    $archivo = UploadedFile::fake()->createWithContent('copia.json', json_encode($d));

    Livewire::test(PrimerUso::class)->set('archivo', $archivo)->call('importar')
        ->assertSet('error', ImportadorCopia::ERROR_INESPERADO);
    expect(Negocio::count())->toBe(0);
});

it('rechaza archivos que no son copias', function () {
    app(ImportadorCopia::class)->validar(['app' => 'otra']);
})->throws(ErrorNegocio::class);
