@php
    use App\Support\Dinero; use App\Support\Catalogos; use App\Services\Comprobantes as CS; use Carbon\Carbon;
    $docTxt = fn ($d) => $d && ! empty($d['nd']) ? (Catalogos::TIPOS_DOC[$d['td'] ?? '1'] ?? 'Doc.').' '.$d['nd'].(! empty($d['nom']) ? ' · '.$d['nom'] : '') : 'Sin documento';
    $plazo = function ($k) { $r = CS::diasRestan(Carbon::parse($k)); return $r < 0 ? ['r', 'Plazo vencido'] : ($r === 0 ? ['r', 'Vence hoy'] : ($r <= 2 ? ['p', 'Vence en '.$r.' día'.($r > 1 ? 's' : '')] : ['s', $r.' días de plazo'])); };
@endphp
<section>
    <div class="seg" style="margin-bottom:12px">
        <button type="button" wire:click="$set('tab', 'pend')" aria-pressed="{{ $tab === 'pend' ? 'true' : 'false' }}">Por emitir <small>{{ $nPend }}</small></button>
        <button type="button" wire:click="$set('tab', 'emit')" aria-pressed="{{ $tab === 'emit' ? 'true' : 'false' }}">Emitidos</button>
        <button type="button" wire:click="$set('tab', 'reg')" aria-pressed="{{ $tab === 'reg' ? 'true' : 'false' }}">Registro de ventas</button>
    </div>
    <p class="cap" style="margin:0 0 10px">{{ Catalogos::REGIMENES[$cfg['regimen']][0] ?? '' }} · {{ ['gravado' => 'IGV 18%', 'exonerado' => 'Exonerado de IGV', 'inafecto' => 'Inafecto'][$cfg['igv']] ?? '' }} · Emisión manual en el portal de SUNAT
        @if (auth()->user()->puede('negocio')) · <a class="link" style="padding:0" href="{{ route('ajustes', ['p' => 'fact']) }}">cambiar</a>@endif</p>

    {{-- ============================ POR EMITIR ============================ --}}
    @if ($tab === 'pend')
        @if ($P)
            @if ($vencidos || $vencen)<div class="note bad">@if ($vencidos)<b>{{ $vencidos }} {{ $vencidos === 1 ? 'comprobante ya pasó' : 'comprobantes ya pasaron' }} el plazo.</b> Emítelos cuanto antes. @endif
                @if ($vencen)<b>{{ $vencen }} {{ $vencen === 1 ? 'vence' : 'vencen' }} hoy o mañana.</b> @endif SUNAT da hasta 7 días calendario para enviarlos.</div>@endif
            <button type="button" class="btn big" wire:click="empezarGuia" style="width:100%;margin-bottom:14px">Emitir uno por uno ({{ count($P) }})</button>
            <div class="stack">
                @foreach ($P as $p)
                    @php [$pc, $pl] = $plazo($p['fecha']); $k = \App\Livewire\Facturacion::clave($p); $esHoy = $p['fecha'] === today()->toDateString(); @endphp
                    <div class="card helper" wire:key="{{ $k }}">
                        <div style="display:flex;justify-content:space-between;gap:10px;align-items:baseline;flex-wrap:wrap"><b>
                            @if ($p['kind'] === 'cierre'){{ $p['otra'] ? 'Otra boleta de cierre · '.$p['n'].($p['n'] === 1 ? ' venta hecha' : ' ventas hechas').' después del cierre' : 'Boleta de cierre · '.$p['n'].($p['n'] === 1 ? ' venta' : ' ventas').' de S/ 5.00 o menos' }}
                            @else{{ Catalogos::TIPOS_COMPROBANTE[$p['tipo']] }} · venta de las {{ $p['venta']->vendida_at->format('H:i') }}@endif
                            {{ $esHoy ? '' : ' del '.Carbon::parse($p['fecha'])->format('d/m') }}</b><span class="tag {{ $pc }}">{{ $pl }}</span></div>
                        <dl style="margin-top:8px"><dt>Cliente</dt><dd>{{ $docTxt($p['doc']) }}@if ($p['tipo'] === '03' && $p['total'] > Catalogos::MAX_SIN_DOC && empty($p['doc']['nd'])) <span class="tag r">Falta DNI (más de S/ 700)</span>@endif</dd><dt>Descripción</dt><dd>{{ $p['desc'] }}</dd><dt>Importe</dt><dd>{{ Dinero::n($p['total']) }}</dd></dl>
                        <div class="actions tools">
                            <button type="button" class="btn ghost sm" x-on:click="copiar(@js($p['desc']), 'Descripción copiada')">Copiar descripción</button>
                            <button type="button" class="btn ghost sm" x-on:click="copiar(@js(Dinero::n($p['total'])), 'Importe copiado')">Copiar importe</button>
                            @if (! empty($p['doc']['nd']))<button type="button" class="btn ghost sm" x-on:click="copiar(@js($p['doc']['nd']), 'Documento copiado')">Copiar {{ ($p['doc']['td'] ?? '') === '6' ? 'RUC' : 'DNI' }}</button>@endif
                            <button type="button" class="btn sm" wire:click="abrirEmitir('{{ $k }}')">Ya la emití</button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="note ok">No hay comprobantes por emitir en el último mes.</div>
        @endif

    {{-- ============================ EMITIDOS / REGISTRO ============================ --}}
    @else
        <div class="daybar"><input type="month" wire:model.live="mes" max="{{ today()->format('Y-m') }}" style="border:1.5px solid var(--line);background:var(--sheet);border-radius:10px;padding:8px 10px">
            @if ($tab === 'reg' && $C->isNotEmpty())<a class="btn ghost sm" style="margin-left:auto" href="{{ route('facturacion.csv', $mes) }}">Descargar para Excel</a>@endif</div>
        @if ($saltos)
            <div class="note warn"><b>Números saltados:</b>
                @foreach ($saltos as $serie => $ns) {{ $serie }}: {{ collect($ns)->take(15)->join(', ') }}{{ count($ns) > 15 ? ' y '.(count($ns) - 15).' más' : '' }}.@endforeach
                Revisa en el portal de SUNAT si se emitieron y regístralos, o si fue un error al anotar el número.</div>
        @endif
        @if ($tab === 'emit')
            @forelse ($C->sortByDesc('emitido_at') as $cp)
                <div class="sale" style="{{ $cp->estado === 'anulado' ? 'opacity:.6' : '' }}" wire:key="cp{{ $cp->id }}"><span class="t">{{ $cp->emitido_at->format('d/m') }}<br>{{ $cp->emitido_at->format('H:i') }}</span>
                    <span class="d"><b>{{ Catalogos::TIPOS_COMPROBANTE[$cp->tipo] ?? $cp->tipo }} {{ $cp->etiqueta() }}</b>@if ($cp->referencia) <span class="cap">(sobre {{ $cp->referencia }})</span>@endif<br>
                        <span class="cap">{{ $docTxt($cp->cliente) }}@if ($cp->cierre_de) · cierre del {{ $cp->cierre_de->format('d/m') }}@endif @if ($cp->vendedor) · {{ $cp->vendedor }}@endif</span><br>
                        @if ($cp->estado === 'anulado')<span class="tag r">Anulado{{ $cp->motivo ? ': '.$cp->motivo : '' }}</span>@else<span class="tag b">Emitido</span>@endif @if (! $cp->numero)<span class="tag p">Falta número</span>@endif</span>
                    <span class="ops"><span class="amt">{{ $cp->tipo === '07' ? '−' : '' }}{{ Dinero::s(abs($cp->total)) }}</span>
                        @if ($cp->estado !== 'anulado')
                            <button type="button" class="link" wire:click="pedirAccion('numero', '{{ $cp->uid }}')">{{ $cp->numero ? 'Corregir número' : 'Poner número' }}</button>
                            @if ($cp->tipo !== '07' && $puedeFactura)<button type="button" class="link" wire:click="pedirAccion('nc', '{{ $cp->uid }}')">Nota de crédito</button>@endif
                            <button type="button" class="link" wire:click="pedirAccion('anular', '{{ $cp->uid }}')">Anular</button>
                        @endif</span></div>
            @empty
                <div class="empty">No hay comprobantes registrados en este mes.</div>
            @endforelse
        @else
            <div class="kpis">
                <div class="card kpi"><div class="cap">Total del mes</div><div class="v">{{ Dinero::s($tot('total')) }}</div></div>
                <div class="card kpi"><div class="cap">Exonerado</div><div class="v">{{ Dinero::s($tot('exonerado')) }}</div></div>
                <div class="card kpi"><div class="cap">Gravado</div><div class="v">{{ Dinero::s($tot('gravado')) }}</div></div>
                <div class="card kpi"><div class="cap">IGV</div><div class="v">{{ Dinero::s($tot('igv')) }}</div></div>
            </div>
            @if ($C->isNotEmpty())
                <div class="scroll"><table><thead><tr><th>Fecha</th><th>Comprobante</th><th>Cliente</th><th class="r">Total</th><th>Estado</th></tr></thead><tbody>
                    @foreach ($C as $cp)<tr><td>{{ $cp->emitido_at->format('d/m/Y') }}</td><td>{{ Catalogos::TIPOS_COMPROBANTE[$cp->tipo] ?? $cp->tipo }} {{ $cp->etiqueta() }}</td><td>{{ $docTxt($cp->cliente) }}</td><td class="r">{{ $cp->tipo === '07' ? '−' : '' }}{{ Dinero::n(abs($cp->total)) }}</td><td>{{ $cp->estado === 'anulado' ? 'Anulado' : 'Emitido' }}</td></tr>@endforeach
                </tbody></table></div>
                <p class="cap">Para tu contador y el Registro de Ventas (SIRE). Los anulados no suman; las notas de crédito restan.</p>
            @else
                <div class="empty">No hay comprobantes registrados en este mes.</div>
            @endif
        @endif
    @endif

    {{-- ============================ registrar un pendiente ============================ --}}
    @if ($x)
        @php $f = $x['tipo'] === '01'; $necesita = $f || $x['total'] > Catalogos::MAX_SIN_DOC; @endphp
        <div class="scrim on" wire:click="cerrar"></div>
        <div class="sheet on" role="dialog" aria-modal="true"><form wire:submit="registrar">
            <h3>{{ $posGuia ? 'Comprobante '.$posGuia.' de '.$nPend : 'Registrar '.mb_strtolower(Catalogos::TIPOS_COMPROBANTE[$x['tipo']]).' emitida' }}</h3>
            <p class="hint">@if ($x['kind'] === 'cierre')Boleta de cierre del {{ Carbon::parse($x['fecha'])->format('d/m/Y') }} ({{ $x['n'] }} {{ $x['n'] === 1 ? 'venta' : 'ventas' }} de S/ 5.00 o menos).@if ($x['fecha'] === today()->toDateString()) Si después vendes más, esas ventas saldrán en otra boleta de cierre.@endif
                @else Venta de las {{ $x['venta']->vendida_at->format('H:i') }}{{ $x['fecha'] !== today()->toDateString() ? ' del '.Carbon::parse($x['fecha'])->format('d/m/Y') : '' }}.@endif
                Emítela en el portal de SUNAT y anota el número que te da.</p>
            <div class="card helper" style="margin-bottom:12px"><div class="cap">Descripción</div><div style="font-weight:700;margin:4px 0 8px;word-break:break-word">{{ $x['desc'] }}</div>
                <div class="actions tools"><button type="button" class="btn ghost sm" x-on:click="copiar(@js($x['desc']), 'Descripción copiada, pégala en SUNAT')">Copiar descripción</button>
                    <button type="button" class="btn ghost sm" x-on:click="copiar(@js(Dinero::n($x['total'])), 'Importe copiado, pégalo en SUNAT')">Copiar importe ({{ Dinero::n($x['total']) }})</button>
                    @if ($doc['nd'])<button type="button" class="btn ghost sm" x-on:click="copiar(@js($doc['nd']), 'Documento copiado')">Copiar {{ $doc['td'] === '6' ? 'RUC' : 'documento' }}</button>@endif</div></div>
            <div class="set">
                @if (! $conDoc)
                    <div class="card" style="margin:0 0 10px;border:2px solid var(--ok)"><div style="font-weight:700">En SUNAT, en "Tipo de documento" elige: SIN DOCUMENTO</div><div class="cap" style="margin:4px 0 8px">Las boletas de hasta S/ 700 no necesitan DNI.</div>
                        <button type="button" class="btn ghost sm" wire:click="$set('conDoc', true)">El cliente quiere su boleta con DNI</button></div>
                @else
                    <div class="two">
                        @if ($f)
                            <label>RUC<input data-solo="doc" wire:model.blur="doc.nd" inputmode="numeric"></label><label>Razón social<input wire:model="doc.nom"></label>
                        @else
                            <label>Documento<select wire:model="doc.td">@foreach (Catalogos::TIPOS_DOC as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
                            <label>Número<input wire:model.blur="doc.nd"></label><label style="grid-column:1/-1">Nombre<input wire:model="doc.nom"></label>
                        @endif
                    </div>
                    @if ($necesita && ! $f)<p class="cap" style="margin:0 0 8px">Pasa de S/ 700: en SUNAT elige DNI (u otro documento) y escribe el número. No se puede "Sin documento".</p>@endif
                @endif
                <div class="two"><label>Serie<input data-solo="serie" wire:model="serie"></label><label>Número que te dio SUNAT<input data-solo="entero" wire:model="numero" inputmode="numeric"></label></div>
                <p class="cap" style="margin:-4px 0 8px">Ya viene el que sigue al último que anotaste. Revisa que sea el mismo que te dio SUNAT; si no, corrígelo.</p>
            </div>
            <p class="err">{{ $error }}</p>
            <div class="actions paybar">@if ($guia)<button type="button" class="btn ghost big" wire:click="saltar">Saltar</button>@endif
                <button class="btn big" type="submit" style="background:var(--ok);color:#fff">{{ $guia ? 'Ya la emití, siguiente' : 'Guardar' }}</button></div>
        </form></div>
    @endif

    {{-- ============================ anular / nota de crédito / número ============================ --}}
    @if ($c && $accion)
        <div class="scrim on" wire:click="cerrar"></div>
        <div class="sheet on" role="dialog" aria-modal="true"><form wire:submit="confirmarAccion">
            @if ($accion === 'anular')
                <h3>Anular {{ mb_strtolower(Catalogos::TIPOS_COMPROBANTE[$c->tipo]) }} {{ $c->etiqueta() }}</h3>
                <p class="hint">Queda como anulado en tu registro{{ count($c->ventas ?? []) || $c->cierre_de ? ' y las ventas vuelven a «Por emitir» para emitirlas bien' : '' }}. <b>Anúlala también en el portal de SUNAT</b> (opción de baja o anulación de comprobantes).</p>
                <div class="chips">@foreach (['Error en los datos del cliente', 'Error en el monto', 'La venta se anuló', 'Otro motivo'] as $m)<button type="button" class="chip" wire:click="$set('motivo', @js($m))" aria-pressed="{{ $motivo === $m ? 'true' : 'false' }}"><b style="font-size:.9rem">{{ $m }}</b></button>@endforeach</div>
                <p class="err">{{ $error }}</p>
                <div class="actions"><button type="submit" class="btn warn big">Anular</button></div>
            @elseif ($accion === 'nc')
                <h3>Nota de crédito sobre {{ $c->etiqueta() }}</h3>
                <p class="hint">Para devoluciones, descuentos después de emitir o corregir una {{ mb_strtolower(Catalogos::TIPOS_COMPROBANTE[$c->tipo]) }}. Emítela en SUNAT y anota aquí su número.@if ($previas) Ya tiene notas por {{ Dinero::s($previas) }}.@endif</p>
                <div class="set"><label>Motivo<select wire:model="motivo">@foreach (['Anulación de la operación', 'Devolución total', 'Devolución de parte', 'Descuento posterior', 'Corrección de la descripción'] as $m)<option>{{ $m }}</option>@endforeach</select></label>
                    <div class="two"><label>Monto (S/)<input data-solo="monto" wire:model="monto" inputmode="decimal"></label><label>Serie y número de la nota<input wire:model="serieNumero" placeholder="{{ $c->serie }}-1"></label></div></div>
                <p class="err">{{ $error }}</p>
                <div class="actions"><button type="submit" class="btn big">Registrar nota de crédito</button></div>
            @else
                <h3>{{ $c->numero ? 'Corregir el número de '.$c->etiqueta() : 'Poner número' }}</h3>
                <p class="hint">{{ Catalogos::TIPOS_COMPROBANTE[$c->tipo] }} de {{ Dinero::s(abs($c->total)) }} del {{ $c->fecha->format('d/m/Y') }}. Escribe la serie y el número exactos que te dio SUNAT.</p>
                <div class="set two"><label>Serie<input data-solo="serie" wire:model="serie"></label><label>Número<input data-solo="entero" wire:model="numero" inputmode="numeric"></label></div>
                <p class="err">{{ $error }}</p>
                <div class="actions"><button type="submit" class="btn big">Guardar</button></div>
            @endif
        </form></div>
    @endif

    @include('livewire.partials.autorizacion')
</section>
