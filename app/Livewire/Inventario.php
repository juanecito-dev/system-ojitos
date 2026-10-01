<?php

namespace App\Livewire;

use App\Models\Producto;
use App\Models\StockBase;
use App\Services\Bitacora;
use App\Services\Compras;
use App\Services\ErrorNegocio;
use App\Services\Inventario as Inv;
use App\Services\Stock;
use App\Support\Dinero;
use App\Support\Texto;
use App\Support\Valida;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Inventario: stock, entradas, conteos, insumos de los servicios y ficha con kárdex (renderInv del sistema anterior). */
#[Title('Inventario')]
class Inventario extends Component
{
    #[Url(as: 'f')]
    public string $filtro = 'control';

    #[Url(as: 'q')]
    public string $buscar = '';

    #[Url(as: 'g')]
    public string $grupo = '';

    /** ventana de entrada o conteo: [id, tipo] */
    #[Locked]
    public ?array $mov = null;

    public string $movN = '';

    public string $movNota = '';

    public string $movCosto = '';

    public string $movMotivo = '';

    /** ficha del producto con sus ajustes y el kárdex */
    #[Locked]
    public ?int $ficha = null;

    public array $aj = ['min' => '', 'costo' => '', 'un' => '', 'f' => '', 'um' => '', 'oculto' => false];

    public string $kDesde = '';

    public string $kHasta = '';

    public int $kVer = 60;

    #[Locked]
    public bool $nuevoInsumo = false;

    public array $ni = ['nombre' => '', 'um' => '', 'stock' => '', 'un' => '', 'f' => '', 'costo' => '', 'min' => ''];

    /** qué gasta un servicio: id del servicio y filas [id, modo gasta|rinde, valor] */
    #[Locked]
    public ?int $consumo = null;

    public array $filas = [];

    public string $error = '';

    public function updatedFiltro(): void
    {
        if (! in_array($this->filtro, ['control', 'reponer', 'sin', 'consumo'], true)) {
            $this->filtro = 'control';
        }
    }

    private function producto(?int $id): ?Producto
    {
        return $id ? Producto::with(['opciones', 'insumos.insumo'])->find($id) : null;
    }

    private function cant(string $v): ?float
    {
        $v = str_replace(',', '.', trim($v));

        return is_numeric($v) ? (float) $v : null;
    }

    // ------------------------------------------------------------ entrada y conteo

    public function abrirMov(int $id, string $tipo): void
    {
        $p = $this->producto($id);
        if (! $p || ! in_array($tipo, ['entrada', 'conteo'], true)) {
            return;
        }
        $this->error = '';
        $this->ficha = null;
        $cur = app(Stock::class)->de($id);
        $uc = $p->unidadCompra();
        $this->mov = ['id' => $id, 'tipo' => $tipo];
        $this->movN = $tipo === 'entrada' ? Stock::formato($uc['f'] > 1 ? $uc['f'] : 12) : Stock::formato($cur ?? 0);
        $this->movNota = '';
        $this->movCosto = '';
        $this->movMotivo = '';
    }

    public function guardarMov(): void
    {
        $p = $this->producto($this->mov['id'] ?? null);
        $n = $this->cant($this->movN);
        if (! $p) {
            return;
        }
        $inv = app(Inv::class);
        try {
            if ($this->mov['tipo'] === 'entrada') {
                $c = Auth::user()->puede('costos') ? Dinero::aCosto($this->movCosto) : null;
                $queda = $inv->entrada($p, (float) $n, trim($this->movNota), $c, Auth::user());
            } else {
                if ($n === null) {
                    throw new ErrorNegocio('Escribe cuántos hay (puede ser 0).');
                }
                $inv->contar($p, $n, $this->movMotivo ?: null, Auth::user());
                $queda = $n;
            }
        } catch (ErrorNegocio $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->mov = null;
        if ($this->filtro === 'sin') {
            $this->filtro = 'control';
        }
        $this->dispatch('toast', texto: $p->nombre.': '.Stock::formato($queda).' en stock');
    }

    // ------------------------------------------------------------ ficha y kárdex

    public function abrirFicha(int $id): void
    {
        $p = $this->producto($id);
        if (! $p) {
            return;
        }
        $uc = $p->unidadCompra();
        $this->ficha = $id;
        $this->error = '';
        $this->aj = ['min' => Stock::formato($p->minimo()), 'costo' => $p->costo ? Dinero::nCosto($p->costo) : '', 'un' => $uc['f'] > 1 ? $uc['n'] : '',
            'f' => $uc['f'] > 1 ? Stock::formato($uc['f']) : '', 'um' => (string) $p->unidad, 'oculto' => $p->oculto];
        $this->kDesde = today()->subDays(29)->toDateString();
        $this->kHasta = today()->toDateString();
        $this->kVer = 60;
    }

    public function kardexDesde(string $cual): void
    {
        $this->kHasta = today()->toDateString();
        $this->kDesde = match ($cual) {
            'mes' => today()->startOfMonth()->toDateString(),
            '90' => today()->subDays(89)->toDateString(),
            'anio' => today()->startOfYear()->toDateString(),
            'todo' => '2000-01-01',
            default => today()->subDays(29)->toDateString(),
        };
        $this->kVer = 60;
    }

    public function guardarAjustes(): void
    {
        $p = $this->producto($this->ficha);
        if (! $p) {
            return;
        }
        $a = $this->aj;
        $min = $this->cant((string) $a['min']);
        $f = $this->cant((string) $a['f']);
        if ($min === null || $min < 0) {
            $this->error = 'Escribe el mínimo, por ejemplo 5.';

            return;
        }
        $cambios = ['stock_minimo' => $min];
        if (Auth::user()->puede('costos')) {
            $c = trim((string) $a['costo']) === '' ? null : Dinero::aCosto($a['costo']);
            if ($c !== null && $c < 0) {
                $this->error = 'Escribe el costo, por ejemplo 0.42';

                return;
            }
            if ((float) $c !== (float) $p->costo) {
                Bitacora::registrar('precio', 'Costo de '.$p->nombre.': '.($p->costo ? Dinero::sCosto($p->costo) : '—').' → '.($c ? Dinero::sCosto($c) : '—'));
            }
            $cambios['costo'] = $c;
        }
        $un = trim((string) $a['un']);
        $p->fijarExtra('uc', $un !== '' && $f > 1 ? ['n' => mb_substr($un, 0, 20), 'f' => $f] : null);
        if ($p->oculto) {
            $cambios['unidad'] = mb_substr(trim((string) $a['um']), 0, 20) ?: null;
            $cambios['oculto'] = (bool) $a['oculto'];
        }
        $p->fill($cambios)->save();
        $this->ficha = null;
        $this->dispatch('toast', texto: 'Ajustes guardados');
    }

    // ------------------------------------------------------------ insumos

    public function abrirInsumo(): void
    {
        $this->nuevoInsumo = true;
        $this->error = '';
        $this->ni = ['nombre' => '', 'um' => '', 'stock' => '', 'un' => '', 'f' => '', 'costo' => '', 'min' => ''];
    }

    public function crearInsumo(): void
    {
        $d = $this->ni;
        if (! Auth::user()->puede('costos')) {
            $d['costo'] = '';
        }
        try {
            app(Inv::class)->crearInsumo($d, Auth::user());
        } catch (ErrorNegocio $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->nuevoInsumo = false;
        $this->filtro = 'control';
        $this->dispatch('toast', texto: 'Insumo agregado: ahora indica qué servicios lo gastan (pestaña Insumos de servicios)');
    }

    public function abrirConsumo(int $id): void
    {
        $p = $this->producto($id);
        if (! $p) {
            return;
        }
        $this->consumo = $id;
        $this->filas = $p->insumos->map(fn ($x) => ['id' => $x->insumo_id, 'modo' => $x->cantidad < 1 ? 'rinde' : 'gasta',
            'v' => $x->cantidad < 1 ? (string) round(1 / $x->cantidad) : Stock::formato($x->cantidad)])->values()->all();
    }

    public function agregarFila(): void
    {
        $c = $this->candidatos($this->consumo)->first();
        if ($c) {
            $this->filas[] = ['id' => $c->id, 'modo' => 'gasta', 'v' => '1'];
        }
    }

    public function quitarFila(int $i): void
    {
        unset($this->filas[$i]);
        $this->filas = array_values($this->filas);
    }

    /** cuánto gasta por cada 1 vendido según la fila */
    public static function porUnidad(array $x): float
    {
        $v = (float) str_replace(',', '.', (string) ($x['v'] ?? 0));

        return $v > 0 ? (($x['modo'] ?? 'gasta') === 'rinde' ? 1 / $v : $v) : 0;
    }

    public function guardarConsumo(): void
    {
        $p = $this->producto($this->consumo);
        if (! $p) {
            return;
        }
        $n = app(Inv::class)->guardarInsumos($p, array_map(fn ($x) => [(int) ($x['id'] ?? 0), self::porUnidad($x)], $this->filas));
        $this->consumo = null;
        $this->dispatch('toast', texto: $n ? 'Listo: cada venta de '.$p->nombre.' descuenta sus insumos' : 'Sin insumos');
    }

    /** lo que puede gastar un servicio: insumos y productos con control de stock */
    public function candidatos(?int $id)
    {
        $conStock = StockBase::pluck('producto_id')->all();

        return Producto::where('id', '!=', $id ?? 0)->where(fn ($q) => $q->where('oculto', true)->orWhereIn('id', $conStock))->orderBy('orden')->get();
    }

    public function cerrar(): void
    {
        $this->mov = null;
        $this->ficha = null;
        $this->nuevoInsumo = false;
        $this->consumo = null;
        $this->error = '';
    }

    public function render(Inv $inv)
    {
        $this->updatedFiltro();
        $yo = Auth::user();
        $st = $inv->stock();
        $todos = Producto::with(['opciones', 'insumos.insumo'])->orderBy('orden')->get();
        $conS = $todos->filter(fn ($p) => array_key_exists($p->id, $st));
        $bajo = $conS->filter(fn ($p) => $st[$p->id] <= $p->minimo());
        $sin = $todos->filter(fn ($p) => ! array_key_exists($p->id, $st) && (($p->opciones->isNotEmpty() && $p->rapido) || $p->oculto));
        $servs = $todos->filter(fn ($p) => ! $p->oculto && ! array_key_exists($p->id, $st) && ! $p->rapido && $p->opciones->isNotEmpty());
        $base = ['reponer' => $bajo, 'sin' => $sin, 'consumo' => $servs][$this->filtro] ?? $conS;
        $grupos = $base->pluck('grupo')->unique()->values();
        if ($this->grupo !== '' && ! $grupos->contains($this->grupo)) {
            $this->grupo = '';
        }
        $q = Texto::norm($this->buscar);
        $lista = $base->filter(fn ($p) => ($this->grupo === '' || $p->grupo === $this->grupo)
            && ($q === '' || str_contains(Texto::norm($p->nombre.' '.$p->grupo.' '.$p->codigos_barra), $q)));

        $datos = [
            'st' => $st, 'lista' => $lista, 'grupos' => $grupos, 'nConS' => $conS->count(), 'nBajo' => $bajo->count(),
            'costos' => $yo->puede('costos'), 'vel' => $inv->velocidad(), 'hoy' => $inv->vendidoHoy(),
            'valor' => $yo->puede('costos') ? $inv->valorCosto($conS) : 0, 'perdidas' => $yo->puede('costos') ? $inv->perdidasMes() : 0,
            'reponer' => Route::has('compras') && $yo->puede('compras') && $bajo->isNotEmpty(),
            'motivos' => Inv::MOTIVOS,
        ];
        if ($this->mov) {
            $datos['movP'] = $todos->firstWhere('id', $this->mov['id']);
        }
        if ($this->ficha && ($fp = $todos->firstWhere('id', $this->ficha))) {
            $desde = Valida::fecha($this->kDesde) ? $this->kDesde : today()->subDays(29)->toDateString();
            $hasta = Valida::fecha($this->kHasta) ? $this->kHasta : today()->toDateString();
            $datos['fp'] = $fp;
            $datos['precios'] = $yo->puede('costos') ? app(Compras::class)->costosDe($fp->id) : collect();
            $datos['kardex'] = $inv->kardex($fp, min($desde, $hasta), max($desde, $hasta));
        }
        if ($this->consumo) {
            $datos['cp'] = $todos->firstWhere('id', $this->consumo);
            $datos['cands'] = $this->candidatos($this->consumo);
        }

        return view('livewire.inventario', $datos);
    }
}
