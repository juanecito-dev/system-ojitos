<div>
    @if (! $negocioId)
        <h2>Iniciar sesión</h2>
        <p class="lg-hi">{{ $saludo }}. ¿En qué negocio trabajas?</p>
        <form class="set lg-form" wire:submit="elegirNegocio" novalidate>
            <label>Código del negocio<input wire:model="codigo" autocapitalize="none" spellcheck="false" placeholder="Ej.: ojitos" autofocus></label>
            <p class="cap lg-err" role="alert">{{ $error }}</p>
            <button class="btn big" type="submit">Continuar</button>
        </form>
        <p class="lg-kb">Este equipo lo recordará. Si no sabes el código, pídeselo al administrador.</p>
    @else
        <h2>Iniciar sesión</h2>
        <p class="lg-hi">{{ $saludo }}. Ingresa con tu rol, tu usuario y tu PIN.</p>
        <form class="set lg-form {{ $error ? 'shake' : '' }}" wire:submit="ingresar" novalidate x-data="{ ver: false }" wire:key="f-{{ md5($error) }}">
            <label>Rol<select wire:model="rol">
                @foreach ($listaRoles as $r)<option value="{{ $r->uid }}">{{ $r->nombre }}</option>@endforeach
            </select></label>
            <label>Usuario<input wire:model="usuario" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="Ej.: alex" @if (! $usuario) autofocus @endif></label>
            <label>PIN<span class="lg-pin"><input data-solo="pin" wire:model="pin" name="password" :type="ver ? 'text' : 'password'" inputmode="numeric" autocomplete="current-password" maxlength="6" placeholder="••••" @if ($usuario) autofocus @endif
                x-on:input="$el.value = $el.value.replace(/\D/g, '').slice(0, 6)"><button type="button" class="lg-eye" x-on:click="ver = !ver" :aria-pressed="ver" x-text="ver ? 'Ocultar' : 'Ver'">Ver</button></span></label>
            <label class="check lg-rec"><input type="checkbox" wire:model="recordar"> Recordar mi usuario en este equipo</label>
            <p class="cap lg-err" role="alert">{{ $error }}</p>
            <button class="btn big" type="submit" wire:loading.attr="disabled"><span wire:loading.remove wire:target="ingresar">Ingresar</span><span wire:loading wire:target="ingresar">Verificando…</span></button>
        </form>
        <p class="lg-kb">¿Olvidaste tu PIN? Pide a un administrador que te asigne uno nuevo en Usuarios y roles.</p>
    @endif
</div>
