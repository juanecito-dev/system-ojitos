@php use App\Support\Dinero; use App\Support\Texto; use App\Services\Clientes as SC; use App\Models\Venta; @endphp
<section>
    <div class="kpis">
        <div class="card kpi"><div class="cap">Te deben en total</div><div class="v">{{ Dinero::s($total) }}</div></div>
        <div class="card kpi"><div class="cap">Clientes con deuda</div><div class="v">{{ $conDeuda }}</div></div>
        <div class="card kpi"><div class="cap">Cobrado este mes</div><div class="v">{{ Dinero::s($cobradoMes) }}</div></div>
        <div class="card kpi"><div class="cap">Clientes registrados</div><div class="v">{{ $registrados }}</div></div>
    </div>
    <div class="daybar"><button type="button" class="btn" wire:click="nuevo">Nuevo cliente</button>
        <input class="field" wire:model.live.debounce.250ms="q" placeholder="Buscar por celular, DNI o nombre" style="flex:1;min-width:180px;width:auto"></div>
    @if (trim($q) === '')
        <div class="seg">
            @foreach (['deben' => 'Me deben', 'frecuentes' => 'Más frecuentes', 'novienen' => 'No vuelven (30+ días)', 'todos' => 'Todos'] as $k => $l)
                <button type="button" wire:click="$set('filtro', '{{ $k }}')" aria-pressed="{{ $filtro === $k ? 'true' : 'false' }}">{{ $l }}</button>
            @endforeach
        </div>
    @endif

    @if ($lista->isNotEmpty())
        <div class="stack">
            @foreach ($lista as $i => $x)
                @php $s = $saldos[$x->id] ?? 0; $d = $desde[$x->id] ?? null; $dias = $d ? (int) $d->copy()->startOfDay()->diffInDays(today()) : 0; @endphp
                <button type="button" class="inv" wire:key="c{{ $x->id }}" wire:click="abrir('{{ $x->uid }}')" style="text-align:left;width:100%">
                    <span class="nm">@if ($filtro === 'frecuentes' && trim($q) === '')<b style="color:var(--soft);margin-right:6px">{{ $i + 1 }}.</b>@endif{{ $x->nombre }}
                        <small>{{ $x->celular ? Texto::fmtCel($x->celular).', ' : '' }}{{ SC::stats($x) }}</small>
                        @if ($s > 0 && $d)<small><span class="tag {{ $dias > 30 ? 'r' : 's' }}" style="margin:2px 0 0">Debe desde {{ $dias > 30 ? 'hace '.$dias.' días' : SC::haceTxt($d) }}</span></small>@endif
                    </span>
                    <span class="qty" style="font-size:1.2rem;color:{{ $s > 0 ? 'var(--magenta)' : 'var(--ok)' }}">{{ $s > 0 ? Dinero::s($s) : 'Al día' }}</span>
                </button>
            @endforeach
        </div>
    @else
        <div class="empty">
            @if (trim($q) !== '') No encontré a nadie con "{{ $q }}".
            @else {{ ['deben' => 'Nadie te debe. Cuando fíes algo, aparecerá aquí.', 'frecuentes' => 'Cuando registres el celular del cliente al cobrar, aquí verás quiénes vienen más.', 'novienen' => 'Ningún cliente habitual ha dejado de venir.'][$filtro] ?? 'Aún no tienes clientes registrados.' }}
            @endif
        </div>
    @endif

    {{-- ===================== ficha del cliente ===================== --}}
    @isset($c)
        @php
            $wa = $c->celular ? 'https://wa.me/51'.$c->celular : null;
            $recordar = $wa ? $wa.'?text='.rawurlencode('Hola '.$c->nombre.', te saluda '.$neg->nombre.'. Te recordamos que tienes un saldo pendiente de '.Dinero::s($saldo).'. ¡Gracias!') : null;
            $diasDeuda = $deudaDesde ? (int) $deudaDesde->copy()->startOfDay()->diffInDays(today()) : 0;
        @endphp
        <div class="scrim on" wire:click="cerrar"></div>
        <div class="sheet on" role="dialog" aria-modal="true" x-data x-on:keydown.escape.window="$wire.cerrar()">
            <h3>{{ $c->nombre }}</h3>
            <p class="hint">{{ collect([$c->celular ? 'Cel. '.Texto::fmtCel($c->celular) : 'Sin celular', SC::documentoTxt($c), $c->direccion, $c->nota])->filter()->join(' · ') }}</p>
            @if ($dups->isNotEmpty())
                <div class="note warn" style="margin-bottom:12px">Parece que está registrado más de una vez: {{ $dups->map(fn ($x) => $x->nombre.($x->celular ? ' ('.Texto::fmtCel($x->celular).')' : ''))->join(', ') }}. <button type="button" class="link" style="padding:0" wire:click="abrirUnir">Unir registros</button></div>
            @endif
            <div class="kpis" style="margin-bottom:12px">
                <div class="card kpi"><div class="cap">Visitas</div><div class="v">{{ $c->visitas }}</div></div>
                <div class="card kpi"><div class="cap">Gastó en total</div><div class="v">{{ Dinero::s($c->gastado) }}</div></div>
                <div class="card kpi"><div class="cap">Última vez</div><div class="v" style="font-size:1.2rem">{{ $c->ultima_visita_at ? SC::haceTxt($c->ultima_visita_at) : '—' }}</div></div>
            </div>
            <div class="card" style="margin-bottom:12px;background:{{ $saldo > 0 ? 'var(--mag-bg)' : 'var(--ok-bg)' }};border:0">
                <div class="cap">{{ $saldo > 0 ? 'Te debe' : ($saldo < 0 ? 'Tiene a favor' : 'Está al día') }}</div>
                <div class="tot" style="font-size:2.2rem;font-weight:800;font-stretch:82%">{{ Dinero::s(abs($saldo)) }}</div>
                @if ($saldo > 0 && $deudaDesde)<div class="cap" style="{{ $diasDeuda > 30 ? 'color:var(--magenta);font-weight:700' : '' }}">Debe desde el {{ $deudaDesde->format('d/m/Y') }} ({{ SC::haceTxt($deudaDesde) }})</div>@endif
                @if ($c->limite_fiado)<div class="cap">Límite de fiado: {{ Dinero::s($c->limite_fiado) }}{{ $saldo > 0 ? ' · le quedan '.Dinero::s(max(0, $c->limite_fiado - $saldo)) : '' }}</div>@endif
            </div>
            @if ($saldo > 0)
                <form class="row" wire:submit="registrarPago"><label for="ab-m">Paga</label><input data-solo="monto" class="field" id="ab-m" wire:model="pago" inputmode="decimal" placeholder="0.00"><div class="quick"><button type="button" wire:click="pagarTodo">Todo ({{ Dinero::n($saldo) }})</button></div></form>
                <div class="chips">@foreach ($metodos as $k => $n)<button type="button" class="chip" wire:click="$set('metodo', '{{ $k }}')" aria-pressed="{{ $metodo === $k ? 'true' : 'false' }}"><b style="font-size:.95rem">{{ $n }}</b></button>@endforeach</div>
                <div class="actions" style="margin-bottom:12px"><button type="button" class="btn big" wire:click="registrarPago">Registrar pago</button></div>
            @endif
            <div class="actions tools" style="margin-bottom:14px">
                @if ($movs->isNotEmpty())<a class="btn sm" href="{{ route('clientes.estado', $c->uid) }}" target="_blank">Estado de cuenta (PDF)</a>@endif
                @if ($recordar && $saldo > 0)<a class="btn ghost sm" href="{{ $recordar }}" target="_blank" rel="noopener">Recordar deuda por WhatsApp</a>@endif
                @if ($wa)<a class="btn ghost sm" href="{{ $wa }}" target="_blank" rel="noopener">Abrir chat de WhatsApp</a>@endif
                <button type="button" class="btn ghost sm" wire:click="pedirDeuda">Anotar deuda anterior</button>
                <button type="button" class="btn ghost sm" wire:click="editarFicha">Editar ficha</button>
                <button type="button" class="btn ghost sm" wire:click="abrirUnir">Unir con otro</button>
            </div>

            <h2 style="margin-top:4px">Fiados y pagos</h2>
            @forelse ($movs as $m)
                <div class="sale" wire:key="m{{ $m->id }}"><span class="t">{{ $m->ocurrido_at->format('d/m') }}<br>{{ $m->ocurrido_at->format('H:i') }}</span>
                    <span class="d">{{ $m->tipo === 'fiado' ? ($m->detalle ?: 'Fiado') : 'Pago '.$neg->nombreMetodo($m->metodo) }}<br><span class="tag {{ $m->tipo === 'fiado' ? 'r' : 'b' }}">{{ $m->tipo === 'fiado' ? 'Fiado' : 'Pagó' }}</span>@if ($m->vendedor)<span class="tag s">{{ Texto::primerNombre($m->vendedor) }}</span>@endif</span>
                    <span class="ops"><span class="amt">{{ $m->tipo === 'fiado' ? '+' : '−' }} {{ Dinero::n($m->monto) }}</span>@unless ($m->venta_uid)<button type="button" class="link" wire:click="pedirBorrar({{ $m->id }})">Borrar</button>@endunless</span></div>
            @empty
                <p class="cap">Sin fiados ni pagos.</p>
            @endforelse

            @if ($pedidos->isNotEmpty())
                <h2>Pedidos</h2>
                @foreach ($pedidos as $p)
                    <a class="inv" href="{{ Route::has('pedidos') ? route('pedidos', ['ver' => $p->uid]) : '#' }}" style="text-align:left;width:100%;margin-bottom:6px;text-decoration:none;color:inherit">
                        <span class="nm">N° {{ str_pad((string) $p->numero, 6, '0', STR_PAD_LEFT) }} · {{ \Illuminate\Support\Str::limit($p->detalle ?: $p->items()->pluck('nombre')->join(', '), 60) }}<small>{{ \App\Support\Catalogos::ETAPAS_PEDIDO[$p->etapa][1] ?? $p->etapa }}</small></span>
                        <span class="qty" style="font-size:1.1rem">{{ Dinero::s($p->total) }}</span></a>
                @endforeach
            @endif

            @if ($documentos->isNotEmpty() && Route::has('redaccion.doc'))
                <h2>Documentos</h2>
                @foreach ($documentos as $dc)
                    @php [$dcc, $dcl] = \App\Models\Documento::ESTADOS[$dc->estado] ?? \App\Models\Documento::ESTADOS['borrador']; @endphp
                    <a class="inv" href="{{ route('redaccion.doc', $dc->uid) }}" style="text-align:left;width:100%;margin-bottom:6px;text-decoration:none;color:inherit">
                        <span class="nm">{{ $dc->titulo ?: 'Documento' }}<small>{{ $dc->created_at?->format('d/m/Y') }}{{ $dc->partes ? ' · '.$dc->partes : '' }}</small></span>
                        <span class="tag {{ $dcc }}">{{ $dcl }}</span></a>
                @endforeach
            @endif

            <h2>Compras <small>{{ $nVentas }}</small></h2>
            @if ($nVentas)
                @if ($masCompra->isNotEmpty())<p class="cap" style="margin:0 0 8px">Lo que más compra: {{ $masCompra->join(', ') }}. {{ $nVentas }} {{ $nVentas === 1 ? 'compra' : 'compras' }} por {{ Dinero::s($totalVentas) }}.</p>@endif
                @foreach ($ventas as $v)
                    <div class="sale" wire:key="v{{ $v->id }}"><span class="t">{{ $v->vendida_at->format('d/m/y') }}<br>{{ $v->vendida_at->format('H:i') }}</span>
                        <span class="d">{{ $v->items->map(fn ($l) => Venta::cant($l->cantidad).' '.$l->nombre)->join(', ') }}<br><span class="tag m">{{ $neg->nombreMetodo($v->metodo) }}</span>@if ($v->comprobante_numero)<span class="tag b">{{ $v->comprobante_numero }}</span>@endif @if ($v->vendedor)<span class="tag s">{{ Texto::primerNombre($v->vendedor) }}</span>@endif</span>
                        <span class="amt">{{ Dinero::s($v->total) }}</span></div>
                @endforeach
                @if ($nVentas > $ventas->count())<div style="text-align:center"><button type="button" class="btn ghost sm" wire:click="$set('compras', {{ $compras + 60 }})">Ver más compras</button></div>@endif
            @else
                <p class="cap">Sin compras registradas con su nombre.</p>
            @endif
        </div>

        {{-- anotar deuda anterior --}}
        @if ($anotando)
            <div class="dlg" role="alertdialog" aria-modal="true"><form wire:submit="anotarDeuda">
                <p>¿Cuánto te debía {{ $c->nombre }} de antes? (S/)</p>
                <input data-solo="monto" class="field wide" wire:model="deuda" inputmode="decimal" style="width:100%;margin-bottom:6px" x-init="$nextTick(() => $el.focus())">
                <p class="err">{{ $error }}</p>
                <div class="actions"><button type="button" class="btn ghost big" wire:click="cerrarVentanas">Cancelar</button><button type="submit" class="btn big">Anotar</button></div>
            </form></div>
        @endif
        @if ($borrando)
            <div class="dlg" role="alertdialog" aria-modal="true"><div>
                <p>¿Borrar este movimiento? Si es un pago, también se quita de la caja.</p>
                <div class="actions"><button type="button" class="btn ghost big" wire:click="cerrarVentanas">Cancelar</button><button type="button" class="btn big warn" wire:click="borrar">Borrar</button></div>
            </div></div>
        @endif
        {{-- unir --}}
        @if ($uniendo)
            <div class="dlg" role="dialog" aria-modal="true"><div>
                @if ($unirOtro)
                    <p>¿Unir <b>{{ $unirOtro->nombre }}</b>{{ $unirOtro->celular ? ' ('.Texto::fmtCel($unirOtro->celular).')' : '' }} con <b>{{ $c->nombre }}</b>?

Sus fiados, pagos, compras y pedidos pasan a {{ $c->nombre }}, y el otro registro sale de la lista.</p>
                    <div class="actions"><button type="button" class="btn ghost big" wire:click="cerrarVentanas">Cancelar</button><button type="button" class="btn big" wire:click="unir">Unir</button></div>
                @else
                    <p><b>Unir con otro cliente</b><br>Si {{ $c->nombre }} está registrado dos veces, busca el otro registro. Todo lo del otro pasa a {{ $c->nombre }}.</p>
                    <input class="field wide" wire:model.live.debounce.250ms="unirQ" placeholder="Celular o nombre del otro registro" autocomplete="off" style="width:100%;margin-bottom:10px" x-init="$nextTick(() => $el.focus())">
                    <div class="stack" style="max-height:260px;overflow:auto">
                        @forelse ($unirLista as $x)
                            <button type="button" class="inv" wire:click="elegirUnir('{{ $x->uid }}')" style="text-align:left;width:100%"><span class="nm">{{ $x->nombre }}<small>{{ collect([$x->celular ? Texto::fmtCel($x->celular) : '', SC::documentoTxt($x), SC::stats($x)])->filter()->join(' · ') }}</small></span>@if (($saldos[$x->id] ?? 0) > 0)<span class="tag r">Debe {{ Dinero::n($saldos[$x->id]) }}</span>@endif</button>
                        @empty
                            <p class="cap">{{ trim($unirQ) !== '' ? 'No encontré a nadie más con eso.' : 'Escribe para buscar.' }}</p>
                        @endforelse
                    </div>
                    <div class="actions" style="margin-top:10px"><button type="button" class="btn ghost big" wire:click="cerrarVentanas">Volver</button></div>
                @endif
            </div></div>
        @endif
    @endisset

    {{-- ===================== nuevo / editar ficha ===================== --}}
    @if ($editar !== null)
        <div class="dlg" role="dialog" aria-modal="true"><form wire:submit="guardarFicha" style="max-width:560px;width:100%;background:var(--sheet);border-radius:16px;padding:18px">
            <h3 style="margin:0 0 10px">{{ $editado ? 'Ficha de '.$editado->nombre : 'Nuevo cliente' }}</h3>
            <div class="set"><div class="two">
                <label>Celular (el mismo de su WhatsApp)<input data-solo="cel" wire:model="f.cel" inputmode="tel" placeholder="9xx xxx xxx"></label>
                <label>Nombre o razón social<input wire:model="f.nombre" placeholder="Ej.: Prof. Rosa, Colegio San Martín"></label>
                <label>DNI o RUC (para boletas y facturas)<input data-solo="doc" wire:model="f.doc" inputmode="numeric" maxlength="11" placeholder="Opcional"></label>
                <label>Dirección<input wire:model="f.dir" placeholder="Opcional, sale en la factura"></label></div>
                <label>Nota<input wire:model="f.nota" placeholder="Opcional: profesor del colegio X, paga a fin de mes…"></label>
                @if (auth()->user()->esAdmin())<label>Límite de fiado (S/)<input data-solo="monto" wire:model="f.limite" inputmode="decimal" placeholder="Sin límite"></label>@endif
            </div>
            <p class="err">{{ $error }}</p>
            <div class="actions">
                @if ($editado && ! $editado->movimientos()->exists() && ! \App\Models\Venta::where('cliente_id', $editado->id)->exists())<button type="button" class="btn ghost big" wire:click="eliminar" wire:confirm="¿Eliminar a {{ $editado->nombre }}?">Eliminar</button>@endif
                <button type="button" class="btn ghost big" wire:click="cerrarVentanas">Cancelar</button><button class="btn big" type="submit">Guardar</button>
            </div>
        </form></div>
    @endif

    @include('livewire.partials.autorizacion')
</section>
