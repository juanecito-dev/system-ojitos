@php use App\Services\Contadores; use App\Support\Dinero; use App\Support\Texto; use Carbon\Carbon; $hoy = $this->esHoy(); $N = fn ($n) => Contadores::numero($n); @endphp
<div>
    @if ($maquinas->isEmpty())
        @if ($admin)
            <h2>Contadores de las máquinas</h2>
            <div class="note info">Anota cada noche el contador de tus fotocopiadoras e impresoras y el sistema te dirá si se hicieron copias que no se cobraron.
                @if ($config) <a class="link" href="{{ route('ajustes', ['p' => 'maq']) }}">Agregar mis máquinas</a>@endif</div>
        @endif
    @else
        <h2>Contadores de las máquinas <small>{{ $hoy ? 'anota la lectura al cerrar' : '' }}</small></h2>
        @if ($hoy)
            <p class="cap" style="margin:-4px 0 10px">Escribe solo el número que marca el contador, sin puntos ni comas. La lectura anterior se toma como punto de partida.</p>
        @endif
        <div class="turnos">
            @foreach ($maquinas as $m)
                <div class="card" style="padding:12px 14px" wire:key="maq{{ $m->id }}">
                    <div style="display:flex;justify-content:space-between;gap:10px;align-items:baseline"><b>{{ $m->nombre }}</b>
                        @if ($admin)<button type="button" class="link" style="padding:0" wire:click="verHistorial({{ $m->id }})">Historial</button>@endif</div>
                    @foreach ($m->contadores ?? [] as $c)
                        @php
                            $key = $m->uid.':'.$c['id'];
                            $l = $cont->lecturaDel($dia, $m->uid, $c['id'], $lecturas);
                            $u = $l ? null : $cont->anterior($dia, $m->uid, $c['id']);
                        @endphp
                        <div style="margin-top:8px" wire:key="c{{ $key }}">
                            <div class="cap">{{ count($m->contadores) > 1 ? $c['n'].' · ' : '' }}{{ Contadores::GRUPOS[$c['tipo']] ?? '' }}.
                                @if ($l)
                                    {{ $hoy ? 'Hoy' : 'Ese día' }}: <b>{{ $N($l->valor) }}</b>{{ $l->desde !== null ? ' ('.$N($l->valor - $l->desde).' copias)' : ', primera lectura' }} · {{ Texto::primerNombre($l->vendedor) }} {{ $l->ocurrido_at->format('H:i') }}
                                @elseif ($u)
                                    Última: {{ $N($u['v']) }} ({{ $u['fecha'] === Carbon::parse($dia)->subDay()->toDateString() ? 'ayer' : Carbon::parse($u['fecha'])->translatedFormat('j M') }})
                                @else
                                    Sin lecturas todavía
                                @endif
                            </div>
                            @if ($hoy)
                                <form class="row" style="margin:6px 0 0" wire:submit="guardar(@js($key))" x-data="{ get v() { return String($wire.lectura[@js($key)] ?? '') } }">
                                    <input data-solo="entero" class="field" wire:model="lectura.{{ $key }}" inputmode="numeric" autocomplete="off" placeholder="{{ $l ? 'Corregir lectura' : 'Lectura al cerrar' }}" style="width:170px">
                                    <button class="btn sm" type="submit">Guardar</button>
                                    <span class="cap" x-text="v.replace(/\D/g, '') ? '= ' + (+v.replace(/\D/g, '')).toLocaleString('en-US') : ''"></span>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        <div class="turnos" style="margin-top:12px">
            @foreach ($cuadre as $g => $o)
                @php
                    $mm = $lecturas->where('tipo', 'merma')->filter(fn ($x) => Contadores::grupoDe($x->grupo, array_keys($cuadre)) === $g);
                    $mal = $admin && $o['listo'] && $o['dif'] > 0;
                @endphp
                <div class="card" style="padding:12px 14px;{{ $mal ? 'border-color:var(--magenta);background:var(--mag-bg)' : '' }}" wire:key="g{{ $g }}"><b>Cuadre {{ Contadores::GRUPOS[$g] }}</b>
                    @if ($admin)
                        <div class="ledger" style="margin-top:8px">
                            <div><span>Según los contadores</span><b>{{ $N($o['usado']) }}</b></div>
                            <div><span>Cobradas en el sistema</span><b>{{ $N($o['esperado']) }}</b></div>
                            <div><span>Pruebas o malogradas</span><b>{{ $N($o['merma']) }}</b></div>
                            <div class="tot"><span>Diferencia</span><span>{{ ! $o['listo'] ? 'Falta completar' : ($o['dif'] === 0 ? 'Cuadra' : ($o['dif'] > 0 ? $N($o['dif']).' sin cobrar' : $N(-$o['dif']).' de más')) }}</span></div>
                        </div>
                        @if ($mal && $o['precio'])
                            <div class="cap" style="margin-top:4px">Son unos {{ Dinero::s($o['monto']) }}, a {{ Dinero::s((int) round($o['precio'])) }} por hoja.</div>
                        @endif
                        @if ($o['listo'] && $o['dif'] < 0)
                            <div class="cap" style="margin-top:4px">Se cobraron más copias de las que marcan los contadores: revisa en Configuración › Máquinas qué productos cuentan y cuántas hojas gasta cada uno.</div>
                        @endif
                    @endif
                    @if ($o['falta'])<div class="cap" style="margin-top:6px">Falta la lectura de: {{ implode(', ', $o['falta']) }}.</div>@endif
                    @if ($o['primero'])<div class="cap" style="margin-top:6px">Primera lectura de {{ implode(', ', $o['primero']) }}: desde el próximo día ya se compara.</div>@endif
                    @if ($mm->isNotEmpty())
                        <div class="cap" style="margin-top:6px">Pruebas anotadas: {{ $mm->map(fn ($x) => $N($x->valor).' ('.Texto::primerNombre($x->vendedor).' '.$x->ocurrido_at->format('H:i').')')->join(', ') }}</div>
                    @endif
                    @if ($hoy)<button type="button" class="link" style="padding:6px 0 0" wire:click="abrirMerma('{{ $g }}')">+ Anotar copias de prueba o malogradas</button>@endif
                </div>
            @endforeach
        </div>
    @endif

    @if ($confirmar)
        <div class="dlg" role="alertdialog" aria-modal="true"><div>
            <p>{{ $confirmar['texto'] }}</p>
            <div class="actions"><button type="button" class="btn ghost big" wire:click="cerrarVentana">Revisar</button><button type="button" class="btn big" wire:click="guardar(@js($confirmar['key']), true)">Sí, guardar</button></div>
        </div></div>
    @endif

    @if ($mermaDe)
        <div class="dlg" role="alertdialog" aria-modal="true"><form wire:submit="anotarMerma">
            <p>¿Cuántas copias de prueba, malogradas o de uso interno ({{ Contadores::GRUPOS[$mermaDe] }})?</p>
            <input data-solo="entero" class="field wide" wire:model="mermaN" inputmode="numeric" autocomplete="off" style="width:100%;margin-bottom:14px" x-init="$nextTick(() => $el.focus())">
            <div class="actions"><button type="button" class="btn ghost big" wire:click="cerrarVentana">Cancelar</button><button type="submit" class="btn big">Anotar</button></div>
        </form></div>
    @endif

    @if ($hist)
        <div class="scrim on" wire:click="cerrarVentana"></div>
        <div class="sheet on" role="dialog" aria-modal="true">
            <h3>{{ $hist->nombre }}</h3>
            <p class="hint">Copias de cada día según su contador (últimos 60 días).</p>
            @if ($filas)
                @php $cs = $hist->contadores ?? []; $dias = collect($filas)->filter(fn ($f) => $f['total'] > 0); @endphp
                @if ($dias->isNotEmpty())
                    <div class="ledger" style="margin-bottom:12px">
                        <div><span>Promedio por día</span><b>{{ $N($dias->avg('total')) }}</b></div>
                        <div><span>El día que más trabajó</span><b>{{ $N($dias->max('total')) }}</b></div>
                    </div>
                @endif
                <div class="scroll"><table><thead><tr><th>Día</th>
                    @foreach ($cs as $c)<th class="r">{{ count($cs) > 1 ? $c['n'] : 'Lectura' }}</th>@endforeach
                    <th class="r">Copias</th></tr></thead><tbody>
                    @foreach ($filas as $f)
                        <tr><td>{{ ucfirst(Carbon::parse($f['fecha'])->translatedFormat('D j M')) }}</td>
                            @foreach ($cs as $c)
                                @php $x = $f['conts'][$c['id']] ?? null; @endphp
                                <td class="r">{{ $x ? $N($x['v']) : '—' }}@if ($x && count($cs) > 1 && $x['copias'] !== null)<br><span class="cap">{{ $N($x['copias']) }}</span>@endif</td>
                            @endforeach
                            <td class="r"><b>{{ collect($f['conts'])->every(fn ($x) => $x['copias'] === null) ? 'primera' : $N($f['total']) }}</b></td></tr>
                    @endforeach
                </tbody></table></div>
            @else
                <div class="empty">Todavía no hay lecturas de esta máquina.</div>
            @endif
            <div class="actions"><button type="button" class="btn ghost big" wire:click="cerrarVentana">Cerrar</button></div>
        </div>
    @endif
</div>
