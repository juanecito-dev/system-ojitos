<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConAutorizacion;
use App\Models\Compra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\Compras as Srv;
use App\Services\ErrorNegocio;
use App\Services\Stock;
use App\Support\Dinero;
use App\Support\NegocioActual;
use App\Support\Texto;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Compras y proveedores: por pagar, pagadas, por reponer y proveedores (renderComp2 del sistema anterior). */
#[Title('Compras y proveedores')]
class Compras extends Component
{
    use ConAutorizacion;

    #[Url(as: 'f')]
    public string $filtro = 'porpagar';

    #[Url(as: 'q')]
    public string $buscar = '';

    public string $mes = '';

    // ventana de la compra
    #[Locked]
    public bool $editando = false;

    #[Locked]
    public ?int $compraId = null;

    public array $c = [];

    public array $lineas = [];

    public string $iq = '';

    #[Locked]
    public bool $borrando = false;

    // pago al proveedor
    #[Locked]
    public ?int $pagoDe = null;

    public string $pMonto = '';

    public string $pMet = 'efectivo';

    public bool $pCaja = true;

    // proveedor
    #[Locked]
    public bool $provAbierto = false;

    #[Locked]
    public ?int $provId = null;

    public array $pv = ['nombre' => '', 'ruc' => '', 'cel' => ''];

    public string $error = '';

    public function mount(): void
    {
        $this->mes = today()->format('Y-m');
    }

    public function updatedFiltro(): void
    {
        if (! in_array($this->filtro, ['porpagar', 'pagadas', 'reponer', 'proveedores'], true)) {
            $this->filtro = 'porpagar';
        }
    }

    private function srv(): Srv
    {
        return app(Srv::class);
    }

    // ------------------------------------------------------------ compra

    public function abrirCompra(?int $id = null, ?string $reponerDe = null): void
    {
        $f = $id ? Compra::with(['items', 'pagos'])->find($id) : null;
        $this->error = '';
        $this->iq = '';
        $this->borrando = false;
        $this->editando = true;
        $this->compraId = $f?->id;
        $prov = $f?->proveedor_id ?? Proveedor::orderBy('nombre')->value('id');
        $lineas = [];
        if ($f) {
            foreach ($f->items as $l) {
                $lineas[] = $this->linea($l->producto_id, $l->nombre, $l->cantidad, $l->factor, $l->unidad, $l->costo_unitario);
            }
        } elseif ($reponerDe !== null) {
            $prov = $reponerDe === '' ? $prov : (int) $reponerDe;
            foreach ($this->srv()->sugerencias()->filter(fn ($x) => (string) ($x['prov_id'] ?? '') === $reponerDe) as $x) {
                $lineas[] = $this->linea($x['p']->id, $x['p']->nombre, $x['cant'], $x['uc']['f'], $x['uc']['n'], $x['costoU']);
            }
        }
        $this->c = [
            'prov' => (string) ($prov ?? '__new'), 'pn' => '', 'numero' => $f?->numero ?? '', 'fecha' => $f?->fecha?->toDateString() ?? today()->toDateString(),
            'cond' => $f?->condicion ?? 'credito', 'vence' => $f?->vence?->toDateString() ?? today()->addDays(30)->toDateString(), 'nota' => $f?->nota ?? '',
            'total' => $f && $f->items->isEmpty() ? Dinero::n($f->total) : '', 'met' => 'efectivo', 'caja' => true,
        ];
        $this->lineas = $lineas;
    }

    private function linea(?int $pid, string $nombre, float $cant, float $factor, ?string $unidad, float $costo): array
    {
        return ['pid' => $pid, 'nombre' => $nombre, 'cant' => Stock::formato($cant), 'f' => $factor > 1 ? Stock::formato($factor) : '1',
            'un' => $factor > 1 ? (string) $unidad : 'Unidad', 'costo' => $costo > 0 ? Dinero::nCosto($costo) : '', 'otra' => false, 'otraN' => '', 'otraF' => ''];
    }

    public function agregarProducto(int $id): void
    {
        $p = Producto::find($id);
        if (! $p) {
            return;
        }
        $uc = $p->unidadCompra();
        $h = $this->srv()->costosDe($p->id)->first();
        $this->lineas[] = $this->linea($p->id, $p->nombre, 1, $uc['f'], $uc['n'], round(($h['c'] ?? ($p->costo ?? 0)) * $uc['f'], 4));
        $this->iq = '';
        $this->dispatch('enfocar-linea', i: count($this->lineas) - 1);
    }

    public function quitarLinea(int $i): void
    {
        unset($this->lineas[$i]);
        $this->lineas = array_values($this->lineas);
    }

    /** cambia la presentación de una línea: 1 (suelta), el factor de compra del producto u «otra» */
    public function updatedLineas($valor, string $clave): void
    {
        if (! preg_match('/^(\d+)\.f$/', $clave, $m) || ! isset($this->lineas[(int) $m[1]])) {
            return;
        }
        $i = (int) $m[1];
        $p = Producto::find($this->lineas[$i]['pid']);
        if ($valor === 'otra') {
            $this->lineas[$i]['otra'] = true;
            $this->lineas[$i]['f'] = '1';

            return;
        }
        $this->lineas[$i]['otra'] = false;
        $this->lineas[$i]['un'] = (float) $valor > 1 && $p ? $p->unidadCompra()['n'] : 'Unidad';
    }

    /** «Otra presentación»: se guarda en el producto para la próxima vez */
    public function fijarPresentacion(int $i): void
    {
        $l = $this->lineas[$i] ?? null;
        $n = trim((string) ($l['otraN'] ?? ''));
        $f = (float) str_replace(',', '.', (string) ($l['otraF'] ?? ''));
        if (! $l || $n === '' || ! ($f > 1)) {
            $this->error = 'Escribe cómo viene (ej.: Caja) y cuántas unidades trae.';

            return;
        }
        if ($p = Producto::find($l['pid'])) {
            $p->fijarExtra('uc', ['n' => mb_substr($n, 0, 20), 'f' => $f]);
            $p->save();
        }
        $this->lineas[$i] = array_merge($l, ['un' => mb_substr($n, 0, 20), 'f' => Stock::formato($f), 'otra' => false, 'otraN' => '', 'otraF' => '']);
        $this->error = '';
    }

    public static function subtotal(array $l): int
    {
        $c = (float) str_replace(',', '.', (string) ($l['cant'] ?? 0));

        return (int) round($c * (Dinero::aCosto($l['costo'] ?? '') ?? 0));
    }

    public function guardarCompra(): void
    {
        $f = $this->compraId ? Compra::find($this->compraId) : null;
        $c = $this->c;
        $lineas = array_map(fn ($l) => ['producto_id' => $l['pid'], 'nombre' => $l['nombre'], 'cantidad' => (float) str_replace(',', '.', (string) $l['cant']),
            'unidad' => $l['un'], 'factor' => (float) $l['f'], 'costo_unitario' => Dinero::aCosto($l['costo']) ?? 0], $this->lineas);
        try {
            $this->srv()->guardar($f, [
                'proveedor_id' => $c['prov'] === '__new' ? null : (int) $c['prov'], 'proveedor_nuevo' => $c['pn'], 'numero' => $c['numero'], 'fecha' => $c['fecha'],
                'condicion' => $c['cond'], 'vence' => $c['vence'], 'nota' => $c['nota'], 'total' => Dinero::aCentimos($c['total']) ?? 0,
            ], $lineas, Auth::user(), $f ? null : [$c['met'], $c['met'] === 'efectivo' && $c['caja']]);
        } catch (ErrorNegocio $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->editando = false;
        $this->dispatch('toast', texto: 'Compra guardada'.($lineas ? ': se sumó al stock' : ''));
    }

    public function pedirBorrar(): void
    {
        $this->borrando = (bool) $this->compraId;
    }

    public function noBorrar(): void
    {
        $this->borrando = false;
    }

    public function eliminarCompra(): void
    {
        $f = $this->compraId ? Compra::with(['pagos', 'proveedor'])->find($this->compraId) : null;
        $this->borrando = false;
        if (! $f || ! $this->requiere('borrar', 'Eliminar la compra '.($f->numero ?: '').' de '.$f->proveedor?->nombre.' por '.Dinero::s($f->total), 'eliminarCompra')) {
            return;
        }
        $this->srv()->eliminar($f, Auth::user());
        $this->editando = false;
        $this->dispatch('toast', texto: 'Compra eliminada');
    }

    // ------------------------------------------------------------ pagos

    public function abrirPago(int $id): void
    {
        $f = Compra::with('pagos')->find($id);
        if (! $f || ! $f->saldo()) {
            return;
        }
        $this->pagoDe = $id;
        $this->pMonto = Dinero::n($f->saldo());
        $this->pMet = 'efectivo';
        $this->pCaja = true;
        $this->error = '';
    }

    public function pagar(): void
    {
        $f = $this->pagoDe ? Compra::with(['pagos', 'proveedor'])->find($this->pagoDe) : null;
        if (! $f) {
            return;
        }
        try {
            $this->srv()->pagar($f, Dinero::aCentimos($this->pMonto) ?? 0, $this->pMet, $this->pCaja, Auth::user());
        } catch (ErrorNegocio $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->pagoDe = null;
        $s = $f->fresh('pagos')->saldo();
        $this->dispatch('toast', texto: $s ? 'Pago registrado, quedan '.Dinero::s($s) : 'Factura pagada por completo');
    }

    // ------------------------------------------------------------ proveedores

    public function abrirProveedor(?int $id = null): void
    {
        $p = $id ? Proveedor::find($id) : null;
        $this->provAbierto = true;
        $this->provId = $p?->id;
        $this->pv = ['nombre' => $p?->nombre ?? '', 'ruc' => $p?->ruc ?? '', 'cel' => $p?->celular ?? ''];
        $this->error = '';
    }

    public function guardarProveedor(): void
    {
        try {
            $this->srv()->guardarProveedor($this->provId ? Proveedor::find($this->provId) : null, $this->pv['nombre'], $this->pv['ruc'], $this->pv['cel']);
        } catch (ErrorNegocio $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->provAbierto = false;
        $this->dispatch('toast', texto: 'Proveedor guardado');
    }

    public function eliminarProveedor(): void
    {
        $p = $this->provId ? Proveedor::find($this->provId) : null;
        try {
            $p && $this->srv()->eliminarProveedor($p);
        } catch (ErrorNegocio $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->provAbierto = false;
    }

    public function cerrar(): void
    {
        $this->editando = false;
        $this->borrando = false;
        $this->pagoDe = null;
        $this->provAbierto = false;
        $this->error = '';
    }

    public function render()
    {
        $this->updatedFiltro();
        $srv = $this->srv();
        $F = Compra::with(['pagos', 'proveedor', 'items'])->get();
        $porPagar = $F->filter(fn ($f) => $f->saldo() > 0);
        $mes = today()->format('Y-m');
        $q = Texto::norm($this->buscar);
        $busca = fn ($f) => $q === '' || str_contains(Texto::norm(($f->proveedor?->nombre ?? '').' '.$f->numero.' '.$f->nota.' '.$f->items->pluck('nombre')->join(' ')), $q);
        $sug = $srv->sugerencias();
        $datos = [
            'porPagarTotal' => $porPagar->sum(fn ($f) => $f->saldo()),
            'vencido' => $porPagar->filter->vencida()->sum(fn ($f) => $f->saldo()),
            'prox' => $porPagar->filter(fn ($f) => $f->venceEn(7))->sum(fn ($f) => $f->saldo()),
            'compMes' => $F->filter(fn ($f) => $f->fecha->format('Y-m') === $mes)->sum('total'),
            'nRep' => $sug->count(), 'neg' => app(NegocioActual::class)->obligatorio(),
            'proveedores' => Proveedor::orderBy('nombre')->get(),
        ];
        if ($this->filtro === 'porpagar') {
            $datos['lista'] = $porPagar->filter($busca)->sortBy(fn ($f) => $f->vence?->toDateString() ?? '9999');
        } elseif ($this->filtro === 'pagadas') {
            $datos['lista'] = $F->filter(fn ($f) => $f->saldo() === 0)->filter($busca)
                ->filter(fn ($f) => $q !== '' || $this->mes === '' || $f->fecha->format('Y-m') === $this->mes)->sortByDesc(fn ($f) => $f->fecha->toDateString().str_pad((string) $f->id, 8, '0', STR_PAD_LEFT));
        } elseif ($this->filtro === 'reponer') {
            $datos['grupos'] = $sug->groupBy(fn ($x) => (string) ($x['prov_id'] ?? ''));
        } else {
            $datos['provs'] = $datos['proveedores']->filter(fn ($p) => $q === '' || str_contains(Texto::norm($p->nombre.' '.$p->ruc), $q))
                ->map(fn ($p) => ['p' => $p, 'fs' => $F->where('proveedor_id', $p->id)]);
        }
        if ($this->editando) {
            $datos['fEdit'] = $this->compraId ? $F->firstWhere('id', $this->compraId) : null;
            $st = app(Stock::class)->todos();
            $datos['st'] = $st;
            $datos['prods'] = Producto::whereIn('id', array_filter(array_column($this->lineas, 'pid')))->get()->keyBy('id');
            $datos['info'] = collect($this->lineas)->mapWithKeys(fn ($l, $i) => [$i => $l['pid'] ? $srv->infoCosto($l['pid']) : '']);
            $iq = Texto::norm(trim($this->iq));
            $datos['hits'] = $iq === '' ? collect() : Producto::orderBy('orden')->get()
                ->filter(fn ($p) => ($p->oculto || $p->rapido || array_key_exists($p->id, $st)) && str_contains(Texto::norm($p->nombre.' '.$p->grupo.' '.$p->codigos_barra), $iq))->take(8);
        }
        if ($this->pagoDe) {
            $datos['fPago'] = $F->firstWhere('id', $this->pagoDe);
        }
        if ($this->provAbierto && $this->provId) {
            $datos['pEdit'] = $datos['proveedores']->firstWhere('id', $this->provId);
            $datos['pCompras'] = $F->where('proveedor_id', $this->provId)->sortByDesc(fn ($f) => $f->fecha->toDateString());
        }

        return view('livewire.compras', $datos);
    }
}
