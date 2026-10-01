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
        $negocioId = app(NegocioActual::class)->obligatorio()->id;
        $fila = Secuencia::where('clave', $clave)->lockForUpdate()->first();
        if (! $fila) {
            try {
                $fila = Secuencia::create(['negocio_id' => $negocioId, 'clave' => $clave, 'valor' => 0]);
            } catch (UniqueConstraintViolationException) {
                $fila = Secuencia::where('clave', $clave)->lockForUpdate()->firstOrFail();
            }
        }
        $n = max($fila->valor, $minimo) + 1;
        $fila->update(['valor' => $n]);

        return $n;
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
