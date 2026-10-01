@php use App\Support\Dinero; use App\Support\Texto; use App\Support\Catalogos; $tipoTxt = ['gasto' => 'Gasto', 'ingreso' => 'Ingreso', 'retiro' => 'Retiro']; @endphp
<section>
    @include('livewire.partials.dia')

    @if ($this->esHoy() && ! $abierto)
        <div class="card" style="margin-bottom:14px"><b>Abrir mi caja</b>
            <p class="cap" style="margin:4px 0 10px">{{ $mios->count() ? 'Tu turno anterior está cerrado. Abre uno nuevo si sigues atendiendo.' : '¿Con cuánto sencillo empiezas? Cada persona cuadra su propia caja.' }}</p>
            <form class="row" style="margin:0" wire:submit="abrirCaja">
                <input data-solo="monto" class="field" wire:model="inicial" inputmode="decimal" placeholder="0.00">
                <div class="quick">@foreach ([0, 20, 50, 100] as $q)<button type="button" wire:click="$set('inicial', '{{ Dinero::n($q * 100) }}')">{{ $q === 0 ? 'Sin sencillo' : $q }}</button>@endforeach</div>
                <button class="btn" type="submit">Abrir caja</button>
            </form>
        </div>
    @endif

    @if ($verFuera)
        <div class="note warn">Hay {{ Dinero::s($fuera['total']) }} en efectivo registrados sin caja abierta ({{ implode(', ', $fuera['quien']) }}). Abre la caja antes de vender para que cuadre.</div>
    @endif

    @if ($admin && $caja->turnos->count() > 1)
        <div class="card" style="margin-bottom:12px;background:var(--paper)">
            <div style="display:flex;justify-content:space-between"><b>Todas las cajas {{ $this->esHoy() ? 'de hoy' : 'del día' }}</b><b>{{ Dinero::s($caja->turnos->sum(fn ($t) => $caja->turno($t)['esperado'])) }}</b></div>
            <p class="cap" style="margin:4px 0 0">{{ $caja->turnos->whereNull('cierra_at')->count() }} abiertas, {{ $caja->turnos->whereNotNull('cierra_at')->count() }} cerradas. Efectivo que debería haber en total.</p>
        </div>
    @endif

    <div class="turnos">
        @foreach ($turnos as $t)
            @php $c = $caja->turno($t); $mine = $t->usuario_id === auth()->id(); $diff = $t->cierra_at ? $t->contado - $c['esperado'] : null; @endphp
            <div class="card turno {{ $t->cierra_at ? 'cerrado' : '' }}" wire:key="t{{ $t->id }}">
                <div style="display:flex;justify-content:space-between;gap:10px;align-items:baseline"><b>Caja de {{ Texto::primerNombre($t->vendedor) }}{{ $mine ? ' (tú)' : '' }}</b>
                    <span class="tag {{ $t->cierra_at ? 's' : 'b' }}">{{ $t->cierra_at ? 'Cerrada '.$t->cierra_at->format('H:i') : 'Abierta desde '.$t->abre_at->format('H:i') }}</span></div>
                <div class="ledger" style="margin-top:8px">
                    <div><span>Sencillo inicial</span><b>{{ Dinero::s($t->inicial) }}</b></div>
                    <div><span>Ventas en efectivo ({{ $c['n'] }})</span><b>+ {{ Dinero::n($c['ventas']) }}</b></div>
                    @if ($c['ing'])<div><span>Otros ingresos</span><b>+ {{ Dinero::n($c['ing']) }}</b></div>@endif
                    @if ($c['gas'])<div><span>Gastos</span><b>− {{ Dinero::n($c['gas']) }}</b></div>@endif
                    @if ($c['ret'])<div><span>Retiros</span><b>− {{ Dinero::n($c['ret']) }}</b></div>@endif
                    <div class="tot"><span>Debe haber</span><span>{{ Dinero::s($c['esperado']) }}</span></div>
                </div>
                @if ($t->cierra_at)
                    <div class="diff {{ $diff === 0 ? 'ok' : 'bad' }}" style="margin-top:8px">{{ $diff === 0 ? 'Cuadró exacto' : ($diff > 0 ? 'Sobraron '.Dinero::s($diff) : 'Faltaron '.Dinero::s(-$diff)) }}</div>
                    <div class="cap">Contó {{ Dinero::s($t->contado) }}@if ($t->cerro_por && $t->cerro_por !== $t->vendedor) · cerró {{ $t->cerro_por }}@endif @if (count($t->correcciones ?? [])) · sencillo corregido {{ count($t->correcciones) }} {{ count($t->correcciones) === 1 ? 'vez' : 'veces' }}@endif</div>
                @elseif (($mine || $admin) && $this->esHoy())
                    <div class="row" style="margin:10px 0 0"><input data-solo="monto" class="field" wire:model="contado.{{ $t->id }}" inputmode="decimal" placeholder="Efectivo contado"><button type="button" class="btn" wire:click="pedirCierre({{ $t->id }})">Cerrar caja</button><button type="button" class="link" wire:click="pedirCorreccion({{ $t->id }})">Corregir sencillo</button></div>
                @endif
            </div>
        @endforeach
    </div>
    @if ($turnos->isEmpty() && ! $legado)
        <div class="empty">{{ $this->esHoy() ? 'Aún no has abierto tu caja hoy.' : 'No hubo cajas abiertas este día.' }}</div>
    @endif
    @if ($legado && $admin)
        <div class="card" style="margin-top:10px"><b>Caja general</b> <span class="cap">(forma anterior, un solo cuadre para todos)</span>
            <div class="ledger" style="margin-top:8px"><div><span>Sencillo inicial</span><b>{{ Dinero::s($caja->dia->caja_inicial ?? 0) }}</b></div><div class="tot"><span>Debe haber</span><span>{{ Dinero::s($caja->legado()) }}</span></div></div>
            @if ($caja->dia->caja_arqueo)<div class="cap">Contado {{ Dinero::s($caja->dia->caja_arqueo['contado'] ?? 0) }}</div>@endif
        </div>
    @endif

    <livewire:caja-contadores :dia="$dia" :key="'cont-'.$dia" />

    @if ($admin && $resumen)
        <div class="actions" style="margin:12px 0 0"><a class="btn ghost" href="https://wa.me/?text={{ rawurlencode($resumen) }}" target="_blank" rel="noopener">Mandarme el resumen del día por WhatsApp</a></div>
        <p class="cap" style="margin:4px 0 0">Se abre tu WhatsApp con el resumen escrito (ventas, gastos, cuadre de cada caja, pendientes de SUNAT y copias). Eliges a quién mandarlo, por ejemplo a ti mismo.</p>
    @endif

    <div class="actions" style="margin:16px 0 4px">
        <button type="button" class="btn" wire:click="abrirMovimiento('gasto')">Registrar gasto</button>
        <button type="button" class="btn ghost" wire:click="abrirMovimiento('retiro')">Retiro de caja</button>
        <button type="button" class="btn ghost" wire:click="abrirMovimiento('ingreso')">Otro ingreso</button>
    </div>
    <h2>Movimientos <small>@if ($movs->count())gastos {{ Dinero::s($movs->where('tipo', 'gasto')->sum('monto')) }}@endif</small></h2>
    @forelse ($movs as $m)
        <div class="sale" wire:key="m{{ $m->id }}"><span class="t">{{ $m->ocurrido_at->format('H:i') }}</span>
            <span class="d"><b>{{ $m->concepto }}</b>@if ($m->nota) {{ $m->nota }}@endif<br><span class="tag {{ $m->tipo === 'gasto' ? 'r' : ($m->tipo === 'retiro' ? 'p' : 'b') }}">{{ $tipoTxt[$m->tipo] ?? $m->tipo }}</span><span class="tag m">{{ $neg->nombreMetodo($m->metodo) }}</span>@if ($m->vendedor)<span class="tag s">{{ $m->vendedor }}</span>@endif</span>
            <span class="ops"><span class="amt">{{ $m->tipo === 'ingreso' ? '+' : '−' }} {{ Dinero::n($m->monto) }}</span><button type="button" class="link" wire:click="borrarMovimiento({{ $m->id }})">Borrar</button></span></div>
    @empty
        <div class="empty">Sin movimientos. Registra aquí lo que compras para el negocio (papel, tinta, útiles) para saber tu ganancia real.</div>
    @endforelse

    {{-- ventana: gasto, retiro u otro ingreso --}}
    @if ($mov)
        <div class="scrim on" wire:click="cerrarVentana"></div>
        <div class="sheet on" role="dialog" aria-modal="true">
            <form wire:submit="guardarMovimiento">
                <h3>{{ ['gasto' => 'Registrar gasto', 'retiro' => 'Retiro de caja', 'ingreso' => 'Otro ingreso'][$mov] }}</h3>
                <p class="hint">{{ ['gasto' => 'Lo que pagas para el negocio. Se resta de tu ganancia en Reportes.', 'retiro' => 'Dinero que sacas de la caja. No es gasto del negocio.', 'ingreso' => 'Dinero que entra y no es venta.'][$mov] }}</p>
                <div class="chips">@foreach (Catalogos::CONCEPTOS[$mov] as $c)<button type="button" class="chip" wire:click="$set('concepto', @js($c))" aria-pressed="{{ $concepto === $c ? 'true' : 'false' }}"><b style="font-size:.95rem">{{ $c }}</b></button>@endforeach</div>
                <div class="row"><label for="m-monto">Monto</label><input data-solo="monto" class="field" id="m-monto" wire:model="monto" inputmode="decimal" placeholder="0.00" x-init="$nextTick(() => $el.focus())"></div>
                <div class="row"><input class="field wide" wire:model="nota" placeholder="Detalle (opcional): 2 millares de papel bond"></div>
                @if ($mov !== 'retiro')
                    <p class="cap" style="margin:0 0 6px">{{ $mov === 'gasto' ? 'Pagado con' : 'Recibido en' }}</p>
                    <div class="chips">@foreach ($metodos as $k => $n)<button type="button" class="chip" wire:click="$set('metodo', '{{ $k }}')" aria-pressed="{{ $metodo === $k ? 'true' : 'false' }}"><b style="font-size:.95rem">{{ $n }}</b></button>@endforeach</div>
                @endif
                <div class="actions"><button class="btn big" type="submit">Guardar</button></div>
            </form>
        </div>
    @endif

    @if ($cerrando)
        @php $tc = $caja->turnos->firstWhere('id', $cerrando); $cc = $tc ? $caja->turno($tc) : null; @endphp
        <div class="dlg" role="alertdialog" aria-modal="true"><div>
            <p>¿Cerrar la caja de {{ Texto::primerNombre($tc?->vendedor) }}? Debe haber {{ Dinero::s($cc['esperado'] ?? 0) }} y contaste {{ Dinero::s(Dinero::aCentimos($contado[$cerrando] ?? '') ?? 0) }}.</p>
            <div class="actions"><button type="button" class="btn ghost big" wire:click="cerrarVentana">Cancelar</button><button type="button" class="btn big" wire:click="cerrarCaja">Aceptar</button></div>
        </div></div>
    @endif

    @if ($corrigiendo)
        <div class="dlg" role="alertdialog" aria-modal="true"><form wire:submit="corregirSencillo">
            <p>Sencillo inicial correcto (S/)</p>
            <input data-solo="monto" class="field wide" wire:model="sencillo" inputmode="decimal" style="width:100%;margin-bottom:14px" x-init="$nextTick(() => $el.select())">
            <div class="actions"><button type="button" class="btn ghost big" wire:click="cerrarVentana">Cancelar</button><button type="submit" class="btn big">Guardar</button></div>
        </form></div>
    @endif

    @if ($fiadoBorrar)
        <div class="dlg" role="alertdialog" aria-modal="true"><div>
            <p>Es un pago de fiado de {{ $fiadoBorrar->nota ?: 'un cliente' }}. Al borrarlo, esa deuda vuelve a figurar. ¿Borrar?</p>
            <div class="actions"><button type="button" class="btn ghost big" wire:click="cerrarVentana">Cancelar</button><button type="button" class="btn big warn" wire:click="borrarMovimiento({{ $fiadoBorrar->id }}, true)">Borrar</button></div>
        </div></div>
    @endif

    @include('livewire.partials.autorizacion')
</section>
