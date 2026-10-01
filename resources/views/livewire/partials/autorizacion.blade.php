@if ($autz)
    @php $ap = $this->listaAutorizadores(); @endphp
    <div class="dlg" role="alertdialog" aria-modal="true" wire:key="autz" x-data x-on:keydown.escape.window="$wire.cancelarAutorizacion()">
        <form wire:submit="confirmarAutorizacion">
            <p><b>{{ $autz['titulo'] }}</b><br>{{ $autz['que'] }}.@if ($autz['motivo']) {{ $autz['motivo'] }}@endif</p>
            @if ($ap->count() > 1)
                <select class="field wide" wire:model="autzUsuario" style="width:100%;margin-bottom:10px">
                    @foreach ($ap as $u)<option value="{{ $u->id }}">{{ $u->nombre }}</option>@endforeach
                </select>
            @endif
            <input data-solo="pin" class="field wide" type="password" inputmode="numeric" maxlength="6" autocomplete="off" wire:model="autzPin" x-init="$nextTick(() => $el.focus())"
                   placeholder="PIN de {{ $ap->count() === 1 ? $ap->first()->primerNombre() : 'quien autoriza' }}" style="width:100%;margin-bottom:6px">
            <p class="err">{{ $autzError }}</p>
            <div class="actions"><button type="button" class="btn ghost big" wire:click="cancelarAutorizacion">Cancelar</button><button class="btn big" type="submit">Autorizar</button></div>
        </form>
    </div>
@endif
