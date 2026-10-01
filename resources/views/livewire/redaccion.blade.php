@php use App\Models\Documento; use App\Support\Dinero; use App\Support\Texto; @endphp
<section>
    <p class="cap" style="margin-top:0">Elige el documento, llena los datos y sale redactado, listo para imprimir, en PDF o en Word. Son modelos generales conforme a la legislación peruana: revísalos con el cliente antes de firmar y, en montos importantes, recomiéndale consultar con un abogado o notario.</p>
    <input class="field wide" wire:model.live.debounce.250ms="buscarModelo" placeholder="Buscar modelo: contrato, declaración, poder…" style="width:100%;margin-bottom:4px" autocomplete="off">

    @forelse ($secciones as $s)
        <h2>{{ $s['l'] }}</h2>
        <div class="mods">
            @foreach ($s['modelos'] as $m)
                <a class="mod" href="{{ route('redaccion.nuevo', $m->uid) }}" style="text-decoration:none;color:inherit" wire:key="m{{ $m->uid }}">
                    <span class="ic" style="font-size:1.3rem">{{ $m->icono }}</span>
                    <span><b>{{ $m->nombre }}</b><small>{{ $m->descripcion }}</small>
                        <small style="margin-top:4px;color:var(--ink);font-weight:600">{{ Dinero::s($precios[$m->uid] ?? $precios[$m->cobro] ?? 0) }}{{ $m->cobro === 'pagina' ? ' por página' : '' }}{{ $m->niveles ? ' · 3 niveles' : '' }}</small></span>
                </a>
            @endforeach
        </div>
    @empty
        <p class="cap">Ningún modelo coincide.</p>
    @endforelse

    <h2>Documentos hechos</h2>
    <input class="field wide" wire:model.live.debounce.250ms="buscarDoc" placeholder="Buscar por nombre, DNI o tipo de documento" style="width:100%;margin-bottom:8px" autocomplete="off">
    <div class="seg" style="margin-bottom:10px">
        @foreach (['todos' => 'Todos', 'borrador' => 'Sin cobrar ('.$cuentas['borrador'].')', 'cobrado' => 'Cobrados', 'entregado' => 'Entregados', 'encargo' => 'Encargos a Claude ('.$cuentas['encargo'].')'] as $k => $l)
            <button type="button" wire:click="$set('estado', '{{ $k }}')" aria-pressed="{{ $estado === $k ? 'true' : 'false' }}">{{ $l }}</button>
        @endforeach
    </div>
    @forelse ($docs as $r)
        @php [$c, $l] = Documento::ESTADOS[$r->estado] ?? Documento::ESTADOS['borrador']; @endphp
        <div class="sale" wire:key="d{{ $r->id }}"><span class="t">{{ $r->created_at?->format('d/m') }}<br>{{ $r->created_at?->format('H:i') }}</span>
            <span class="d"><b>{{ $r->titulo ?: ($nombres[$r->plantilla] ?? 'Documento') }}</b><br><span class="cap">{{ $r->partes }}</span><br>
                <span class="tag {{ $c }}">{{ $l }}</span>
                @if ($r->encargo === 'pendiente')<span class="tag m">Por redactar</span>@elseif ($r->encargo === 'listo')<span class="tag b">Listo para revisar</span>@endif
                @if ($r->vendedor)<span class="tag s">{{ Texto::primerNombre($r->vendedor) }}</span>@endif</span>
            <span class="ops"><a class="btn ghost sm" href="{{ route('redaccion.doc', $r->uid) }}">Abrir</a><a class="link" href="{{ route('redaccion.nuevo', ['modelo' => $r->plantilla, 'base' => $r->uid]) }}">Usar como base</a></span></div>
    @empty
        <div class="empty">{{ $buscarDoc !== '' || $estado !== 'todos' ? 'Ningún documento coincide.' : 'Aquí aparecerán los documentos que redactes, para volver a imprimirlos o usarlos como base.' }}</div>
    @endforelse
    @if ($hayMas)<button type="button" class="btn ghost" style="width:100%;margin-top:8px" wire:click="$set('ver', {{ $ver + 40 }})">Ver documentos anteriores</button>@endif
</section>
