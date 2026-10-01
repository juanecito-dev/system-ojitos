<?php

namespace App\Services;

use App\Models\Secuencia;
use App\Models\Usuario;
use App\Models\Venta;
use App\Support\NegocioActual;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/** Números correlativos sin repetir, aunque dos equipos cobren al mismo tiempo. */
class Numeracion
{
    /** Siguiente valor de un contador del negocio. Llamar dentro de una transacción. */
    public function siguiente(string $clave, int $minimo = 0): int
    {
        $fila = $this->fila($clave);
        $n = max($fila->valor, $minimo) + 1;
        $fila->update(['valor' => $n]);

        return $n;
    }

    /** Valor guardado de un contador del negocio (0 si no hay). */
    public function valor(string $clave): int
    {
        return (int) Secuencia::where('clave', $clave)->value('valor');
    }

    /** Pone un contador en un valor (puede bajar: sirve para corregir un número mal escrito). */
    public function fijar(string $clave, int $valor): void
    {
        DB::transaction(fn () => $this->fila($clave)->update(['valor' => max(0, $valor)]));
    }

    /** Sube un contador hasta este valor si estaba más abajo; nunca lo baja. */
    public function subirA(string $clave, int $valor): void
    {
        DB::transaction(function () use ($clave, $valor) {
            $fila = $this->fila($clave);
            if ($fila->valor < $valor) {
                $fila->update(['valor' => $valor]);
            }
        });
    }

    /** la fila del contador, bloqueada hasta que termine la transacción (se crea si no existe) */
    private function fila(string $clave): Secuencia
    {
        $negocioId = app(NegocioActual::class)->obligatorio()->id;
        $fila = Secuencia::where('clave', $clave)->lockForUpdate()->first();
        if (! $fila) {
            try {
                $fila = DB::transaction(fn () => Secuencia::create(['negocio_id' => $negocioId, 'clave' => $clave, 'valor' => 0]));
            } catch (UniqueConstraintViolationException) {
                $fila = Secuencia::where('clave', $clave)->lockForUpdate()->firstOrFail();
            }
        }

        return $fila;
    }

    /** Espera su turno: mientras dure la transacción, nadie más pasa por esta clave (por ejemplo, una serie de comprobantes). */
    public function bloquear(string $clave): void
    {
        $this->siguiente('bloqueo:'.$clave);
    }

    /** Nota de venta propia de cada usuario y día: A1-001, A1-002… */
    public function ticket(Usuario $u, string $fecha): string
    {
        $code = $u->codigoTicket();
        $max = Venta::where('fecha', $fecha)->where('numero', 'like', $code.'-%')->pluck('numero')
            ->map(fn ($n) => (int) substr($n, strlen($code) + 1))->max() ?? 0;
        $n = $this->siguiente('ticket:'.$fecha.':'.$code, $max);

        return $code.'-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }

    public static function enTransaccion(callable $fn): mixed
    {
        return DB::transaction($fn, 3);
    }
}
