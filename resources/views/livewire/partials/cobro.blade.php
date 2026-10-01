@php
    use App\Support\Dinero; use App\Support\Texto; use App\Models\Venta;
    $haceTxt = function ($t) { if (! $t) return ''; $d = (int) $t->copy()->startOfDay()->diffInDays(today()); return $d <= 0 ? 'hoy' : ($d === 1 ? 'ayer' : 'hace '.$d.' días'); };
    $stats = fn ($c) => $c->visitas ? $c->visitas.($c->visitas === 1 ? ' visita' : ' visitas').', gastó '.Dinero::s($c->gastado).($c->ultima_visita_at ? ', última '.$haceTxt($c->ultima_visita_at) : '') : 'Primera vez';
    $f = $pay['cpe'] === '01';
    $showDoc = $f || $grande || $pay['conDoc'];
    $pide = $f || $over || $pay['pide'];
@endphp
<div class="scrim on" wire:click="cerrarCobro"></div>
<div class="sheet on" role="dialog" aria-modal="true" x-on:keydown.escape.window="$wire.cerrarCobro()">
    <h3>Cobrar</h3>
    <ul class="lines">
        @foreach ($orden as $i => $l)
            <li wire:key="l{{ $i }}">
                @php $fijo = ! empty($l['pedido']); @endphp
                <span class="d" @if ($puedeDesc && ! $fijo) wire:click="editarPrecio({{ $i }})" style="cursor:pointer" @endif>
                    <b>{{ $l['nombre'] }}</b>
                    <small>{{ ! empty($l['det']) ? $l['det'].', ' : '' }}@if (! empty($l['lista']) && $l['lista'] != $l['precio'])<s>{{ Dinero::n($l['lista']) }}</s> @endif{{ Dinero::s($l['precio']) }} c/u @if ($puedeDesc && $editar !== $i && ! $fijo) · <u>cambiar precio</u>@endif</small>
                </span>
                @if ($fijo)<span class="cap">1</span>@else<span class="stepper sm"><button type="button" wire:click="linea({{ $i }}, -1)" aria-label="Menos">−</button><span>{{ $l['cant'] }}</span><button type="button" wire:click="linea({{ $i }}, 1)" aria-label="Más">+</button></span>@endif
                <b style="min-width:70px;text-align:right">{{ Dinero::n($l['cant'] * $l['precio']) }}</b>
                <button type="button" class="x" wire:click="quitar({{ $i }})" aria-label="Quitar">×</button>
            </li>
            @if ($editar === $i)
                <li class="ledit" wire:key="e{{ $i }}">
                    <label for="pe-p">Precio c/u</label>
                    <input data-solo="monto" class="field" id="pe-p" inputmode="decimal" wire:model="precioEdit" wire:keydown.enter.prevent="aplicarPrecio" style="width:100px;padding:8px" x-init="$nextTick(() => { $el.focus(); $el.select() })">
                    <span class="quick">@foreach ([5, 10, 20] as $q)<button type="button" wire:click="precioMenos({{ $q }})">−{{ $q }}%</button>@endforeach
                        @if (! empty($l['lista']))<button type="button" wire:click="precioNormal">Precio normal {{ Dinero::n($l['lista']) }}</button>@endif</span>
                    <button type="button" class="btn sm" wire:click="aplicarPrecio">Aplicar</button><button type="button" class="link" wire:click="editarPrecio({{ $i }})">Cancelar</button>
                </li>
            @endif
        @endforeach
    </ul>

    {{-- cliente --}}
    <div style="margin-bottom:12px">
        @if ($cli)
            <div class="card" style="padding:12px;{{ $deuda > 0 ? 'border-color:var(--magenta);' : '' }}">
                <div style="display:flex;gap:10px;align-items:center">
                    <span style="width:38px;height:38px;border-radius:50%;background:var(--ink);color:var(--paper);display:flex;align-items:center;justify-content:center;font-weight:800;flex:none">{{ Texto::inicial($cli->nombre) }}</span>
                    <span style="flex:1;min-width:0"><b>{{ $cli->nombre }}</b>@if ($cli->celular) <span class="cap">{{ Texto::fmtCel($cli->celular) }}</span>@endif<br><span class="cap">{{ $stats($cli) }}</span></span>
                    <button type="button" class="x" wire:click="quitarCliente" aria-label="Quitar cliente" style="border:0;background:none;font-size:1.4rem;color:var(--soft)">×</button>
                </div>
                @if ($cli->limite_fiado && $pay['metodo'] === 'fiado')
                    <div class="note {{ $deuda + $tot > $cli->limite_fiado ? 'bad' : 'info' }}" style="margin:10px 0 0">Límite de fiado: {{ Dinero::s($cli->limite_fiado) }}.@if ($deuda + $tot > $cli->limite_fiado) Con esta venta lo pasa: pedirá el PIN de un administrador.@endif</div>
                @endif
                @if ($deuda > 0)
                    <div class="note bad" style="margin:10px 0 0"><b>⚠ Todavía te debe {{ Dinero::s($deuda) }}</b>.
                        @if ($pay['metodo'] !== 'fiado')
                            <div style="margin-top:8px"><button type="button" class="btn sm {{ $pay['abono'] ? '' : 'warn' }}" wire:click="alternarAbono">{{ $pay['abono'] ? 'No cobrar la deuda ahora' : 'Cobrar la deuda también ('.Dinero::n($deuda).')' }}</button></div>
                        @endif
                    </div>
                @endif
            </div>
        @else
            <div class="row" style="margin-bottom:6px"><label for="p-cq">Cliente</label>
                <input class="field wide" id="p-cq" autocomplete="off" wire:model.live.debounce.250ms="clienteQ"
                       placeholder="{{ $pay['metodo'] === 'fiado' ? 'Obligatorio para fiar: celular o nombre' : 'Opcional: celular (o sus últimos dígitos) o nombre' }}"></div>
            @if (trim($clienteQ) !== '')
                @php $d9 = Texto::cel9($clienteQ); $esCel = (bool) preg_match('/^\d{9}$/', $d9); @endphp
                <div class="stack">
                    @foreach ($clientes as $c)
                        <button type="button" class="inv" wire:click="elegirCliente({{ $c->id }})" style="text-align:left;width:100%;padding:10px 12px">
                            <span class="nm">{{ $c->nombre }}<small>{{ $c->celular ? Texto::fmtCel($c->celular).', ' : '' }}{{ $stats($c) }}</small></span>
                            @if (($s = $c->saldo()) > 0)<span class="tag r">Debe {{ Dinero::n($s) }}</span>@endif
                        </button>
                    @endforeach
                    @unless ($clientes->contains(fn ($c) => $c->celular === $d9 && $d9 !== ''))
                        <div class="card" style="padding:10px 12px;background:var(--paper)"><b style="font-size:.92rem">Cliente nuevo{{ $esCel ? ': '.Texto::fmtCel($d9) : '' }}</b>
                            <div class="row" style="margin:8px 0 0"><input class="field wide" wire:model="clienteNuevo" placeholder="{{ $esCel ? 'Nombre (opcional)' : 'Celular (opcional)' }}" @unless ($esCel) inputmode="tel" @endunless><button type="button" class="btn sm" wire:click="registrarCliente">Registrar</button></div></div>
                    @endunless
                </div>
            @endif
        @endif
    </div>

    {{-- descuento --}}
    <div class="row" style="margin-bottom:8px" @unless ($puedeDesc) title="Pedirá autorización" @endunless><span class="cap" style="min-width:80px">Descuento</span>
        <div class="quick">@foreach ($descuentos as [$v, $etq])<button type="button" wire:click="descuento({{ $v }})" style="{{ $v === (int) $pay['desc'] && $pay['descOtro'] === '' ? 'background:var(--ink);color:var(--paper)' : '' }}">{{ $etq }}</button>@endforeach</div>
        <input data-solo="monto" class="field" inputmode="decimal" placeholder="Otro" style="width:90px;padding:8px" wire:model.blur="pay.descOtro"></div>

    <div class="sub"><span>{{ $pay['abono'] ? 'Venta '.Dinero::s($tot).' + deuda '.Dinero::n($pay['abono']) : 'Total' }}@if ($pay['desc']) <small class="cap">({{ Dinero::s($bruto) }} − {{ Dinero::n($pay['desc']) }})</small>@endif</span><strong>{{ Dinero::s($grand) }}</strong></div>

    <div class="chips">@foreach ($metodos as $k => $n)<button type="button" class="chip" wire:click="metodo('{{ $k }}')" aria-pressed="{{ $pay['metodo'] === $k ? 'true' : 'false' }}"><b>{{ $n }}</b></button>@endforeach</div>

    @if (in_array($pay['metodo'], ['yape', 'plin'], true))
        @php $Y = $pay['metodo'] === 'yape' ? 'Yape' : 'Plin'; $qr = $pay['metodo'] === 'yape' ? $neg->qr_yape : $neg->qr_plin; $num = $neg->ajuste('yapeCel') ?: $neg->celular; $nom = $neg->ajuste('yapeNom') ?: $neg->titular; @endphp
        @if ($qr)
            <div class="qrbox" x-data="{ grande: false }"><img src="{{ $qr }}" alt="QR de {{ $Y }}" x-on:click="grande = true">
                <div><b>{{ $Y }} a {{ $nom }}</b><br><span class="cap">{{ Texto::fmtCel($num) }}</span><br><button type="button" class="btn ghost sm" style="margin-top:8px" x-on:click="grande = true">Mostrar grande al cliente</button></div>
                <template x-teleport="body"><div class="qrfull" x-show="grande" x-on:click="grande = false" role="dialog"><div><img src="{{ $qr }}" alt="QR"><p>{{ $Y }} · {{ Dinero::s($grand) }}</p><button type="button" class="btn big">Cerrar</button></div></div></template>
            </div>
        @else
            <p class="cap" style="margin:-4px 0 12px">{{ $Y }} a {{ Texto::fmtCel($num) }}. @if (auth()->user()->esAdmin()) Sube tu QR en Configuración para mostrarlo aquí.@endif</p>
        @endif
    @endif

    @if ($pay['metodo'] === 'efectivo')
        <div class="row"><label for="p-rec">Paga con</label><input data-solo="monto" class="field" id="p-rec" inputmode="decimal" placeholder="{{ Dinero::n($grand) }}" wire:model.live.debounce.300ms="pay.rec">
            <div class="quick">@foreach ($billetes as $b)<button type="button" wire:click="recibido({{ $b }})">{{ $b === $grand ? 'Exacto' : str_replace('.00', '', Dinero::n($b)) }}</button>@endforeach</div></div>
        <p class="vuelto">{{ $vuelto !== null ? 'Vuelto: '.Dinero::s($vuelto) : '' }}</p>
    @endif

    <p class="err" role="alert">{{ $error }}</p>

    {{-- comprobante --}}
    <div class="card cpebox" style="margin-bottom:14px">
        <div class="row" style="margin:0 0 {{ $showDoc || $pide ? 10 : 0 }}px"><span class="cap" style="min-width:80px">Comprobante</span>
            <div class="seg" style="margin:0;flex:1"><button type="button" wire:click="tipoCpe('03')" aria-pressed="{{ $f ? 'false' : 'true' }}">Boleta</button>@if ($puedeFactura)<button type="button" wire:click="tipoCpe('01')" aria-pressed="{{ $f ? 'true' : 'false' }}">Factura</button>@endif</div></div>
        @if ($showDoc)
            <div class="set two" style="margin-bottom:4px">
                @if ($f)
                    <label>RUC del cliente<input data-solo="doc" wire:model.blur="pay.doc.nd" inputmode="numeric" maxlength="11" placeholder="20xxxxxxxxx"></label>
                    <label>Razón social<input wire:model.blur="pay.doc.nom"></label>
                    <label style="grid-column:1/-1">Dirección (opcional)<input wire:model.blur="pay.doc.dir"></label>
                @else
                    <label>Documento<select wire:model.live="pay.doc.td">@foreach (\App\Support\Catalogos::TIPOS_DOC as $k => $n) @if ($k !== '0')<option value="{{ $k }}">{{ $n }}</option>@endif @endforeach</select></label>
                    <label>Número<input data-solo="doc" wire:model.blur="pay.doc.nd" inputmode="numeric"></label>
                    <label style="grid-column:1/-1">Nombre del cliente<input wire:model.blur="pay.doc.nom"></label>
                @endif
            </div>
            <p class="cap" style="margin:0 0 8px">
                @if ($pay['doc']['nd'] && ! \App\Services\Comprobantes::documentoValido($f ? '6' : $pay['doc']['td'], $pay['doc']['nd']))
                    <span style="color:var(--magenta)">{{ $f || $pay['doc']['td'] === '6' ? 'Ese RUC no es válido (revisa los 11 dígitos).' : 'Revisa el número de documento.' }}</span>
                @elseif ($grande)Pasa de S/ 700: la boleta debe llevar el documento del comprador.@endif
            </p>
        @else
            @if ($pide)<p class="cap" style="margin:0 0 6px;color:var(--ok);font-weight:700">En SUNAT, en "Tipo de documento" elige: SIN DOCUMENTO</p>@endif
            <button type="button" class="link" wire:click="ponerDoc" style="padding:0 0 8px">+ Poner DNI del cliente en la boleta</button>
        @endif
        @if (! $over && ! $f)
            <label class="check" style="margin:0 0 8px"><input type="checkbox" wire:model.live="pay.pide"> El cliente pide su boleta (si no, va a la boleta de cierre del día)</label>
        @endif
        @if ($pide)
            <label class="check" style="margin:0"><input type="checkbox" wire:model.live="pay.emitida"> Ya la emití en SUNAT</label>
            @if ($pay['emitida'])
                <div class="row" style="margin:8px 0 0"><input data-solo="serie" class="field" wire:model.blur="pay.serie" placeholder="{{ $serieSug }}" style="width:90px"><span>–</span><input data-solo="entero" class="field" wire:model.blur="pay.numero" inputmode="numeric" placeholder="{{ $numSug }}" style="width:120px"><span class="cap">número que te dio SUNAT</span></div>
            @else
                <p class="cap" style="margin:6px 0 0">Si no la emites ahora, queda en Facturación › Por emitir con todo listo para copiar.</p>
            @endif
        @endif
    </div>

    <div class="actions paybar">
        <button type="button" class="btn ghost big" wire:click="cobrar(true)" wire:loading.attr="disabled" wire:target="cobrar">Con ticket</button>
        <button type="button" class="btn big" id="p-ok" wire:click="cobrar(false)" wire:loading.attr="disabled" wire:target="cobrar">{{ $pay['metodo'] === 'fiado' ? 'Fiar' : 'Cobrar' }} {{ Dinero::s($grand) }}</button>
    </div>
</div>
