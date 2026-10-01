@php use App\Support\Catalogos; use App\Support\Dinero; use App\Support\Texto;
    $hace = function ($t) { if (! $t) return ''; $d = (int) $t->copy()->startOfDay()->diffInDays(today()); return $d <= 0 ? 'hoy' : ($d === 1 ? 'ayer' : 'hace '.$d.' días'); };
@endphp
<section>
    <p class="cap">Cada persona entra con su rol, su usuario y su PIN. Todo lo que haga (ventas, tickets, gastos, encargos, fiados) queda con su nombre. Si alguien no tiene un permiso, tú puedes autorizarlo en el momento poniendo tu PIN en su pantalla.</p>

    <h2>Usuarios <small>{{ $usuarios->where('activo', true)->count() }} activos</small></h2>
    <div class="stack" style="margin-bottom:10px">
        @foreach ($usuarios as $x)
            <div class="inv" style="{{ $x->activo ? '' : 'opacity:.6' }}" wire:key="u{{ $x->id }}">
                <span class="av" style="width:38px;height:38px;border-radius:50%;background:var(--ink);color:var(--paper);display:flex;align-items:center;justify-content:center;font-weight:800;flex:none">{{ $x->inicial() }}</span>
                <span class="nm">{{ $x->nombre }}{{ $x->id === auth()->id() ? ' (tú)' : '' }}@unless ($x->activo) <span class="tag s">Desactivado</span>@endunless @if ($acceso->bloqueo($x)) <span class="tag r">PIN bloqueado</span>@endif
                    <small>{{ $x->rol->nombre }} · usuario «{{ $x->usuario }}»{{ $x->ultimo_ingreso_at ? ' · entró '.$hace($x->ultimo_ingreso_at) : '' }}@if ($x->pin_legado) · PIN del sistema anterior @endif</small></span>
                @if ($x->activo)
                    <button type="button" class="btn ghost sm" wire:click="editarUsuario({{ $x->id }})">Editar</button>
                    @if ($x->id !== auth()->id())<button type="button" class="link" wire:click="pedirDesactivar({{ $x->id }})">Desactivar</button>@endif
                @else
                    <button type="button" class="btn ghost sm" wire:click="activar({{ $x->id }})">Activar</button>
                @endif
            </div>
        @endforeach
    </div>
    <div class="actions"><button type="button" class="btn" wire:click="nuevoUsuario">Crear usuario</button></div>

    <h2>Roles <small>qué puede hacer cada tipo de usuario</small></h2>
    <div class="stack" style="margin-bottom:10px">
        @foreach ($roles as $rol)
            @php
                $claves = $rol->claves();
                $mods = collect(Catalogos::PERMISOS_MODULO)->only($claves)->map(fn ($n) => explode(' (', $n)[0]);
                $acc = collect(Catalogos::PERMISOS_ACCION)->only($claves)->map(fn ($n) => mb_strtolower(mb_substr($n, 0, 1)).mb_substr($n, 1));
                $tope = collect([$rol->desc_max ? Dinero::s($rol->desc_max) : '', $rol->desc_pct ? $rol->desc_pct.'%' : ''])->filter()->join(' o ');
            @endphp
            <div class="card" wire:key="r{{ $rol->id }}">
                <div style="display:flex;justify-content:space-between;gap:10px;align-items:baseline"><b>{{ $rol->nombre }}</b><span class="cap">{{ $rol->usuarios_count }} {{ $rol->usuarios_count === 1 ? 'usuario' : 'usuarios' }}</span></div>
                <p class="cap" style="margin:6px 0">
                    @if ($rol->es_admin) Puede hacer todo, incluso crear usuarios y roles, y autorizar a los demás.
                    @else Entra a: {{ $mods->join(', ') ?: 'solo Inicio' }}. {{ $acc->isNotEmpty() ? 'Puede '.$acc->join(', ').'.' : 'Todo lo demás lo hace con autorización.' }}@if ($tope) Descuento máximo sin autorización: {{ $tope }}.@endif
                    @endif
                </p>
                @unless ($rol->es_admin)
                    <div class="actions"><button type="button" class="btn ghost sm" wire:click="editarRol({{ $rol->id }})">Editar permisos</button>@unless ($rol->usuarios_count)<button type="button" class="link" wire:click="eliminarRol({{ $rol->id }})" wire:confirm="¿Eliminar el rol {{ $rol->nombre }}?">Eliminar</button>@endunless</div>
                @endunless
            </div>
        @endforeach
    </div>
    <div class="actions"><button type="button" class="btn ghost" wire:click="nuevoRol">Crear rol</button></div>

    <h2>Actividad <small>ingresos, cambios, autorizaciones y anulaciones</small></h2>
    <div class="daybar" style="flex-wrap:wrap;gap:8px">
        <select class="field" wire:model.live="actDias">@foreach ([1 => 'Hoy', 7 => 'Últimos 7 días', 30 => 'Últimos 30 días'] as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
        <select class="field" wire:model.live="actUsuario"><option value="">Todos los usuarios</option>@foreach ($usuarios as $x)<option value="{{ $x->id }}">{{ $x->nombre }}</option>@endforeach</select>
        <select class="field" wire:model.live="actTipo"><option value="">Todo</option>@foreach (Catalogos::TIPOS_ACTIVIDAD as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
    </div>
    @forelse ($act as $a)
        <div class="sale"><span class="t">{{ $a['t']->format('d/m') }}<br>{{ $a['t']->format('H:i') }}</span>
            <span class="d">{{ $a['det'] }}<br><span class="tag {{ in_array($a['tipo'], ['anula', 'bloqueo'], true) ? 'r' : ($a['tipo'] === 'autoriza' ? 'p' : 's') }}">{{ Catalogos::TIPOS_ACTIVIDAD[$a['tipo']] ?? $a['tipo'] }}</span>@if ($a['vend'])<span class="tag m">{{ Texto::primerNombre($a['vend']) }}</span>@endif @if ($a['eq'])<span class="tag s">{{ $a['eq'] }}</span>@endif</span></div>
    @empty
        <p class="cap">No hay actividad registrada en este periodo.</p>
    @endforelse

    {{-- formulario de usuario --}}
    @if ($editUsuario !== null)
        <div class="scrim on" wire:click="cerrar"></div>
        <div class="sheet on" role="dialog" aria-modal="true">
            <form wire:submit="guardarUsuario">
                <h3>{{ $editado ? 'Editar a '.$editado->nombre : 'Crear usuario' }}</h3>
                <div class="set">
                    <div class="two"><label>Nombre<input wire:model="u.nombre" placeholder="Ej.: Carlos Ríos"></label><label>Usuario para entrar<input wire:model="u.usuario" autocapitalize="none" spellcheck="false" placeholder="Ej.: carlos"></label></div>
                    <label>Rol<select wire:model="u.rol" @disabled($ultimoAdmin)>@foreach ($roles as $rol)<option value="{{ $rol->id }}">{{ $rol->nombre }}</option>@endforeach</select></label>
                    @if ($ultimoAdmin)<p class="cap" style="margin:-4px 0 10px">Es el único administrador, por eso no se puede cambiar su rol.</p>@endif
                    <div class="two"><label>{{ $editado ? 'PIN nuevo (déjalo vacío para no cambiarlo)' : 'PIN de 4 a 6 números' }}<input data-solo="pin" wire:model="u.pin" inputmode="numeric" type="password" maxlength="6" placeholder="••••"></label><label>Repite el PIN<input data-solo="pin" wire:model="u.pin2" inputmode="numeric" type="password" maxlength="6" placeholder="••••"></label></div>
                    @if ($editado && $acceso->bloqueo($editado))<label class="check" style="flex-direction:row;color:var(--ink)"><input type="checkbox" wire:model="u.desbloquear"> Desbloquear su PIN (está bloqueado por intentos fallidos)</label>@endif
                </div>
                <p class="err">{{ $error }}</p>
                <div class="actions"><button type="button" class="btn ghost big" wire:click="cerrar">Cancelar</button><button class="btn big" type="submit">{{ $editado ? 'Guardar cambios' : 'Crear usuario' }}</button></div>
            </form>
        </div>
    @endif

    {{-- formulario de rol --}}
    @if ($editRol !== null)
        <div class="scrim on" wire:click="cerrar"></div>
        <div class="sheet on" role="dialog" aria-modal="true">
            <form wire:submit="guardarRol">
                <h3>{{ $editRol ? 'Permisos de '.$r['nombre'] : 'Crear rol' }}</h3>
                <div class="set"><label>Nombre del rol<input wire:model="r.nombre" placeholder="Ej.: Cajero, Practicante, Encargado"></label></div>
                <p class="cap" style="margin:4px 0 8px"><b>Módulos que puede abrir</b> (Inicio siempre está)</p>
                <div class="permgrid">@foreach (Catalogos::PERMISOS_MODULO as $k => $l)<label class="check" style="margin:0 0 8px;font-weight:500"><input type="checkbox" wire:model="r.permisos" value="{{ $k }}"> {{ $l }}</label>@endforeach</div>
                <p class="cap" style="margin:14px 0 8px"><b>Acciones permitidas</b>. Lo que no marques lo puede hacer con la autorización de un administrador.</p>
                <div class="permgrid">@foreach (Catalogos::PERMISOS_ACCION as $k => $l)<label class="check" style="margin:0 0 8px;font-weight:500"><input type="checkbox" wire:model="r.permisos" value="{{ $k }}"> {{ $l }}</label>@endforeach</div>
                <p class="cap" style="margin:14px 0 8px"><b>Tope de descuento sin autorización</b> (déjalo vacío para no poner tope)</p>
                <div class="set two"><label>Hasta S/<input data-solo="monto" wire:model="r.descMax" inputmode="decimal" placeholder="Ej.: 2.00"></label><label>o hasta %<input data-solo="entero" wire:model="r.descPct" inputmode="numeric" placeholder="Ej.: 10"></label></div>
                <p class="err">{{ $error }}</p>
                <div class="actions"><button type="button" class="btn ghost big" wire:click="cerrar">Cancelar</button><button class="btn big" type="submit">Guardar rol</button></div>
            </form>
        </div>
    @endif

    @if ($porDesactivar)
        <div class="dlg" role="alertdialog" aria-modal="true"><div>
            <p>¿Desactivar a {{ $porDesactivar->nombre }}? Ya no podrá entrar. Sus ventas y su historial se conservan, y puedes volver a activarlo cuando quieras.</p>
            <div class="actions"><button type="button" class="btn ghost big" wire:click="cerrar">Cancelar</button><button type="button" class="btn big warn" wire:click="desactivar">Desactivar</button></div>
        </div></div>
    @endif
</section>
