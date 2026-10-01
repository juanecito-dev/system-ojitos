<?php

namespace App\Services;

use App\Models\LecturaContador;
use App\Models\Maquina;
use App\Models\Producto;
use App\Models\Venta;
use App\Support\NegocioActual;
use App\Support\Texto;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Contadores de las fotocopiadoras e impresoras (cuadreMaq() del sistema anterior).
 * Cada noche se anota lo que marca cada contador; lo usado en el día (lectura − anterior)
 * se compara con las copias cobradas y con las de prueba o malogradas.
 */
class Contadores
{
    /** grupo del cuadre => nombre */
    public const GRUPOS = ['bn' => 'B/N', 'color' => 'Color', 'total' => 'Todas las copias'];

    public const TIPOS = ['bn' => 'B/N', 'color' => 'Color', 'total' => 'Total (B/N y color juntos)'];

    /** productos que cuentan si el negocio todavía no eligió */
    public const PRODS_INICIO = ['bn_a4' => ['g' => 'bn', 'f' => 1], 'bn_a3' => ['g' => 'bn', 'f' => 1], 'col_a4' => ['g' => 'color', 'f' => 1], 'col_a3' => ['g' => 'color', 'f' => 1]];

    /** más copias que esto desde la lectura anterior se confirman antes de guardar */
    public const SALTO_GRANDE = 20000;

    private ?Collection $maquinas = null;

    public function maquinas(): Collection
    {
        return $this->maquinas ??= Maquina::orderBy('orden')->orderBy('id')->get();
    }

    /** qué productos gastan contador: uid => [g => bn|color|total, f => hojas] */
    public function prods(): array
    {
        return (array) app(NegocioActual::class)->obligatorio()->ajuste('maquinas.prods', []);
    }

    /** grupos que se cuadran: si alguna máquina tiene contador total, todo va junto */
    public function grupos(): array
    {
        $ts = $this->maquinas()->flatMap(fn ($m) => collect($m->contadores ?? [])->pluck('tipo'))->unique();

        return $ts->contains('total') ? ['total'] : array_values(array_filter(['bn', 'color'], fn ($t) => $ts->contains($t)));
    }

    public static function grupoDe(?string $tipo, array $grupos): string
    {
        return ($grupos[0] ?? null) === 'total' ? 'total' : (string) $tipo;
    }

    public static function nombre(Maquina $m, array $c): string
    {
        return $m->nombre.(count($m->contadores ?? []) > 1 ? ' · '.($c['n'] ?? '') : '');
    }

    public static function numero(int|float $n): string
    {
        return number_format($n, 0, '.', ',');
    }

    /** la lectura del día que vale (la última, si se corrigió) */
    public function lecturaDel(string $fecha, string $maq, string $cont, ?Collection $lecturas = null): ?LecturaContador
    {
        $L = $lecturas ?? LecturaContador::where('fecha', $fecha)->where('tipo', 'fin')->get();

        return $L->where('tipo', 'fin')->where('maquina_uid', $maq)->where('contador_uid', $cont)->sortBy('ocurrido_at')->last();
    }

    /** la última lectura de un día anterior: [v, fecha] o null */
    public function anterior(string $fecha, string $maq, string $cont): ?array
    {
        $l = LecturaContador::where('tipo', 'fin')->where('maquina_uid', $maq)->where('contador_uid', $cont)->where('fecha', '<', $fecha)
            ->orderByDesc('fecha')->orderByDesc('ocurrido_at')->first();
        $u = collect($this->maquinas()->firstWhere('uid', $maq)->contadores ?? [])->firstWhere('id', $cont)['ult'] ?? null;
        // la copia del sistema anterior guarda la última lectura aunque ese día ya no venga en la copia
        if (is_array($u) && isset($u['v'], $u['d']) && $u['d'] < $fecha && (! $l || $u['d'] > $l->fecha->toDateString())) {
            return ['v' => (int) $u['v'], 'fecha' => (string) $u['d']];
        }

        return $l ? ['v' => (int) $l->valor, 'fecha' => $l->fecha->toDateString()] : null;
    }

    /**
     * Cuadre del día por grupo: usado (según contadores), esperado (cobradas), merma, dif,
     * falta (contadores sin lectura), primero (sin lectura anterior), precio por hoja y si ya está listo.
     */
    public function cuadre(string $fecha, ?Collection $ventas = null): array
    {
        $gs = $this->grupos();
        $out = [];
        foreach ($gs as $g) {
            $out[$g] = ['usado' => 0, 'esperado' => 0, 'merma' => 0, 'falta' => [], 'primero' => [], 'precio' => INF, 'dif' => 0, 'listo' => false, 'monto' => 0];
        }
        if (! $gs) {
            return $out;
        }
        $L = LecturaContador::where('fecha', $fecha)->get();
        foreach ($this->maquinas() as $m) {
            foreach ($m->contadores ?? [] as $c) {
                $g = self::grupoDe($c['tipo'] ?? null, $gs);
                if (! isset($out[$g])) {
                    continue;
                }
                $l = $this->lecturaDel($fecha, $m->uid, $c['id'], $L);
                if (! $l) {
                    $out[$g]['falta'][] = self::nombre($m, $c);
                } elseif ($l->desde === null) {
                    $out[$g]['primero'][] = self::nombre($m, $c);
                } else {
                    $out[$g]['usado'] += $l->valor - $l->desde;
                }
            }
        }
        $P = $this->prods();
        $V = $ventas ?? Venta::with('items')->where('fecha', $fecha)->get();
        $uids = Producto::withTrashed()->whereIn('uid', array_keys($P))->pluck('uid', 'id');
        foreach ($V as $v) {
            foreach ($v->items as $it) {
                $p = $P[$it->producto_uid ?: ($uids[$it->producto_id] ?? '')] ?? null;
                $g = $p ? self::grupoDe($p['g'] ?? null, $gs) : null;
                if (! $g || ! isset($out[$g])) {
                    continue;
                }
                $f = max(1, (int) ($p['f'] ?? 1));
                $out[$g]['esperado'] += (float) $it->cantidad * $f;
                $out[$g]['precio'] = min($out[$g]['precio'], $it->precio / $f);
            }
        }
        foreach ($L->where('tipo', 'merma') as $l) {
            $g = self::grupoDe($l->grupo, $gs);
            if (isset($out[$g])) {
                $out[$g]['merma'] += $l->valor;
            }
        }
        foreach ($gs as $g) {
            $o = &$out[$g];
            if (! is_finite($o['precio'])) {
                $o['precio'] = $this->precioLista($g, $gs);
            }
            $o['esperado'] = (int) round($o['esperado']);
            $o['dif'] = $o['usado'] - $o['esperado'] - $o['merma'];
            $o['listo'] = ! $o['falta'] && ! $o['primero'];
            $o['monto'] = $o['listo'] && $o['dif'] > 0 ? (int) round($o['dif'] * $o['precio']) : 0;
            unset($o);
        }

        return $out;
    }

    /** el precio por hoja más bajo del catálogo, si ese día no se cobró ninguna */
    private function precioLista(string $g, array $gs): float
    {
        $P = $this->prods();
        $ps = Producto::with('opciones')->whereIn('uid', array_keys($P))->get()
            ->filter(fn ($p) => self::grupoDe($P[$p->uid]['g'] ?? null, $gs) === $g && $p->opciones->isNotEmpty())
            ->map(fn ($p) => $p->opciones->first()->precio / max(1, (int) ($P[$p->uid]['f'] ?? 1)));

        return $ps->isEmpty() ? 0.0 : (float) $ps->min();
    }

    /** copias sin cobrar de los días ya cuadrados entre dos fechas: [copias, monto, días] */
    public function sinCobrar(string $desde, string $hasta): array
    {
        $r = ['copias' => 0, 'monto' => 0, 'dias' => 0];
        if (! $this->grupos()) {
            return $r;
        }
        $fechas = LecturaContador::whereBetween('fecha', [$desde, $hasta])->pluck('fecha')->map(fn ($f) => substr((string) $f, 0, 10))->unique();
        foreach ($fechas as $f) {
            $dia = 0;
            foreach ($this->cuadre($f) as $o) {
                if ($o['listo'] && $o['dif'] > 0) {
                    $r['copias'] += $o['dif'];
                    $r['monto'] += $o['monto'];
                    $dia++;
                }
            }
            $r['dias'] += $dia ? 1 : 0;
        }

        return $r;
    }

    /** Guarda la lectura al cerrar. Si es de más de SALTO_GRANDE copias, pide confirmar. */
    public function anotar(string $fecha, string $maq, string $cont, int $valor, bool $confirmado = false): array
    {
        $m = $this->maquinas()->firstWhere('uid', $maq);
        $c = $m ? collect($m->contadores ?? [])->firstWhere('id', $cont) : null;
        if (! $c) {
            throw new ErrorNegocio('Esa máquina ya no existe. Recarga la página.');
        }
        if ($valor < 0 || $valor > 999_999_999) {
            throw new ErrorNegocio('Revisa el número del contador: tiene hasta 9 cifras.');
        }
        $hoy = $this->lecturaDel($fecha, $maq, $cont);
        $desde = $hoy ? $hoy->desde : ($this->anterior($fecha, $maq, $cont)['v'] ?? null);
        if ($desde !== null && $valor < $desde) {
            throw new ErrorNegocio('Es menor que la lectura anterior ('.self::numero($desde).'). Revisa el número.');
        }
        if ($desde !== null && $valor - $desde > self::SALTO_GRANDE && ! $confirmado) {
            return ['confirmar' => 'Son '.self::numero($valor - $desde).' copias desde la lectura anterior ('.self::numero($desde).'). ¿El número está bien?'];
        }
        $yo = Auth::user();
        LecturaContador::create([
            'uid' => Texto::nuevoUid(), 'fecha' => $fecha, 'tipo' => 'fin', 'maquina_uid' => $maq, 'contador_uid' => $cont,
            'valor' => $valor, 'desde' => $desde, 'usuario_id' => $yo?->id, 'vendedor' => $yo?->nombre,
            'ocurrido_at' => $fecha === today()->toDateString() ? now() : Carbon::parse($fecha.' 20:00'),
        ]);
        if ($hoy) {
            Bitacora::registrar('caja', 'Corrigió la lectura de '.self::nombre($m, $c).': '.self::numero($hoy->valor).' → '.self::numero($valor));
        }

        return ['ok' => self::nombre($m, $c).': '.self::numero($valor).' guardado'];
    }

    public function merma(string $fecha, string $grupo, int $copias): void
    {
        if (! in_array($grupo, $this->grupos(), true) || $copias <= 0) {
            throw new ErrorNegocio('Escribe cuántas copias fueron.');
        }
        if ($copias > 100_000) {
            throw new ErrorNegocio('Son demasiadas copias de prueba: revisa el número.');
        }
        $yo = Auth::user();
        LecturaContador::create([
            'uid' => Texto::nuevoUid(), 'fecha' => $fecha, 'tipo' => 'merma', 'grupo' => $grupo, 'valor' => $copias,
            'usuario_id' => $yo?->id, 'vendedor' => $yo?->nombre, 'ocurrido_at' => $fecha === today()->toDateString() ? now() : Carbon::parse($fecha.' 20:00'),
        ]);
        Bitacora::registrar('caja', 'Anotó '.self::numero($copias).' copias de prueba o malogradas ('.self::GRUPOS[$grupo].')');
    }

    /**
     * Historial de una máquina: por día (más reciente primero), la lectura y las copias de cada contador.
     * [[fecha, total, conts => [cid => [v, copias|null]]]]
     */
    public function historial(Maquina $m, int $dias = 60): array
    {
        $L = LecturaContador::where('tipo', 'fin')->where('maquina_uid', $m->uid)->where('fecha', '>=', today()->subDays($dias)->toDateString())
            ->orderBy('ocurrido_at')->get()->groupBy(fn ($l) => $l->fecha->toDateString());
        $out = [];
        foreach ($L->sortKeysDesc() as $f => $ls) {
            $fila = ['fecha' => $f, 'total' => 0, 'conts' => []];
            foreach ($m->contadores ?? [] as $c) {
                $l = $ls->where('contador_uid', $c['id'])->last();
                if ($l) {
                    $n = $l->desde === null ? null : $l->valor - $l->desde;
                    $fila['conts'][$c['id']] = ['v' => $l->valor, 'copias' => $n];
                    $fila['total'] += $n ?? 0;
                }
            }
            $out[] = $fila;
        }

        return $out;
    }
}
