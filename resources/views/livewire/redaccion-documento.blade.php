@php use App\Models\Documento; use App\Redaccion\Curriculum; use App\Redaccion\Motor; use App\Support\Dinero; use App\Support\Texto; @endphp
<section x-data x-on:redaccion-url.window="history.replaceState(null, '', $event.detail.url); window.scrollTo(0, 0)">
    @if ($vista === 'form' && $mod)
        <div class="daybar"><a class="btn ghost sm" href="{{ route('redaccion') }}">‹ Documentos</a><b style="font-size:1.15rem">{{ $mod->icono }} {{ $mod->nombre }}</b></div>
        @if ($mod->nota)<div class="note info">{{ $mod->nota }}</div>@endif

        @if ($mod->niveles)
            <div class="card" style="margin-bottom:12px"><b>Nivel del contrato</b>
                <div class="seg" style="margin:8px 0 4px">@foreach (Motor::NIVELES as $n => [$l])<button type="button" wire:click="nivel({{ $n }})" aria-pressed="{{ $nv === $n ? 'true' : 'false' }}">{{ $l }}</button>@endforeach</div>
                <p class="cap" style="margin:0">{{ Motor::NIVELES[$nv][1] }}. Las cláusulas que entran en cada nivel las ves y cambias al final.</p></div>
        @endif

        @foreach ($tarjetas as $ti => $tj)
            <div class="card" style="margin-bottom:12px" wire:key="tj{{ $ti }}-{{ $tj['persona'] }}">
                <b>{{ $tj['titulo'] }}</b>
                @if ($tj['persona'])
                    @php $p = $tj['persona']; @endphp
                    <div style="margin:8px 0 10px"><input class="field wide" wire:model.live.debounce.300ms="busca.{{ $p }}" placeholder="Buscar cliente guardado: DNI, celular o nombre" autocomplete="off" style="width:100%">
                        @if (isset($hallados[$p]))
                            <div class="stack" style="margin-top:6px">
                                @forelse ($hallados[$p] as $c)
                                    <button type="button" class="inv" wire:click="cargarCliente('{{ $p }}', {{ $c->id }})" style="text-align:left;width:100%;padding:8px 12px"><span class="nm">{{ $c->nombre }}<small>{{ $c->documento ? 'DNI '.$c->documento : 'Sin DNI' }}{{ $c->celular ? ', '.Texto::fmtCel($c->celular) : '' }}{{ $c->direccion ? ', '.$c->direccion : '' }}</small></span></button>
                                @empty
                                    <p class="cap" style="margin:0">No está registrado. Llena sus datos abajo y quedará guardado.</p>
                                @endforelse
                            </div>
                        @endif
                    </div>
                @endif
                @if (! empty($tj['lista']))
                    @php $L = $tj['lista']; $items = array_values(array_filter((array) ($d[$L['id']] ?? []), 'is_array')) ?: [[]]; @endphp
                    @foreach ($items as $ii => $it)
                        <div style="border-top:1px solid var(--line);margin-top:10px;padding-top:8px" wire:key="li-{{ $L['id'] }}-{{ $ii }}-{{ count($items) }}">
                            <div style="display:flex;justify-content:space-between;align-items:center"><span class="cap">{{ $L['item'] }} {{ $ii + 1 }}</span>
                                @if (count($items) > 1 || collect($it)->filter(fn ($v) => is_scalar($v) && trim((string) $v) !== '')->isNotEmpty())<button type="button" class="link" style="color:var(--magenta)" wire:click="quitarItem('{{ $L['id'] }}', {{ $ii }})">Quitar</button>@endif</div>
                            <div class="set two" style="margin-top:6px">
                                @foreach ($L['campos'] as $f)
                                    @include('redaccion.campo', ['f' => $f, 'ruta' => 'd.'.$L['id'].'.'.$ii.'.'.$f['id'], 'frases' => Motor::frasesDe($f, $d), 'clicFrase' => "fraseItem('".$L['id']."', ".$ii.", '".$f['id']."'"])
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    <button type="button" class="btn ghost sm" style="margin-top:10px" wire:click="agregarItem('{{ $L['id'] }}')">+ {{ $L['add'] }}</button>
                @else
                    <div class="set two" style="margin-top:8px">
                        @foreach ($tj['campos'] as $f)
                            @include('redaccion.campo', ['f' => $f, 'ruta' => 'd.'.$f['id'], 'frases' => Motor::frasesDe($f, $d), 'clicFrase' => "frase('".$f['id']."'", 'foto' => $d[$f['id']] ?? null])
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach

        @if ($clausulas)
            @php $nOn = collect($clausulas)->filter(fn ($c) => $motor->clOn($c, $d))->count(); @endphp
            <div class="card" style="margin-bottom:12px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px"><b>Cláusulas del contrato</b><span class="cap">{{ $nOn }} de {{ count($clausulas) }} incluidas</span></div>
                <p class="cap" style="margin:0 0 8px">Marca o desmarca, súbelas o bájalas. Se numeran solas (PRIMERA, SEGUNDA…). La etiqueta dice desde qué nivel entra cada una.</p>
                @foreach ($clausulas as $i => $c)
                    @php $tag = ! empty($c['custom']) ? ['m', 'Propia'] : (! empty($c['opt']) ? ['s', 'Opcional'] : ['b', Motor::NIVELES[$c['n'] ?? 1][0]]); @endphp
                    <div style="display:flex;gap:8px;align-items:center;padding:7px 0;border-top:1px solid var(--line)" wire:key="cl{{ $c['id'] }}">
                        <input type="checkbox" @checked($motor->clOn($c, $d)) @disabled($c['id'] === 'juris') wire:click="clausula('{{ $c['id'] }}', $event.target.checked)" aria-label="Incluir {{ $c['h'] }}" style="width:20px;height:20px">
                        <span style="flex:1;min-width:0"><b style="font-size:.9rem">{{ ! empty($c['custom']) ? mb_strtoupper($c['h']) : $c['h'] }}</b> <span class="tag {{ $tag[0] }}" style="font-size:.7rem">{{ $tag[1] }}</span>
                            @if (! empty($c['custom']))<br><span class="cap">{{ mb_strlen($c['t']) > 90 ? mb_substr($c['t'], 0, 90).'…' : $c['t'] }}</span>@endif</span>
                        @if (! empty($c['custom']))<button type="button" class="link" wire:click="abrirClausula('{{ $c['id'] }}')">Editar</button><button type="button" class="link" style="color:var(--magenta)" wire:click="quitarClausula('{{ $c['id'] }}')">Quitar</button>@endif
                        @if ($i)<button type="button" class="link" wire:click="mover('{{ $c['id'] }}', -1)" aria-label="Subir">↑</button>@endif
                        @if ($i < count($clausulas) - 1)<button type="button" class="link" wire:click="mover('{{ $c['id'] }}', 1)" aria-label="Bajar">↓</button>@endif
                    </div>
                @endforeach
                <button type="button" class="btn ghost sm" style="margin-top:8px" wire:click="abrirClausula">+ Agregar cláusula propia</button>
            </div>
        @endif

        <div class="actions" style="position:sticky;bottom:0;background:var(--paper);padding:10px 0 calc(10px + env(safe-area-inset-bottom,0px));border-top:1px solid var(--line)">
            <button type="button" class="btn big" wire:click="generar">Generar documento</button>
        </div>

        @if ($clEdit)
            <div class="scrim on" wire:click="cerrarVentana"></div>
            <div class="sheet on" role="dialog" aria-modal="true"><form wire:submit="guardarClausula">
                <h3>{{ $clEdit === 'nuevo' ? 'Agregar cláusula propia' : 'Editar cláusula' }}</h3>
                <p class="hint">Escribe el título y lo que debe decir. Sale tal cual, con mayúscula inicial y punto final. Si prefieres que Claude la redacte en lenguaje formal, usa «Encargar a Claude».</p>
                <div class="set"><label>Título<input wire:model="clH" placeholder="Ej.: Uso de la cochera"></label>
                    <label>Qué debe decir<textarea wire:model="clT" rows="6" style="border:1.5px solid var(--line);background:var(--sheet);border-radius:10px;padding:10px;font-size:1rem;color:var(--ink);resize:vertical" placeholder="Ej.: EL ARRENDATARIO podrá usar la cochera solo para estacionar su motocicleta."></textarea></label></div>
                <div class="actions paybar"><button class="btn big" type="submit">{{ $clEdit === 'nuevo' ? 'Agregar' : 'Guardar' }}</button></div>
            </form></div>
        @endif

        @if ($avisos)
            <div class="dlg" role="alertdialog" aria-modal="true"><div>
                @if (! empty($avisos['editado']))<p>Este documento tiene correcciones hechas a mano en el texto. Si lo vuelves a generar con los datos, esas correcciones se pierden.</p>@endif
                @if ($avisos['faltan'])<p>Faltan {{ count($avisos['faltan']) }} {{ count($avisos['faltan']) === 1 ? 'dato; saldrá' : 'datos; saldrán' }} como [COMPLETAR]: {{ implode(', ', array_slice($avisos['faltan'], 0, 4)) }}{{ count($avisos['faltan']) > 4 ? ' y '.(count($avisos['faltan']) - 4).' más' : '' }}.</p>@endif
                @foreach ($avisos['obs'] as $o)<p style="margin:0 0 6px">• {{ $o }}</p>@endforeach
                <p>¿Generar igual el documento?</p>
                <div class="actions"><button type="button" class="btn ghost big" wire:click="cerrarVentana">Revisar</button><button type="button" class="btn big" wire:click="generar(true)">Generar igual</button></div>
            </div></div>
        @endif
    @else
        @php
            $est = $doc?->estado ?? 'borrador';
            [$ec, $el] = Documento::ESTADOS[$est] ?? Documento::ESTADOS['borrador'];
            $cuando = $est === 'entregado' ? $doc->entregado_at : ($est === 'cobrado' ? $doc->cobrado_at : null);
        @endphp
        <div x-data="hojaEditable(t => $wire.corregir(t))">
        <div class="daybar">@if ($mod)<button type="button" class="btn ghost sm" x-on:click="await enviar(); $wire.editarDatos()">‹ Editar datos</button>@endif<a class="btn ghost sm" href="{{ route('redaccion') }}" x-on:click.prevent="await enviar(); location.href = $el.href">Documentos</a>
            <span style="margin-left:auto"></span><span class="tag {{ $ec }}">{{ $el }}{{ $cuando ? ' el '.$cuando->format('d/m') : '' }}{{ $est === 'cobrado' && $ticket ? ' · ticket '.$ticket : '' }}</span>
            @if ($paginas)<span class="cap">{{ $paginas }} {{ $paginas === 1 ? 'página' : 'páginas' }} en A4</span>@endif</div>
        @if ($faltan)<div class="note warn">Hay {{ $faltan }} {{ $faltan === 1 ? 'dato marcado' : 'datos marcados' }} como <mark>[COMPLETAR]</mark>. Puedes escribirlos tocando directamente el texto o volviendo a «Editar datos».</div>@endif
        @if ($bloques || $cv)
            <div class="actions" style="margin-bottom:8px">
                <button type="button" class="btn" x-on:click="await enviar(); window.open(@js(route('redaccion.pdf', $doc->uid)), '_blank')">Ver PDF para imprimir</button>
                <button type="button" class="btn ghost" x-on:click="await enviar(); location.href = @js(route('redaccion.word', $doc->uid))">Descargar Word</button>
                <button type="button" class="btn warn" x-on:click="await enviar(); $wire.abrirCobro()">{{ $est === 'borrador' ? 'Cobrar' : 'Cobrar otra vez' }}</button>
                @if ($est === 'cobrado')<button type="button" class="btn ghost" wire:click="entregar">Marcar entregado</button>@endif
            </div>
            @if ($cv)
                @php $dd = $doc->campos(); $tm = Curriculum::temaDe($dd); @endphp
                <div class="seg" style="margin-bottom:10px">@foreach (Curriculum::DISENOS as $id => [$l])<button type="button" wire:click="diseno('{{ $id }}')" aria-pressed="{{ $doc->plantilla === $id ? 'true' : 'false' }}">{{ $l }}</button>@endforeach</div>
                @if ($doc->plantilla === 'cv_moderno')
                    <div class="card" style="margin-bottom:12px"><b>Diseño</b>
                        <div class="cvm-panel">
                            <div><span class="cap">Color</span><div class="cvm-sw">@foreach (Curriculum::COLORES as $hx => $cl)<button type="button" title="{{ $cl }}" aria-label="{{ $cl }}" aria-pressed="{{ strtolower($tm['color']) === strtolower($hx) ? 'true' : 'false' }}" style="background:{{ $hx }}" wire:click="tema('color', '{{ $hx }}')"></button>@endforeach
                                <label class="cvm-own" title="Otro color">+<input type="color" value="{{ $tm['color'] }}" x-on:change="$wire.tema('color', $event.target.value)"></label></div></div>
                            @foreach (Curriculum::TEMA_OPCIONES as $k => [$tl, $ops])
                                <div><span class="cap">{{ $tl }}</span><div class="seg" style="margin:0">@foreach ($ops as $v => $l)<button type="button" wire:click="tema('{{ $k }}', '{{ $v }}')" aria-pressed="{{ $tm[$k] === $v ? 'true' : 'false' }}">{{ $l }}</button>@endforeach</div></div>
                            @endforeach
                        </div></div>
                @endif
                <p class="cap" style="margin:0 0 8px">Para cambiar los textos o la foto, toca «Editar datos». El diseño se cambia con un toque y no se pierde nada.</p>
                <div class="docwrap"><div class="docpage cvpage {{ $doc->plantilla === 'cv_moderno' ? 'cvm' : '' }}" id="dv-page">@include('redaccion.cv', ['d' => $dd, 'diseno' => $doc->plantilla])</div></div>
            @else
                <p class="cap" style="margin:0 0 8px">Toca cualquier párrafo para corregirlo. Los cambios se guardan solos. Para agregar, quitar o mover cláusulas, toca «Editar datos».</p>
                <div class="docwrap"><div class="docpage" id="dv-page" x-ref="hoja" wire:ignore x-on:input="cambio()" x-on:focusout="enviar()" x-on:paste="pegar($event)" x-on:keydown.enter.prevent>@include('redaccion.hoja', ['bloques' => $bloques, 'editable' => true])</div></div>
            @endif
        @else
            <div class="empty">Este documento se hizo en el sistema anterior con un diseño que todavía no está aquí. Sus datos están guardados.</div>
        @endif
        </div>

        @if ($cobrando)
            <div class="scrim on" wire:click="cerrarCobro"></div>
            <div class="sheet on" role="dialog" aria-modal="true">
                <h3>Cobrar documento</h3>
                @if (isset($cobroError))
                    <div class="note warn">{{ $cobroError }}</div>
                @else
                    @if ($est !== 'borrador')<div class="note warn" style="margin-bottom:10px">Este documento ya se cobró{{ $doc->cobrado_at ? ' el '.$doc->cobrado_at->format('d/m/Y H:i') : '' }}{{ $ticket ? ' (ticket '.$ticket.')' : '' }}. Cóbralo otra vez solo si es un trabajo nuevo, por ejemplo más copias o cambios.</div>@endif
                    <div class="card" style="margin-bottom:10px"><div style="display:flex;justify-content:space-between"><span>{{ $cv ? 'Currículum '.mb_strtolower(Curriculum::DISENOS[$doc->plantilla][0]) : 'Redacción' }}{{ $cobro['porPag'] ? ' · '.$cobro['paginas'].($cobro['paginas'] === 1 ? ' página' : ' páginas').' × '.Dinero::s($cobro['unit']) : '' }}</span><b>{{ Dinero::s($cobro['redac']) }}</b></div></div>
                    @if ($cobro['bnP'])
                        <div class="row"><label for="cd-ej">Ejemplares que imprimes</label><input class="field" id="cd-ej" inputmode="numeric" wire:model.live.debounce.300ms="ej" style="max-width:90px"></div>
                        <p class="cap" style="margin:0 0 10px">{{ $cobro['ej'] > 1 ? 'El primero va con la redacción; los otros '.($cobro['ej'] - 1).' se cobran como impresión B/N: '.$cobro['extra'].' hojas × '.Dinero::s($cobro['bnP']).' = '.Dinero::s($cobro['extra'] * $cobro['bnP']).'.' : 'Solo el original.' }}</p>
                    @endif
                    <div class="card" style="margin-bottom:12px"><div style="display:flex;justify-content:space-between;font-size:1.2rem"><span>Total</span><b>{{ Dinero::s($cobro['total']) }}</b></div></div>
                    <div class="actions paybar"><button type="button" class="btn big" wire:click="agregarAlCobro">Agregar al cobro</button></div>
                @endif
            </div>
        @endif
    @endif
</section>
