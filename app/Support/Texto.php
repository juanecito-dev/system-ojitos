<?php

namespace App\Support;

use Illuminate\Support\Str;

class Texto
{
    /** sin tildes y en minúsculas, para buscar */
    public static function norm(?string $s): string
    {
        return Str::lower(Str::ascii((string) $s));
    }

    /** nombre de usuario válido: minúsculas, sin tildes ni espacios */
    public static function usuario(?string $s): string
    {
        return preg_replace('/[^a-z0-9._-]/', '', self::norm(trim((string) $s)));
    }

    public static function inicial(?string $nombre): string
    {
        return Str::upper(Str::substr(trim((string) $nombre) ?: '?', 0, 1));
    }

    public static function primerNombre(?string $nombre): string
    {
        return explode(' ', trim((string) $nombre))[0] ?? '';
    }

    /** celular peruano de 9 dígitos (quita el 51 del inicio) */
    public static function cel9(?string $x): string
    {
        $d = preg_replace('/\D/', '', (string) $x);
        if (strlen($d) === 11 && str_starts_with($d, '51')) {
            $d = substr($d, 2);
        }

        return $d;
    }

    public static function fmtCel(?string $x): string
    {
        $d = self::cel9($x);

        return strlen($d) === 9 ? substr($d, 0, 3).' '.substr($d, 3, 3).' '.substr($d, 6) : $d;
    }

    /** texto que acepta el portal de SUNAT: sin tildes ni símbolos, máximo 250 */
    public static function sunat(string $t): string
    {
        $t = str_replace(['ñ', 'Ñ', '×'], ['n', 'N', 'x'], $t);
        $t = Str::ascii($t);
        $t = preg_replace('/[^A-Za-z0-9 .,-]/', ' ', $t);
        $t = trim(preg_replace('/\s+/', ' ', $t));

        return Str::limit($t, 250, '');
    }

    public static function nuevoUid(): string
    {
        return base_convert((string) (int) (microtime(true) * 1000), 10, 36).Str::lower(Str::random(4));
    }

    private const UNI = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez', 'once', 'doce', 'trece', 'catorce', 'quince',
        'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve', 'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis',
        'veintisiete', 'veintiocho', 'veintinueve'];

    private const DEC = ['', '', '', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];

    private const CEN = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

    /** 1234 → "mil doscientos treinta y cuatro" (igual que letras() del sistema anterior) */
    public static function letras(int $n): string
    {
        $n = abs($n);
        $apoc = fn ($t) => preg_replace(['/veintiuno$/u', '/uno$/u'], ['veintiún', 'un'], $t);

        return match (true) {
            $n === 0 => 'cero',
            $n < 30 => self::UNI[$n],
            $n < 100 => self::DEC[intdiv($n, 10)].($n % 10 ? ' y '.self::UNI[$n % 10] : ''),
            $n === 100 => 'cien',
            $n < 1000 => self::CEN[intdiv($n, 100)].($n % 100 ? ' '.self::letras($n % 100) : ''),
            $n < 1000000 => (intdiv($n, 1000) === 1 ? 'mil' : $apoc(self::letras(intdiv($n, 1000))).' mil').($n % 1000 ? ' '.self::letras($n % 1000) : ''),
            default => (intdiv($n, 1000000) === 1 ? 'un millón' : $apoc(self::letras(intdiv($n, 1000000))).' millones').($n % 1000000 ? ' '.self::letras($n % 1000000) : ''),
        };
    }

    /** 12350 (céntimos) → "CIENTO VEINTITRÉS Y 50/100 SOLES" */
    public static function montoLetras(int $centimos): string
    {
        return mb_strtoupper(self::letras(intdiv($centimos, 100)).' y '.str_pad((string) ($centimos % 100), 2, '0', STR_PAD_LEFT).'/100 soles');
    }
}
