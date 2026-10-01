@php
    $neg = app(\App\Support\NegocioActual::class)->get();
    $equipo = request()->cookie('equipo');
    $fecha = \Illuminate\Support\Str::ucfirst(now()->translatedFormat('l j \d\e F'));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Iniciar sesión' }} · {{ $neg->nombre ?? 'Ojitos' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@75..100,400..800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/ojitos.css') }}?v={{ filemtime(public_path('css/ojitos.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/extra.css') }}?v={{ filemtime(public_path('css/extra.css')) }}">
    @livewireStyles
</head>
<body>
<div class="login" id="login">
    <aside class="lg-brand">
        <span class="lg-dots c" aria-hidden="true"></span><span class="lg-dots m" aria-hidden="true"></span><span class="lg-dots y" aria-hidden="true"></span>
        <div class="lg-top">
            @if ($neg?->logo)<img class="lg-logo" src="{{ $neg->logo }}" alt="">@endif
            <p class="lg-name">{{ $neg->nombre ?? 'Ojitos' }}</p>
            @if ($neg?->giro)<p class="lg-giro">{{ $neg->giro }}</p>@endif
            <div class="lg-strip" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
        </div>
        <div x-data="{ h: '' }" x-init="const f = () => h = new Date().toLocaleTimeString('es-PE', {hour: 'numeric', minute: '2-digit'}); f(); setInterval(f, 15000)">
            <div class="lg-time" x-text="h"></div>
            <div class="lg-date">{{ $fecha }}</div>
        </div>
        <div class="lg-foot">@if ($neg?->ciudad){{ $neg->ciudad }} ·@endif @if ($equipo)<b>{{ $equipo }}</b> ·@endif Guardado en el servidor</div>
        <svg class="lg-reg" viewBox="0 0 26 26" aria-hidden="true"><circle cx="13" cy="13" r="7" fill="none" stroke="#F1F3F5" stroke-width="1.5"/><path d="M13 0v26M0 13h26" stroke="#F1F3F5" stroke-width="1.5"/></svg>
    </aside>
    <div class="lg-panel"><div class="box">{{ $slot }}</div></div>
</div>
<script src="{{ asset('js/campos.js') }}?v={{ filemtime(public_path('js/campos.js')) }}"></script>
@livewireScripts
</body>
</html>
