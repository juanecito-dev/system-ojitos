@php use App\Services\Contadores; $sel = 'padding:6px;border-radius:8px;border:1px solid var(--line);background:var(--paper);color:var(--ink)'; @endphp
<div>
    <h2>Máquinas y contadores <small>para el cuadre de copias en Caja</small></h2>
    <div class="card">
        <p class="cap" style="margin:0 0 10px">Agrega cada fotocopiadora o impresora. Si tu máquina tiene un contador para B/N y otro para color, agrega los dos; si tiene uno solo para todo, elige «Total».</p>
        <div class="stack">
            @foreach ($maquinas as $m)
                <div class="pitem" style="margin:0" wire:key="m{{ $m->id }}">
                    <div class="h"><input value="{{ $m->nombre }}" aria-label="Nombre de la máquina" x-on:change="$wire.nombre({{ $m->id }}, $event.target.value)">
                        <button type="button" class="link" wire:click="pedirQuitar({{ $m->id }})">Quitar</button></div>
                    @foreach ($m->contadores ?? [] as $c)
                        <div class="row" style="margin:0 0 8px" wire:key="c{{ $c['id'] }}">
                            <input class="field" value="{{ $c['n'] }}" style="width:150px;padding:8px" aria-label="Nombre del contador" x-on:change="$wire.contador({{ $m->id }}, @js($c['id']), 'n', $event.target.value)">
                            <select style="{{ $sel }}" aria-label="Qué cuenta" x-on:change="$wire.contador({{ $m->id }}, @js($c['id']), 'tipo', $event.target.value)">
                                @foreach (Contadores::TIPOS as $k => $l)<option value="{{ $k }}" @selected($c['tipo'] === $k)>{{ $l }}</option>@endforeach
                            </select>
                            @if (count($m->contadores) > 1)<button type="button" class="link" wire:click="quitarContador({{ $m->id }}, @js($c['id']))">Quitar</button>@endif
                        </div>
                    @endforeach
                    <button type="button" class="link" style="padding:0" wire:click="agregarContador({{ $m->id }})">+ Otro contador</button>
                </div>
            @endforeach
        </div>
        <div class="actions" style="margin:10px 0 0"><button type="button" class="btn sm" style="flex:none" wire:click="agregar">Agregar máquina</button></div>

        @if ($maquinas->isNotEmpty())
            <h3 style="margin:16px 0 4px;font-size:1rem">Qué productos gastan contador</h3>
            <p class="cap" style="margin:0 0 8px">Muchas fotocopiadoras cuentan una hoja A3 como 2: si es tu caso, pon 2 en «Hojas».</p>
            <div class="scroll"><table><thead><tr><th>Producto</th><th>Cuenta en</th><th class="r">Hojas</th></tr></thead><tbody>
                @foreach ($candidatos as $p)
                    @php $x = $prods[$p->uid] ?? null; @endphp
                    <tr wire:key="p{{ $p->id }}"><td>{{ $p->nombre }}</td>
                        <td><select style="{{ $sel }}" aria-label="Cuenta en" x-on:change="$wire.prodGrupo(@js($p->uid), $event.target.value)">
                            <option value="">No cuenta</option>
                            @if ($total)
                                <option value="total" @selected($x)>Contador total</option>
                            @else
                                <option value="bn" @selected(($x['g'] ?? '') === 'bn')>B/N</option>
                                <option value="color" @selected(($x['g'] ?? '') === 'color')>Color</option>
                            @endif
                        </select></td>
                        <td class="r"><input inputmode="numeric" value="{{ $x ? ($x['f'] ?? 1) : '' }}" @disabled(! $x) style="{{ $sel }};width:56px;text-align:right" aria-label="Hojas por unidad"
                                             x-on:change="$wire.prodHojas(@js($p->uid), $event.target.value)"></td></tr>
                @endforeach
            </tbody></table></div>
        @endif
    </div>

    @if ($quitar)
        <div class="dlg" role="alertdialog" aria-modal="true"><div>
            <p>¿Quitar {{ $quitar->nombre }}? Sus lecturas pasadas se conservan.</p>
            <div class="actions"><button type="button" class="btn ghost big" wire:click="pedirQuitar(0)">Cancelar</button><button type="button" class="btn big warn" wire:click="quitar">Quitar</button></div>
        </div></div>
    @endif
</div>
