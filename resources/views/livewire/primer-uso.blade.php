<div>
    <h2>Bienvenido</h2>
    <p class="lg-hi">Es la primera vez que se abre el sistema en este servidor.</p>
    <div class="seg" style="margin-top:14px">
        <button type="button" wire:click="$set('modo', 'copia')" aria-pressed="{{ $modo === 'copia' ? 'true' : 'false' }}">Traer mi copia</button>
        <button type="button" wire:click="$set('modo', 'nuevo')" aria-pressed="{{ $modo === 'nuevo' ? 'true' : 'false' }}">Empezar de cero</button>
    </div>
    @if ($modo === 'copia')
        <form class="set lg-form" wire:submit="importar">
            <p class="cap" style="margin:0 0 10px">En el sistema actual ve a <b>Configuración › Datos y copias › Descargar copia</b> y elige aquí ese archivo. Se traen ventas, caja, clientes, productos, usuarios y todo lo demás. Entrarás con tu mismo usuario y PIN.</p>
            <label>Archivo de la copia<input type="file" wire:model="archivo" accept=".json,application/json"></label>
            <label>Código del negocio (opcional)<input wire:model="codigo" autocapitalize="none" placeholder="Ej.: ojitos"></label>
            <p class="cap lg-err" role="alert">{{ $error }}</p>
            <button class="btn big" type="submit" wire:loading.attr="disabled"><span wire:loading.remove wire:target="importar,archivo">Importar y continuar</span><span wire:loading wire:target="importar,archivo">Trabajando…</span></button>
            @if ($log)<ul class="importlog">@foreach ($log as $l)<li>{{ $l }}</li>@endforeach</ul>@endif
        </form>
    @else
        <form class="set lg-form" wire:submit="crear">
            <div class="two"><label>Nombre del negocio<input wire:model="negocio" placeholder="Ej.: Ojitos"></label><label>Código (opcional)<input wire:model="codigo" autocapitalize="none" placeholder="Ej.: ojitos"></label></div>
            <div class="two"><label>Tu nombre<input wire:model="nombre"></label><label>Usuario para entrar<input wire:model="usuario" autocapitalize="none" spellcheck="false" placeholder="Ej.: alex"></label></div>
            <label>Tipo de negocio<select wire:model="rubro">@foreach (\App\Support\Catalogos::RUBROS as $k => [$n]) <option value="{{ $k }}">{{ $n }}</option> @endforeach</select></label>
            <div class="two"><label>PIN de 4 a 6 números<input data-solo="pin" wire:model="pin" inputmode="numeric" type="password" maxlength="6" placeholder="••••"></label><label>Repite el PIN<input data-solo="pin" wire:model="pin2" inputmode="numeric" type="password" maxlength="6" placeholder="••••"></label></div>
            <p class="cap lg-err" role="alert">{{ $error }}</p>
            <button class="btn big" type="submit">Crear y entrar</button>
        </form>
    @endif
</div>
