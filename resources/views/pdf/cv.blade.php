{{-- Currículum en PDF A4: el mismo diseño de la pantalla (redaccion/cv). El moderno va sin márgenes, con su banda de color. --}}
<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title>Currículum vitae</title>
<style>
    @if ($diseno === 'cv_moderno')
    @page { margin: 14mm 0 12mm 0; }
    @page :first { margin-top: 0; }
    @else
    @page { margin: 16mm 18mm; }
    @endif
    body { margin: 0; }
    mark { background: #ffe58a; }
</style></head>
<body>
@if ($diseno === 'cv_moderno')
    @php $T = App\Redaccion\Curriculum::temaDe($d); @endphp
    {{-- el fondo de la columna lateral, en todas las hojas --}}
    <div style="position:fixed;top:-20mm;bottom:-20mm;{{ $T['lado'] === 'der' ? 'right' : 'left' }}:0;width:86mm;z-index:-1;background:{{ App\Redaccion\Curriculum::colores($T['color'])['s'] }}"></div>
@endif
@include('redaccion.cv', ['d' => $d, 'diseno' => $diseno, 'pdf' => true])
</body></html>
