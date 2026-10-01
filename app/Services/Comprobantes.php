<?php

namespace App\Services;

use App\Models\Comprobante;
use App\Models\Dia;
use App\Models\Negocio;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Support\Catalogos;
use App\Support\Dinero;
use App\Support\NegocioActual;
use App\Support\Texto;
use App\Support\Valida;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Registro de comprobantes emitidos a mano en el portal de SUNAT (SEE-SOL): lo que falta emitir,
 * boletas de cierre, anulaciones, notas de crédito, números y registro de ventas.
 */
class Comprobantes
{
    public function config(?Negocio $n = null): array
    {
        $n ??= app(NegocioActual::class)->obligatorio();

        return [
            'regimen' => $n->ajuste('regimen', 'rer'),
            'igv' => $n->ajuste('igv', 'exonerado'),
            'serieB' => $n->ajuste('serieB', 'EB01'),
            'serieF' => $n->ajuste('serieF', 'E001'),
            'modo' => $n->ajuste('modoCpe', 'manual'),
        ];
    }

    public function puedeFactura(): bool
    {
        return Catalogos::REGIMENES[$this->config()['regimen']][1] ?? true;
    }

    public function serieDe(string $tipo): string
    {
        return $tipo === '01' ? $this->config()['serieF'] : $this->config()['serieB'];
    }

    public function ultimoNumero(string $serie, bool $nota = false): int
    {
        $max = Comprobante::where('serie', $serie)->where('tipo', $nota ? '=' : '!=', '07')->pluck('numero')->map(fn ($x) => (int) $x)->max() ?? 0;

        return $nota ? $max : max((int) app(NegocioActual::class)->obligatorio()->ajuste('ult_'.$serie, 0), $max);
    }

    /** las notas de crédito llevan su propio correlativo en SUNAT, aunque usen la misma serie */
    public function numeroUsado(string $serie, ?string $numero, ?int $excepto = null, bool $nota = false): ?Comprobante
    {
        if (! $numero) {
            return null;
        }

        return Comprobante::where('serie', strtoupper($serie))->where('numero', (string) (int) $numero)->where('tipo', $nota ? '=' : '!=', '07')
            ->where('estado', '!=', 'anulado')->when($excepto, fn ($q) => $q->where('id', '!=', $excepto))->first();
    }

    /** gravado / exonerado / inafecto / igv según la configuración del negocio */
    public function montos(int $total): array
    {
        $m = $this->config()['igv'];
        if ($m === 'gravado') {
            $base = (int) round($total / 1.18);

            return ['gravado' => $base, 'exonerado' => 0, 'inafecto' => 0, 'igv' => $total - $base];
        }

        return ['gravado' => 0, 'exonerado' => $m === 'exonerado' ? $total : 0, 'inafecto' => $m === 'inafecto' ? $total : 0, 'igv' => 0];
    }

    public static function rucValido(?string $r): bool
    {
        $r = preg_replace('/\D/', '', (string) $r);
        if (! preg_match('/^(10|15|16|17|20)\d{9}$/', $r)) {
            return false;
        }
        $w = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $s = 0;
        foreach ($w as $i => $x) {
            $s += $x * (int) $r[$i];
        }
        $d = 11 - ($s % 11);
        if ($d === 10) {
            $d = 0;
        }
        if ($d === 11) {
            $d = 1;
        }

        return $d === (int) $r[10];
    }

    public static function documentoValido(string $td, ?string $nd): bool
    {
        $nd = trim((string) $nd);

        return match ($td) {
            '6' => self::rucValido($nd),
            '1' => (bool) preg_match('/^\d{8}$/', $nd),
            '0' => true,
            default => strlen($nd) >= 5,
        };
    }

    /** Anota un comprobante emitido y lo une a sus ventas (y guarda el detalle de productos) */
    public function registrar(array $d): Comprobante
    {
        $total = (int) $d['total'];

        return DB::transaction(function () use ($d, $total) {
            $serie = strtoupper(($d['serie'] ?? '') ?: $this->serieDe($d['tipo']));
            $numero = ! empty($d['numero']) ? (string) (int) $d['numero'] : null;
            // un equipo a la vez por serie: lo que se revisa aquí no cambia hasta guardar
            app(Numeracion::class)->bloquear('cpe:'.$serie);
            if ($numero && $this->numeroUsado($serie, $numero, null, $d['tipo'] === '07')) {
                throw new ErrorNegocio('Ya registraste '.$serie.'-'.$numero.'. Revisa el número.');
            }
            $uids = collect($d['ventas'] ?? [])->pluck('id')->all();
            if ($uids && ($ya = Venta::whereIn('uid', $uids)->where('boleta', true)->first())) {
                throw new ErrorNegocio('Ya se registró '.($ya->comprobante_numero ?: 'un comprobante').' para '.(count($uids) > 1 ? 'estas ventas' : 'esta venta').' desde otro equipo. No se guardó nada.');
            }
            try {
                $c = $this->crear($d, $serie, $numero, $total);
            } catch (UniqueConstraintViolationException) {
                throw new ErrorNegocio('Ya registraste '.$serie.'-'.$numero.'. Revisa el número.');
            }
            $this->recordarUltimo($c);
            if ($uids) {
                Venta::whereIn('uid', $uids)->update([
                    'boleta' => true, 'comprobante_uid' => $c->uid, 'comprobante_fecha' => $c->fecha->toDateString(),
                    'comprobante_numero' => $c->numero ? $c->etiqueta() : null,
                ]);
            }
            $this->guardarDetalle($c, $uids, $total);
            if (! empty($d['cierre_de'])) {
                Dia::updateOrCreate(['fecha' => $d['cierre_de']], ['cierre' => true, 'cierre_comprobante_uid' => $c->uid]);
            }

            return $c;
        });
    }

    /** dentro de su propio punto de guardado: si el número choca con el índice único, la transacción de afuera sigue sana */
    private function crear(array $d, string $serie, ?string $numero, int $total): Comprobante
    {
        $u = Auth::user();

        return DB::transaction(fn () => Comprobante::create([
            'uid' => Texto::nuevoUid(),
            'fecha' => today(),
            'tipo' => $d['tipo'],
            'serie' => $serie,
            'numero' => $numero,
            'cliente' => $d['cliente'] ?? null,
            'total' => $total,
            ...$this->montos(abs($total)),
            'descripcion' => mb_substr($d['descripcion'] ?? '', 0, 250),
            'estado' => 'emitido',
            'modo' => $this->config()['modo'],
            'referencia' => $d['referencia'] ?? null,
            'motivo' => $d['motivo'] ?? null,
            'ventas' => $d['ventas'] ?? [],
            'cierre_de' => $d['cierre_de'] ?? null,
            'usuario_id' => $u?->id,
            'vendedor' => $u?->nombre,
            'emitido_at' => now(),
        ]));
    }

    /**
     * detalle de productos (para el envío automático a SUNAT más adelante). Las líneas van a su precio
     * y el descuento de las ventas queda como descuento global: líneas − descuento = total.
     */
    private function guardarDetalle(Comprobante $c, array $uids, int $total): void
    {
        $items = VentaItem::whereIn('venta_id', Venta::whereIn('uid', $uids)->select('id'))->where('tercero', false)->get()
            ->groupBy(fn ($l) => $l->nombre.'|'.$l->detalle.'|'.$l->precio);
        foreach ($items as $grupo) {
            $l = $grupo->first();
            $c->items()->create(['producto_uid' => $l->producto_uid, 'descripcion' => mb_substr(Texto::sunat($l->nombre.($l->detalle ? ' '.$l->detalle : '')), 0, 250),
                'cantidad' => $grupo->sum('cantidad'), 'precio' => $l->precio, 'subtotal' => $grupo->sum('subtotal')]);
        }
        $lineas = (int) $items->flatten()->sum('subtotal');
        if ($uids && $lineas > abs($total)) {
            $c->update(['descuento' => $lineas - abs($total)]);
        }
        if (! $uids) {
            $c->items()->create(['descripcion' => mb_substr($c->descripcion ?: 'Servicio', 0, 250), 'cantidad' => 1, 'precio' => abs($total), 'subtotal' => abs($total)]);
        }
    }

    private function recordarUltimo(Comprobante $c): void
    {
        if (! $c->numero || $c->tipo === '07') {
            return;
        }
        $neg = app(NegocioActual::class)->obligatorio();
        if ((int) $c->numero > (int) $neg->ajuste('ult_'.$c->serie, 0)) {
            $neg->fijarAjuste('ult_'.$c->serie, (string) $c->numero);
            $neg->save();
        }
    }

    /** días que quedan del plazo de 7 días (negativo = vencido) */
    public static function diasRestan(Carbon $fecha): int
    {
        return (int) today()->diffInDays($fecha->copy()->addDays(7), false);
    }

    /**
     * Lo que falta emitir en el último mes: la boleta de cierre de cada día (ventas de hasta S/ 5)
     * y las ventas que necesitan su propia boleta o factura.
     *
     * @return array<int, array{kind:string, fecha:string, total:int, n:int, ids:array, otra:bool, desc:string, tipo:string, doc:?array, venta:?Venta}>
     */
    public function pendientes(int $dias = 31): array
    {
        $desde = today()->subDays($dias)->toDateString();
        $V = Venta::with('items')->where('fecha', '>=', $desde)->where('boleta', false)->orderBy('vendida_at')->get()->groupBy(fn ($v) => $v->fecha->toDateString());
        $D = Dia::where('fecha', '>=', $desde)->get()->keyBy(fn ($d) => $d->fecha->toDateString());
        $out = [];
        foreach ($V->sortKeys() as $k => $ventas) {
            $dia = $D[$k] ?? null;
            // días marcados como cerrados antes de este módulo (sin detalle del comprobante)
            $cubierto = $dia && $dia->cierre && ! $dia->cierre_comprobante_uid;
            $men = $cubierto ? collect() : $ventas->filter->vaAlCierre();
            $sum = (int) $men->sum(fn ($v) => $v->propio());
            if ($sum > 0) {
                $out[] = ['kind' => 'cierre', 'fecha' => $k, 'total' => $sum, 'n' => $men->count(), 'ids' => $men->pluck('uid')->all(), 'otra' => (bool) $dia?->cierre_comprobante_uid,
                    'desc' => Texto::sunat('Servicios de fotocopiado e impresion ventas menores del dia '.Carbon::parse($k)->format('d-m-Y')), 'tipo' => '03', 'doc' => null, 'venta' => null];
            }
            foreach ($ventas->filter->faltaComprobante() as $v) {
                $req = $v->comprobante_pedido ?? [];
                $out[] = ['kind' => 'venta', 'fecha' => $k, 'total' => $v->propio(), 'n' => 1, 'ids' => [$v->uid], 'otra' => false, 'desc' => $v->descripcionSunat(),
                    'tipo' => $req['tipo'] ?? '03', 'doc' => $req['doc'] ?? null, 'venta' => $v];
            }
        }

        return $out;
    }

    /** Registra un pendiente ya emitido en el portal de SUNAT */
    public function emitirPendiente(array $x, string $serie, string $numero, ?array $doc): Comprobante
    {
        $f = $x['tipo'] === '01';
        $td = $f ? '6' : ($doc['td'] ?? '0');
        $nd = trim((string) ($doc['nd'] ?? ''));
        $nom = trim((string) ($doc['nom'] ?? ''));
        $serie = strtoupper(trim($serie));
        $numero = preg_replace('/\D/', '', $numero);
        if ($f && (! self::rucValido($nd) || $nom === '')) {
            throw new ErrorNegocio('La factura necesita un RUC válido y la razón social.');
        }
        if ($nd !== '' && $td !== '0' && ! self::documentoValido($td, $nd)) {
            throw new ErrorNegocio('Revisa el número de documento.');
        }
        if (! $f && $x['total'] > Catalogos::MAX_SIN_DOC && $nd === '') {
            throw new ErrorNegocio('Pasa de S/ 700: escribe el documento del comprador.');
        }
        if (! Valida::serie($serie, $x['tipo'])) {
            throw new ErrorNegocio($f ? 'La serie de la factura tiene 4 letras o números y empieza con F o E (por ejemplo E001).'
                : 'La serie de la boleta tiene 4 letras o números y empieza con B o EB (por ejemplo EB01).');
        }
        if ($numero !== '' && ! Valida::numeroCpe($numero)) {
            throw new ErrorNegocio('El número del comprobante tiene de 1 a 8 cifras.');
        }
        // registrar() revisa, con la serie bloqueada, que el número y las ventas no estén ya registrados
        $c = $this->registrar(['tipo' => $x['tipo'], 'serie' => $serie, 'numero' => $numero, 'cliente' => $nd !== '' && $td !== '0' ? ['td' => $td, 'nd' => $nd, 'nom' => $nom, 'dir' => $doc['dir'] ?? ''] : null,
            'total' => $x['total'], 'ventas' => collect($x['ids'])->map(fn ($id) => ['k' => $x['fecha'], 'id' => $id])->all(),
            'cierre_de' => $x['kind'] === 'cierre' ? $x['fecha'] : null, 'descripcion' => $x['desc']]);
        Bitacora::registrar('factura', Catalogos::TIPOS_COMPROBANTE[$c->tipo].' '.$c->etiqueta().' registrada ('.Dinero::s($c->total).')');

        return $c;
    }

    /** Queda anulado en el registro; sus ventas vuelven a «Por emitir» */
    public function anular(Comprobante $c, string $motivo, Usuario $u): void
    {
        DB::transaction(function () use ($c, $motivo, $u) {
            $c->update(['estado' => 'anulado', 'motivo' => $motivo, 'anulado_at' => now(), 'anulado_por' => $u->nombre]);
            Venta::where('comprobante_uid', $c->uid)->update(['boleta' => false, 'comprobante_uid' => null, 'comprobante_fecha' => null, 'comprobante_numero' => null]);
            if ($c->cierre_de) {
                Dia::where('fecha', $c->cierre_de->toDateString())->where('cierre_comprobante_uid', $c->uid)->update(['cierre' => false, 'cierre_comprobante_uid' => null]);
            }
            Bitacora::registrar('factura', 'Anuló '.mb_strtolower(Catalogos::TIPOS_COMPROBANTE[$c->tipo]).' '.$c->etiqueta().': '.$motivo, $u);
        });
    }

    /** lo que ya se devolvió con notas de crédito sobre este comprobante */
    public function notasPrevias(Comprobante $c): int
    {
        return (int) Comprobante::where('tipo', '07')->where('estado', '!=', 'anulado')->where('referencia', $c->etiqueta())->sum('total');
    }

    public function notaCredito(Comprobante $c, int $monto, string $motivo, string $serieNumero, Usuario $u): Comprobante
    {
        $ya = $this->notasPrevias($c);
        if ($monto <= 0 || $monto + $ya > abs($c->total)) {
            throw new ErrorNegocio('El monto debe ser mayor que 0 y, sumando las notas anteriores ('.Dinero::s($ya).'), no pasar el total del comprobante.');
        }
        [$se, $nu] = array_pad(explode('-', strtoupper(trim($serieNumero)), 2), 2, '');
        $nu = preg_replace('/\D/', '', $nu);
        if (($se !== '' && ! Valida::serie($se)) || ($nu !== '' && ! Valida::numeroCpe($nu))) {
            throw new ErrorNegocio('Escribe la nota como serie y número, por ejemplo EB01-12 (serie de 4 letras o números).');
        }
        if ($nu !== '' && $this->numeroUsado($se ?: $c->serie, $nu, null, true)) {
            throw new ErrorNegocio('Ese número de nota ya está registrado.');
        }
        $n = $this->registrar(['tipo' => '07', 'serie' => $se ?: $c->serie, 'numero' => $nu, 'cliente' => $c->cliente, 'total' => $monto,
            'referencia' => $c->etiqueta(), 'motivo' => $motivo, 'descripcion' => Texto::sunat('Nota de credito '.$motivo)]);
        Bitacora::registrar('factura', 'Nota de crédito '.$n->etiqueta().' sobre '.$c->etiqueta().' por '.Dinero::s($monto), $u);

        return $n;
    }

    /** Pone o corrige el número de un comprobante (por ejemplo, si se anotó mal) */
    public function cambiarNumero(Comprobante $c, string $serie, string $numero, Usuario $u): void
    {
        $serie = strtoupper(trim($serie)) ?: $c->serie;
        $numero = preg_replace('/\D/', '', $numero);
        if (! Valida::numeroCpe($numero) || ! Valida::serie($serie, $c->tipo === '07' ? null : $c->tipo)) {
            throw new ErrorNegocio('Escribe la serie (4 letras o números: '.($c->tipo === '01' ? 'F o E' : 'B o EB').' al inicio) y el número que te dio SUNAT (hasta 8 cifras).');
        }
        $antes = $c->numero ? $c->etiqueta() : null;
        DB::transaction(function () use ($c, $serie, $numero, $antes) {
            app(Numeracion::class)->bloquear('cpe:'.$serie);
            if ($this->numeroUsado($serie, $numero, $c->id, $c->tipo === '07')) {
                throw new ErrorNegocio('Ese número ya está registrado.');
            }
            try {
                DB::transaction(fn () => $c->update(['serie' => $serie, 'numero' => (string) (int) $numero]));
            } catch (UniqueConstraintViolationException) {
                throw new ErrorNegocio('Ese número ya está registrado.');
            }
            Venta::where('comprobante_uid', $c->uid)->update(['comprobante_numero' => $c->etiqueta()]);
            if ($antes) {
                Comprobante::where('tipo', '07')->where('referencia', $antes)->update(['referencia' => $c->etiqueta()]);
            }
            $this->recordarUltimo($c);
        });
        Bitacora::registrar('factura', $antes ? 'Corrigió el número '.$antes.' → '.$c->etiqueta() : 'Puso el número '.$c->etiqueta(), $u);
    }

    /** números que faltan entre el primero y el último registrado de cada serie en ese mes (boletas y facturas) */
    public function saltos(string $mes): array
    {
        $out = [];
        Comprobante::where('fecha', '>=', $mes.'-01')->where('fecha', '<=', Carbon::parse($mes.'-01')->endOfMonth()->toDateString())
            ->whereNotNull('numero')->where('tipo', '!=', '07')->get()->groupBy('serie')->each(function ($cs, $serie) use (&$out) {
                $ns = $cs->pluck('numero')->map(fn ($n) => (int) $n)->unique()->sort()->values();
                if ($ns->count() < 2) {
                    return;
                }
                $faltan = array_values(array_diff(range($ns->first(), $ns->last()), $ns->all()));
                if ($faltan && count($faltan) <= 500) {
                    $out[$serie] = $faltan;
                }
            });

        return $out;
    }
}
