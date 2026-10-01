<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

/**
 * Verificación del PIN y bloqueo por intentos (vale en todos los equipos porque se guarda en el usuario).
 * Acepta los PIN del sistema anterior (PBKDF2 «p2:» o SHA-256) y los pasa al formato de Laravel al entrar.
 */
class AccesoPin
{
    public const INTENTOS_ANTES_DE_BLOQUEAR = 5;

    public function verificar(Usuario $u, string $pin): bool
    {
        if ($u->pin) {
            return Hash::check($pin, $u->pin);
        }
        if (! $u->pin_legado) {
            return false;
        }
        $ok = hash_equals($u->pin_legado, $this->hashLegado($pin, (string) $u->pin_sal, str_starts_with($u->pin_legado, 'p2:')));
        if ($ok) {
            $u->forceFill(['pin' => Hash::make($pin), 'pin_legado' => null, 'pin_sal' => null, 'pin_largo' => strlen($pin)])->save();
        }

        return $ok;
    }

    /** igual que hashPin2 / hashPin de caja-rapida.html */
    public function hashLegado(string $pin, string $sal, bool $pbkdf2): string
    {
        if ($pbkdf2) {
            return 'p2:'.bin2hex(hash_pbkdf2('sha256', $pin, $sal, 150000, 32, true));
        }

        return hash('sha256', $sal.':'.$pin);
    }

    /** texto de espera si está bloqueado, o '' */
    public function bloqueo(Usuario $u): string
    {
        if ($u->bloqueado_hasta && $u->bloqueado_hasta->isFuture()) {
            return 'Demasiados intentos. Espera '.(int) ceil(now()->diffInSeconds($u->bloqueado_hasta, true)).' segundos.';
        }

        return '';
    }

    public function fallo(Usuario $u): void
    {
        $n = $u->intentos_fallidos + 1;
        $hasta = $n >= self::INTENTOS_ANTES_DE_BLOQUEAR ? now()->addSeconds(min(300, 30 * ($n - 4))) : null;
        $u->forceFill(['intentos_fallidos' => $n, 'bloqueado_hasta' => $hasta])->save();
        if ($n === self::INTENTOS_ANTES_DE_BLOQUEAR) {
            Bitacora::registrar('bloqueo', 'PIN de '.$u->nombre.' bloqueado por '.$n.' intentos fallidos');
        }
    }

    public function exito(Usuario $u): void
    {
        if ($u->intentos_fallidos || $u->bloqueado_hasta) {
            $u->forceFill(['intentos_fallidos' => 0, 'bloqueado_hasta' => null])->save();
        }
    }

    public function desbloquear(Usuario $u): void
    {
        $u->forceFill(['intentos_fallidos' => 0, 'bloqueado_hasta' => null])->save();
    }

    /** '' si el PIN nuevo sirve; si no, el motivo */
    public static function problemaPinNuevo(string $pin, ?string $repetido = null, ?string $actual = null): string
    {
        if (! preg_match('/^\d{4,6}$/', $pin)) {
            return 'El PIN debe tener de 4 a 6 números.';
        }
        if ($repetido !== null && $pin !== $repetido) {
            return 'Los PIN no coinciden.';
        }
        if ($actual !== null && $pin === $actual) {
            return 'El PIN nuevo es igual al actual.';
        }
        if (preg_match('/^(\d)\1+$/', $pin) || str_contains('0123456789', $pin) || str_contains('9876543210', $pin)) {
            return 'Ese PIN es muy fácil de adivinar (como 1111 o 1234). Elige otro.';
        }

        return '';
    }
}
