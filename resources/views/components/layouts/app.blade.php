@php
    use App\Support\Acceso;
    use App\Support\NegocioActual;
    $neg = app(NegocioActual::class)->get();
    $yo = auth()->user();
    $menu = Acceso::menu();
    $seccion = '';
    $avisos = app(\App\Support\Avisos::class)->menu();
    $moduloActual = collect($menu)->keys()->first(fn ($k) => request()->routeIs($menu[$k]['ruta'].'*'));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($title ?? 'Inicio').' · '.($neg->nombre ?? 'Ojitos') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@75..100,400..800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/ojitos.css') }}?v={{ filemtime(public_path('css/ojitos.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/extra.css') }}?v={{ filemtime(public_path('css/extra.css')) }}">
    @livewireStyles
</head>
<body class="m-{{ $moduloActual ?? 'inicio' }}" x-data="ojitosApp({{ (int) ($neg?->ajuste('autoLock') ?: 0) }})" x-on:toast.window="avisar($event.detail)"
      x-on:keydown.window="teclas($event)" x-on:pointerdown.window="actividad()" x-on:keydown.window.capture="actividad()">
<aside class="side" id="side" :class="{ open: menu }" aria-label="Módulos">
    <div class="strip" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
    <div class="brandbox"><div class="brand">{{ $neg->nombre ?? 'Ojitos' }}</div><div class="who">{{ $neg->giro ?? '' }}</div></div>
    <nav class="nav" id="nav">
        @foreach ($menu as $k => $m)
            @if ($m['seccion'] !== $seccion)
                <div class="sec">{{ $m['seccion'] }}</div>
                @php $seccion = $m['seccion']; @endphp
            @endif
            <a href="{{ route($m['ruta']) }}" class="navlink" @if ($moduloActual === $k) aria-current="page" @endif>
                <x-icono :name="$k" /><span>{{ $m['titulo'] }}</span>
                @if (! empty($avisos[$k]))<span class="cnt">{{ $avisos[$k] }}</span>@endif
            </a>
        @endforeach
    </nav>
    <div class="foot">
        <div class="me">
            <span class="av {{ $yo->colorAvatar() }}">{{ $yo->inicial() }}</span>
            <span style="flex:1;min-width:0" title="{{ $yo->nombre }}"><b>{{ $yo->nombre }}</b><small>{{ $yo->rol->nombre }}</small></span>
        </div>
        <div class="me-acc">
            <button class="btn ghost sm" type="button" title="Cambiar mi PIN" x-on:click="Livewire.dispatch('abrirMiPin'); menu = false">Mi PIN</button>
            <form method="POST" action="{{ route('salir') }}" id="form-salir">@csrf<button class="btn ghost sm" type="submit">Cambiar usuario</button></form>
        </div>
        <span class="sync">Guardado en el servidor</span>
    </div>
</aside>
<div class="dscrim" :class="{ on: menu }" x-on:click="menu = false"></div>
<div class="content">
    <header class="mtop"><div class="in">
        <button class="iconbtn" type="button" aria-label="Abrir menú" x-on:click="menu = true"><x-icono name="menu" /></button>
        <h1>{{ $title ?? 'Inicio' }}</h1>
        @if ($moduloActual !== 'vender' && isset($menu['vender']))
            <a class="btn sm" href="{{ route('vender') }}">Vender</a>
        @endif
    </div></header>
    <main>
        {{ $slot }}
    </main>
</div>

<livewire:mi-pin />

<div class="toast" :class="{ on: aviso.on }" role="status">
    <span x-text="aviso.texto"></span>
    <button type="button" x-show="aviso.accion" x-text="aviso.accion && aviso.accion.label" x-on:click="accionAviso()"></button>
</div>

<script>window.OJITOS_VENDER = @js(isset($menu['vender']) ? route('vender') : null);</script>
<script src="{{ asset('js/campos.js') }}?v={{ filemtime(public_path('js/campos.js')) }}"></script>
<script src="{{ asset('js/ojitos.js') }}?v={{ filemtime(public_path('js/ojitos.js')) }}"></script>
<script src="{{ asset('js/pos.js') }}?v={{ filemtime(public_path('js/pos.js')) }}"></script>
<script src="{{ asset('js/ticket.js') }}?v={{ filemtime(public_path('js/ticket.js')) }}"></script>
@livewireScripts
@if (session('toast'))
    <script>document.addEventListener('livewire:initialized', () => window.dispatchEvent(new CustomEvent('toast', {detail: {texto: @js(session('toast'))}})), {once: true});</script>
@endif
</body>
</html>
