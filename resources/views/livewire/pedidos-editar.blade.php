@php
    use App\Support\Dinero; use App\Support\Texto; use App\Models\Venta;
    $esLista = $editLista !== null; $nuevo = $editando === 'nuevo'; $cot = ($e['etapa'] ?? '') === 'cotizado';
@endphp
<section>
    <div class="daybar"><button type="button" class="btn ghost sm" wire:click="cancelarEdicion" @if ($nuevo) wire:confirm="¿Salir sin guardar?" @endif>‹ {{ $esLista ? 'Listas' : 'Pedidos' }}</button>
        <b style="font-size:1.15rem">{{ $esLista ? 'Lista de útiles' : ($nuevo ? ($cot ? 'Nueva cotización' : 'Nuevo encargo') : 'Editar pedido') }}</b>
        @if ($nuevo && ! $esLista)
            <div class="seg" style="margin:0 0 0 auto"><button type="button" wire:click="tipo('cotizado')" aria-pressed="{{ $cot ? 'true' : 'false' }}">Cotización</button><button type="button" wire:click="tipo('proceso')" aria-pressed="{{ $cot ? 'false' : 'true' }}">Encargo</button></div>
        @endif
    </div>

    @if ($esLista)
        <div class="card" style="margin-bottom:12px"><div class="set two"><label>Nombre de la lista *<input wire:model="e.lista.nombre" placeholder="Ej.: 1.er grado 2027"></label><label>Colegio o institución<input wire:model="e.lista.inst" placeholder="Ej.: I.E. N° 32004 San Pedro"></label></div></div>
    @else
        <div class="card" style="margin-bottom:12px"><b>Cliente</b>
            <div style="margin:8px 0"><input class="field wide" wire:model.live.debounce.250ms="eCliQ" placeholder="Buscar cliente guardado: celular, DNI o nombre" autocomplete="off" style="width:100%">
                @if (trim($eCliQ) !== '')
                    <div class="stack" style="margin-top:6px">
                        @forelse ($cliHits as $x)
                            <button type="button" class="inv" wire:click="elegirCliente({{ $x->id }})" style="text-align:left;width:100%;padding:8px 12px"><span class="nm">{{ $x->nombre }}<small>{{ collect([\App\Services\Clientes::documentoTxt($x), $x->celular ? Texto::fmtCel($x->celular) : ''])->filter()->join(' · ') }}</small></span>@if (($saldos[$x->id] ?? 0) > 0)<span class="tag r">Debe {{ Dinero::n($saldos[$x->id]) }}</span>@endif</button>
                        @empty
                            <p class="cap" style="margin:0">No está guardado; llena sus datos abajo y se guardará.</p>
                        @endforelse
                    </div>
                @endif
            </div>
            <div class="set two">
                <label>Nombre o razón social *<input wire:model="e.cliente.nombre" placeholder="Ej.: Prof. Rosa Quispe"></label>
                <label>Celular<input data-solo="cel" wire:model="e.cliente.cel" inputmode="tel" placeholder="Para avisarle por WhatsApp"></label>
                <label>DNI o RUC (opcional)<input data-solo="doc" wire:model="e.cliente.doc" inputmode="numeric" maxlength="11" placeholder="Con RUC, al cobrar sale factura"></label>
                <label>Institución (opcional)<input wire:model="e.cliente.inst" placeholder="Ej.: I.E. N° 32004 San Pedro"></label>
            </div>
            @if ($e['cliente_id'])<p class="cap" style="margin:0">Cliente guardado: el pedido aparece en su ficha.</p>@endif
        </div>
    @endif

    <div class="card" style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap"><b>{{ $esLista ? 'Útiles de la lista' : 'Qué hay que hacer' }}</b>
            @unless ($esLista)<button type="button" class="btn ghost sm" wire:click="$set('usandoLista', true)">Usar una lista de útiles</button>@endunless</div>
        @unless ($esLista)
            <div class="set" style="margin-top:8px"><label>Detalle del trabajo{{ $e['items'] ? ' (opcional)' : '' }}<textarea wire:model="e.detalle" rows="2" style="border:1.5px solid var(--line);background:var(--paper);border-radius:10px;padding:10px;font-size:1rem;color:var(--ink);resize:vertical" placeholder="Ej.: 3 planos A1 color, anillado de tesis, 50 invitaciones"></textarea></label></div>
        @endunless
        <div style="margin:8px 0"><input class="field wide" wire:model.live.debounce.250ms="eItemQ" placeholder="Agregar de tu catálogo: anillado, color A4, lapicero…" autocomplete="off" style="width:100%">
            @if (trim($eItemQ) !== '')
                <div class="stack" style="margin-top:6px">
                    @forelse ($itemHits as $it)
                        <div class="inv" style="padding:8px 12px;flex-wrap:wrap"><span class="nm">{{ $it->nombre }}<small>{{ $it->grupo }}@isset($stock[$it->id]) · hay {{ \App\Services\Stock::formato($stock[$it->id]) }}@endisset</small></span>
                            <span class="quick">@foreach ($it->opciones as $k => $o)<button type="button" wire:click="agregarProducto({{ $it->id }}, {{ $k }})">{{ $o->etiqueta ? $o->etiqueta.' ' : '' }}{{ Dinero::n($o->precio) }}</button>@endforeach</span></div>
                    @empty
                        <p class="cap" style="margin:0">No está en tu catálogo; usa «+ Ítem libre».</p>
                    @endforelse
                </div>
            @endif
        </div>
        @if ($e['items'])
            <div class="scroll"><table class="pe-tab"><thead><tr><th>Cant.</th><th>Descripción</th><th>P. unit.</th><th class="r">Importe</th><th></th></tr></thead><tbody>
                @foreach ($e['items'] as $i => $l)
                    <tr wire:key="it{{ $i }}-{{ $l['pid'] ?? 'x' }}">
                        <td><input data-solo="entero" wire:model.blur="e.items.{{ $i }}.cant" inputmode="numeric" style="width:64px"></td>
                        <td><input wire:model.blur="e.items.{{ $i }}.nombre" style="width:100%;min-width:160px"><input wire:model.blur="e.items.{{ $i }}.det" placeholder="Detalle (opcional)" style="width:100%;margin-top:4px;font-size:.85rem">
                            @if (($l['pid'] ?? null) && isset($stock[$l['pid']]))<span class="tag {{ $stock[$l['pid']] < (int) $l['cant'] ? 'r' : 's' }}">{{ $stock[$l['pid']] < (int) $l['cant'] ? 'Solo hay ' : 'Hay ' }}{{ \App\Services\Stock::formato($stock[$l['pid']]) }}</span>@endif</td>
                        <td><input data-solo="monto" value="{{ Dinero::n($l['precio']) }}" x-on:change="$wire.precioItem({{ $i }}, $event.target.value)" inputmode="decimal" style="width:84px"></td>
                        <td class="r"><b>{{ Dinero::n((int) $l['cant'] * (int) $l['precio']) }}</b></td>
                        <td><button type="button" class="x" wire:click="quitarItem({{ $i }})" aria-label="Quitar" style="border:0;background:none;font-size:1.3rem;color:var(--soft)">×</button></td></tr>
                @endforeach
            </tbody></table></div>
        @else
            <p class="cap">{{ $esLista ? 'Busca arriba y agrega cada útil de la lista.' : 'Puedes escribir solo el detalle y el total, o agregar productos del catálogo para que el total se calcule solo.' }}</p>
        @endif
        <div class="actions" style="margin-top:8px"><button type="button" class="btn ghost sm" wire:click="itemLibre">+ Ítem libre</button></div>
        @unless ($esLista)
            @if ($e['items'])
                <div class="row" style="margin:12px 0 0;justify-content:flex-end"><label style="min-width:auto">Descuento (S/)</label><input data-solo="monto" class="field" wire:model.blur="e.descuento" inputmode="decimal" placeholder="0.00" style="width:110px"></div>
                <div class="sub"><span>Total @if ($totalForm !== $subForm)<small class="cap">({{ Dinero::s($subForm) }} − {{ Dinero::n($subForm - $totalForm) }})</small>@endif</span><strong>{{ Dinero::s($totalForm) }}</strong></div>
            @else
                <div class="row" style="margin:12px 0 0;justify-content:flex-end"><label style="min-width:auto">Total (S/) *</label><input data-solo="monto" class="field" wire:model.blur="e.monto" inputmode="decimal" placeholder="0.00" style="width:120px"></div>
            @endif
            @if ($pagadoForm)<p class="cap" style="margin:6px 0 0">Ya pagó {{ Dinero::s($pagadoForm) }}: el total no puede ser menor.</p>@endif
        @endunless
    </div>

    @unless ($esLista)
        @if ($cot)
            <div class="card" style="margin-bottom:12px"><b>Condiciones de la cotización</b>
                <div class="set two" style="margin-top:8px">
                    <label>Válida por (días)<input data-solo="entero" wire:model="e.validez" inputmode="numeric"></label>
                    <label>Fecha<input type="date" wire:model="e.fecha"></label>
                    <label style="grid-column:1/-1">Tiempo de entrega<input wire:model="e.cond_entrega"></label>
                    <label style="grid-column:1/-1">Forma de pago<input wire:model="e.cond_pago"></label>
                    <label style="grid-column:1/-1">Notas (opcional)<textarea wire:model="e.notas" rows="2" style="border:1.5px solid var(--line);background:var(--paper);border-radius:10px;padding:10px;font-size:1rem;color:var(--ink)"></textarea></label>
                </div></div>
        @else
            <div class="card" style="margin-bottom:12px"><b>Entrega</b>
                <div class="set two" style="margin-top:8px"><label>Fecha<input type="date" wire:model="e.fecha_entrega"></label><label>Hora (opcional)<input type="time" wire:model="e.hora"></label>
                    <label>Lo hace (opcional)<select wire:model="e.responsable"><option value="">—</option>@foreach ($usuarios as $n)<option>{{ $n }}</option>@endforeach</select></label></div>
                <div class="quick" style="margin:-4px 0 4px"><button type="button" wire:click="entregaEn(0)">Hoy</button><button type="button" wire:click="entregaEn(1)">Mañana</button><button type="button" wire:click="entregaEn(2)">Pasado</button></div>
                @if ($nuevo)
                    <div class="row" style="margin:10px 0 6px"><label>Adelanto</label><input data-solo="monto" class="field" wire:model.live.debounce.300ms="e.adelanto" inputmode="decimal" placeholder="0.00"></div>
                    @if (Dinero::aCentimos($e['adelanto']) > 0)
                        <div class="chips">@foreach ($neg->metodosActivos() as $k => $n)<button type="button" class="chip" wire:click="$set('e.metodo', '{{ $k }}')" aria-pressed="{{ $e['metodo'] === $k ? 'true' : 'false' }}"><b style="font-size:.95rem">{{ $n }}</b></button>@endforeach</div>
                    @endif
                @endif
            </div>
        @endif
    @endunless

    <p class="err">{{ $error }}</p>
    <div class="actions" style="position:sticky;bottom:0;background:var(--paper);padding:10px 0;border-top:1px solid var(--line)">
        <button type="button" class="btn big" wire:click="guardar" wire:loading.attr="disabled">{{ $esLista ? 'Guardar lista' : ($nuevo ? ($cot ? 'Guardar y ver cotización' : 'Guardar encargo') : 'Guardar cambios') }}</button></div>

    @if ($usandoLista)
        <div class="scrim on" wire:click="cerrarVentanas"></div>
        <div class="sheet on" role="dialog" aria-modal="true">
            <h3>Usar una lista de útiles</h3><p class="hint">Se agregan los útiles con los precios de hoy. Luego puedes quitar lo que el cliente ya tiene.</p>
            <input class="field wide" wire:model.live.debounce.250ms="usarQ" placeholder="Buscar lista o colegio" autocomplete="off" style="width:100%;margin-bottom:10px">
            <div class="stack">
                @forelse ($listasUsar as $t)
                    <button type="button" class="inv" wire:click="usarLista('{{ $t->uid }}')" style="text-align:left;width:100%"><span class="nm">{{ $t->nombre }}<small>{{ $t->institucion ? $t->institucion.' · ' : '' }}{{ count($t->items) }} útiles</small></span></button>
                @empty
                    <p class="cap">Aún no tienes listas, o ninguna coincide. Créalas en Pedidos › Listas de útiles.</p>
                @endforelse
            </div>
        </div>
    @endif
</section>
