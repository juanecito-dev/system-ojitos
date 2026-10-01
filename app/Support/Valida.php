<?php

namespace App\Support;

use App\Services\Comprobantes;

/**
 * Reglas para los datos que se escriben a mano. Las pantallas solo dejan teclear lo que corresponde
 * (public/js/campos.js), pero el servidor lo vuelve a revisar aquí antes de guardar.
 */
class Valida
{
    /** fecha real AAAA-MM-DD (no acepta 2026-02-31 ni 2026-13-01) */
    public static function fecha(mixed $s): bool
    {
        return is_string($s) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]) && (int) $m[1] >= 2000;
    }

    /** mes real AAAA-MM */
    public static function mes(mixed $s): bool
    {
        return is_string($s) && preg_match('/^(\d{4})-(\d{2})$/', $s, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12 && (int) $m[1] >= 2000;
    }

    /** DNI: exactamente 8 números */
    public static function dni(mixed $s): bool
    {
        return is_string($s) && (bool) preg_match('/^\d{8}$/', $s);
    }

    public static function ruc(mixed $s): bool
    {
        return is_string($s) && Comprobantes::rucValido($s) && strlen($s) === 11;
    }

    /** DNI (8) o RUC válido (11) */
    public static function documento(mixed $s): bool
    {
        return self::dni($s) || self::ruc($s);
    }

    /** celular peruano: 9 números que empiezan con 9 */
    public static function celular(mixed $s): bool
    {
        return is_string($s) && (bool) preg_match('/^9\d{8}$/', $s);
    }

    /**
     * serie de un comprobante: 4 letras o números; las boletas empiezan con B (o EB en el portal),
     * las facturas con F (o E); las notas usan la serie del comprobante.
     */
    public static function serie(mixed $s, ?string $tipo = null): bool
    {
        if (! is_string($s) || ! preg_match('/^[A-Z0-9]{4}$/', $s)) {
            return false;
        }

        return match ($tipo) {
            '03' => str_starts_with($s, 'B') || str_starts_with($s, 'EB'),
            '01' => str_starts_with($s, 'F') || (str_starts_with($s, 'E') && ! str_starts_with($s, 'EB')),
            default => true,
        };
    }

    /** número de comprobante de SUNAT: 1 a 8 dígitos */
    public static function numeroCpe(mixed $s): bool
    {
        return is_string($s) && preg_match('/^\d{1,8}$/', $s) && (int) $s > 0;
    }
}
