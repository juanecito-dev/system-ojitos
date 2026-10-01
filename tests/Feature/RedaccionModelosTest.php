<?php

use App\Models\ModeloRedaccion;
use App\Redaccion\Catalogo;
use App\Redaccion\Curriculum;
use App\Redaccion\Formato;
use App\Redaccion\Motor;

beforeEach(function () {
    negocioDePrueba();
    Catalogo::sincronizar();
});

/** datos de ejemplo que sirven para cualquier modelo */
function datosEjemplo(Motor $m): array
{
    $d = $m->iniciales();
    foreach ($m->definicionCampos() as $c) {
        if (($c['t'] ?? null) === 'persona') {
            $d += [$c['id'].'_nombre' => 'Juana Pérez Ríos', $c['id'].'_dni' => '45678912', $c['id'].'_dom' => 'Jr. Huallaga 450, Tingo María'];

            continue;
        }
        if (! empty($c['id']) && ! array_key_exists($c['id'], $d)) {
            $d[$c['id']] = match ($c['type'] ?? '') {
                'money' => '1500', 'num' => '12', 'date' => today()->toDateString(), 'check' => false, 'area' => 'primera línea', default => 'dato de prueba'
            };
        }
    }

    return $d;
}

it('todos los modelos se arman sin errores y en los tres niveles', function () {
    $ms = ModeloRedaccion::where('activo', true)->get();
    expect($ms->count())->toBeGreaterThan(5);
    foreach ($ms as $m) {
        $motor = new Motor($m);
        if ($motor->esCv()) {
            // el currículum no tiene texto: se dibuja con sus datos (ver RedaccionTest)
            expect(Curriculum::bloques(datosEjemplo($motor), $m->uid))->not->toBeEmpty();

            continue;
        }
        foreach ($m->niveles ? [1, 2, 3] : [2] as $nv) {
            $d = datosEjemplo($motor);
            $d['nivel'] = $nv;
            $t = $motor->texto($d);
            $b = Formato::bloques($t);
            expect($b)->not->toBeEmpty()->and(collect($b)->pluck('k'))->toContain('sign');
            expect($t)->not->toContain('{{')->not->toContain('@if');
        }
    }
});

it('la solicitud sale con sumilla, párrafos que empiezan con «Que,» y anexos', function () {
    $m = new Motor(Catalogo::modelo('solicitud'));
    $d = $m->iniciales() + ['sumilla' => 'Solicito constancia de estudios', 'cargo' => 'Director(a)', 'inst' => 'I.E. N° 32004', 'sol_nombre' => 'Ana Ríos', 'sol_dni' => '12345678',
        'sol_dom' => 'Jr. Lima 120', 'pedido' => "necesito la constancia para una beca\n\nestoy al día en mis pagos", 'anexos' => "Copia de DNI\nRecibo de pago"];
    $t = $m->texto($d);
    expect($t)->toContain('[sum] **SUMILLA:** SOLICITO CONSTANCIA DE ESTUDIOS')
        ->toContain('[p] Que, necesito la constancia para una beca.')->toContain('[p] Que, estoy al día en mis pagos.')
        ->toContain('[li] 1-B. Recibo de pago')->toContain('[firma] ANA RÍOS | 12345678 | Solicitante');
});

it('el contrato numera sus cláusulas y el nivel cambia cuáles entran', function () {
    $m = new Motor(Catalogo::modelo('cv_terreno'));
    $d = datosEjemplo($m);
    $d['nivel'] = 1;
    $simple = $m->texto($d);
    $d['nivel'] = 3;
    $avanzado = $m->texto($d);
    expect($simple)->toContain('**PRIMERA.- ANTECEDENTES:**')->not->toContain('SANEAMIENTO')
        ->and($avanzado)->toContain('SANEAMIENTO')->toContain('SOLUCIÓN DE CONTROVERSIAS')
        ->and(substr_count($avanzado, '.- '))->toBeGreaterThan(substr_count($simple, '.- '));
    expect($simple)->toContain('S/ 1,500.00 (MIL QUINIENTOS Y 00/100 SOLES)');
});
