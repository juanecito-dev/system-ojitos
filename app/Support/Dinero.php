<?php

namespace App\Support;

/** Los montos se guardan en céntimos (enteros), como en el sistema anterior. */
class Dinero
{
    /** 150 → "S/ 1.50" */
    public static function s(int|float|null $c): string
    {
        return 'S/ '.self::n($c);
    }

    /** 150 → "1.50" */
    public static function n(int|float|null $c): string
    {
        $c = (int) round($c ?? 0);
        $neg = $c < 0 ? '-' : '';

        return $neg.number_format(abs($c) / 100, 2, '.', '');
    }

    /** costos, que pueden tener fracción de céntimo: 2.5 → "0.025", 52.84 → "0.53" (solo los de menos de 10 céntimos llevan más decimales) */
    public static function nCosto(int|float|null $c): string
    {
        if (abs((float) $c) >= 10) {
            return self::n($c);
        }
        $t = rtrim(number_format(((float) ($c ?? 0)) / 100, 4, '.', ''), '0');

        return strlen(substr(strrchr($t, '.'), 1)) < 2 ? number_format(((float) ($c ?? 0)) / 100, 2, '.', '') : $t;
    }

    public static function sCosto(int|float|null $c): string
    {
        return 'S/ '.self::nCosto($c);
    }

    /** el monto más alto que se acepta al escribir: S/ 10 millones (evita errores de tipeo como 1e9) */
    public const MAXIMO = 1_000_000_000;

    /**
     * Texto escrito a mano → número en soles, como se escribe en Perú:
     * «1.50» y «1,50» = 1.5; «1,500» y «1,500.00» = 1500 (coma de miles); «1.500.000» y «1.500,50» también.
     * Sin notación científica («1e3») ni letras. Inválido → null.
     */
    public static function leer(mixed $v, bool $comaDeMiles = true): ?float
    {
        $t = str_replace([' ', "\u{00A0}", 'S/', 's/', 'S/.'], '', trim((string) $v));
        $neg = str_starts_with($t, '-');
        $t = ltrim($t, '-');
        if ($t === '' || ! preg_match('/^[\d.,]+$/', $t) || ! preg_match('/\d/', $t)) {
            return null;
        }
        $c = substr_count($t, ',');
        $p = substr_count($t, '.');
        if ($c && $p) {
            // el último signo es el decimal; el otro, de miles
            $dec = strrpos($t, ',') > strrpos($t, '.') ? ',' : '.';
            $t = str_replace($dec === ',' ? '.' : ',', '', $t);
            if (substr_count($t, $dec) > 1) {
                return null;
            }
            $t = str_replace(',', '.', $t);
        } elseif ($comaDeMiles && ($c > 1 || ($c === 1 && preg_match('/^[1-9]\d{0,2},\d{3}$/', $t)))) {
            $t = str_replace(',', '', $t);          // 1,500 o 1,500,000
        } elseif ($p > 1) {
            $t = str_replace('.', '', $t);          // 1.500.000
        } else {
            $t = str_replace(',', '.', $t);         // 1,50
        }
        if (! is_numeric($t)) {
            return null;
        }

        return $neg ? -(float) $t : (float) $t;
    }

    /** "0.025" → 2.5 céntimos (costos); texto vacío o inválido → null */
    public static function aCosto(mixed $v): ?float
    {
        $n = self::leer($v, false);   // en los costos la coma siempre es decimal: 0,025

        return $n === null || abs($n * 100) > self::MAXIMO ? null : round($n * 100, 4);
    }

    /** "1.50" o "1,50" → 150; "1,500" → 150000; texto vacío, inválido o exagerado → null */
    public static function aCentimos(mixed $v): ?int
    {
        $n = self::leer($v);
        if ($n === null) {
            return null;
        }
        $c = (int) round($n * 100);

        return abs($c) > self::MAXIMO ? null : $c;
    }
}
