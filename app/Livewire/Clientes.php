<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConAutorizacion;
use App\Models\Cliente;
use App\Models\ClienteMovimiento;
use App\Models\Documento;
use App\Models\Pedido;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Services\Bitacora;
use App\Services\Clientes as ServicioClientes;
use App\Services\ErrorNegocio;
use App\Support\Dinero;
use App\Support\NegocioActual;
use App\Support\Texto;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Clientes y fiados')]
class Clientes extends Component
{
    use ConAutorizacion;

    #[Url(as: 'f')]
    public string $filtro = 'deben';

    public string $q = '';

    /** ficha abierta */
    #[Url(as: 'c')]
    public ?string $ver = null;

    public string $pago = '';

    public string $metodo = 'efectivo';

    public int $compras = 40;

    /** formulario: null cerrado, '' nuevo, uid editar */
    #[Locked]
    public ?string $editar = null;

    public array $f = ['cel' => '', 'nombre' => '', 'doc' => '', 'dir' => '', 'nota' => '', 'limite' => ''];

    #[Locked]
    public bool $uniendo = false;

    public string $unirQ = '';

    #[Locked]
    public ?string $unirCon = null;

    #[Locked]
    public bool $anotando = false;

    public string $deuda = '';

    #[Locked]
    public ?int $borrando = null;

    public string $error = '';

    private function cliente(?string $uid = null): ?Cliente
    {
        $uid ??= $this->ver;

        return $uid ? Cliente::where('uid', $uid)->first() : null;
    }

    public function abrir(string $uid): void
    {
        $this->ver = $uid;
        $this->pago = '';
        $this->metodo = 'efectivo';
        $this->compras = 40;
        $this->cerrarVentanas();
    }

    public function cerrar(): void
    {
        $this->ver = null;
        $this->cerrarVentanas();
    }

    public function cerrarVentanas(): void
    {
        $this->editar = null;
        $this->uniendo = false;
        $this->unirCon = null;
        $this->anotando = false;
        $this->borrando = null;
        $this->error = '';
    }

    // ------------------------------------------------------------ pagos y deudas

    public function registrarPago(ServicioClientes $srv): void
    {
        $c = $this->cliente();
        $v = Dinero::aCentimos($this->pago);
        if (! $c) {
            return;
        }
        try {
            $srv->registrarPago($c, (int) $v, $this->metodo, Auth::user());
        } catch (ErrorNegocio $e) {
            $this->dispatch('toast', texto: $e->getMessage());

            return;
        }
        $this->pago = '';
        $this->dispatch('toast', texto: 'Pago registrado: '.Dinero::s($v).($this->metodo === 'efectivo' ? ', entra a la caja' : ''));
    }

    public function pagarTodo(): void
    {
        $c = $this->cliente();
        $this->pago = $c ? Dinero::n(max(0, $c->saldo())) : '';
    }

    public function pedirDeuda(): void
    {
        $c = $this->cliente();
        if (! $c || ! $this->requiere('fiar', 'Anotar una deuda de '.$c->nombre, 'pedirDeuda')) {
            return;
        }
        $this->anotando = true;
        $this->deuda = '';
    }

    public function anotarDeuda(ServicioClientes $srv): void
    {
        $c = $this->cliente();
        if (! $c || ! $this->anotando) {
            return;
        }
        try {
            $srv->anotarDeuda($c, (int) Dinero::aCentimos($this->deuda), Auth::user());
        } catch (ErrorNegocio $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->anotando = false;
        $this->dispatch('toast', texto: 'Deuda anotada');
    }

    public function pedirBorrar(int $id): void
    {
        $m = ClienteMovimiento::find($id);
        if (! $m || $m->venta_uid || ! $this->requiere('borrar', 'Borrar un movimiento de fiado de '.Dinero::s($m->monto), 'pedirBorrar', [$id])) {
            return;
        }
        $this->borrando = $id;
    }

    public function borrar(ServicioClientes $srv): void
    {
        $m = $this->borrando ? ClienteMovimiento::find($this->borrando) : null;
        $this->borrando = null;
        if ($m) {
            $srv->borrarMovimiento($m);
            $this->dispatch('toast', texto: 'Movimiento borrado');
        }
    }

    // ------------------------------------------------------------ ficha

    public function nuevo(): void
    {
        $this->editar = '';
        $this->error = '';
        $this->f = ['cel' => '', 'nombre' => '', 'doc' => '', 'dir' => '', 'nota' => '', 'limite' => ''];
    }

    public function editarFicha(): void
    {
        $c = $this->cliente();
        if (! $c) {
            return;
        }
        $this->editar = $c->uid;
        $this->error = '';
        $this->f = ['cel' => Texto::fmtCel($c->celular), 'nombre' => $c->nombre, 'doc' => (string) $c->documento, 'dir' => (string) $c->direccion,
            'nota' => (string) $c->nota, 'limite' => $c->limite_fiado ? Dinero::n($c->limite_fiado) : ''];
    }

    public function guardarFicha(ServicioClientes $srv): void
    {
        if ($this->editar === null) {
            return;
        }
        $c = $this->editar !== '' ? $this->cliente($this->editar) : null;
        $n = trim($this->f['nombre']);
        $cel = Texto::cel9($this->f['cel']);
        $doc = preg_replace('/\D/', '', $this->f['doc']);
        $this->error = $srv->problemaFicha($c, $n, $cel, $doc);
        if ($this->error) {
            return;
        }
        $datos = ['nombre' => $n ?: 'Cliente '.substr($cel, -4), 'celular' => $cel ?: null, 'documento' => $doc ?: null,
            'direccion' => trim($this->f['dir']) ?: null, 'nota' => trim($this->f['nota']) ?: null];
        // el límite de fiado solo lo cambia un administrador
        if (Auth::user()->esAdmin()) {
            $lim = Dinero::aCentimos($this->f['limite']);
            $datos['limite_fiado'] = $lim > 0 ? $lim : null;
            if ($c && $c->limite_fiado !== $datos['limite_fiado']) {
                Bitacora::registrar('usuario', 'Límite de fiado de '.$c->nombre.': '.($datos['limite_fiado'] ? Dinero::s($datos['limite_fiado']) : 'sin límite'));
            }
        }
        if ($c) {
            $c->update($datos);
        } else {
            $c = Cliente::create($datos + ['uid' => Texto::nuevoUid()]);
            $this->ver = $c->uid;
        }
        $this->editar = null;
        $this->dispatch('toast', texto: 'Cliente guardado');
    }

    public function eliminar(): void
    {
        $c = $this->editar ? $this->cliente($this->editar) : null;
        if (! $c || $c->movimientos()->exists() || Venta::where('cliente_id', $c->id)->exists()) {
            return;
        }
        Bitacora::registrar('usuario', 'Eliminó el cliente '.$c->nombre);
        $c->delete();
        $this->ver = null;
        $this->editar = null;
        $this->dispatch('toast', texto: 'Cliente eliminado');
    }

    // ------------------------------------------------------------ unir

    public function abrirUnir(): void
    {
        $this->uniendo = true;
        $this->unirQ = '';
        $this->unirCon = null;
    }

    public function elegirUnir(string $uid): void
    {
        if ($uid !== $this->ver && $this->cliente($uid)) {
            $this->unirCon = $uid;
        }
    }

    public function unir(ServicioClientes $srv): void
    {
        $queda = $this->cliente();
        $otro = $this->unirCon ? $this->cliente($this->unirCon) : null;
        if ($queda && $otro) {
            $srv->unir($queda, $otro);
            $this->dispatch('toast', texto: 'Clientes unidos');
        }
        $this->uniendo = false;
        $this->unirCon = null;
    }

    // ------------------------------------------------------------

    private function buscar(string $q, int $max = 20)
    {
        $q = trim($q);
        $d = Texto::cel9($q);
        $soloNum = (bool) preg_match('/^\d+$/', preg_replace('/[\s+-]/', '', $q));

        return Cliente::query()->where(function ($w) use ($q, $d, $soloNum) {
            if ($soloNum && strlen($d) >= 3) {
                $w->where('celular', 'like', '%'.$d.'%')->orWhere('documento', 'like', '%'.$d.'%');
            } else {
                $w->where('nombre', 'like', '%'.$q.'%');
            }
        })->orderByDesc('visitas')->limit($max)->get();
    }

    public function render(ServicioClientes $srv)
    {
        $saldos = $srv->saldos();
        $desde = $srv->deudasDesde($saldos);
        $mes = today()->format('Y-m');
        $datos = [
            'total' => array_sum(array_filter($saldos, fn ($s) => $s > 0)),
            'conDeuda' => count(array_filter($saldos, fn ($s) => $s > 0)),
            'cobradoMes' => (int) ClienteMovimiento::where('tipo', 'abono')->where('ocurrido_at', '>=', today()->startOfMonth())->sum('monto'),
            'registrados' => Cliente::count(), 'saldos' => $saldos, 'desde' => $desde, 'mes' => $mes,
            'neg' => app(NegocioActual::class)->obligatorio(),
        ];

        if (trim($this->q) !== '') {
            $lista = $this->buscar($this->q);
        } else {
            $lista = match ($this->filtro) {
                'deben' => Cliente::whereIn('id', array_keys(array_filter($saldos, fn ($s) => $s > 0)))->get()->sortByDesc(fn ($c) => $saldos[$c->id] ?? 0),
                'frecuentes' => Cliente::where('visitas', '>', 0)->orderByDesc('visitas')->orderByDesc('gastado')->limit(100)->get(),
                'novienen' => Cliente::where('visitas', '>=', 2)->where('ultima_visita_at', '<', now()->subDays(30))->orderBy('ultima_visita_at')->limit(100)->get(),
                default => Cliente::orderBy('nombre')->limit(300)->get(),
            };
        }
        $datos['lista'] = $lista->values();

        $c = $this->cliente();
        if ($this->ver && ! $c) {
            $this->ver = null;
        }
        if ($c) {
            $ids = array_merge([$c->uid], $c->alias ?? []);
            $ventas = Venta::with('items')->where('cliente_id', $c->id)->orderByDesc('vendida_at');
            $datos += [
                'c' => $c, 'saldo' => $saldos[$c->id] ?? 0, 'deudaDesde' => $desde[$c->id] ?? null,
                'dups' => $srv->posiblesDuplicados($c),
                'movs' => $c->movimientos()->orderByDesc('ocurrido_at')->orderByDesc('id')->limit(100)->get(),
                'pedidos' => Pedido::where('cliente_id', $c->id)->orderByDesc('creado_at')->limit(10)->get(),
                'documentos' => Documento::where(fn ($q) => $q->where('cliente_id', $c->id)
                    ->when(strlen((string) $c->documento) >= 8, fn ($x) => $x->orWhere('busqueda', 'like', '%'.$c->documento.'%')))->latest()->limit(10)->get(),
                'ventas' => (clone $ventas)->limit($this->compras)->get(),
                'nVentas' => (clone $ventas)->count(), 'totalVentas' => (int) (clone $ventas)->sum('total'),
                'masCompra' => VentaItem::whereIn('venta_id', Venta::where('cliente_id', $c->id)->select('id'))->where('tercero', false)
                    ->selectRaw('nombre, SUM(cantidad) AS n')->groupBy('nombre')->orderByDesc('n')->limit(3)->pluck('nombre'),
                'unirLista' => $this->uniendo ? (trim($this->unirQ) !== '' ? $this->buscar($this->unirQ, 8) : $srv->posiblesDuplicados($c))->where('id', '!=', $c->id)->values() : collect(),
                'unirOtro' => $this->unirCon ? $this->cliente($this->unirCon) : null,
                'metodos' => $datos['neg']->metodosActivos(),
            ];
        }
        $datos['editado'] = $this->editar ? $this->cliente($this->editar) : null;

        return view('livewire.clientes', $datos);
    }
}
