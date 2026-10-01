{{-- Un campo del formulario de Redacción. $f = campo, $ruta = propiedad de Livewire (d.x o d.lista.0.x),
     $frases = frases de ejemplo ya elegidas, $clicFrase = llamada con el número de la frase (se agrega al final) --}}
@php
    $tipo = $f['type'] ?? '';
    $st = ! empty($f['full']) || in_array($tipo, ['area', 'check', 'photo'], true) ? 'grid-column:1/-1' : '';
    $lab = $f['label'].(! empty($f['req']) ? ' *' : '');
    $fid = (string) ($f['id'] ?? '');
    $solo = match (true) {
        $fid === 'dni' || str_ends_with($fid, '_dni') || str_ends_with($fid, 'Dni') => 'dni',
        $fid === 'cel' || str_ends_with($fid, '_cel') => 'cel',
        $fid === 'ruc' || str_ends_with($fid, '_ruc') => 'ruc',
        $tipo === 'money' => 'soles',
        $tipo === 'num' => in_array($fid, ['area', 'tasa'], true) ? 'cantidad' : 'entero',
        default => null,
    };
@endphp
@if ($tipo === 'select')
    <label style="{{ $st }}" wire:key="f-{{ $ruta }}">{{ $lab }}<select wire:model.live="{{ $ruta }}">@foreach ($f['opts'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
@elseif ($tipo === 'check')
    <label class="check" style="flex-direction:row;color:var(--ink);{{ $st }}" wire:key="f-{{ $ruta }}"><input type="checkbox" wire:model.live="{{ $ruta }}"> {{ $f['label'] }}</label>
@elseif ($tipo === 'photo')
    <label style="{{ $st }}" wire:key="f-{{ $ruta }}">{{ $lab }}
        <span style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
            @if (! empty($foto))<img src="{{ $foto }}" alt="Foto" style="width:60px;height:76px;object-fit:cover;border-radius:6px;border:1px solid var(--line)">@endif
            <input type="file" accept="image/*" style="border:0;padding:0;background:none"
                   x-on:change="const f = $event.target.files[0]; if (f) ojitosFoto(f).then(u => $wire.ponerFoto(u)).catch(() => $dispatch('toast', {texto: 'No se pudo leer la foto'})); $event.target.value = ''">
            @if (! empty($foto))<button type="button" class="link" wire:click="quitarFoto">Quitar foto</button>@endif
        </span>
    </label>
@else
    <label style="{{ $st }}" wire:key="f-{{ $ruta }}">{{ $lab }}
        @if ($frases)
            <span class="quick" style="margin:4px 0">@foreach ($frases as $fi => $fr)<button type="button" wire:click="{{ $clicFrase }}, {{ $fi }})">{{ mb_strlen($fr) > 44 ? mb_substr($fr, 0, 42).'…' : $fr }}</button>@endforeach</span>
        @endif
        @if ($tipo === 'area')
            <textarea wire:model.blur="{{ $ruta }}" rows="4" placeholder="{{ $f['ph'] ?? '' }}" style="border:1.5px solid var(--line);background:var(--paper);border-radius:10px;padding:10px;font-size:1rem;color:var(--ink);resize:vertical"></textarea>
        @else
            <input type="{{ $tipo === 'date' ? 'date' : 'text' }}" @if ($solo) data-solo="{{ $solo }}" @endif wire:model.blur="{{ $ruta }}" placeholder="{{ $f['ph'] ?? '' }}" @if (in_array($tipo, ['money', 'num'], true) || ! empty($f['num'])) inputmode="decimal" @endif>
        @endif
        @if (! empty($f['ayuda']))<span class="cap" style="margin-top:2px">{{ $f['ayuda'] }}</span>@endif
    </label>
@endif
