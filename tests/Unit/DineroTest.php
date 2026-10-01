<?php

use App\Services\Comprobantes;
use App\Support\Dinero;
use App\Support\Texto;

it('muestra y lee montos en céntimos', function () {
    expect(Dinero::s(150))->toBe('S/ 1.50')->and(Dinero::n(123456))->toBe('1234.56')
        ->and(Dinero::aCentimos('1,50'))->toBe(150)->and(Dinero::aCentimos('S/ 2'))->toBe(200)->and(Dinero::aCentimos('abc'))->toBeNull();
});

it('los costos aceptan fracciones de céntimo', function () {
    expect(Dinero::aCosto('0.025'))->toBe(2.5)->and(Dinero::nCosto(2.5))->toBe('0.025')->and(Dinero::nCosto(150))->toBe('1.50')
        ->and(Dinero::sCosto(42))->toBe('S/ 0.42')->and(Dinero::nCosto(52.84))->toBe('0.53')->and(Dinero::aCosto(''))->toBeNull();
});

it('valida RUC y DNI como el sistema anterior', function () {
    expect(Comprobantes::rucValido('10745784548'))->toBeTrue()->and(Comprobantes::rucValido('20123456789'))->toBeFalse()
        ->and(Comprobantes::documentoValido('1', '45678912'))->toBeTrue()->and(Comprobantes::documentoValido('1', '4567'))->toBeFalse();
});

it('limpia el texto para el portal de SUNAT', function () {
    expect(Texto::sunat('2 Cañón × fotocopias (B/N)'))->toBe('2 Canon x fotocopias B N')
        ->and(Texto::cel9('+51 987 654 321'))->toBe('987654321')->and(Texto::usuario(' Álex '))->toBe('alex');
});
