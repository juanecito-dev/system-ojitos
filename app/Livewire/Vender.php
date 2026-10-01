<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConAutorizacion;
use App\Models\Cliente;
use App\Models\Documento;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Services\Comprobantes;
use App\Services\ErrorNegocio;
use App\Services\Pedidos;
use App\Services\Redaccion;
use App\Services\Stock;
use App\Services\Ventas;
use App\Support\Catalogos;
use App\Support\Dinero;
use App\Support\NegocioActual;
use App\Support\Texto;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Punto de venta. Tocar productos y armar el pedido pasa en el navegador (instantáneo);
 * al cobrar, el servidor vuelve a revisar precios, permisos y reglas antes de guardar.
 */
#[Title('Punto de venta')]
class Vender extends Component
{
    use ConAutorizacion;

    /** líneas: [pid, uid, nombre, det, cant, precio, lista?, tercero?] (se llena desde el navegador) */
    public array $orden = [];

    public bool $cobrando = false;

    public array $pay = [];

    public ?int $editar = null;

    public string $precioEdit = '';

    public string $error = '';

    public string $clienteQ = '';

    public string $clienteNuevo = '';

    #[Locked]
    public ?string $ticketUid = null;

    #[Locked]
    public array $concedidos = [];

    public string $codigoInicial = '';

    /** líneas de un documento de Redacción que se agregan a lo que ya había en la caja */
    #[Locked]
    public array $docLineas = [];

    /** pedido que se está cobrando desde la caja (saldo al entregar, o todo de una vez si es cotización) */
    #[Locked]
    public ?string $pedidoUid = null;

    #[Locked]
    public bool $pedidoDirecto = false;

    /**
     * Llave de este cobro: será el uid de la venta. La crea el navegador y la guarda con el pedido,
     * así que si se corta la conexión y se vuelve a cobrar (aunque se recargue la página) no se duplica la venta.
     */
    #[Locked]
    public string $intento = '';

    public function mount(): void
    {
        $this->pay = self::payInicial();
        $this->codigoInicial = (string) request()->query('codigo', '');
        if ($uid = request()->query('pedido')) {
            $this->cargarPedido((string) $uid);
        }
        if ($uid = request()->query('documento')) {
            $this->cargarDocumento((string) $uid, (int) request()->query('ej', 1));
        }
    }

    /** La redacción del documento (por página o por documento) y los ejemplares adicionales como impresión B/N (cobrarDoc) */
    public function cargarDocumento(string $uid, int $ejemplares): void
    {
        $doc = Documento::where('uid', $uid)->first();
        if (! $doc) {
            return;
        }
        $this->docLineas = app(Redaccion::class)->lineasCobro($doc, $ejemplares);
        if ($doc->cliente_id && ! $this->pay['cliente']) {
            $this->pay['cliente'] = $doc->cliente_id;
        }
    }

    /** Pone en la caja el saldo de un pedido (o la cotización completa, si se la lleva ahora) */
    public function cargarPedido(string $uid): void
    {
        $p = Pedido::with(['items', 'pagos'])->where('uid', $uid)->first();
        if (! $p) {
            return;
        }
        $directo = $p->etapa === 'cotizado';
        if (! $directo && (! $p->activo() || $p->saldo() <= 0)) {
            return;
        }
        $this->pay = self::payInicial();
        if ($directo) {
            $this->orden = $p->items->isNotEmpty()
                ? $p->items->map(fn ($l) => ['pid' => $l->producto_id, 'nombre' => $l->nombre, 'det' => trim(($l->detalle ? $l->detalle.' · ' : '').$p->numeroTxt()),
                    'cant' => (int) $l->cantidad, 'precio' => $l->precio, 'ped' => true])->values()->all()
                : [['pedido' => true, 'pid' => null, 'nombre' => 'Pedido '.$p->numeroTxt(), 'det' => mb_substr((string) $p->detalle, 0, 60), 'cant' => 1, 'precio' => $p->total]];
            if ($p->descuento) {
                if (Auth::user()->puede('descuentos')) {
                    $this->pay['desc'] = $p->descuento;
                } else {
                    $this->dispatch('toast', texto: 'La cotización tenía descuento, pero tu usuario no puede aplicarlo');
                }
            }
        } else {
            $this->orden = [['pedido' => true, 'pid' => null, 'nombre' => 'Saldo pedido '.$p->numeroTxt(), 'det' => mb_substr($p->descripcion(), 0, 60), 'cant' => 1, 'precio' => $p->saldo()]];
        }
        $this->pedidoUid = $p->uid;
        $this->pedidoDirecto = $directo;
        if ($p->cliente_id) {
            $this->pay['cliente'] = $p->cliente_id;
        }
        if ($q = app(Pedidos::class)->datosComprobante($p)) {
            $this->pay['cpe'] = $q['tipo'];
            $this->pay['doc'] = $q['doc'];
            $this->pay['conDoc'] = $q['tipo'] === '03';
        }
        $this->intento = Texto::nuevoUid();
        $this->cobrando = true;
    }

    private function pedido(): ?Pedido
    {
        return $this->pedidoUid ? Pedido::with(['items', 'pagos'])->where('uid', $this->pedidoUid)->first() : null;
    }

    private function tienePedido(): bool
    {
        return $this->pedidoUid && collect($this->orden)->contains(fn ($l) => ! empty($l['pedido']) || ! empty($l['ped']));
    }

    public static function payInicial(): array
    {
        return ['metodo' => 'efectivo', 'rec' => '', 'desc' => 0, 'descOtro' => '', 'cliente' => null, 'abono' => 0,
            'cpe' => '03', 'pide' => false, 'conDoc' => false, 'doc' => ['td' => '1', 'nd' => '', 'nom' => '', 'dir' => ''],
            'emitida' => false, 'serie' => '', 'numero' => ''];
    }

    // ------------------------------------------------------------------ catálogo

    private ?Collection $catalogo = null;

    public function catalogo(): Collection
    {
        return $this->catalogo ??= Producto::with(['opciones', 'insumos'])->orderBy('orden')->get()->keyBy('id');
    }

    /** datos mínimos que usa el navegador para armar el pedido sin esperar */
    public function catalogoJs(array $stock): array
    {
        return $this->catalogo()->reject->oculto->map(fn (Producto $p) => [
            'id' => $p->id, 'u' => $p->uid, 'n' => $p->nombre, 'c' => $p->color, 'g' => $p->grupo, 'q' => $p->rapido, 'f' => $p->monto_libre,
            'd' => $p->pide_detalle, 't' => $p->es_tasa, 'fav' => $p->favorito, 'cb' => $p->codigos(),
            'ops' => $p->opciones->map(fn ($o) => ['l' => $o->etiqueta, 'p' => $o->precio])->values(),
            's' => $stock[$p->id] ?? null, 'min' => $p->minimo(),
            'k' => Texto::norm($p->nombre.' '.$p->grupo.' '.$p->codigos_barra),
        ])->values()->all();
    }

    public function catalogoFresco(Stock $stock): array
    {
        return $this->catalogoJs($stock->todos());
    }

    public function alternarFavorito(int $id): void
    {
        $p = Producto::find($id);
        if ($p) {
            $p->update(['favorito' => ! $p->favorito]);
        }
    }

    // ------------------------------------------------------------------ pedido

    /** Rehace las líneas con los datos del servidor: nombre, costo y precio permitido */
    private function lineas(): array
    {
        $cat = $this->catalogo();
        $out = [];
        foreach ($this->orden as $l) {
            $cant = (int) ($l['cant'] ?? 0);
            $precio = (int) ($l['precio'] ?? 0);
            if ($cant <= 0 || $precio <= 0) {
                continue;
            }
            if (! empty($l['pedido'])) {
                // saldo del pedido (o su total, si no tiene productos): tiene que ser exacto
                $ped = $this->pedido();
                $debe = $ped ? ($this->pedidoDirecto ? $ped->total : $ped->saldo()) : null;
                if (! $ped || $cant !== 1 || $precio !== $debe) {
                    throw new ErrorNegocio('El monto del pedido cambió. Vacía la caja y vuelve a cobrarlo desde Pedidos.');
                }
                $out[] = ['producto_id' => null, 'producto_uid' => 'pedido', 'nombre' => mb_substr((string) $l['nombre'], 0, 120),
                    'detalle' => mb_substr((string) ($l['det'] ?? ''), 0, 120), 'cantidad' => 1, 'precio' => $precio];

                continue;
            }
            if (! empty($l['ped']) && ($ped = $this->pedido())) {
                // producto de la cotización: vale el precio cotizado
                $it = $ped->items->first(fn ($x) => $x->precio === $precio && ($l['pid'] ? $x->producto_id === (int) $l['pid'] : ! $x->producto_id && $x->nombre === ($l['nombre'] ?? '')));
                if ($it) {
                    $prod = $it->producto_id ? $cat->get($it->producto_id) : null;
                    $out[] = ['producto_id' => $prod?->id, 'producto_uid' => $prod?->uid ?? 'pedido', 'nombre' => $it->nombre,
                        'detalle' => mb_substr((string) ($l['det'] ?? ''), 0, 120), 'cantidad' => $cant, 'precio' => $precio,
                        'costo' => $prod && (($prod->costo && $prod->opciones->count() === 1) || $prod->insumos->isNotEmpty()) ? $prod->costoReal() : null];

                    continue;
                }
            }
            if (! empty($l['tercero'])) {
                $out[] = ['producto_id' => null, 'producto_uid' => 'tasa', 'nombre' => 'Tasa pagada por el cliente', 'detalle' => mb_substr((string) ($l['det'] ?? ''), 0, 120),
                    'cantidad' => $cant, 'precio' => $precio, 'tercero' => true];

                continue;
            }
            $p = $cat->get((int) ($l['pid'] ?? 0));
            if (! $p || $p->oculto) {
                throw new ErrorNegocio('Un producto del pedido ya no existe. Vacía el pedido y vuelve a agregarlo.');
            }
            $precios = $p->opciones->pluck('precio')->all();
            $lista = isset($l['lista']) ? (int) $l['lista'] : null;
            if (! $p->monto_libre && ! in_array($precio, $precios, true)) {
                // precio cambiado al cobrar: el precio normal tiene que ser uno de los del catálogo
                if ($lista === null || ! in_array($lista, $precios, true)) {
                    $lista = min($precios ?: [$precio]);
                }
            } elseif ($lista !== null && ! in_array($lista, $precios, true) && ! $p->monto_libre) {
                $lista = null;
            }
            $costo = ($p->costo && $p->opciones->count() === 1) || $p->insumos->isNotEmpty() ? $p->costoReal() : null;
            $out[] = ['producto_id' => $p->id, 'producto_uid' => $p->uid, 'nombre' => $p->nombre, 'detalle' => mb_substr((string) ($l['det'] ?? ''), 0, 120),
                'cantidad' => $cant, 'precio' => $precio, 'lista' => $lista !== null && $lista !== $precio ? $lista : null, 'costo' => $costo,
                // solo la redacción y sus copias pueden llevar el documento (así una línea cualquiera no lo marca como cobrado)
                'documento_uid' => ! empty($l['doc']) && in_array($p->uid, ['s_contrato', 's_solicitud', 's_cv', 'bn_a4'], true)
                    && Documento::where('uid', (string) $l['doc'])->exists() ? (string) $l['doc'] : null];
        }

        return $out;
    }

    private function bruto(): int
    {
        return array_sum(array_map(fn ($l) => (int) ($l['cant'] ?? 0) * (int) ($l['precio'] ?? 0), $this->orden));
    }

    private function terceros(): int
    {
        return array_sum(array_map(fn ($l) => ! empty($l['tercero']) ? (int) $l['cant'] * (int) $l['precio'] : 0, $this->orden));
    }

    /**
     * descuento total: el del cobro + las rebajas de precio por línea.
     * Las rebajas salen de las líneas que rehace el servidor (lineas()), nunca del «lista» que manda el navegador:
     * si no lo mandara, un precio bajado pasaría sin permiso ni tope.
     */
    private function descTotal(): int
    {
        $rebajas = 0;
        foreach ($this->lineas() as $l) {
            if (isset($l['lista']) && $l['lista'] > $l['precio']) {
                $rebajas += (int) round(($l['lista'] - $l['precio']) * $l['cantidad']);
            }
        }

        return (int) $this->pay['desc'] + $rebajas;
    }

    private function excedeTope(int $d): bool
    {
        $yo = Auth::user();
        if ($yo->esAdmin() || ! $d) {
            return false;
        }
        $r = $yo->rol;
        $base = $this->bruto() + ($d - (int) $this->pay['desc']);   // lo que costaba a precio normal

        return ($r->desc_max && $d > $r->desc_max) || ($r->desc_pct && $d > $base * $r->desc_pct / 100);
    }

    public function abrirCobro(?string $intento = null): void
    {
        $this->orden = array_values(array_filter($this->orden, fn ($l) => ($l['cant'] ?? 0) > 0));
        if (! $this->orden) {
            return;
        }
        if ($intento !== null && preg_match('/^[A-Za-z0-9-]{8,60}$/', $intento)) {
            $this->intento = $intento;
        } elseif ($this->intento === '') {
            $this->intento = Texto::nuevoUid();
        }
        if ($v = $this->yaGuardada()) {
            $this->terminarCobro($v, false, true);

            return;
        }
        $this->error = '';
        $this->editar = null;
        $this->cobrando = true;
        $this->ajustarPay();
    }

    public function cerrarCobro(): void
    {
        $this->cobrando = false;
        $this->editar = null;
        $this->concedidos = [];
    }

    private function ajustarPay(): void
    {
        $base = $this->bruto() - $this->terceros();
        if ($this->pay['desc'] >= $base) {
            $this->pay['desc'] = 0;
        }
        $cli = $this->cliente();
        if (! $cli) {
            $this->pay['cliente'] = null;
        }
        if ($this->pay['metodo'] === 'fiado' || ! $cli || $cli->saldo() <= 0) {
            $this->pay['abono'] = 0;
        }
    }

    public function cliente(): ?Cliente
    {
        return $this->pay['cliente'] ? Cliente::find($this->pay['cliente']) : null;
    }

    public function linea(int $i, int $delta): void
    {
        if (! isset($this->orden[$i]) || ! empty($this->orden[$i]['pedido'])) {
            return;
        }
        $this->orden[$i]['cant'] = max(0, (int) $this->orden[$i]['cant'] + $delta);
        if ($this->orden[$i]['cant'] === 0) {
            $this->quitar($i);

            return;
        }
        $this->ajustarPay();
    }

    public function quitar(int $i): void
    {
        unset($this->orden[$i]);
        $this->orden = array_values($this->orden);
        $this->editar = null;
        if (! $this->orden) {
            $this->cerrarCobro();
        }
        $this->ajustarPay();
    }

    public function editarPrecio(int $i): void
    {
        if ((! Auth::user()->puede('descuentos') && ! Auth::user()->esAdmin()) || ! empty($this->orden[$i]['pedido'])) {
            return;
        }
        $this->editar = $this->editar === $i ? null : $i;
        $this->precioEdit = isset($this->orden[$i]) ? Dinero::n($this->orden[$i]['precio']) : '';
    }

    public function precioMenos(int $pct): void
    {
        $l = $this->orden[$this->editar] ?? null;
        if ($l) {
            $base = $l['lista'] ?? $l['precio'];
            $this->precioEdit = Dinero::n(max(1, (int) round($base * (100 - $pct) / 100)));
        }
    }

    public function aplicarPrecio(?int $c = null): void
    {
        $i = $this->editar;
        if ($i === null || ! isset($this->orden[$i])) {
            return;
        }
        $c ??= Dinero::aCentimos($this->precioEdit);
        if (! ($c > 0)) {
            $this->dispatch('toast', texto: 'Escribe un precio válido');

            return;
        }
        $l = &$this->orden[$i];
        $l['lista'] ??= $l['precio'];
        $l['precio'] = $c;
        if ($l['lista'] === $l['precio']) {
            unset($l['lista']);
        }
        unset($l);
        $this->editar = null;
        $this->ajustarPay();
        $this->dispatch('toast', texto: 'Precio cambiado: '.Dinero::s($c).' c/u');
    }

    public function precioNormal(): void
    {
        $l = $this->orden[$this->editar] ?? null;
        if ($l && isset($l['lista'])) {
            $this->aplicarPrecio((int) $l['lista']);
        }
    }

    public function metodo(string $m): void
    {
        $this->pay['metodo'] = $m;
        $this->ajustarPay();
    }

    public function descuento(int $c): void
    {
        $this->pay['desc'] = max(0, $c);
        $this->pay['descOtro'] = '';
        $this->ajustarPay();
    }

    public function updatedPayDescOtro(): void
    {
        $c = Dinero::aCentimos($this->pay['descOtro']) ?? 0;
        $base = $this->bruto() - $this->terceros();
        if ($c >= $base) {
            $this->dispatch('toast', texto: 'El descuento no puede ser mayor que lo tuyo ('.Dinero::s($base).')');
            $c = 0;
        }
        $this->pay['desc'] = max(0, $c);
    }

    public function recibido(int $c): void
    {
        $this->pay['rec'] = Dinero::n($c);
    }

    // ------------------------------------------------------------------ cliente

    public function buscarClientes(): Collection
    {
        $t = trim($this->clienteQ);
        if ($t === '') {
            return collect();
        }
        $d = Texto::cel9($t);
        $soloNum = preg_match('/^\d+$/', preg_replace('/[\s+-]/', '', $t));
        $q = Cliente::query();
        if ($soloNum && strlen($d) >= 3) {
            $q->where(fn ($w) => $w->where('celular', 'like', '%'.$d.'%')->when(strlen($d) >= 6, fn ($x) => $x->orWhere('documento', 'like', '%'.$d.'%')));
        } else {
            $q->where('nombre', 'like', '%'.trim($t).'%');
        }

        return $q->orderByDesc('visitas')->limit(5)->get();
    }

    public function elegirCliente(int $id): void
    {
        $this->pay['cliente'] = $id;
        $this->clienteQ = '';
        $this->ajustarPay();
    }

    public function quitarCliente(): void
    {
        $this->pay['cliente'] = null;
        $this->pay['abono'] = 0;
    }

    public function registrarCliente(): void
    {
        $t = trim($this->clienteQ);
        $d = Texto::cel9($t);
        $esCel = (bool) preg_match('/^\d{9}$/', $d);
        $extra = trim($this->clienteNuevo);
        $nombre = $esCel ? ($extra ?: 'Cliente '.substr($d, -4)) : $t;
        $cel = $esCel ? $d : Texto::cel9($extra);
        if ($nombre === '') {
            return;
        }
        if ($cel && ($x = Cliente::where('celular', $cel)->first())) {
            $this->elegirCliente($x->id);

            return;
        }
        $c = Cliente::create(['uid' => Texto::nuevoUid(), 'nombre' => $nombre, 'celular' => $cel ?: null]);
        $this->clienteNuevo = '';
        $this->elegirCliente($c->id);
        $this->dispatch('toast', texto: 'Cliente registrado: '.$nombre);
    }

    public function alternarAbono(): void
    {
        $c = $this->cliente();
        $this->pay['abono'] = $this->pay['abono'] ? 0 : max(0, (int) $c?->saldo());
    }

    // ------------------------------------------------------------------ comprobante

    public function tipoCpe(string $t): void
    {
        $this->pay['cpe'] = $t === '01' ? '01' : '03';
        if ($t === '01') {
            $this->pay['doc']['td'] = '6';
        } elseif ($this->pay['doc']['td'] === '6' && ! Comprobantes::rucValido($this->pay['doc']['nd'])) {
            $this->pay['doc']['td'] = '1';
        }
        $this->pay['serie'] = '';
        if ($this->pay['cliente'] && ! $this->pay['doc']['nd']) {
            $this->llenarDoc();
        }
    }

    public function ponerDoc(): void
    {
        $this->pay['conDoc'] = true;
        $this->llenarDoc();
    }

    private function llenarDoc(): void
    {
        $c = $this->cliente();
        if (! $c) {
            return;
        }
        if ($c->documento) {
            $this->pay['doc']['nd'] = $c->documento;
            $this->pay['doc']['td'] = strlen($c->documento) === 11 ? '6' : '1';
        }
        if (! $this->pay['doc']['nom'] && ! preg_match('/^Cliente \d{4}$/', $c->nombre)) {
            $this->pay['doc']['nom'] = $c->nombre;
        }
        if (! $this->pay['doc']['dir'] && $c->direccion) {
            $this->pay['doc']['dir'] = $c->direccion;
        }
    }

    // ------------------------------------------------------------------ cobrar

    public function cobrar(bool $conTicket = false, ?Ventas $ventas = null): void
    {
        $ventas ??= app(Ventas::class);
        $this->error = '';
        if ($v = $this->yaGuardada()) {   // reintento de un cobro que ya se guardó: no se cobra dos veces
            $this->terminarCobro($v, $conTicket, true);

            return;
        }
        $yo = Auth::user();
        $total = $this->bruto() - (int) $this->pay['desc'];
        if (! $this->orden) {
            return;
        }
        if ($this->pay['metodo'] === 'fiado' && ! $this->pay['cliente']) {
            $this->error = 'Para fiar, elige o registra al cliente (celular o nombre).';

            return;
        }
        if ($this->pay['metodo'] === 'fiado' && ! $this->permitido('fiar', 'Vender al fiado '.Dinero::s($total), [$conTicket])) {
            return;
        }
        $cli = $this->cliente();
        if ($this->pay['metodo'] === 'fiado' && $cli?->limite_fiado && $cli->saldo() + $total > $cli->limite_fiado) {
            if (! $this->permitido('fiar', 'Fiar '.Dinero::s($total).' a '.$cli->nombre, [$conTicket], ['forzar' => true, 'soloAdmin' => true,
                'motivo' => 'Pasa su límite de fiado ('.Dinero::s($cli->limite_fiado).'; ya debe '.Dinero::s($cli->saldo()).'): un administrador debe autorizarlo.'])) {
                return;
            }
        }
        try {
            $d = $this->descTotal();
        } catch (ErrorNegocio $e) {
            $this->error = $e->getMessage();

            return;
        }
        if ($d > 0 && ! $yo->puede('descuentos') && ! $this->permitido('descuentos', 'Descuento de '.Dinero::s($d), [$conTicket])) {
            return;
        }
        if ($d > 0 && $yo->puede('descuentos') && $this->excedeTope($d)) {
            $r = $yo->rol;
            $tope = collect([$r->desc_max ? Dinero::s($r->desc_max) : '', $r->desc_pct ? $r->desc_pct.'%' : ''])->filter()->join(' o ');
            if (! $this->permitido('descuentos', 'Descuento de '.Dinero::s($d), [$conTicket], ['forzar' => true, 'soloAdmin' => true,
                'motivo' => 'Pasa el tope de tu rol ('.$tope.'): un administrador debe autorizarlo.'])) {
                return;
            }
        }

        $p = $this->pay;
        $conPedido = $this->tienePedido();
        $pedidoAntes = $conPedido ? $this->pedido() : null;
        $pide = $p['cpe'] === '01' || $p['pide'] || $this->bruto() - (int) $p['desc'] - $this->terceros() > Catalogos::LIMITE_CIERRE;
        try {
            $v = DB::transaction(fn () => $this->registrarConPedido($ventas, $yo, $p, $pide, $pedidoAntes));
        } catch (ErrorNegocio $e) {
            $this->error = $e->getMessage();

            return;
        } catch (UniqueConstraintViolationException $e) {
            // el mismo cobro llegó dos veces a la vez: el otro ya la guardó
            if (! ($v = $this->yaGuardada())) {
                throw $e;
            }
            $this->terminarCobro($v, $conTicket, true);

            return;
        }
        $this->terminarCobro($v, $conTicket);
    }

    /** la venta de este cobro, si ya se guardó (se cortó la conexión y se volvió a cobrar) */
    private function yaGuardada(): ?Venta
    {
        $v = $this->intento !== '' ? Venta::conAnuladas()->with('cliente')->where('uid', $this->intento)->first() : null;
        if ($v?->anulada_at) {
            // esa llave ya se usó en una venta que luego se deshizo: este cobro es otro
            $this->intento = Texto::nuevoUid();

            return null;
        }

        return $v;
    }

    /** deja la caja lista para la siguiente venta y avisa cuánto se cobró */
    private function terminarCobro(Venta $v, bool $conTicket, bool $repetido = false): void
    {
        $this->orden = [];
        $this->pay = self::payInicial();
        $this->cobrando = false;
        $this->concedidos = [];
        $this->pedidoUid = null;
        $this->pedidoDirecto = false;
        $this->intento = '';
        if ($repetido) {
            $this->dispatch('venta-registrada');
            $this->dispatch('toast', texto: 'Esta venta ya se había guardado ('.Dinero::s($v->total + $v->abono).'). No se cobró dos veces.', largo: true);
            if ($conTicket) {
                $this->ticketUid = $v->uid;
            }

            return;
        }
        $quien = $v->cliente ? Texto::primerNombre($v->cliente->nombre) : '';
        $grand = $v->total + $v->abono;
        $msg = $v->metodo === 'fiado' ? 'Fiado a '.$quien.': '.Dinero::s($v->total)
            : 'Cobrado '.Dinero::s($grand).($quien ? ' a '.$quien : '').($v->abono ? ' (incluye deuda)' : '');
        if ($v->pago > $v->total) {
            $msg .= ', vuelto '.Dinero::s($v->pago - $v->total);
        }
        $this->dispatch('venta-registrada');
        $this->dispatch('toast', texto: $msg, accion: ['label' => 'Deshacer', 'evento' => 'deshacerVenta', 'params' => ['uid' => $v->uid]]);
        if ($conTicket) {
            $this->ticketUid = $v->uid;
        }
    }

    /** Guarda la venta y, si era de un pedido, lo deja pagado y entregado (todo o nada) */
    private function registrarConPedido(Ventas $ventas, Usuario $yo, array $p, bool $pide, ?Pedido $pedido): Venta
    {
        if ($pedido) {
            // primero el pedido: bloqueado y revisado, antes de comparar montos y guardar la venta
            $pedido = app(Pedidos::class)->bloquearParaCobro($pedido, $this->pedidoDirecto);
        }
        $v = $ventas->registrar($yo, [
            'lineas' => $this->lineas(),
            'descuento' => (int) $p['desc'],
            'metodo' => $p['metodo'],
            'recibido' => Dinero::aCentimos($p['rec']),
            'cliente_id' => $p['cliente'],
            'abono' => (int) $p['abono'],
            'cpe' => ['tipo' => $p['cpe'], 'pide' => $p['pide'] || $p['cpe'] === '01', 'doc' => $p['doc']['nd'] ? $p['doc'] : null,
                'emitida' => $pide && $p['emitida'], 'serie' => $p['serie'], 'numero' => $p['numero']],
            'pedido_uid' => $pedido?->uid,
            'uid' => $this->intento ?: null,
        ]);
        if ($pedido) {
            app(Pedidos::class)->cobrado($pedido, $v, $this->pedidoDirecto, $yo);
        }

        return $v;
    }

    /** como requiere(), pero recuerda lo ya autorizado durante este cobro */
    private function permitido(string $permiso, string $que, array $params, array $opt = []): bool
    {
        // lo ya autorizado vale solo para este mismo pedido y cobro: si cambian (otro descuento, otra línea), se vuelve a pedir
        $clave = $permiso.(! empty($opt['forzar']) ? '!' : '');
        if (($this->concedidos[$clave] ?? null) === $this->huellaAutorizacion()) {
            return true;
        }
        if ($this->requiere($permiso, $que, 'cobrar', $params, $opt)) {
            if ($this->autorizo) {
                $this->concedidos[$clave] = $this->huellaAutorizacion();
            }

            return true;
        }

        return false;
    }

    #[On('deshacerVenta')]
    public function deshacerVenta(string $uid, Ventas $ventas): void
    {
        $v = Venta::with('items')->where('uid', $uid)->first();
        $yo = Auth::user();
        if (! $v || $v->usuario_id !== $yo->id || $v->vendida_at->lt(now()->subMinutes(10))) {
            $this->dispatch('toast', texto: 'Esa venta ya no se puede deshacer aquí. Anúlala en Ventas.');

            return;
        }
        $ventas->anular($v, $yo, 'Deshecha al momento', 'deshecha');
        $ped = $v->pedido_uid ? Pedido::where('uid', $v->pedido_uid)->first() : null;
        $this->pedidoUid = $ped?->uid;
        $this->pedidoDirecto = $ped?->etapa === 'cotizado';
        $this->orden = $v->items->map(fn (VentaItem $l) => array_filter([
            'pid' => $l->producto_id, 'nombre' => $l->nombre, 'det' => $l->detalle ?? '', 'cant' => (int) $l->cantidad, 'precio' => $l->precio,
            'lista' => $l->precio_lista, 'tercero' => $l->tercero ?: null, 'doc' => $l->documento_uid,
            'pedido' => $ped && $l->producto_uid === 'pedido' && ! $l->producto_id ? true : null,
            'ped' => $ped && ! ($l->producto_uid === 'pedido' && ! $l->producto_id) ? true : null,
        ], fn ($x) => $x !== null))->values()->all();
        $this->dispatch('orden-restaurada', orden: $this->orden);
        $this->dispatch('toast', texto: 'Venta deshecha, el pedido volvió a la caja');
    }

    public function cerrarTicket(): void
    {
        $this->ticketUid = null;
    }

    public function render(Stock $stock)
    {
        $neg = app(NegocioActual::class)->obligatorio();
        $st = $stock->todos();
        $cat = $this->catalogo();
        $hoy = VentaItem::query()->join('ventas', 'ventas.id', '=', 'venta_items.venta_id')->whereNull('ventas.anulada_at')
            ->where('ventas.negocio_id', $neg->id)->where('ventas.fecha', today()->toDateString())->whereNotNull('venta_items.producto_id')
            ->selectRaw('venta_items.producto_id, COUNT(*) AS n')->groupBy('venta_items.producto_id')->orderByDesc('n')->limit(6)->pluck('producto_id');
        $grupos = $cat->reject->oculto->groupBy('grupo');

        $datos = ['neg' => $neg, 'stock' => $st, 'grupos' => $grupos, 'top' => $hoy->map(fn ($id) => $cat->get($id))->filter()->reject->oculto->values(),
            'catalogoJs' => $this->catalogoJs($st)];

        if ($this->cobrando) {
            $bruto = $this->bruto();
            $terc = $this->terceros();
            $base = $bruto - $terc;
            $tot = $bruto - (int) $this->pay['desc'];
            $cli = $this->cliente();
            $grand = $tot + (int) $this->pay['abono'];
            $rec = Dinero::aCentimos($this->pay['rec']);
            $pct = fn ($p) => (int) (round($base * $p / 100 / 10) * 10);
            $cmp = app(Comprobantes::class);
            $datos += [
                'bruto' => $bruto, 'base' => $base, 'tot' => $tot, 'grand' => $grand, 'cli' => $cli, 'deuda' => $cli ? $cli->saldo() : 0,
                'vuelto' => $rec !== null && $rec >= $grand ? $rec - $grand : null,
                'billetes' => collect([$grand, 1000, 2000, 5000, 10000])->filter(fn ($b) => $b >= $grand)->unique()->take(4)->values(),
                'descuentos' => collect([[0, 'Sin'], [$pct(5), '5%'], [$pct(10), '10%'], [50, 'S/ 0.50'], [100, 'S/ 1']])
                    ->filter(fn ($x, $i) => $i === 0 || ($x[0] > 0 && $x[0] < $base))->unique(0)->values(),
                'metodos' => $neg->metodosActivos() + ['fiado' => $neg->nombreMetodo('fiado')],
                'over' => $tot - $terc > Catalogos::LIMITE_CIERRE, 'grande' => $tot - $terc > Catalogos::MAX_SIN_DOC,
                'puedeFactura' => $cmp->puedeFactura(), 'serieSug' => $this->pay['serie'] ?: $cmp->serieDe($this->pay['cpe']),
                'numSug' => $cmp->ultimoNumero($this->pay['serie'] ?: $cmp->serieDe($this->pay['cpe'])) + 1,
                'clientes' => $cli ? collect() : $this->buscarClientes(),
                'puedeDesc' => Auth::user()->puede('descuentos'),
            ];
        }
        if ($this->ticketUid) {
            $datos['ticket'] = Venta::with(['items', 'cliente'])->where('uid', $this->ticketUid)->first();
        }

        return view('livewire.vender', $datos);
    }
}
