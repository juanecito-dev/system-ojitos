{{-- El documento como hoja (blocksHTML del sistema anterior). $bloques de App\Redaccion\Formato; $editable pone contenteditable. --}}
@php
    $seg = function ($segs) {
        return collect($segs)->map(function ($s) {
            $h = str_replace('[COMPLETAR]', '<mark>[COMPLETAR]</mark>', e($s[0]));
            return ! empty($s[1]) ? '<b>'.$h.'</b>' : $h;
        })->join('');
    };
    $ed = ! empty($editable) ? ' contenteditable="true"' : '';
@endphp
@foreach ($bloques as $i => $b)
    @if ($b['k'] === 'title')
        <h1 data-k="title" data-i="{{ $i }}"{!! $ed !!}>{{ $b['t'] }}</h1>
    @elseif ($b['k'] === 'sign')
        <div data-k="sign" data-f="{{ json_encode($b['f'], JSON_UNESCAPED_UNICODE) }}" class="firmas{{ count($b['f']) === 1 ? ' uno' : '' }}">
            @foreach ($b['f'] as $f)
                <div class="firma">@if (empty($f['nh']))<div class="huella">Huella</div>@endif<div class="linea"></div><b>{{ $f['n'] }}</b><br>DNI N° {{ $f['d'] }}<br><span>{{ $f['r'] }}</span></div>
            @endforeach
        </div>
    @else
        <p data-k="{{ $b['k'] }}" data-i="{{ $i }}"{!! $ed !!}>{!! $seg($b['s']) !!}</p>
    @endif
@endforeach
