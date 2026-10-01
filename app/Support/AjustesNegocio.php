<?php

namespace App\Support;

/**
 * Los ajustes que se guardan dentro del negocio (negocios.ajustes): su valor por defecto
 * y qué se acepta en cada uno. Así todo el sistema usa el mismo valor cuando el negocio no eligió nada.
 */
class AjustesNegocio
{
    public const POR_DEFECTO = [
        'regimen' => 'rer',
        'igv' => 'exonerado',
        'serieB' => 'EB01',
        'serieF' => 'E001',
        'modoCpe' => 'manual',
        'tkAncho' => '80',
        'autoLock' => '0',
    ];

    public static function porDefecto(string $clave): mixed
    {
        return self::POR_DEFECTO[$clave] ?? null;
    }

    /** '' si el valor sirve; si no, el motivo (vacío siempre se puede, salvo en las listas) */
    public static function problema(string $k, mixed $v): string
    {
        $s = is_string($v) ? $v : (string) $v;
        if (str_starts_with($k, 'ult_')) {
            return $s === '' || preg_match('/^\d{1,8}$/', $s) ? '' : 'Escribe solo el número (hasta 8 cifras), por ejemplo 445.';
        }

        return match ($k) {
            'ruc' => $s === '' || Valida::ruc($s) ? '' : 'Ese RUC no es válido: revisa los 11 números.',
            'celular', 'yapeCel' => $s === '' || Valida::celular(Texto::cel9($s)) ? '' : 'El celular debe tener 9 números y empezar con 9.',
            'serieB' => Valida::serie($s, '03') ? '' : 'La serie de boletas tiene 4 letras o números y empieza con B o EB (por ejemplo EB01).',
            'serieF' => Valida::serie($s, '01') ? '' : 'La serie de facturas tiene 4 letras o números y empieza con F o E (por ejemplo E001).',
            'meta' => $s === '' || (($c = Dinero::aCentimos($s)) !== null && $c >= 0) ? '' : 'Escribe la meta en soles, por ejemplo 150.',
            'regimen' => isset(Catalogos::REGIMENES[$s]) ? '' : 'Elige el régimen de la lista.',
            'igv' => in_array($s, ['exonerado', 'gravado', 'inafecto'], true) ? '' : 'Elige una opción de la lista.',
            'tkAncho' => in_array($s, ['58', '80'], true) ? '' : 'Elige 58 u 80 mm.',
            'autoLock' => in_array($s, ['0', '5', '10', '15', '30'], true) ? '' : 'Elige una opción de la lista.',
            default => '',
        };
    }
}
