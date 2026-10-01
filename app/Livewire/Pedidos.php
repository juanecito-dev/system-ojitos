<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConAutorizacion;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\PlantillaUtiles;
use App\Models\Producto;
use App\Models\Usuario;
use App\Services\Clientes as ServicioClientes;
use App\Services\ErrorNegocio;
use App\Services\Pedidos as ServicioPedidos;
use App\Services\Stock;
use App\Support\Dinero;
use App\Support\NegocioActual;
use App\Support\Texto;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Pedidos')]
class Pedidos extends Component
{
    use ConAutorizacion;

    #[Url(as: 'f')]
    public string $filtro = 'proceso';   // proceso, entregas, cotizado, entregado, todos

    public string $q = '';

    #[Url(as: 'ver')]
    public ?string $ver = null;

    #[Url(as: 'listas')]
    public bool $listas = false;

    public string $listasQ = '';

    // ---- editor: null cerrado; 'nuevo' o uid del pedido; 'lista' para una lista de útiles
    #[Locked]
    public ?string $editando = null;

    #[Locked]
    public ?string $editLista = null;

    public array $e = [];

    public string $eCliQ = '';

    public string $eItemQ = '';

    public string $error = '';

    // ---- ventanas
    #[Locked]
    public ?string $aceptando = null;

    public array $ac = ['fecha' => '', 'hora' => '', 'adelanto' => '', 'metodo' => 'efectivo'];

    #[Locked]
    public ?string $pagando = null;

    public array $pg = ['monto' => '', 'metodo' => 'efectivo'];

    #[Locked]
    public ?string $borrando = null;

    public ?string $guardandoLista = null;

    public string $nombreLista = '';

    public bool $usandoLista = false;

    public string $usarQ = '';

    private function pedido(?string $uid): ?Pedido
    {
        return $uid ? Pedido::with(['items', 'pagos'])->where('uid', $uid)->first() : null;
    }

    private function srv(): ServicioPedidos
    {
        return app(ServicioPedidos::class);
    }

    public function cerrarVentanas(): void
    {
        $this->aceptando = null;
        $this->pagando = null;
        $this->borrando = null;
        $this->guardandoLista = null;
        $this->usandoLista = false;
        $this->error = '';
    }

    public function abrir(string $uid): void
    {
        $this->ver = $uid;
        $this->editando = null;
        $this->listas = false;
        $this->cerrarVentanas();
    }

    public function volver(): void
    {
        $this->ver = null;
        $this->editando = null;
        $this->editLista = null;
        $this->cerrarVentanas();
    }

    // ================================================================ editor

    private function formVacio(string $etapa): array
    {
        return ['etapa' => $etapa, 'cliente' => ['nombre' => '', 'cel' => '', 'doc' => '', 'inst' => '', 'dir' => ''], 'cliente_id' => null,
            'items' => [], 'detalle' => '', 'monto' => '', 'descuento' => '', 'validez' => 7, 'fecha' => today()->toDateString(),
            'cond_entrega' => ServicioPedidos::COND_ENTREGA, 'cond_pago' => ServicioPedidos::COND_PAGO, 'notas' => '',
            'fecha_entrega' => $etapa === 'cotizado' ? '' : today()->addDay()->toDateString(), 'hora' => '', 'responsable' => '',
            'adelanto' => '', 'metodo' => 'efectivo', 'lista' => ['nombre' => '', 'inst' => '']];
    }

    public function nuevo(string $etapa = 'proceso'): void
    {
        $this->editando = 'nuevo';
        $this->editLista = null;
        $this->ver = null;
        $this->listas = false;
        $this->error = '';
        $this->e = $this->formVacio($etapa === 'cotizado' ? 'cotizado' : 'proceso');
    }

    public function tipo(string $etapa): void
    {
        if ($this->editando === 'nuevo') {
            $this->e['etapa'] = $etapa === 'cotizado' ? 'cotizado' : 'proceso';
            if ($this->e['etapa'] === 'proceso' && ! $this->e['fecha_entrega']) {
                $this->e['fecha_entrega'] = today()->addDay()->toDateString();
            }
        }
    }

    private function aForm(Pedido $p, bool $duplicar = false): array
    {
        $f = $this->formVacio($p->etapa === 'cotizado' || $p->etapa === 'rechazado' ? 'cotizado' : 'proceso');
        $f['cliente'] = array_merge($f['cliente'], array_intersect_key($p->cliente ?? [], $f['cliente']));
        $f['cliente_id'] = $p->cliente_id;
        $f['items'] = $p->items->map(fn ($l) => ['pid' => $l->producto_id, 'nombre' => $l->nombre, 'det' => (string) $l->detalle, 'cant' => (int) $l->cantidad,
            'precio' => $duplicar ? $this->srv()->precioHoy($l->producto_id, $l->detalle, $l->precio) : $l->precio])->values()->all();
        $f['detalle'] = (string) $p->detalle;
        $f['monto'] = $p->items->isEmpty() ? Dinero::n($p->total) : '';
        $f['descuento'] = $p->descuento ? Dinero::n($p->descuento) : '';
        $f['validez'] = $p->validez;
        $f['cond_entrega'] = (string) $p->cond_entrega;
        $f['cond_pago'] = (string) $p->cond_pago;
        $f['notas'] = (string) $p->notas;
        if (! $duplicar) {
            $f['fecha'] = $p->fecha->toDateString();
            $f['fecha_entrega'] = $p->fecha_entrega?->toDateString() ?? '';
            $f['hora'] = (string) $p->hora_entrega;
            $f['responsable'] = (string) $p->responsable;
        }

        return $f;
    }

    public function editar(string $uid): void
    {
        $p = $this->pedido($uid);
        if (! $p || $p->etapa === 'entregado') {
            return;
        }
        $this->editando = $p->uid;
        $this->editLista = null;
        $this->error = '';
        $this->e = $this->aForm($p);
    }

    public function duplicar(string $uid): void
    {
        $p = $this->pedido($uid);
        if (! $p) {
            return;
        }
        $this->editando = 'nuevo';
        $this->editLista = null;
        $this->ver = null;
        $this->e = $this->aForm($p, true);
        $this->dispatch('toast', texto: 'Copia lista con los precios de hoy: cambia lo necesario');
    }

    public function agregarProducto(int $pid, int $op): void
    {
        $p = Producto::with('opciones')->find($pid);
        $o = $p?->opciones[$op] ?? null;
        if (! $o) {
            return;
        }
        $det = $p->opciones->count() > 1 && $o->etiqueta ? $o->etiqueta : '';
        foreach ($this->e['items'] as $i => $l) {
            if (($l['pid'] ?? null) === $p->id && (int) $l['precio'] === $o->precio && ($l['det'] ?? '') === $det) {
                $this->e['items'][$i]['cant']++;
                $this->eItemQ = '';

                return;
            }
        }
        $this->e['items'][] = ['pid' => $p->id, 'nombre' => $p->nombre, 'det' => $det, 'cant' => 1, 'precio' => $o->precio];
        $this->eItemQ = '';
    }

    public function itemLibre(): void
    {
        $this->e['items'][] = ['pid' => null, 'nombre' => '', 'det' => '', 'cant' => 1, 'precio' => 0];
    }

    public function quitarItem(int $i): void
    {
        unset($this->e['items'][$i]);
        $this->e['items'] = array_values($this->e['items']);
    }

    public function precioItem(int $i, string $v): void
    {
        if (isset($this->e['items'][$i])) {
            $c = Dinero::aCentimos($v);
            $this->e['items'][$i]['precio'] = $c !== null && $c >= 0 ? $c : $this->e['items'][$i]['precio'];
        }
    }

    public function elegirCliente(int $id): void
    {
        $c = Cliente::find($id);
        if (! $c) {
            return;
        }
        $this->e['cliente_id'] = $c->id;
        $this->e['cliente'] = array_merge($this->e['cliente'], ['nombre' => $c->nombre, 'doc' => $c->documento ?: $this->e['cliente']['doc'],
            'cel' => $c->celular ? Texto::fmtCel($c->celular) : $this->e['cliente']['cel'], 'dir' => $c->direccion ?: $this->e['cliente']['dir']]);
        $this->eCliQ = '';
    }

    public function entregaEn(int $dias): void
    {
        $this->e['fecha_entrega'] = today()->addDays($dias)->toDateString();
    }

    public function usarLista(string $uid): void
    {
        $t = PlantillaUtiles::where('uid', $uid)->first();
        if (! $t) {
            return;
        }
        foreach ($this->srv()->itemsDeLista($t) as $l) {
            $this->e['items'][] = $l;
        }
        if ($t->institucion && ! $this->e['cliente']['inst']) {
            $this->e['cliente']['inst'] = $t->institucion;
        }
        $this->usandoLista = false;
        $this->dispatch('toast', texto: 'Lista agregada: '.$t->nombre);
    }

    private function totalForm(): int
    {
        $sub = collect($this->e['items'] ?? [])->sum(fn ($l) => (int) ($l['cant'] ?? 0) * (int) ($l['precio'] ?? 0));
        if (! ($this->e['items'] ?? [])) {
            return (int) Dinero::aCentimos($this->e['monto'] ?? '');
        }
        $d = (int) Dinero::aCentimos($this->e['descuento'] ?? '');

        return $d > 0 && $d < $sub ? $sub - $d : $sub;
    }

    public function guardar()
    {
        $yo = Auth::user();
        if ($this->editLista !== null) {
            return $this->guardarLista();
        }
        $p = $this->editando && $this->editando !== 'nuevo' ? $this->pedido($this->editando) : null;
        $d = $this->e;
        $d['monto'] = Dinero::aCentimos($d['monto']) ?? 0;
        $d['descuento'] = Dinero::aCentimos($d['descuento']) ?? 0;
        try {
            $p = $this->srv()->guardar($p, $d, $yo, $p ? 0 : (int) Dinero::aCentimos($d['adelanto'] ?? ''), $d['metodo'] ?? 'efectivo');
        } catch (ErrorNegocio $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->editando = null;
        $this->ver = $p->uid;
        $this->dispatch('toast', texto: ($p->etapa === 'cotizado' ? 'Cotización ' : 'Pedido ').$p->numeroTxt().' guardado');
    }

    public function cancelarEdicion(): void
    {
        $this->listas = $this->editLista !== null;
        $this->editando = null;
        $this->editLista = null;
        $this->error = '';
    }

    // ================================================================ acciones

    public function marcarListo(string $uid): void
    {
        if ($p = $this->pedido($uid)) {
            $this->srv()->marcarListo($p);
            $this->dispatch('toast', texto: 'Listo. Avísale por WhatsApp');
        }
    }

    public function pedirAceptar(string $uid): void
    {
        $p = $this->pedido($uid);
        if (! $p) {
            return;
        }
        $this->aceptando = $uid;
        $this->ac = ['fecha' => today()->addDay()->toDateString(), 'hora' => '', 'adelanto' => '', 'metodo' => 'efectivo'];
    }

    public function adelantoRapido(int $c): void
    {
        $this->ac['adelanto'] = $c > 0 ? Dinero::n($c) : '';
    }

    public function aceptar(): void
    {
        $p = $this->pedido($this->aceptando);
        if (! $p) {
            return;
        }
        $ade = (int) Dinero::aCentimos($this->ac['adelanto']);
        try {
            $this->srv()->aceptar($p, $this->ac['fecha'] ?: null, $this->ac['hora'] ?: null, $ade, $this->ac['metodo'], Auth::user());
        } catch (ErrorNegocio $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->aceptando = null;
        $this->filtro = 'proceso';
        $this->dispatch('toast', texto: 'Pasó a encargo'.($ade ? ', adelanto '.Dinero::s($ade).' registrado' : ''));
    }

    public function cobrarAhora(string $uid)
    {
        return $this->redirectRoute('vender', ['pedido' => $uid]);
    }

    public function rechazar(string $uid): void
    {
        if ($p = $this->pedido($uid)) {
            $this->srv()->rechazar($p);
        }
    }

    public function reabrir(string $uid): void
    {
        if ($p = $this->pedido($uid)) {
            $this->srv()->reabrir($p);
        }
    }

    public function renovar(string $uid): void
    {
        if ($p = $this->pedido($uid)) {
            $this->srv()->renovar($p);
            $this->dispatch('toast', texto: 'Renovada con fecha y precios de hoy');
        }
    }

    public function responsable(string $uid, string $quien): void
    {
        if ($p = $this->pedido($uid)) {
            $this->srv()->responsable($p, mb_substr(trim($quien), 0, 40));
            $this->dispatch('toast', texto: 'Guardado');
        }
    }

    public function pedirPago(string $uid): void
    {
        $this->pagando = $uid;
        $this->pg = ['monto' => '', 'metodo' => 'efectivo'];
        $this->error = '';
    }

    public function pagar(): void
    {
        $p = $this->pedido($this->pagando);
        if (! $p) {
            return;
        }
        $v = (int) Dinero::aCentimos($this->pg['monto']);
        try {
            $this->srv()->registrarPago($p, $v, $this->pg['metodo'], 'Pago a cuenta', Auth::user());
        } catch (ErrorNegocio $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->pagando = null;
        $this->dispatch('toast', texto: 'Pago registrado: '.Dinero::s($v).'. Saldo '.Dinero::s($p->fresh(['items', 'pagos'])->saldo()));
    }

    public function entregar(string $uid)
    {
        $p = $this->pedido($uid);
        if (! $p || ! $p->activo()) {
            return;
        }
        if ($p->saldo() > 0) {
            return $this->redirectRoute('vender', ['pedido' => $uid]);
        }
        $this->srv()->entregar($p, Auth::user());
        $this->dispatch('toast', texto: 'Entregado', accion: ['label' => 'Deshacer', 'evento' => 'deshacerEntrega', 'params' => ['uid' => $uid]]);
    }

    #[On('deshacerEntrega')]
    public function deshacerEntrega(string $uid): void
    {
        if ($p = $this->pedido($uid)) {
            $this->srv()->deshacerEntrega($p);
        }
    }

    public function pedirEliminar(string $uid): void
    {
        $p = $this->pedido($uid);
        if (! $p || ! $this->requiere('borrar', 'Eliminar el pedido '.$p->numeroTxt(), 'pedirEliminar', [$uid])) {
            return;
        }
        $this->borrando = $uid;
    }

    public function eliminar(): void
    {
        $p = $this->pedido($this->borrando);
        $this->borrando = null;
        if ($p) {
            $this->srv()->eliminar($p);
            $this->ver = null;
            $this->dispatch('toast', texto: 'Pedido eliminado');
        }
    }

    public function pedirGuardarLista(string $uid): void
    {
        $p = $this->pedido($uid);
        $this->guardandoLista = $uid;
        $this->nombreLista = $p?->dato('inst') ?? '';
    }

    public function guardarComoLista(): void
    {
        $p = $this->pedido($this->guardandoLista);
        if (! $p || trim($this->nombreLista) === '') {
            return;
        }
        $this->srv()->guardarComoLista($p, $this->nombreLista);
        $this->guardandoLista = null;
        $this->dispatch('toast', texto: 'Lista guardada: '.trim($this->nombreLista));
    }

    // ================================================================ listas de útiles

    public function verListas(): void
    {
        $this->listas = true;
        $this->ver = null;
        $this->editando = null;
    }

    public function editarLista(?string $uid = null): void
    {
        $t = $uid ? PlantillaUtiles::where('uid', $uid)->first() : null;
        $this->editando = 'lista';
        $this->editLista = $t?->uid ?? '';
        $this->error = '';
        $this->e = $this->formVacio('cotizado');
        $this->e['items'] = $t ? $this->srv()->itemsDeLista($t) : [];
        $this->e['lista'] = ['nombre' => $t?->nombre ?? '', 'inst' => $t?->institucion ?? ''];
    }

    private function guardarLista(): void
    {
        $items = collect($this->e['items'])->filter(fn ($l) => trim((string) ($l['nombre'] ?? '')) !== '' && ($l['cant'] ?? 0) > 0)->values();
        $nombre = trim($this->e['lista']['nombre'] ?? '');
        if ($nombre === '') {
            $this->error = 'Ponle un nombre a la lista.';

            return;
        }
        if ($items->isEmpty()) {
            $this->error = 'Agrega al menos un útil.';

            return;
        }
        $uids = Producto::withTrashed()->whereIn('id', $items->pluck('pid')->filter())->pluck('uid', 'id');
        $datos = ['nombre' => $nombre, 'institucion' => trim($this->e['lista']['inst'] ?? '') ?: null,
            'items' => $items->map(fn ($l) => ['pid' => $l['pid'] ?? null, 'sid' => $uids[$l['pid'] ?? 0] ?? null, 'nombre' => $l['nombre'], 'det' => (string) ($l['det'] ?? ''),
                'cant' => (int) $l['cant'], 'precio' => (int) $l['precio']])->all()];
        $t = $this->editLista ? PlantillaUtiles::where('uid', $this->editLista)->first() : null;
        $t ? $t->update($datos) : PlantillaUtiles::create($datos + ['uid' => Texto::nuevoUid()]);
        $this->editando = null;
        $this->editLista = null;
        $this->listas = true;
        $this->dispatch('toast', texto: 'Lista guardada');
    }

    public function cotizarLista(string $uid): void
    {
        $t = PlantillaUtiles::where('uid', $uid)->first();
        if (! $t) {
            return;
        }
        $this->nuevo('cotizado');
        $this->e['items'] = $this->srv()->itemsDeLista($t);
        $this->e['cliente']['inst'] = (string) $t->institucion;
        $this->e['notas'] = 'Lista: '.$t->nombre;
        $this->dispatch('toast', texto: 'Escribe el nombre del padre o madre y guarda');
    }

    public function eliminarLista(string $uid): void
    {
        PlantillaUtiles::where('uid', $uid)->delete();
    }

    // ================================================================

    public function render(Stock $stock)
    {
        $neg = app(NegocioActual::class)->obligatorio();
        $datos = ['neg' => $neg, 'usuarios' => Usuario::where('activo', true)->orderBy('orden')->get()->map->primerNombre()->unique()->values()];

        if ($this->editando) {
            $datos['stock'] = $stock->todos();
            $datos['totalForm'] = $this->totalForm();
            $datos['subForm'] = collect($this->e['items'] ?? [])->sum(fn ($l) => (int) ($l['cant'] ?? 0) * (int) ($l['precio'] ?? 0));
            $datos['pagadoForm'] = $this->editando !== 'nuevo' && $this->editando !== 'lista' ? ($this->pedido($this->editando)?->pagado() ?? 0) : 0;
            $datos['cliHits'] = trim($this->eCliQ) === '' ? collect() : Cliente::where(function ($w) {
                $q = trim($this->eCliQ);
                $d = preg_replace('/\D/', '', $q);
                $w->where('nombre', 'like', '%'.$q.'%');
                if (strlen($d) >= 3) {
                    $w->orWhere('celular', 'like', '%'.$d.'%')->orWhere('documento', 'like', '%'.$d.'%');
                }
            })->limit(5)->get();
            $n = Texto::norm(trim($this->eItemQ));
            $datos['itemHits'] = $n === '' ? collect() : Producto::with('opciones')->orderBy('orden')->get()
                ->filter(fn ($p) => $p->opciones->isNotEmpty() && str_contains(Texto::norm($p->nombre.' '.$p->grupo.' '.$p->codigos_barra), $n))->take(8)->values();
            $datos['listasUsar'] = $this->usandoLista ? PlantillaUtiles::orderBy('nombre')->get()
                ->filter(fn ($t) => trim($this->usarQ) === '' || str_contains(Texto::norm($t->nombre.' '.$t->institucion), Texto::norm($this->usarQ)))->values() : collect();
            $datos['saldos'] = app(ServicioClientes::class)->saldos();

            return view('livewire.pedidos-editar', $datos);
        }

        if ($this->listas) {
            $st = $stock->todos();
            $datos['plantillas'] = PlantillaUtiles::orderBy('nombre')->get()
                ->filter(fn ($t) => trim($this->listasQ) === '' || str_contains(Texto::norm($t->nombre.' '.$t->institucion), Texto::norm($this->listasQ)))
                ->map(function ($t) use ($st) {
                    $its = $this->srv()->itemsDeLista($t);

                    return ['t' => $t, 'items' => $its, 'total' => collect($its)->sum(fn ($l) => $l['cant'] * $l['precio']),
                        'falta' => collect($its)->filter(fn ($l) => $l['pid'] && isset($st[$l['pid']]) && $st[$l['pid']] < $l['cant'])->count()];
                })->values();

            return view('livewire.pedidos-listas', $datos);
        }

        if ($this->ver && ($p = Pedido::with(['items', 'pagos', 'historial'])->where('uid', $this->ver)->first())) {
            $datos['p'] = $p;
            $datos['aceptando'] = $this->aceptando;
            $datos['metodos'] = $neg->metodosActivos();

            return view('livewire.pedidos-ver', $datos);
        }
        $this->ver = null;

        // ---- lista
        $todos = Pedido::with(['items', 'pagos'])->orderByDesc('creado_at')->get();
        $q = Texto::norm(trim($this->q));
        $qd = preg_replace('/\D/', '', $this->q);
        $coincide = fn (Pedido $p) => $q === '' || str_contains(Texto::norm(implode(' ', [$p->numeroTxt(), $p->dato('nombre'), $p->dato('inst'), $p->descripcion()])), $q)
            || (strlen($qd) >= 3 && (str_contains($p->celular(), $qd) || str_contains((string) $p->numero, ltrim($qd, '0'))));
        $F = [
            'proceso' => fn ($p) => $p->activo(), 'entregas' => fn ($p) => $p->activo(),
            'cotizado' => fn ($p) => in_array($p->etapaVista(), ['cotizado', 'vencido'], true),
            'entregado' => fn ($p) => in_array($p->etapa, ['entregado', 'rechazado'], true), 'todos' => fn () => true,
        ];
        $lista = $todos->filter($F[$this->filtro] ?? $F['proceso'])->filter($coincide);
        $lista = match ($this->filtro) {
            'proceso', 'entregas' => $lista->sortBy(fn ($p) => ($p->fecha_entrega?->toDateString() ?? '9').($p->hora_entrega ?? '')),
            'entregado' => $lista->sortByDesc(fn ($p) => $p->entregado_at ?? $p->cerrado_at ?? $p->creado_at),
            default => $lista,
        };
        $act = $todos->filter->activo();
        $cot = $todos->filter(fn ($p) => $p->etapaVista() === 'cotizado');
        $mes = today()->format('Y-m');
        $cotMes = $todos->filter(fn ($p) => $p->cotizada && $p->fecha->format('Y-m') === $mes);
        $datos += [
            'lista' => $lista->values(), 'act' => $act, 'cot' => $cot, 'olvidados' => $todos->filter->olvidado(),
            'cotMes' => $cotMes, 'acMes' => $cotMes->filter(fn ($p) => ! in_array($p->etapa, ['cotizado', 'rechazado'], true)),
            'cuenta' => collect($F)->map(fn ($f) => $todos->filter($f)->count()),
        ];
        if ($this->filtro === 'entregas') {
            $hoy = today();
            $datos['grupos'] = $lista->groupBy(fn ($p) => match (true) {
                ! $p->fecha_entrega => '6 Sin fecha',
                $p->fecha_entrega->lt($hoy) => '1 Atrasados',
                $p->fecha_entrega->isSameDay($hoy) => '2 Hoy',
                $p->fecha_entrega->isSameDay($hoy->copy()->addDay()) => '3 Mañana',
                $p->fecha_entrega->lte($hoy->copy()->addDays(7)) => '4 Esta semana',
                default => '5 Más adelante',
            })->sortKeys();
        }

        return view('livewire.pedidos', $datos);
    }
}
