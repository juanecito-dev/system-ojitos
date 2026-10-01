<div class="daybar">
    <button type="button" class="btn ghost sm" wire:click="irDia(-1)" aria-label="Día anterior">‹</button>
    <input type="date" wire:model.live="dia" max="{{ today()->toDateString() }}" aria-label="Día">
    <button type="button" class="btn ghost sm" wire:click="irDia(1)" @disabled($this->esHoy()) aria-label="Día siguiente">›</button>
    @unless ($this->esHoy())<button type="button" class="btn ghost sm" wire:click="hoy">Hoy</button>@endunless
    <span style="margin-left:auto"></span>{{ $extra ?? '' }}
</div>
