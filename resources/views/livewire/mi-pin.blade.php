<div>
    @if ($abierto)
        <div class="scrim on" wire:click="cerrar"></div>
        <div class="sheet on" role="dialog" aria-modal="true" x-data x-on:keydown.escape.window="$wire.cerrar()">
            <form wire:submit="guardar">
                <h3>Cambiar mi PIN</h3>
                <p class="hint">{{ auth()->user()->nombre }}: escribe tu PIN actual y el nuevo. Nadie más lo verá.</p>
                <div class="set">
                    <label>PIN actual<input data-solo="pin" wire:model="actual" inputmode="numeric" type="password" maxlength="6" placeholder="••••" x-init="$nextTick(() => $el.focus())"></label>
                    <div class="two">
                        <label>PIN nuevo (4 a 6 números)<input data-solo="pin" wire:model="nuevo" inputmode="numeric" type="password" maxlength="6" placeholder="••••"></label>
                        <label>Repite el PIN nuevo<input data-solo="pin" wire:model="repite" inputmode="numeric" type="password" maxlength="6" placeholder="••••"></label>
                    </div>
                </div>
                <p class="err">{{ $error }}</p>
                <div class="actions"><button class="btn ghost big" type="button" wire:click="cerrar">Cancelar</button><button class="btn big" type="submit">Cambiar PIN</button></div>
            </form>
        </div>
    @endif
</div>
