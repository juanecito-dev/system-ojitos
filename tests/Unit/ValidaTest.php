<?php

use App\Redaccion\Ayudas;
use App\Support\Dinero;
use App\Support\Valida;

it('el DNI solo acepta exactamente 8 números', function () {
    expect(Valida::dni('45678912'))->toBeTrue()
        ->and(Valida::dni('4567891'))->toBeFalse()          // 7 números
        ->and(Valida::dni('456789123'))->toBeFalse()        // 9 números
        ->and(Valida::dni('4567891a'))->toBeFalse()         // con letra
        ->and(Valida::dni('4567.891'))->toBeFalse()         // con punto
        ->and(Valida::dni(' 45678912'))->toBeFalse()        // con espacio
        ->and(Valida::dni('-4567891'))->toBeFalse()
        ->and(Valida::dni(45678912))->toBeFalse()           // solo texto escrito, nunca un número suelto
        ->and(Valida::dni(''))->toBeFalse();
});

it('el RUC tiene 11 números y su dígito de control; el documento puede ser DNI o RUC', function () {
    expect(Valida::ruc('10745784548'))->toBeTrue()->and(Valida::ruc('10745784547'))->toBeFalse()->and(Valida::ruc('1074578454'))->toBeFalse()
        ->and(Valida::documento('45678912'))->toBeTrue()->and(Valida::documento('10745784548'))->toBeTrue()->and(Valida::documento('123456789'))->toBeFalse();
});

it('el celular tiene 9 números y empieza con 9', function () {
    expect(Valida::celular('987654321'))->toBeTrue()->and(Valida::celular('887654321'))->toBeFalse()
        ->and(Valida::celular('98765432'))->toBeFalse()->and(Valida::celular('9876543210'))->toBeFalse()->and(Valida::celular('98765432a'))->toBeFalse();
});

it('solo acepta fechas que existen', function () {
    expect(Valida::fecha('2026-02-28'))->toBeTrue()->and(Valida::fecha('2024-02-29'))->toBeTrue()
        ->and(Valida::fecha('2025-02-29'))->toBeFalse()->and(Valida::fecha('2026-02-31'))->toBeFalse()
        ->and(Valida::fecha('2026-13-01'))->toBeFalse()->and(Valida::fecha('2026-00-10'))->toBeFalse()
        ->and(Valida::fecha('26-02-10'))->toBeFalse()->and(Valida::fecha('hoy'))->toBeFalse()->and(Valida::fecha(null))->toBeFalse()
        ->and(Valida::mes('2026-09'))->toBeTrue()->and(Valida::mes('2026-13'))->toBeFalse()->and(Valida::mes('2026-00'))->toBeFalse();
});

it('la serie de boleta empieza con B o EB, la de factura con F o E, y el número tiene hasta 8 cifras', function () {
    expect(Valida::serie('EB01', '03'))->toBeTrue()->and(Valida::serie('B001', '03'))->toBeTrue()->and(Valida::serie('E001', '03'))->toBeFalse()
        ->and(Valida::serie('E001', '01'))->toBeTrue()->and(Valida::serie('F001', '01'))->toBeTrue()->and(Valida::serie('EB01', '01'))->toBeFalse()
        ->and(Valida::serie('EB1', '03'))->toBeFalse()->and(Valida::serie('eb01', '03'))->toBeFalse()->and(Valida::serie('EB-1'))->toBeFalse()
        ->and(Valida::numeroCpe('445'))->toBeTrue()->and(Valida::numeroCpe('0'))->toBeFalse()->and(Valida::numeroCpe('123456789'))->toBeFalse()
        ->and(Valida::numeroCpe('12a'))->toBeFalse();
});

it('lee los montos como se escriben en Perú', function () {
    expect(Dinero::aCentimos('1.50'))->toBe(150)->and(Dinero::aCentimos('1,50'))->toBe(150)->and(Dinero::aCentimos('12,5'))->toBe(1250)
        ->and(Dinero::aCentimos('1,500'))->toBe(150000)            // coma de miles
        ->and(Dinero::aCentimos('1,500.50'))->toBe(150050)
        ->and(Dinero::aCentimos('1.500,50'))->toBe(150050)
        ->and(Dinero::aCentimos('1,500,000'))->toBe(150000000)
        ->and(Dinero::aCentimos('1.500.000'))->toBe(150000000)
        ->and(Dinero::aCentimos('0,500'))->toBe(50)                // no empieza con 0 un grupo de miles
        ->and(Dinero::aCentimos('S/ 20'))->toBe(2000)->and(Dinero::aCentimos('-5'))->toBe(-500);
});

it('no acepta montos con letras, notación científica ni exagerados', function () {
    expect(Dinero::aCentimos('1e3'))->toBeNull()->and(Dinero::aCentimos('0x1A'))->toBeNull()->and(Dinero::aCentimos('12abc'))->toBeNull()
        ->and(Dinero::aCentimos('.'))->toBeNull()->and(Dinero::aCentimos('1.2.3,4,5'))->toBeNull()
        ->and(Dinero::aCentimos('15000000'))->toBeNull()        // más de S/ 10 millones: error de tipeo
        ->and(Dinero::aCentimos('10000000'))->toBe(1_000_000_000);
});

it('en los costos la coma siempre es decimal', function () {
    expect(Dinero::aCosto('0,025'))->toBe(2.5)->and(Dinero::aCosto('1,025'))->toBe(102.5)->and(Dinero::aCosto('1e2'))->toBeNull();
});

it('un contrato por «1,500» dice mil quinientos soles, no S/ 1.50', function () {
    $h = new Ayudas(['monto' => '1,500']);
    expect($h->monto('monto'))->toBe('S/ 1,500.00 (MIL QUINIENTOS Y 00/100 SOLES)');
});
