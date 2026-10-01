@php use App\Services\Stock; use App\Support\Dinero; @endphp
<table class="tab">
    <thead><tr><th>Producto</th><th class="r" style="width:22mm">Stock</th><th style="width:20mm">Unidad</th><th class="r" style="width:26mm">Costo unit.</th><th class="r" style="width:28mm">Valor</th></tr></thead>
    <tbody>
    @foreach ($grupos as $g => $filas)
        <tr><td colspan="4" style="background:#e9edf1;font-weight:bold">{{ $g }}</td><td class="r" style="background:#e9edf1;font-weight:bold">{{ Dinero::n($filas->sum('valor')) }}</td></tr>
        @foreach ($filas as $x)
            <tr><td>{{ $x['p']->nombre }}</td><td class="r">{{ Stock::formato($x['n']) }}</td><td>{{ $x['p']->unidad ?: 'unid.' }}</td>
                <td class="r">{{ $x['p']->costo ? Dinero::nCosto($x['p']->costo) : '—' }}</td><td class="r">{{ Dinero::n($x['valor']) }}</td></tr>
        @endforeach
    @endforeach
    </tbody>
</table>
<table class="tot"><tr class="grand"><td>TOTAL</td><td class="r">{{ Dinero::s($total) }}</td></tr></table>
@if ($sinCosto)
    <p class="small" style="margin-top:5mm">{{ $sinCosto }} {{ $sinCosto === 1 ? 'producto no tiene' : 'productos no tienen' }} costo registrado y figuran con valor 0. Regístralo en Inventario (ficha del producto) o con una compra.</p>
@endif
