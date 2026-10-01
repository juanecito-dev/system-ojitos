<?php

use App\Livewire\Ajustes;
use App\Models\Maquina;
use App\Models\Negocio;
use App\Services\Comprobantes;
use App\Services\Contadores;
use App\Services\Numeracion;
use App\Support\NegocioActual;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(fn () => negocioDePrueba());

it('guardar un ajuste no borra el que otro equipo guardó al mismo tiempo', function () {
    $mio = Negocio::find(app(NegocioActual::class)->id());
    $otro = Negocio::find($mio->id);

    $otro->guardarAjuste('tkPie', 'Vuelva pronto');
    $mio->guardarAjuste('meta', '150.00');           // $mio no sabía del tkPie
    $mio->save();                                     // y guardar otra cosa tampoco lo pisa

    $guardado = Negocio::find($mio->id);
    expect($guardado->ajuste('tkPie'))->toBe('Vuelva pronto')->and($guardado->ajuste('meta'))->toBe('150.00');

    $mio->guardarAjuste('tkPie', '');
    expect(Negocio::find($mio->id)->ajuste('tkPie'))->toBeNull()->and(Negocio::find($mio->id)->ajuste('meta'))->toBe('150.00');
});

it('un ajuste sin elegir usa su valor por defecto y uno inválido no se guarda', function () {
    $neg = app(NegocioActual::class)->obligatorio();
    expect($neg->ajuste('serieB'))->toBe('EB01')->and($neg->ajuste('regimen'))->toBe('rer')->and($neg->ajuste('tkAncho'))->toBe('80')
        ->and(fn () => $neg->guardarAjuste('tkAncho', '100'))->toThrow(InvalidArgumentException::class)
        ->and(Negocio::find($neg->id)->ajuste('tkAncho'))->toBe('80');
});

it('la última boleta emitida va en su contador y la configuración no la baja', function () {
    entrarComo('alex');
    $srv = app(Comprobantes::class);
    Livewire::test(Ajustes::class)->call('fijar', 'ult_EB01', '445');
    expect(app(Numeracion::class)->valor('ult:EB01'))->toBe(445)->and($srv->ultimoNumero('EB01'))->toBe(445)
        ->and(app(NegocioActual::class)->obligatorio()->fresh()->ajuste('ult_EB01'))->toBeNull();

    // otra pestaña con la configuración vieja guarda un ajuste: el número sigue igual
    Negocio::find(app(NegocioActual::class)->id())->guardarAjuste('meta', '90.00');
    app(Numeracion::class)->subirA('ult:EB01', 446);
    app(Numeracion::class)->subirA('ult:EB01', 300);   // nunca baja solo
    expect($srv->ultimoNumero('EB01'))->toBe(446);

    // pero Alex sí puede corregir un número mal escrito
    Livewire::test(Ajustes::class)->call('fijar', 'ult_EB01', '440');
    expect(app(Numeracion::class)->valor('ult:EB01'))->toBe(440);
});

it('el último número se cuenta como número, no como texto', function () {
    foreach (['99', '100', '7'] as $n) {
        DB::table('comprobantes')->insert(['negocio_id' => app(NegocioActual::class)->id(), 'uid' => 'c'.$n, 'tipo' => '03', 'serie' => 'EB09',
            'numero' => $n, 'fecha' => '2026-10-01', 'emitido_at' => now(), 'total' => 100, 'estado' => 'registrado', 'created_at' => now(), 'updated_at' => now()]);
    }
    expect(app(Comprobantes::class)->ultimoNumero('EB09'))->toBe(100)->and(app(Comprobantes::class)->ultimoNumero('EB09', true))->toBe(0);
});

it('pasa los números guardados en la configuración a su lugar', function () {
    $neg = app(NegocioActual::class)->obligatorio();
    Maquina::create(['uid' => 'mq1', 'nombre' => 'Ricoh', 'contadores' => [['id' => 'ct1', 'n' => 'Contador', 'tipo' => 'bn']], 'orden' => 0]);
    app(Numeracion::class)->subirA('ult:E001', 140);
    DB::table('negocios')->where('id', $neg->id)->update(['ajustes' => json_encode([
        'meta' => '100.00', 'ult_EB01' => '445', 'ult_E001' => '134',
        'maquinas' => ['prods' => ['bn_a4' => ['g' => 'bn', 'f' => 1]], 'ult' => ['mq1:ct1' => ['v' => 120500, 't' => 1, 'd' => '2026-09-21']]],
    ])]);

    (require database_path('migrations/2026_10_08_000004_fase_1_numeros_fuera_de_ajustes.php'))->up();

    $neg = Negocio::find($neg->id);
    app(NegocioActual::class)->set($neg);
    expect($neg->ajustes)->toBe(['meta' => '100.00', 'maquinas' => ['prods' => ['bn_a4' => ['g' => 'bn', 'f' => 1]]]])
        ->and(app(Numeracion::class)->valor('ult:EB01'))->toBe(445)
        ->and(app(Numeracion::class)->valor('ult:E001'))->toBe(140)              // se queda con el mayor
        ->and(app(Contadores::class)->anterior('2026-10-01', 'mq1', 'ct1'))->toBe(['v' => 120500, 'fecha' => '2026-09-21']);
});
