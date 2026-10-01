@php
    use App\Services\Inventario as Inv; use App\Services\Stock; use App\Support\Dinero; use App\Livewire\Inventario as InvLw;
    $F = fn ($n) => Stock::formato($n);
    $selSt = 'padding:10px;border-radius:12px;border:1.5px solid var(--line);background:var(--sheet);color:var(--ink)';
@endphp
<section>
    <div class="kpis">
        <div class="card kpi"><div class="cap">Con control de stock</div><div class="v">{{ $nConS }}</div></div>
        <div class="card kpi"><div class="cap">Por reponer</div><div class="v" style="color:{{ $nBajo ? 'var(--magenta)' : 'inherit' }}">{{ $nBajo }}</div><div class="cap">en su mínimo o menos</div></div>
        @if ($costos)
            <div class="card kpi"><div class="cap">Mercadería a precio de costo</div><div class="v">{{ Dinero::s($valor) }}</div>
                <div class="cap"><a class="link" style="padding:0" href="{{ route('inventario.valorizado') }}" target="_blank">Inventario valorizado (PDF)</a></div></div>
            <div class="card kpi" @if ($perdidas) style="background:var(--mag-bg);border-color:var(--magenta)" @endif><div class="cap">Pérdidas del mes</div><div class="v">{{ Dinero::s($perdidas) }}</div><div class="cap">faltantes al contar (sin errores de conteo)</div></div>
        @endif
    </div>

    <div class="daybar">
        <a class="btn" href="{{ route('inventario.toma') }}">Toma de inventario</a>
        <button type="button" class="btn ghost" wire:click="abrirInsumo">Nuevo insumo</button>
        @if ($reponer)<a class="btn ghost" href="{{ route('compras', ['f' => 'reponer']) }}">Lista de reposición ({{ $nBajo }})</a>@endif
        <input class="field" wire:model.live.debounce.250ms="buscar" placeholder="Buscar producto o código" style="flex:1;min-width:170px;width:auto">
        @if ($grupos->count() > 1)
            <select wire:model.live="grupo" style="{{ $selSt }}"><option value="">Todos los grupos</option>@foreach ($grupos as $g)<option>{{ $g }}</option>@endforeach</select>
        @endif
    </div>
    <div class="seg">
        @foreach (['control' => 'Con stock', 'reponer' => 'Por reponer', 'sin' => 'Sin control', 'consumo' => 'Insumos de servicios'] as $k => $l)
            <button type="button" wire:click="$set('filtro', '{{ $k }}')" aria-pressed="{{ $filtro === $k ? 'true' : 'false' }}">{{ $l }}</button>
        @endforeach
    </div>
    @if ($filtro === 'consumo')
        <p class="cap" style="margin:0 0 10px">Indica qué gasta cada servicio (por ejemplo, 1 copia A4 = 1 hoja bond) y el stock de esos insumos bajará solo con cada venta. Así sabes cuánto te cuesta de verdad cada servicio.</p>
    @endif

    @if ($lista->isNotEmpty())
        <div class="stack">
            @foreach ($lista as $p)
                @php $has = array_key_exists($p->id, $st); $pr = $p->opciones->first()?->precio; @endphp
                @if ($filtro === 'consumo')
                    @php $ic = $p->costoInsumos(); @endphp
                    <div class="inv" wire:key="p{{ $p->id }}"><span class="nm">{{ $p->nombre }}<small>
                        @if ($p->insumos->isNotEmpty())
                            {{ $p->insumos->map(fn ($x) => ($x->cantidad < 1 ? '1 '.($x->insumo?->unidad ?: $x->insumo?->nombre).' rinde '.round(1 / $x->cantidad) : $F($x->cantidad).' '.($x->insumo?->unidad ? $x->insumo->unidad.' de '.$x->insumo->nombre : $x->insumo?->nombre)))->join(' + ') }}
                            @if ($costos && $ic && $pr) · insumos {{ Dinero::sCosto($ic) }} de {{ Dinero::s($pr) }}: ganas {{ Dinero::sCosto($pr - $ic) }} ({{ round(($pr - $ic) / $pr * 100) }}%)@endif
                        @else
                            No gasta insumos registrados
                        @endif
                    </small></span><button type="button" class="btn ghost sm" wire:click="abrirConsumo({{ $p->id }})">{{ $p->insumos->isNotEmpty() ? 'Cambiar' : 'Indicar insumos' }}</button></div>
                @else
                    @php $d = $has ? Inv::diasAlcanza($st[$p->id], $vel[$p->id] ?? 0) : null; @endphp
                    <div class="inv {{ $has && $st[$p->id] <= $p->minimo() ? 'low' : '' }}" wire:key="p{{ $p->id }}">
                        <span class="nm" @if ($has) wire:click="abrirFicha({{ $p->id }})" style="cursor:pointer" @endif>{{ $p->nombre }}@if ($p->oculto) <span class="tag s">Insumo</span>@endif
                            <small>{{ $p->grupo }}{{ $pr ? ', '.Dinero::s($pr) : '' }}{{ $costos && $p->costo ? ', costo '.Dinero::sCosto($p->costo) : '' }}{{ $has ? ' · mínimo '.$F($p->minimo()) : '' }}
                                @if ($d !== null) · <span style="{{ $d < 3 ? 'color:var(--magenta);font-weight:700' : '' }}">{{ $d > 90 ? 'te alcanza más de 3 meses' : 'te alcanza ~'.$d.($d === 1 ? ' día' : ' días') }}</span>@endif
                                @if (! empty($hoy[$p->id])) · hoy vendiste {{ $F($hoy[$p->id]) }}@endif
                            </small></span>
                        @if ($has)
                            <span class="qty">{{ $F($st[$p->id]) }}@if ($p->unidad)<small style="display:block;font-size:.7rem;font-weight:600;color:var(--soft)">{{ $p->unidad }}</small>@endif</span>
                            <button type="button" class="btn sm" wire:click="abrirMov({{ $p->id }}, 'entrada')">+ Entrada</button>
                            <button type="button" class="btn ghost sm" wire:click="abrirMov({{ $p->id }}, 'conteo')">Contar</button>
                        @else
                            <button type="button" class="btn ghost sm" wire:click="abrirMov({{ $p->id }}, 'conteo')">Controlar stock</button>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>
    @else
        <div class="empty">{{ $buscar !== '' ? 'No encontré «'.$buscar.'».' : ['reponer' => 'Nada por reponer: todo está por encima de su mínimo.', 'sin' => 'Todos tus útiles ya tienen control de stock.', 'consumo' => 'No hay servicios.'][$filtro] ?? 'Aún no controlas stock. Ve a «Sin control» y elige qué productos contar.' }}</div>
    @endif

    {{-- entrada o conteo --}}
    @if ($mov && isset($movP))
        @php $cur = $st[$movP->id] ?? null; $ent = $mov['tipo'] === 'entrada'; @endphp
        <div class="scrim on" wire:click="cerrar"></div>
        <div class="sheet on" role="dialog" aria-modal="true" x-data="{ n: $wire.entangle('movN'), cur: {{ $cur ?? 'null' }}, costo: {{ (float) ($movP->costo ?? 0) }},
                get dif() { const v = parseFloat(String(this.n).replace(',', '.')); return this.cur === null || isNaN(v) ? 0 : Math.round((v - this.cur) * 1000) / 1000 } }">
            <form wire:submit="guardarMov">
                <h3>{{ $ent ? 'Entrada de ' : 'Contar ' }}{{ $movP->nombre }}</h3>
                <p class="hint">{{ $ent ? 'Lo que te llegó. Se suma al stock actual ('.$F($cur ?? 0).'). Si viene con factura, mejor regístralo en Compras: así también actualiza el costo.' : ($cur === null ? 'Cuántos tienes ahora. Desde aquí el sistema los irá descontando solo.' : 'Cuántos hay de verdad. Reemplaza el número actual ('.$F($cur).').') }}{{ $movP->unidad ? ' En '.$movP->unidad.'.' : '' }}</p>
                <div class="row">
                    <div class="stepper"><button type="button" aria-label="Menos" x-on:click="n = String(Math.max(0, (parseFloat(n) || 0) - 1))">−</button>
                        <input data-solo="cantidad" x-model="n" inputmode="decimal" aria-label="Cantidad" x-on:focus="$el.select()"><button type="button" aria-label="Más" x-on:click="n = String((parseFloat(n) || 0) + 1)">+</button></div>
                    <div class="quick">@foreach ($ent ? [6, 10, 12, 24, 50, 100] : [0, 5, 10, 20, 50] as $q)<button type="button" x-on:click="n = '{{ $q }}'">{{ $q }}</button>@endforeach</div>
                </div>
                @if ($ent)
                    <div class="row"><input class="field wide" wire:model="movNota" placeholder="Detalle (opcional): de dónde vino"></div>
                    @if ($costos)
                        <div class="row"><label for="k-c">Costo c/u</label><input data-solo="costo" class="field" id="k-c" wire:model="movCosto" inputmode="decimal" placeholder="{{ $movP->costo ? Dinero::nCosto($movP->costo) : 'opcional' }}"><span class="cap">recalcula el costo promedio</span></div>
                    @endif
                @elseif ($cur !== null)
                    <div x-show="dif" x-cloak>
                        <p class="cap" style="margin:0 0 6px"><b x-text="(dif < 0 ? 'Faltan ' + (-dif) : 'Sobran ' + dif) + (dif < 0 && costo {{ $costos ? '' : '&& false' }} ? ' (S/ ' + (-dif * costo / 100).toFixed(2) + ')' : '') + '.'"></b> ¿Por qué?</p>
                        <div class="chips">@foreach ($motivos as $m)<button type="button" class="chip" wire:click="$set('movMotivo', @js($m))" aria-pressed="{{ $movMotivo === $m ? 'true' : 'false' }}"><b style="font-size:.9rem">{{ $m }}</b></button>@endforeach</div>
                    </div>
                @endif
                <p class="err">{{ $error }}</p>
                <div class="actions"><button class="btn big" type="submit">Guardar</button></div>
            </form>
        </div>
    @endif

    {{-- ficha del producto con el kárdex --}}
    @if ($ficha && isset($fp))
        @php $d = Inv::diasAlcanza($st[$fp->id] ?? null, $vel[$fp->id] ?? 0); @endphp
        <div class="scrim on" wire:click="cerrar"></div>
        <div class="sheet on" role="dialog" aria-modal="true">
            <h3>{{ $fp->nombre }}</h3>
            <p class="hint">{{ $fp->grupo }} · stock {{ $F($st[$fp->id] ?? 0) }}{{ $fp->unidad ? ' '.$fp->unidad : '' }}{{ $costos && $fp->costo ? ' · costo promedio '.Dinero::sCosto($fp->costo) : '' }}{{ $d !== null ? ' · te alcanza ~'.$d.' días' : '' }}</p>
            <div class="actions" style="margin-bottom:12px"><button type="button" class="btn sm" wire:click="abrirMov({{ $fp->id }}, 'entrada')">+ Entrada</button><button type="button" class="btn ghost sm" wire:click="abrirMov({{ $fp->id }}, 'conteo')">Contar</button></div>
            <form class="card" style="margin-bottom:12px" wire:submit="guardarAjustes"><b>Ajustes del producto</b>
                <div class="set two" style="margin-top:8px">
                    <label>Stock mínimo (avisa al llegar aquí)<input data-solo="cantidad" wire:model="aj.min" inputmode="decimal"></label>
                    @if ($costos)<label>Costo por unidad (S/)<input data-solo="costo" wire:model="aj.costo" inputmode="decimal" placeholder="Se calcula solo con las compras"></label>@endif
                    <label>Se compra por (ej.: Caja, Millar)<input wire:model="aj.un" placeholder="Unidad"></label>
                    <label>¿Cuántas trae?<input data-solo="entero" wire:model="aj.f" inputmode="numeric" placeholder="1"></label>
                    @if ($fp->oculto)<label>Se mide en (ej.: hojas, unidades)<input wire:model="aj.um"></label>@endif
                </div>
                @if ($fp->oculto)<label class="check" style="margin:0 0 8px"><input type="checkbox" wire:model="aj.oculto"> Es un insumo (no aparece en el punto de venta)</label>@endif
                <p class="err">{{ $error }}</p>
                <div class="actions"><button class="btn sm" type="submit" style="flex:none">Guardar ajustes</button></div>
            </form>
            @if ($precios->isNotEmpty())
                @php $bar = $precios->sortBy('c')->first(); @endphp
                <h2>Historial de precios <small>lo que te costó en cada compra</small></h2>
                <div class="scroll" style="margin-bottom:12px"><table><thead><tr><th>Fecha</th><th>Proveedor</th><th>Compraste</th><th class="r">Costo c/u</th></tr></thead><tbody>
                    @foreach ($precios->take(20) as $h)
                        <tr><td style="white-space:nowrap">{{ $h['fecha'] ? \Carbon\Carbon::parse($h['fecha'])->format('d/m/Y') : '—' }}</td><td>{{ $h['prov'] }}</td>
                            <td>{{ $h['cant'] !== null ? $F($h['cant']).' '.($h['factor'] > 1 ? mb_strtolower($h['unidad']).' × '.$F($h['factor']) : ($fp->unidad ?: 'unid.')) : '' }}</td>
                            <td class="r"><b style="{{ $precios->count() > 1 && $h === $bar ? 'color:var(--ok)' : '' }}">{{ Dinero::sCosto($h['c']) }}</b></td></tr>
                    @endforeach
                </tbody></table></div>
                @if ($precios->count() > 1)<p class="cap" style="margin:-6px 0 12px">En verde, el más barato: {{ $bar['prov'] }}.</p>@endif
            @endif
            <h2>Kárdex <small>{{ count($kardex) }} {{ count($kardex) === 1 ? 'movimiento' : 'movimientos' }}</small></h2>
            <div class="daybar">
                <input type="date" wire:model.live="kDesde" max="{{ today()->toDateString() }}" aria-label="Desde"><span class="cap">al</span><input type="date" wire:model.live="kHasta" max="{{ today()->toDateString() }}" aria-label="Hasta">
                <div class="quick">@foreach (['30' => '30 días', 'mes' => 'Este mes', '90' => '3 meses', 'anio' => 'Este año', 'todo' => 'Todo'] as $k => $l)<button type="button" wire:click="kardexDesde('{{ $k }}')">{{ $l }}</button>@endforeach</div>
            </div>
            @if ($kardex)
                <div class="scroll"><table><thead><tr><th>Fecha</th><th>Movimiento</th><th class="r">Entra</th><th class="r">Sale</th><th class="r">Saldo</th></tr></thead><tbody>
                    @foreach (array_slice($kardex, 0, $kVer) as $r)
                        <tr><td style="white-space:nowrap">{{ $r['t']->format('d/m') }} {{ $r['t']->format('H:i') }}</td><td>{{ $r['que'] }}@if ($r['vend']) <span class="cap">· {{ \App\Support\Texto::primerNombre($r['vend']) }}</span>@endif</td>
                            <td class="r">{{ $r['d'] > 0 ? $F($r['d']) : '' }}</td><td class="r">{{ $r['d'] < 0 ? $F(-$r['d']) : '' }}</td><td class="r"><b>{{ $F($r['saldo']) }}</b></td></tr>
                    @endforeach
                </tbody></table></div>
                @if (count($kardex) > $kVer)<div class="actions"><button type="button" class="btn ghost sm" wire:click="$set('kVer', {{ $kVer + 100 }})">Ver {{ min(100, count($kardex) - $kVer) }} más</button></div>@endif
            @else
                <p class="cap">Sin movimientos en esas fechas.</p>
            @endif
            <div class="actions"><button type="button" class="btn ghost big" wire:click="cerrar">Cerrar</button></div>
        </div>
    @endif

    {{-- nuevo insumo --}}
    @if ($nuevoInsumo)
        <div class="scrim on" wire:click="cerrar"></div>
        <div class="sheet on" role="dialog" aria-modal="true">
            <form wire:submit="crearInsumo">
                <h3>Nuevo insumo</h3><p class="hint">Materiales que usas para tus servicios y no vendes sueltos: papel por millar, tóner, anillos, tapas, micas. No aparecen en el punto de venta.</p>
                <div class="set">
                    <label>Nombre<input wire:model="ni.nombre" placeholder="Ej.: Papel bond A4 75 g" x-init="$nextTick(() => $el.focus())"></label>
                    <div class="two">
                        <label>Se mide en<input wire:model="ni.um" placeholder="Ej.: hojas, unidades, tóner"></label>
                        <label>Cuánto tienes ahora<input data-solo="cantidad" wire:model="ni.stock" inputmode="decimal" placeholder="Ej.: 2500"></label>
                        <label>Se compra por (opcional)<input wire:model="ni.un" placeholder="Ej.: Millar, Caja"></label>
                        <label>¿Cuántas trae?<input data-solo="entero" wire:model="ni.f" inputmode="numeric" placeholder="Ej.: 1000"></label>
                        @if ($costos)<label>Costo de esa compra (S/)<input data-solo="costo" wire:model="ni.costo" inputmode="decimal" placeholder="Ej.: 25.00"></label>@endif
                        <label>Stock mínimo<input data-solo="cantidad" wire:model="ni.min" inputmode="decimal" placeholder="Ej.: 500"></label>
                    </div>
                </div>
                <p class="err">{{ $error }}</p>
                <div class="actions"><button class="btn big" type="submit">Agregar insumo</button></div>
            </form>
        </div>
    @endif

    {{-- qué gasta un servicio --}}
    @if ($consumo && isset($cp))
        @php
            $sel = 'padding:8px;border-radius:10px;border:1.5px solid var(--line);background:var(--sheet);color:var(--ink)';
            $ci = collect($filas)->sum(fn ($x) => ($cands->firstWhere('id', (int) ($x['id'] ?? 0))?->costo ?? 0) * InvLw::porUnidad($x));
            $cpr = $cp->opciones->first()?->precio;
        @endphp
        <div class="scrim on" wire:click="cerrar"></div>
        <div class="sheet on" role="dialog" aria-modal="true">
            <h3>¿Qué gasta {{ $cp->nombre }}?</h3><p class="hint">Por cada 1 que vendes. Para el tóner usa «1 rinde», por ejemplo: 1 tóner rinde 7000 copias.</p>
            @if ($cands->isEmpty())<div class="note warn">Primero agrega tus insumos con «Nuevo insumo» o controla el stock del producto que se gasta.</div>@endif
            <div class="stack">
                @foreach ($filas as $i => $x)
                    @php $ins = $cands->firstWhere('id', (int) $x['id']); @endphp
                    <div class="card" style="padding:10px" wire:key="fila{{ $i }}"><div class="row" style="margin:0">
                        <select wire:model.live="filas.{{ $i }}.id" style="{{ $sel }};flex:1;min-width:150px">@foreach ($cands as $c)<option value="{{ $c->id }}">{{ $c->nombre }}</option>@endforeach</select>
                        <select wire:model.live="filas.{{ $i }}.modo" style="{{ $sel }}"><option value="gasta">gasta</option><option value="rinde">1 rinde</option></select>
                        <input data-solo="cantidad" class="field" wire:model.live.debounce.400ms="filas.{{ $i }}.v" inputmode="decimal" style="width:90px;padding:8px">
                        <span class="cap">{{ $x['modo'] === 'rinde' ? 'servicios' : ($ins?->unidad ?: 'unid.') }}</span>
                        <button type="button" wire:click="quitarFila({{ $i }})" aria-label="Quitar" style="border:0;background:none;font-size:1.2rem;color:var(--soft)">×</button>
                    </div></div>
                @endforeach
            </div>
            @if ($cands->isNotEmpty())<button type="button" class="btn ghost sm" style="margin:10px 0" wire:click="agregarFila">+ Agregar insumo</button>@endif
            @if ($costos)
                <div class="card" style="margin-bottom:12px;background:var(--paper)">Costo de insumos por unidad: <b>{{ Dinero::sCosto($ci) }}</b>@if ($cpr) de un precio de {{ Dinero::s($cpr) }} · ganas <b>{{ Dinero::sCosto($cpr - $ci) }}</b> ({{ round(($cpr - $ci) / $cpr * 100) }}%)@endif</div>
            @endif
            <div class="actions"><button type="button" class="btn big" wire:click="guardarConsumo">Guardar</button></div>
        </div>
    @endif
</section>
