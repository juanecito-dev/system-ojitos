<?php

namespace App\Services;

use App\Events\ClientesUnidos;
use App\Models\CajaMovimiento;
use App\Models\Cliente;
use App\Models\ClienteMovimiento;
use App\Models\Usuario;
use App\Models\Venta;
use App\Support\Dinero;
use App\Support\NegocioActual;
use App\Support\Texto;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Clientes y fiados: saldos, pagos, deudas anteriores, estado de cuenta y unir duplicados. */
class Clientes
{
    /** [cliente_id => saldo] de los clientes que deben (o tienen saldo a favor), sin sumar sus movimientos */
    public function saldos(): array
    {
        return Cliente::where('saldo', '!=', 0)->pluck('saldo', 'id')->map(fn ($s) => (int) $s)->all();
    }

    /**
     * Revisa lo que debe cada cliente contra sus movimientos y lo corrige.
     *
     * @return array<int, array{guardado: int, real: int}> (cliente_id => …)
     */
    public function recalcularSaldos(?array $ids = null): array
    {
        $real = ClienteMovimiento::when($ids !== null, fn ($q) => $q->whereIn('cliente_id', $ids))
            ->selectRaw("cliente_id, SUM(CASE WHEN tipo = 'fiado' THEN monto ELSE -monto END) AS s")
            ->groupBy('cliente_id')->pluck('s', 'cliente_id')->map(fn ($s) => (int) $s);
        $mal = [];
        foreach (Cliente::when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->get(['id', 'saldo']) as $c) {
            $r = (int) ($real[$c->id] ?? 0);
            if ((int) $c->saldo !== $r) {
                $mal[$c->id] = ['guardado' => (int) $c->saldo, 'real' => $r];
                Cliente::whereKey($c->id)->update(['saldo' => $r]);
            }
        }

        return $mal;
    }

    /** Movimientos con el saldo que iba quedando, desde la última vez que estuvo al día */
    public function estadoCuenta(Cliente $c): array
    {
        $ms = $c->movimientos()->orderBy('ocurrido_at')->orderBy('id')->get();
        $run = 0;
        $corte = 0;
        $filas = [];
        foreach ($ms as $i => $m) {
            $run += $m->tipo === 'fiado' ? $m->monto : -$m->monto;
            if ($run === 0 && $i < $ms->count() - 1) {
                $corte = $i + 1;
            }
            $filas[] = ['m' => $m, 'saldo' => $run];
        }
        if ($run === 0) {
            return ['filas' => array_slice($filas, -20), 'desde' => null, 'saldo' => 0];
        }

        return ['filas' => array_slice($filas, $corte), 'desde' => $filas[$corte]['m']->ocurrido_at ?? null, 'saldo' => $run];
    }

    /** desde cuándo debe (el día en que empezó su deuda actual), o null si está al día */
    public function deudaDesde(Cliente $c): ?Carbon
    {
        $e = $this->estadoCuenta($c);

        return $e['saldo'] > 0 ? $e['desde'] : null;
    }

    /** [cliente_id => fecha en que empezó su deuda actual] de los que deben */
    public function deudasDesde(array $saldos): array
    {
        $ids = array_keys(array_filter($saldos, fn ($s) => $s > 0));
        if (! $ids) {
            return [];
        }
        $out = [];
        ClienteMovimiento::whereIn('cliente_id', $ids)->orderBy('ocurrido_at')->orderBy('id')->get()->groupBy('cliente_id')
            ->each(function ($ms, $cid) use (&$out) {
                $run = 0;
                $desde = null;
                foreach ($ms as $m) {
                    if ($run <= 0 && $m->tipo === 'fiado') {
                        $desde = $m->ocurrido_at;
                    }
                    $run += $m->tipo === 'fiado' ? $m->monto : -$m->monto;
                    if ($run <= 0) {
                        $desde = null;
                    }
                }
                $out[$cid] = $desde;
            });

        return $out;
    }

    public function registrarPago(Cliente $c, int $monto, string $metodo, Usuario $u): ClienteMovimiento
    {
        if ($monto <= 0) {
            throw new ErrorNegocio('Escribe cuánto pagó.');
        }
        $debe = (int) ($this->saldos()[$c->id] ?? 0);
        if ($monto > $debe) {
            // como en los pedidos y las compras: no se cobra más de lo que se debe (evita errores de tipeo como 500 en vez de 50)
            throw new ErrorNegocio($debe > 0 ? 'Es más de lo que debe ('.Dinero::s($debe).').' : 'No tiene deuda pendiente.');
        }
        if (! array_key_exists($metodo, app(NegocioActual::class)->obligatorio()->metodosActivos())) {
            $metodo = 'efectivo';
        }

        return DB::transaction(function () use ($c, $monto, $metodo, $u) {
            $mov = ClienteMovimiento::create([
                'uid' => Texto::nuevoUid(), 'cliente_id' => $c->id, 'tipo' => 'abono', 'monto' => $monto, 'metodo' => $metodo,
                'fecha' => today(), 'usuario_id' => $u->id, 'vendedor' => $u->nombre, 'ocurrido_at' => now(),
            ]);
            CajaMovimiento::create([
                'uid' => Texto::nuevoUid(), 'fecha' => today(), 'tipo' => 'ingreso', 'concepto' => 'Pago de fiado', 'nota' => $c->nombre,
                'monto' => $monto, 'metodo' => $metodo, 'referencia' => $mov->uid, 'usuario_id' => $u->id, 'vendedor' => $u->nombre, 'ocurrido_at' => now(),
            ]);
            Bitacora::registrar('caja', 'Pago de fiado de '.$c->nombre.': '.Dinero::s($monto));

            return $mov;
        });
    }

    public function anotarDeuda(Cliente $c, int $monto, Usuario $u, string $detalle = 'Deuda anterior'): ClienteMovimiento
    {
        if ($monto <= 0) {
            throw new ErrorNegocio('Escribe cuánto debía.');
        }
        Bitacora::registrar('usuario', 'Anotó una deuda de '.Dinero::s($monto).' a '.$c->nombre);

        return ClienteMovimiento::create([
            'uid' => Texto::nuevoUid(), 'cliente_id' => $c->id, 'tipo' => 'fiado', 'monto' => $monto, 'detalle' => $detalle,
            'fecha' => today(), 'usuario_id' => $u->id, 'vendedor' => $u->nombre, 'ocurrido_at' => now(),
        ]);
    }

    /** Borra un fiado o un pago anotado a mano (los de una venta se quitan anulando la venta) */
    public function borrarMovimiento(ClienteMovimiento $m): void
    {
        if ($m->venta_uid) {
            throw new ErrorNegocio('Ese movimiento es de una venta: anúlala en Ventas.');
        }
        DB::transaction(function () use ($m) {
            if ($m->tipo === 'abono') {
                // si ese pago ya entró en una caja cerrada, se corrige desde la caja de hoy
                foreach (CajaMovimiento::where('referencia', $m->uid)->get() as $cm) {
                    app(CuadreCaja::class)->quitar($cm, Auth::user(), 'Se borró un pago de fiado');
                }
            }
            Bitacora::registrar('usuario', 'Borró un '.($m->tipo === 'abono' ? 'pago' : 'fiado').' de '.Dinero::s($m->monto).' de '.$m->cliente?->nombre);
            $m->delete();
        });
    }

    /** Clientes que parecen la misma persona (mismo celular, documento o nombre) */
    public function posiblesDuplicados(Cliente $c): Collection
    {
        $n = trim(Texto::norm($c->nombre));
        $generico = (bool) preg_match('/^cliente \d{4}$/', $n);

        return Cliente::where('id', '!=', $c->id)->where(function ($q) use ($c, $generico) {
            $q->whereRaw('1 = 0');
            if ($c->celular) {
                $q->orWhere('celular', $c->celular);
            }
            if ($c->documento) {
                $q->orWhere('documento', $c->documento);
            }
            if (! $generico) {
                $q->orWhereRaw('LOWER(nombre) = ?', [mb_strtolower(trim($c->nombre))]);
            }
        })->get();
    }

    /** Pasa todo lo de $otro a $queda y borra $otro */
    public function unir(Cliente $queda, Cliente $otro): void
    {
        if ($queda->id === $otro->id) {
            return;
        }
        DB::transaction(function () use ($queda, $otro) {
            $queda->refresh();
            $otro->refresh();
            ClienteMovimiento::withTrashed()->where('cliente_id', $otro->id)->update(['cliente_id' => $queda->id]);
            Venta::where('cliente_id', $otro->id)->update(['cliente_id' => $queda->id]);
            event(new ClientesUnidos($queda, $otro));   // cada módulo pasa lo suyo (pedidos, documentos…)
            $queda->visitas += $otro->visitas;
            $queda->gastado += $otro->gastado;
            if ($otro->ultima_visita_at && (! $queda->ultima_visita_at || $otro->ultima_visita_at->gt($queda->ultima_visita_at))) {
                $queda->ultima_visita_at = $otro->ultima_visita_at;
            }
            foreach (['celular', 'documento', 'direccion', 'nota', 'limite_fiado'] as $f) {
                if (! $queda->{$f} && $otro->{$f}) {
                    $queda->{$f} = $otro->{$f};
                }
            }
            $queda->alias = array_values(array_unique(array_merge($queda->alias ?? [], [$otro->uid], $otro->alias ?? [])));
            $queda->save();
            Bitacora::registrar('usuario', 'Unió el cliente '.$otro->nombre.' con '.$queda->nombre);
            $otro->delete();
            $this->recalcularSaldos([$queda->id]);   // sus movimientos pasaron todos juntos
        });
    }

    /** '' si los datos sirven; si no, el motivo (mismas reglas que la ficha del sistema anterior) */
    public function problemaFicha(?Cliente $c, string $nombre, string $cel, string $doc): string
    {
        if ($nombre === '' && $cel === '') {
            return 'Escribe el celular o el nombre.';
        }
        if ($cel !== '' && strlen($cel) !== 9) {
            return 'El celular debe tener 9 números.';
        }
        if ($doc !== '' && ! (strlen($doc) === 8 || (strlen($doc) === 11 && Comprobantes::rucValido($doc)))) {
            return strlen($doc) === 11 ? 'Ese RUC no es válido: revisa los 11 dígitos.' : 'El DNI tiene 8 números y el RUC 11.';
        }
        if ($cel !== '' && ($d = Cliente::where('celular', $cel)->where('id', '!=', $c?->id)->first())) {
            return 'Ese celular ya es de '.$d->nombre.'. Si es la misma persona, usa «Unir con otro».';
        }
        if ($doc !== '' && ($d = Cliente::where('documento', $doc)->where('id', '!=', $c?->id)->first())) {
            return 'Ese documento ya es de '.$d->nombre.'. Si es la misma persona, usa «Unir con otro».';
        }

        return '';
    }

    public static function haceTxt(?Carbon $t): string
    {
        if (! $t) {
            return '';
        }
        $d = (int) $t->copy()->startOfDay()->diffInDays(today());

        return $d <= 0 ? 'hoy' : ($d === 1 ? 'ayer' : 'hace '.$d.' días');
    }

    public static function stats(Cliente $c): string
    {
        return $c->visitas ? $c->visitas.($c->visitas === 1 ? ' visita' : ' visitas').', gastó '.Dinero::s($c->gastado)
            .($c->ultima_visita_at ? ', última '.self::haceTxt($c->ultima_visita_at) : '') : 'Primera vez';
    }

    public static function documentoTxt(Cliente $c): string
    {
        return $c->documento ? (strlen($c->documento) === 11 ? 'RUC ' : 'DNI ').$c->documento : '';
    }
}
