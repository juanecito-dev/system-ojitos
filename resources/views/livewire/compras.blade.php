@php
    use App\Livewire\Compras as Cw; use App\Services\Compras as Srv; use App\Services\Stock; use App\Support\Dinero; use App\Support\Texto;
    $F = fn ($n) => Stock::formato($n);
    $fecha = fn ($d) => $d ? $d->format('d/m/Y') : '';
    $sel = 'padding:8px;border-radius:10px;border:1.5px solid var(--line);background:var(--sheet);color:var(--ink)';
@endphp
<section x-data x-on:enfocar-linea.window="$nextTick(() => { const i = document.querySelector('[data-cc=\'' + $event.detail.i + '\']'); if (i) { i.focus(); i.select(); } })">
    <div class="kpis">
        <div class="card kpi"><div class="cap">Debes a proveedores</div><div class="v">{{ Dinero::s($porPagarTotal) }}</div></div>
        <div class="card kpi" @if ($vencido) style="background:var(--mag-bg);border-color:var(--magenta)" @endif><div class="cap">Vencido</div><div class="v">{{ Dinero::s($vencido) }}</div></div>
        <div class="card kpi"><div class="cap">Vence en 7 días</div><div class="v">{{ Dinero::s($prox) }}</div></div>
        <div class="card kpi"><div class="cap">Compras este mes</div><div class="v">{{ Dinero::s($compMes) }}</div></div>
    </div>
    <div class="daybar">
        <button type="button" class="btn big" wire:click="abrirCompra">Registrar compra</button>
        <span style="margin-left:auto"></span>
        <input type="month" wire:model.live="mes" max="{{ today()->format('Y-m') }}" aria-label="Mes" style="border:1.5px solid var(--line);background:var(--sheet);border-radius:10px;padding:8px 10px">
        <a class="btn ghost sm" href="{{ route('compras.csv', ['mes' => $mes ?: today()->format('Y-m')]) }}">Registro de compras (Excel)</a>
    </div>
    <div class="seg">
        @foreach (['porpagar' => 'Por pagar', 'pagadas' => 'Pagadas', 'reponer' => 'Por reponer', 'proveedores' => 'Proveedores'] as $k => $l)
            <button type="button" wire:click="$set('filtro', '{{ $k }}')" aria-pressed="{{ $filtro === $k ? 'true' : 'false' }}">{{ $l }}@if ($k === 'reponer' && $nRep) <small>{{ $nRep }}</small>@endif</button>
        @endforeach
    </div>
    @if (in_array($filtro, ['porpagar', 'pagadas', 'proveedores'], true))
        <div class="daybar"><input class="field" wire:model.live.debounce.250ms="buscar" placeholder="{{ $filtro === 'proveedores' ? 'Buscar proveedor o RUC' : 'Buscar por proveedor, número o producto' }}" style="flex:1;min-width:170px;width:auto">
            @if ($filtro === 'proveedores')<button type="button" class="btn" wire:click="abrirProveedor">Nuevo proveedor</button>@endif</div>
    @endif

    {{-- ============================ POR PAGAR Y PAGADAS ============================ --}}
    @if (in_array($filtro, ['porpagar', 'pagadas'], true))
        @if ($filtro === 'pagadas' && $buscar === '')<p class="cap" style="margin:0 0 10px">Pagadas de {{ ucfirst(\Carbon\Carbon::parse(($mes ?: today()->format('Y-m')).'-01')->translatedFormat('F Y')) }}. Cambia el mes arriba o busca para ver cualquier compra.</p>@endif
        @if ($lista->isNotEmpty())
            <div class="stack">
                @foreach ($lista as $f)
                    @php $sal = $f->saldo(); $late = $f->vencida(); $soon = ! $late && $f->venceEn(7); @endphp
                    <div class="enc {{ $late ? 'late' : ($sal ? '' : 'entregado') }}" wire:key="c{{ $f->id }}">
                        <div class="top"><span class="who">{{ $f->proveedor?->nombre ?? 'Proveedor' }}</span><span class="money">{{ Dinero::s($f->total) }}</span></div>
                        <div class="meta">{{ $f->numero ?: 'Sin número' }}, {{ $fecha($f->fecha) }}{{ $f->condicion === 'credito' ? ', a crédito' : ', al contado' }}{{ $f->nota ? '. '.$f->nota : '' }}</div>
                        @if ($f->items->isNotEmpty())<div class="det" style="font-size:.9rem">{{ $f->items->take(5)->map(fn ($l) => $F($l->cantidad).' '.($l->factor > 1 ? mb_strtolower($l->unidad).' ' : '').$l->nombre)->join(', ') }}{{ $f->items->count() > 5 ? '…' : '' }}</div>@endif
                        <div>@if ($sal)<span class="tag p">Saldo {{ Dinero::s($sal) }}</span>@else<span class="tag b">Pagada</span>@endif
                            @if ($sal && $f->vence)<span class="tag {{ $late ? 'r' : ($soon ? 'p' : 's') }}">{{ $late ? 'Vencida el ' : 'Vence el ' }}{{ $fecha($f->vence) }}</span>@endif
                            @if ($f->pagado() && $sal)<span class="tag m">Pagado {{ Dinero::s($f->pagado()) }}</span>@endif
                            @if ($f->items->isNotEmpty())<span class="tag s">Sumó al stock</span>@endif</div>
                        <div class="actions">@if ($sal)<button type="button" class="btn sm" wire:click="abrirPago({{ $f->id }})">Registrar pago</button>@endif<button type="button" class="link" wire:click="abrirCompra({{ $f->id }})">Ver o editar</button></div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty">{{ $buscar !== '' ? 'No encontré «'.$buscar.'».' : ($filtro === 'porpagar' ? 'No debes nada a tus proveedores.' : 'No hay compras pagadas en ese mes.') }}</div>
        @endif
    @endif

    {{-- ============================ POR REPONER ============================ --}}
    @if ($filtro === 'reponer')
        @if ($grupos->isNotEmpty())
            <p class="cap" style="margin:0 0 10px">Productos en su mínimo o menos, agrupados por el último proveedor que te los vendió. La cantidad alcanza para unas 2 semanas según lo que vendes.</p>
            <div class="stack">
                @foreach ($grupos as $pid => $L)
                    @php $pr = $proveedores->firstWhere('id', (int) $pid); $cel = Texto::cel9($pr?->celular); $txt = Srv::textoPedido($pr, $L); @endphp
                    <div class="card" wire:key="r{{ $pid ?: 'x' }}">
                        <div style="display:flex;justify-content:space-between;gap:10px"><b>{{ $pr?->nombre ?? 'Sin proveedor registrado' }}</b><span class="cap">≈ {{ Dinero::s($L->sum(fn ($x) => $x['cant'] * $x['costoU'])) }}</span></div>
                        <div class="ledger" style="margin:8px 0">
                            @foreach ($L as $x)
                                <div><span>{{ $x['cant'] }} {{ $x['uc']['f'] > 1 ? mb_strtolower($x['uc']['n']).' × '.$F($x['uc']['f']).' de ' : '' }}{{ $x['p']->nombre }} <span class="cap">(quedan {{ $F($x['stock']) }}, mín. {{ $F($x['p']->minimo()) }})</span></span><b>{{ $x['costoU'] ? Dinero::s($x['cant'] * $x['costoU']) : '' }}</b></div>
                            @endforeach
                        </div>
                        <div class="actions">
                            @if (strlen($cel) === 9)
                                <a class="btn ghost sm" href="https://wa.me/51{{ $cel }}?text={{ rawurlencode($txt) }}" target="_blank" rel="noopener">Pedir por WhatsApp</a>
                            @else
                                <button type="button" class="btn ghost sm" x-on:click="navigator.clipboard.writeText(@js($txt)); $dispatch('toast', {texto: 'Pedido copiado'})">Copiar pedido</button>
                            @endif
                            <button type="button" class="btn sm" wire:click="abrirCompra(null, '{{ $pid }}')">Llegó: registrar compra</button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty">Nada por reponer: todo está por encima de su mínimo. Ajusta el mínimo de cada producto en Inventario.</div>
        @endif
    @endif

    {{-- ============================ PROVEEDORES ============================ --}}
    @if ($filtro === 'proveedores')
        <div class="stack">
            @forelse ($provs as $x)
                @php $deuda = $x['fs']->sum(fn ($f) => $f->saldo()); @endphp
                <button type="button" class="inv" wire:key="p{{ $x['p']->id }}" wire:click="abrirProveedor({{ $x['p']->id }})" style="text-align:left;width:100%">
                    <span class="nm">{{ $x['p']->nombre }}<small>{{ collect([$x['p']->ruc ? 'RUC '.$x['p']->ruc : '', $x['p']->celular ? 'cel. '.Texto::fmtCel($x['p']->celular) : '', $x['fs']->count() ? $x['fs']->count().' '.($x['fs']->count() === 1 ? 'compra' : 'compras').' por '.Dinero::s($x['fs']->sum('total')) : ''])->filter()->join(', ') }}</small></span>
                    <span class="qty" style="font-size:1.1rem;color:{{ $deuda ? 'var(--magenta)' : 'inherit' }}">{{ $deuda ? Dinero::s($deuda) : '' }}</span>
                </button>
            @empty
                <div class="empty">{{ $buscar !== '' ? 'No encontré «'.$buscar.'».' : 'Aún no registras proveedores.' }}</div>
            @endforelse
        </div>
    @endif

    {{-- ============================ VENTANA: COMPRA ============================ --}}
    @if ($editando)
        @php $tot = $lineas ? collect($lineas)->sum(fn ($l) => Cw::subtotal($l)) : (Dinero::aCentimos($c['total']) ?? 0); $pagado = $fEdit?->pagado() ?? 0; @endphp
        <div class="scrim on" wire:click="cerrar"></div>
        <div class="sheet on" role="dialog" aria-modal="true">
            <h3>{{ $fEdit ? 'Compra '.($fEdit->numero ?? '') : 'Registrar compra' }}</h3>
            <div class="set">
                <label>Proveedor<select wire:model.live="c.prov">@foreach ($proveedores as $p)<option value="{{ $p->id }}">{{ $p->nombre }}</option>@endforeach<option value="__new">Nuevo proveedor…</option></select></label>
                @if ($c['prov'] === '__new')<label>Nombre del proveedor<input wire:model="c.pn" placeholder="Ej.: Librería Central"></label>@endif
                <div class="two"><label>N° de factura o boleta<input wire:model="c.numero" placeholder="F011-00026493"></label><label>Fecha<input type="date" wire:model="c.fecha" max="{{ today()->toDateString() }}"></label></div>
            </div>
            <div class="card" style="margin-bottom:12px;background:var(--paper)"><b>Qué compraste</b> <span class="cap">(se suma solo al stock y actualiza el costo)</span>
                <div style="margin:8px 0"><input class="field wide" wire:model.live.debounce.250ms="iq" placeholder="Buscar producto o insumo…" autocomplete="off" style="width:100%">
                    @if ($iq !== '')
                        <div class="stack" style="margin-top:6px">
                            @forelse ($hits as $h)
                                <button type="button" class="inv" wire:click="agregarProducto({{ $h->id }})" style="text-align:left;width:100%;padding:8px 12px"><span class="nm">{{ $h->nombre }}<small>{{ $h->grupo }}{{ array_key_exists($h->id, $st) ? ' · hay '.$F($st[$h->id]) : '' }}{{ $h->costo ? ' · costo '.Dinero::sCosto($h->costo) : '' }}</small></span></button>
                            @empty
                                <p class="cap" style="margin:0">No está en tu catálogo. Agrégalo en Configuración (o como insumo en Inventario).</p>
                            @endforelse
                        </div>
                    @endif
                </div>
                @if ($lineas)
                    <div class="stack">
                        @foreach ($lineas as $i => $l)
                            @php $pp = $prods[$l['pid']] ?? null; $uc = $pp?->unidadCompra() ?? ['n' => 'Unidad', 'f' => 1]; $sub = Cw::subtotal($l); $unid = (float) str_replace(',', '.', (string) $l['cant']) * (float) $l['f']; @endphp
                            <div class="card" style="padding:10px" wire:key="l{{ $i }}-{{ $l['pid'] }}">
                                <div style="display:flex;justify-content:space-between;gap:8px"><b>{{ $l['nombre'] }}</b><button type="button" wire:click="quitarLinea({{ $i }})" aria-label="Quitar" style="border:0;background:none;font-size:1.2rem;color:var(--soft)">×</button></div>
                                @if ($pp && ! array_key_exists($pp->id, $st))<p class="cap" style="margin:2px 0">Aún no controlas su stock: empezará con lo que compres. Si ya tenías, cuéntalo en Inventario.</p>@endif
                                <div class="row" style="margin:6px 0 0">
                                    <input data-solo="cantidad" class="field" data-cc="{{ $i }}" wire:model.live.debounce.400ms="lineas.{{ $i }}.cant" inputmode="decimal" style="width:70px;padding:8px" aria-label="Cantidad">
                                    <select wire:model.live="lineas.{{ $i }}.f" style="{{ $sel }}" aria-label="Presentación">
                                        <option value="1">{{ $pp?->unidad ?: 'Unidad' }}</option>
                                        @if ($uc['f'] > 1)<option value="{{ $F($uc['f']) }}">{{ $uc['n'] }} × {{ $F($uc['f']) }}</option>@endif
                                        @if ((float) $l['f'] > 1 && abs((float) $l['f'] - $uc['f']) > 0.001)<option value="{{ $l['f'] }}">{{ $l['un'] }} × {{ $l['f'] }}</option>@endif
                                        @if ($pp)<option value="otra">Otra presentación…</option>@endif
                                    </select>
                                    <span class="cap">a</span><input data-solo="costo" class="field" wire:model.live.debounce.400ms="lineas.{{ $i }}.costo" inputmode="decimal" placeholder="Costo" style="width:90px;padding:8px" aria-label="Costo por esa presentación"><span class="cap">c/u</span>
                                    <b style="margin-left:auto">{{ Dinero::n($sub) }}</b>
                                </div>
                                @if ($l['otra'])
                                    <div class="row" style="margin:6px 0 0"><input class="field" wire:model="lineas.{{ $i }}.otraN" placeholder="Cómo viene: Caja, Millar…" style="width:170px;padding:8px">
                                        <input data-solo="entero" class="field" wire:model="lineas.{{ $i }}.otraF" inputmode="numeric" placeholder="¿Cuántas trae?" style="width:120px;padding:8px"><button type="button" class="btn sm" wire:click="fijarPresentacion({{ $i }})">Usar</button></div>
                                @endif
                                <div class="cap" style="margin-top:4px">{{ $unid > 0 ? 'Suma '.$F($unid).' '.($pp?->unidad ?: 'unidades').($sub ? ', '.Dinero::sCosto($sub / $unid).' cada una' : '') : '' }}{{ ($info[$i] ?? '') !== '' ? '. '.$info[$i] : '' }}</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="cap" style="margin:0">Opcional: si no agregas productos, escribe solo el total.</p>
                @endif
            </div>
            <div class="set"><div class="two">
                <label>Total (S/)@if ($lineas)<input value="{{ Dinero::n($tot) }}" disabled>@else<input data-solo="monto" wire:model.live.debounce.400ms="c.total" inputmode="decimal" placeholder="0.00">@endif</label>
                <label>Nota<input wire:model="c.nota" placeholder="Ej.: útiles campaña escolar"></label>
            </div></div>
            @if ($pagado)<p class="cap" style="margin:-4px 0 8px">Ya le pagaste {{ Dinero::s($pagado) }}: el total no puede ser menor.</p>@endif
            <div class="seg">@foreach (['credito' => 'A crédito', 'contado' => 'Al contado'] as $k => $n)<button type="button" wire:click="$set('c.cond', '{{ $k }}')" aria-pressed="{{ $c['cond'] === $k ? 'true' : 'false' }}">{{ $n }}</button>@endforeach</div>
            @if ($c['cond'] === 'credito')
                <div class="set"><label>Vence el<input type="date" wire:model="c.vence"></label></div>
            @elseif (! $fEdit)
                <div class="chips">@foreach ($neg->metodosActivos() as $k => $n)<button type="button" class="chip" wire:click="$set('c.met', '{{ $k }}')" aria-pressed="{{ $c['met'] === $k ? 'true' : 'false' }}"><b style="font-size:.95rem">{{ $n }}</b></button>@endforeach</div>
                @if ($c['met'] === 'efectivo')<label class="check"><input type="checkbox" wire:model="c.caja"> El dinero sale de la caja de hoy</label>@endif
            @endif
            <p class="err">{{ $error }}</p>
            <div class="actions">@if ($fEdit)<button type="button" class="btn ghost big" wire:click="pedirBorrar">Eliminar</button>@endif<button type="button" class="btn big" wire:click="guardarCompra">Guardar</button></div>
        </div>
        @if ($borrando && $fEdit)
            @php $enCaja = $fEdit->pagos->whereNotNull('caja_mov_uid')->sum('monto'); @endphp
            <div class="dlg" role="alertdialog" aria-modal="true"><div>
                <p>¿Eliminar la compra {{ $fEdit->numero }} de {{ $fEdit->proveedor?->nombre }} por {{ Dinero::s($fEdit->total) }}?
                    @if ($fEdit->items->isNotEmpty())<br><br>Las unidades que sumó al stock se descuentan.@endif
                    @if ($enCaja)<br><br>Los pagos en efectivo que salieron de la caja ({{ Dinero::s($enCaja) }}) se quitan de la caja.@elseif ($fEdit->pagado())<br><br>Tenía pagos por {{ Dinero::s($fEdit->pagado()) }} que no salieron de la caja.@endif</p>
                <div class="actions"><button type="button" class="btn ghost big" wire:click="noBorrar">Cancelar</button><button type="button" class="btn big warn" wire:click="eliminarCompra">Eliminar</button></div>
            </div></div>
        @endif
    @endif

    {{-- ============================ VENTANA: PAGO ============================ --}}
    @if ($pagoDe && isset($fPago))
        <div class="scrim on" wire:click="cerrar"></div>
        <div class="sheet on" role="dialog" aria-modal="true">
            <form wire:submit="pagar">
                <h3>Pagar a {{ $fPago->proveedor?->nombre ?? 'Proveedor' }}</h3><p class="hint">{{ $fPago->numero ?: 'Compra' }} del {{ $fecha($fPago->fecha) }}. Saldo {{ Dinero::s($fPago->saldo()) }}.</p>
                <div class="row"><label for="pp-m">Monto</label><input data-solo="monto" class="field" id="pp-m" wire:model="pMonto" inputmode="decimal"></div>
                <div class="chips">@foreach ($neg->metodosActivos() as $k => $n)<button type="button" class="chip" wire:click="$set('pMet', '{{ $k }}')" aria-pressed="{{ $pMet === $k ? 'true' : 'false' }}"><b style="font-size:.95rem">{{ $n }}</b></button>@endforeach</div>
                @if ($pMet === 'efectivo')<label class="check"><input type="checkbox" wire:model="pCaja"> El dinero sale de la caja de hoy</label>@endif
                <p class="err">{{ $error }}</p>
                <div class="actions"><button class="btn big" type="submit">Registrar pago</button></div>
            </form>
        </div>
    @endif

    {{-- ============================ VENTANA: PROVEEDOR ============================ --}}
    @if ($provAbierto)
        <div class="scrim on" wire:click="cerrar"></div>
        <div class="sheet on" role="dialog" aria-modal="true">
            <form wire:submit="guardarProveedor">
                <h3>{{ $provId ? 'Proveedor' : 'Nuevo proveedor' }}</h3>
                <div class="set"><label>Nombre<input wire:model="pv.nombre" x-init="$nextTick(() => { if (!$el.value) $el.focus() })"></label>
                    <div class="two"><label>RUC<input data-solo="ruc" wire:model="pv.ruc" inputmode="numeric" maxlength="11"></label><label>Celular (para pedirle por WhatsApp)<input data-solo="cel" wire:model="pv.cel" inputmode="tel"></label></div></div>
                <p class="err">{{ $error }}</p>
                <div class="actions">@if ($provId && isset($pCompras) && $pCompras->isEmpty())<button type="button" class="btn ghost big" wire:click="eliminarProveedor">Eliminar</button>@endif<button class="btn big" type="submit">Guardar</button></div>
            </form>
            @if (isset($pCompras) && $pCompras->isNotEmpty())
                <h2>Compras <small>{{ $pCompras->count() }} por {{ Dinero::s($pCompras->sum('total')) }}</small></h2>
                <div class="scroll"><table><thead><tr><th>Fecha</th><th>Número</th><th class="r">Total</th><th class="r">Saldo</th></tr></thead><tbody>
                    @foreach ($pCompras->take(30) as $f)
                        <tr><td>{{ $fecha($f->fecha) }}</td><td>{{ $f->numero ?: '—' }}</td><td class="r">{{ Dinero::n($f->total) }}</td><td class="r">{{ $f->saldo() ? Dinero::n($f->saldo()) : 'Pagada' }}</td></tr>
                    @endforeach
                </tbody></table></div>
            @endif
        </div>
    @endif

    @include('livewire.partials.autorizacion')
</section>
