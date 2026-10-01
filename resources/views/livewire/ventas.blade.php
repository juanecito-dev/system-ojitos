@php use App\Support\Dinero; use App\Models\Venta; @endphp
<section>
    @include('livewire.partials.dia', ['extra' => $r['n'] ? new \Illuminate\Support\HtmlString('<a class="btn ghost sm" href="'.route('ventas.csv', $dia).'">Descargar para Excel</a>') : null])

    <div class="hero">
        <div class="card"><div class="cap">{{ $this->esHoy() ? 'Vendido hoy' : 'Vendido el '.\Carbon\Carbon::parse($dia)->format('d/m/Y') }}</div><div class="tot">{{ Dinero::s($r['total']) }}</div>
            <div class="cap">@if ($r['tasas'])Además cobraste {{ Dinero::s($r['tasas']) }} de tasas (pagalo.pe / Banco de la Nación), que no son tu ganancia. @endif{{ $r['n'] }} ventas, ticket promedio {{ Dinero::s($r['n'] ? intdiv($r['total'], $r['n']) : 0) }}</div></div>
        <div class="card methods"><div class="cap" style="margin-bottom:4px">Cómo te pagaron</div>
            @foreach ($metodos as [$n, $monto])<div><span>{{ $n }}</span><b>{{ Dinero::s($monto) }}</b></div>@endforeach
        </div>
        <a class="dcard {{ $boletasPend ? 'alert' : 'good' }}" @if (\App\Support\Acceso::modulo('comprobantes')) href="{{ route('facturacion') }}" wire:navigate @endif><span class="cap">Boletas SUNAT</span><span class="v">{{ $boletasPend }}</span>
            <span class="cap">{{ $boletasPend ? 'Por pasar a SUNAT. Toca para registrarlas.' : 'Todo emitido' }}</span></a>
    </div>

    @if ($vend)
        <h2>Por vendedor</h2>
        <div class="scroll"><table><thead><tr><th>Vendedor</th><th class="r">Ventas</th><th class="r">Total</th><th class="r">En efectivo</th></tr></thead><tbody>
            @foreach ($vend as $n => $b)<tr><td>{{ $n }}</td><td class="r">{{ $b['n'] }}</td><td class="r">{{ Dinero::s($b['tot']) }}</td><td class="r">{{ Dinero::s($b['efe']) }}</td></tr>@endforeach
        </tbody></table></div>
    @endif

    <h2>Por servicio y producto</h2>
    @if ($por)
        <div class="scroll"><table><thead><tr><th>Servicio</th><th class="r">Cant.</th><th class="r">Total</th></tr></thead><tbody>
            @foreach ($por as $n => $b)
                <tr><td class="bar-cell"><i style="width:{{ max(4, $b['sub'] / $maxRow * 100) }}%"></i><span>{{ $n }}</span></td><td class="r">{{ Venta::cant($b['cant']) }}</td><td class="r">{{ Dinero::s($b['sub']) }}</td></tr>
            @endforeach
        </tbody></table></div>
    @else
        <div class="empty">Sin ventas este día.@if ($this->esHoy()) Registra la primera desde Punto de venta.@endif</div>
    @endif

    @if ($r['n'])
        <h2>Ventas <small>{{ $r['n'] }}</small></h2>
        @foreach ($V as $v)
            <div class="sale" wire:key="v{{ $v->id }}">
                <span class="t">{{ $v->vendida_at->format('H:i') }}</span>
                <span class="d">{{ $v->resumen() }}<br>
                    @if ($v->boleta)<span class="tag b">Con boleta{{ $v->comprobante_numero ? ' '.$v->comprobante_numero : '' }}</span>@elseif ($v->faltaComprobante())<span class="tag p">Falta boleta</span>@else<span class="tag s">Va al cierre</span>@endif
                    <span class="tag m">{{ $neg->nombreMetodo($v->metodo) }}</span>@if ($v->vendedor)<span class="tag s">{{ $v->vendedor }}</span>@endif
                    @if ($v->numero)<span class="tag s">{{ $v->numero }}</span>@endif
                </span>
                <span class="ops"><span class="amt">{{ Dinero::s($v->total) }}</span>
                    <button type="button" class="link" x-on:click="copiar(@js($v->descripcionSunat()), 'Descripción copiada')">Copiar texto</button>
                    <button type="button" class="link" x-on:click="copiar(@js(Dinero::n($v->propio())), 'Importe copiado')">Copiar importe</button>
                    <button type="button" class="link" wire:click="$set('ticketUid', '{{ $v->uid }}')">Ticket</button>
                    <button type="button" class="link" wire:click="pedirAnular('{{ $v->uid }}')">Anular</button></span>
            </div>
        @endforeach
    @endif

    @if ($nAnuladas)
        <p style="margin:14px 0 0;text-align:right"><button type="button" class="link" wire:click="$toggle('verAnuladas')">{{ $verAnuladas ? 'Ocultar el historial de anuladas' : 'Ver el historial de anuladas ('.$nAnuladas.')' }}</button></p>
    @endif
    @if ($verAnuladas)
        <h2>Historial de anuladas <small>las más recientes primero</small></h2>
        @foreach ($anuladas as $a)
            <div class="sale" wire:key="an{{ $a->id }}">
                <span class="t">{{ $a->anulada_at->format('d/m') }}<br>{{ $a->anulada_at->format('H:i') }}</span>
                <span class="d">{{ $a->detalle }}<br>
                    <span class="tag {{ $a->tipo === 'deshecha' ? 'p' : 'r' }}">{{ $a->tipo === 'deshecha' ? 'Deshecha al momento' : 'Anulada' }}</span>
                    @if ($a->venta_at)<span class="tag s">Vendida el {{ $a->venta_at->format('d/m H:i') }}</span>@endif
                    @if ($a->vendedor_original)<span class="tag s">{{ $a->vendedor_original }}</span>@endif
                    @if ($a->numero)<span class="tag s">{{ $a->numero }}</span>@endif
                    @if ($a->por)<span class="tag m">Anuló {{ $a->por }}</span>@endif
                    @if ($a->motivo)<br><span class="cap">Motivo: {{ $a->motivo }}</span>@endif
                </span>
                <span class="ops"><span class="amt">{{ Dinero::s($a->total) }}</span></span>
            </div>
        @endforeach
    @endif

    @if ($ventaAnular)
        <div class="dlg" role="alertdialog" aria-modal="true" x-data x-on:keydown.escape.window="$wire.cancelarAnular()">
            <form wire:submit="anular">
                <p>¿Anular la venta de {{ Dinero::s($ventaAnular->total) }} de las {{ $ventaAnular->vendida_at->format('H:i') }}?
                    @if ($cpeAnular && ($cpeAnular->cierre_de || count($cpeAnular->ventas ?? []) > 1))

Está incluida en la boleta de cierre {{ $cpeAnular->etiqueta() }} ({{ Dinero::s($cpeAnular->total) }}). Ese monto ya no coincidirá: registra una nota de crédito por {{ Dinero::s($ventaAnular->propio()) }} en Facturación.
                    @elseif ($cpeAnular)

Tiene la {{ mb_strtolower(\App\Support\Catalogos::TIPOS_COMPROBANTE[$cpeAnular->tipo] ?? 'boleta') }} {{ $cpeAnular->etiqueta() }}: quedará anulada en tu registro. Recuerda anularla también en el portal de SUNAT.
                    @endif
                    @if ($ventaAnular->origen_tipo === 'pedido')

Es un pago de un pedido: su saldo se ajustará.
                    @endif

¿Por qué se anula? (queda anotado en Reportes)</p>
                <input class="field wide" wire:model="motivo" style="width:100%;margin-bottom:14px" x-init="$nextTick(() => $el.focus())">
                <div class="actions"><button type="button" class="btn ghost big" wire:click="cancelarAnular">Cancelar</button><button type="submit" class="btn big warn">Anular venta</button></div>
            </form>
        </div>
    @endif

    @if ($ticket)
        <div class="scrim on" wire:click="$set('ticketUid', null)"></div>
        <div class="sheet on" role="dialog" aria-modal="true">
            <h3>Ticket</h3>
            <p class="hint">Descárgalo en PDF para imprimirlo, o como imagen para mandarlo por WhatsApp.</p>
            @include('pdf.ticket-html', ['v' => $ticket, 'neg' => $neg])
            <div class="actions" style="margin-bottom:10px">
                <a class="btn big" href="{{ route('ticket.pdf', $ticket->uid) }}" target="_blank">Descargar PDF</a>
                <button type="button" class="btn ghost big" x-on:click="ticketImagen(@js(route('ticket.filas', $ticket->uid)), @js('ticket-'.$ticket->numeroTicket().'.png'))">Guardar imagen</button>
            </div>
            <div style="text-align:center;margin-top:8px"><button type="button" class="link" wire:click="$set('ticketUid', null)">Cerrar</button></div>
        </div>
    @endif

    @include('livewire.partials.autorizacion')
</section>
