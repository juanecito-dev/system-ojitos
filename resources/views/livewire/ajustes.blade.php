@php use App\Support\Catalogos; use App\Support\Dinero; use App\Services\Stock; $yo = auth()->user(); @endphp
<section>
    <div class="seg" style="margin-bottom:12px;flex-wrap:wrap">
        @foreach ($tabs as $k => [$l])<button type="button" wire:click="$set('pestana', '{{ $k }}')" aria-pressed="{{ $pestana === $k ? 'true' : 'false' }}">{{ $l }}</button>@endforeach
    </div>

    {{-- ============================ NEGOCIO ============================ --}}
    @if ($pestana === 'negocio')
        <h2>Datos del negocio <small>salen en el ticket y en los documentos</small></h2>
        <div class="card set two">
            @foreach (['nombre' => 'Nombre del local', 'giro' => 'Rubro', 'titular' => 'Titular', 'ruc' => 'RUC', 'direccion' => 'Dirección', 'celular' => 'Celular', 'ciudad' => 'Ciudad (para documentos)'] as $f => $l)
                <label>{{ $l }}<input @if ($f === 'ruc') data-solo="ruc" inputmode="numeric" @elseif ($f === 'celular') data-solo="cel" inputmode="tel" @endif value="{{ $neg->{$f} }}" x-on:change="$wire.fijar('{{ $f }}', $event.target.value)"></label>
            @endforeach
            <label>Meta de venta diaria (S/)<input data-solo="monto" value="{{ $neg->ajuste('meta') }}" inputmode="decimal" x-on:change="$wire.fijar('meta', $event.target.value)"></label>
        </div>
        <h2>Módulos que usas <small>los que apagues no aparecen en el menú de nadie</small></h2>
        <div class="card" x-data>
            <label style="display:block;margin-bottom:10px">Tipo de negocio
                <select class="field wide" style="width:100%" x-on:change="$wire.cambiarRubro($event.target.value, confirm('¿Usar los módulos sugeridos para este tipo de negocio?'))">
                    @foreach (Catalogos::RUBROS as $k => [$n])<option value="{{ $k }}" @selected($neg->rubro === $k)>{{ $n }}</option>@endforeach
                </select></label>
            <div class="two">
                @foreach (Catalogos::MODULOS_OPCIONALES as $m)
                    <label class="check" style="flex-direction:row;color:var(--ink);margin:0 0 8px"><input type="checkbox" @checked($neg->moduloActivo($m)) x-on:change="$wire.modulo('{{ $m }}', $event.target.checked)"> {{ Catalogos::MODULOS[$m][0] }}</label>
                @endforeach
            </div>
            <p class="cap" style="margin:4px 0 0">Punto de venta, Ventas, Usuarios y Configuración siempre están. Apagar un módulo no borra sus datos.</p>
        </div>
    @endif

    {{-- ============================ TICKET ============================ --}}
    @if ($pestana === 'ticket')
        <h2>Cómo sale el ticket</h2>
        <div class="card set two">
            <label>Ancho del papel<select x-on:change="$wire.fijar('tkAncho', $event.target.value)">
                @foreach (['80' => '80 mm (el más común)', '58' => '58 mm (impresora pequeña)'] as $v => $l)<option value="{{ $v }}" @selected((string) $neg->ajuste('tkAncho', '80') === $v)>{{ $l }}</option>@endforeach
            </select></label>
            <label class="check" style="flex-direction:row;color:var(--ink);align-self:end"><input type="checkbox" @checked($neg->ajuste('tkQr')) x-on:change="$wire.fijar('tkQr', $event.target.checked)"> Poner el QR de Yape al final</label>
            <label style="grid-column:1/-1">Mensaje al pie<input value="{{ $neg->ajuste('tkPie') }}" placeholder="¡Gracias por su preferencia!" x-on:change="$wire.fijar('tkPie', $event.target.value)"></label>
            <div style="grid-column:1/-1"><span class="cap">Logo (sale arriba, en blanco y negro)</span>
                <div style="display:flex;gap:12px;align-items:center;margin-top:6px">
                    @if ($neg->logo)<img src="{{ $neg->logo }}" alt="Logo" style="max-height:60px;max-width:160px;border:1px solid var(--line);border-radius:6px;background:#fff">@else<div class="qrph" style="width:120px;height:50px">Sin logo</div>@endif
                    <label class="btn ghost sm" style="cursor:pointer" x-on:click="$wire.subirImagen('logo')">{{ $neg->logo ? 'Cambiar' : 'Subir logo' }}<input type="file" accept="image/*" wire:model="imagen" hidden></label>
                    @if ($neg->logo)<button type="button" class="link" wire:click="quitarImagen('logo')">Quitar</button>@endif
                </div></div>
        </div>
        <p class="cap">El ancho también se puede elegir por equipo en «Datos y copias», si una PC tiene otra impresora.</p>
        <h2>Vista previa</h2>
        <div class="ticket-prev">@include('pdf.ticket-html', ['v' => $demo, 'neg' => $neg])</div>
    @endif

    {{-- ============================ COBROS ============================ --}}
    @if ($pestana === 'cobros')
        <h2>Métodos de pago <small>los apagados no salen al cobrar</small></h2>
        <div class="card">
            @foreach (Catalogos::METODOS as $k => $l)
                <div style="display:flex;gap:10px;align-items:center;padding:6px 0;border-top:1px solid var(--line)">
                    <input type="checkbox" style="width:20px;height:20px" @checked($k === 'efectivo' || ! in_array($k, $neg->metodosApagados(), true)) @disabled($k === 'efectivo') x-on:change="$wire.metodoActivo('{{ $k }}', $event.target.checked)">
                    <input class="field" value="{{ $neg->nombreMetodo($k) }}" style="flex:1" aria-label="Nombre de {{ $l }}" x-on:change="$wire.metodoNombre('{{ $k }}', $event.target.value)">
                </div>
            @endforeach
            <p class="cap" style="margin:8px 0 0">Puedes cambiarles el nombre (por ejemplo, «Transferencia BCP» o «Tarjeta POS»). Los reportes los muestran con ese nombre.</p>
        </div>
        <h2>Yape y Plin <small>el QR sale al cobrar</small></h2>
        <div class="card">
            <div class="set two"><label>Número de Yape / Plin<input data-solo="cel" value="{{ $neg->ajuste('yapeCel') }}" placeholder="{{ $neg->celular }}" x-on:change="$wire.fijar('yapeCel', $event.target.value)"></label>
                <label>Nombre que aparece<input value="{{ $neg->ajuste('yapeNom') }}" placeholder="{{ $neg->titular }}" x-on:change="$wire.fijar('yapeNom', $event.target.value)"></label></div>
            <div class="qrset">
                @foreach (['qr_yape' => 'Yape', 'qr_plin' => 'Plin'] as $c => $Y)
                    <div><span class="cap">QR de {{ $Y }}</span>
                        @if ($neg->{$c})<img src="{{ $neg->{$c} }}" alt="QR de {{ $Y }}">@else<div class="qrph">Sin QR</div>@endif
                        <label class="btn ghost sm" style="cursor:pointer" x-on:click="$wire.subirImagen('{{ $c }}')">{{ $neg->{$c} ? 'Cambiar' : 'Subir foto del QR' }}<input type="file" accept="image/*" wire:model="imagen" hidden></label>
                        @if ($neg->{$c})<button type="button" class="link" wire:click="quitarImagen('{{ $c }}')">Quitar</button>@endif
                    </div>
                @endforeach
            </div>
            <p class="cap" style="margin:8px 0 0">Toma una captura del QR desde tu app de Yape o Plin (o una foto clara del cartel) y súbela.</p>
        </div>
    @endif

    {{-- ============================ FACTURACIÓN ============================ --}}
    @if ($pestana === 'fact')
        <h2>Facturación electrónica</h2>
        <div class="card set two">
            <label>Régimen tributario<select x-on:change="$wire.fijar('regimen', $event.target.value)">@foreach (Catalogos::REGIMENES as $k => [$n, $f])<option value="{{ $k }}" @selected($cfg['regimen'] === $k)>{{ $n }}{{ $f ? '' : ' (solo boletas)' }}</option>@endforeach</select></label>
            <label>IGV de tus ventas<select x-on:change="$wire.fijar('igv', $event.target.value)">@foreach (['exonerado' => 'Exonerado (Amazonía y otras zonas)', 'gravado' => 'Gravado con IGV 18% (precios incluyen IGV)', 'inafecto' => 'Inafecto'] as $k => $l)<option value="{{ $k }}" @selected($cfg['igv'] === $k)>{{ $l }}</option>@endforeach</select></label>
            <label>Serie de boletas<input data-solo="serie" value="{{ $neg->ajuste('serieB') }}" placeholder="EB01" x-on:change="$wire.fijar('serieB', $event.target.value)"></label>
            <label>Serie de facturas<input data-solo="serie" value="{{ $neg->ajuste('serieF') }}" placeholder="E001" x-on:change="$wire.fijar('serieF', $event.target.value)"></label>
            <label>Última boleta emitida en SUNAT<input data-solo="entero" inputmode="numeric" value="{{ $ultB ?: '' }}" placeholder="Ej. 445" x-on:change="$wire.fijar('ult_{{ $cfg['serieB'] }}', $event.target.value)"></label>
            <label>Última factura emitida en SUNAT<input data-solo="entero" inputmode="numeric" value="{{ $ultF ?: '' }}" placeholder="Ej. 134" x-on:change="$wire.fijar('ult_{{ $cfg['serieF'] }}', $event.target.value)"></label>
            <label style="grid-column:1/-1">Forma de emitir<select disabled><option>Manual: emito en el portal de SUNAT (SEE-SOL / App Emprender) y anoto el número</option></select></label>
            <p class="cap" style="grid-column:1/-1;margin:0">La emisión automática con SUNAT (Greenter) llega en la etapa 5. Consulta con tu contador tu régimen y si tus ventas están exoneradas.</p>
        </div>
    @endif

    {{-- ============================ MÁQUINAS ============================ --}}
    @if ($pestana === 'maq')
        <livewire:ajustes-maquinas />
    @endif

    {{-- ============================ SEGURIDAD ============================ --}}
    @if ($pestana === 'seguridad')
        <h2>Seguridad</h2>
        <div class="card set two"><label>Bloquear el sistema si nadie lo usa<select x-on:change="$wire.fijar('autoLock', $event.target.value)">
            @foreach (['0' => 'Nunca', '5' => '5 minutos', '10' => '10 minutos', '15' => '15 minutos', '30' => '30 minutos'] as $v => $l)<option value="{{ $v }}" @selected((string) ($neg->ajuste('autoLock') ?: '0') === $v)>{{ $l }}</option>@endforeach
        </select></label>
            <p class="cap" style="margin:0;align-self:end">Después de 5 PIN incorrectos, ese usuario espera antes de volver a intentar, en cualquier equipo.</p></div>
        @if ($yo->esAdmin())<div class="actions" style="margin-top:12px"><a class="btn ghost" href="{{ route('usuarios') }}">Usuarios, roles y actividad</a></div>@endif
    @endif

    {{-- ============================ PRODUCTOS ============================ --}}
    @if ($pestana === 'productos')
        <div class="actions" style="margin-bottom:10px"><button type="button" class="btn" wire:click="abrir('nuevo')">Agregar producto</button><button type="button" class="btn ghost" wire:click="abrir('masivo')">Cambiar precios por grupo</button><button type="button" class="btn ghost" wire:click="abrir('grupo')">Renombrar un grupo</button></div>
        <input class="field wide" wire:model.live.debounce.250ms="buscar" placeholder="Buscar producto, grupo o código" style="width:100%;margin-bottom:10px" autocomplete="off">
        <p class="cap">Cada cambio se guarda al salir del campo y queda en la actividad.</p>
        @forelse ($productos as $g => $L)
            <details class="grp" @if ($loop->first || $buscar) open @endif wire:key="g{{ md5($g) }}"><summary>{{ $g }} <small class="cap">({{ $L->count() }})</small></summary>
                @foreach ($L as $i)
                    @php $st = $stock[$i->id] ?? null; $mg = $i->margen(); @endphp
                    <div class="pitem" wire:key="p{{ $i->id }}">
                        <div class="h"><input value="{{ $i->nombre }}" aria-label="Nombre" x-on:change="$wire.productoCampo({{ $i->id }}, 'nombre', $event.target.value)">
                            <button type="button" class="link" title="Favorito en el punto de venta" style="text-decoration:none;font-size:1.2rem;color:{{ $i->favorito ? 'var(--yellow)' : 'var(--soft)' }}" wire:click="productoCampo({{ $i->id }}, 'favorito', '')">{{ $i->favorito ? '★' : '☆' }}</button>
                            <button type="button" class="link" wire:click="quitarProducto({{ $i->id }})">Quitar</button></div>
                        <div class="pgrid">
                            @foreach ($i->opciones as $j => $o)
                                <label>{{ $o->etiqueta ?: ($i->opciones->count() > 1 ? 'Opción '.($j + 1) : 'Precio') }}<input data-solo="monto" inputmode="decimal" value="{{ Dinero::n($o->precio) }}" x-on:change="$wire.productoCampo({{ $i->id }}, 'op{{ $j }}', $event.target.value)"></label>
                            @endforeach
                            @if ($i->monto_libre && $i->opciones->isEmpty())<span class="cap" style="align-self:center">Monto libre</span>@endif
                            @if ($i->opciones->count() === 1 && $yo->puede('costos'))<label>Costo<input data-solo="costo" inputmode="decimal" value="{{ $i->costo ? Dinero::nCosto($i->costo) : '' }}" placeholder="—" x-on:change="$wire.productoCampo({{ $i->id }}, 'costo', $event.target.value)"></label>@endif
                            @if ($mg !== null && $yo->puede('costos'))<span class="cap" style="align-self:end;padding-bottom:8px;color:{{ $mg < 20 ? 'var(--magenta)' : 'var(--ok)' }}">Ganas {{ Dinero::s($i->opciones->first()->precio - $i->costo) }} ({{ $mg }}%)</span>@endif
                            @if ($i->rapido || $st !== null)<label>Stock<input data-solo="cantidad" inputmode="decimal" value="{{ Stock::formato($st) }}" placeholder="—" x-on:change="$wire.productoCampo({{ $i->id }}, 'stock', $event.target.value)"></label>@endif
                            @if ($i->opciones->isNotEmpty())<label>Código de barras<input value="{{ $i->codigos_barra }}" placeholder="Escanéalo aquí" style="width:150px" x-on:change="$wire.productoCampo({{ $i->id }}, 'codigos', $event.target.value)"></label>@endif
                        </div>
                    </div>
                @endforeach
            </details>
        @empty
            <p class="cap">Ningún producto coincide.</p>
        @endforelse

        @if ($ventana === 'nuevo')
            <div class="scrim on" wire:click="cerrar"></div>
            <div class="sheet on" role="dialog" aria-modal="true"><form wire:submit="agregarProducto">
                <h3>Agregar producto</h3>
                <div class="set">
                    <label>Nombre<input wire:model="nuevo.nombre" placeholder="Ej.: Cuaderno cuadriculado" x-init="$nextTick(() => $el.focus())"></label>
                    <div class="two"><label>Grupo<select wire:model.live="nuevo.grupo">@foreach ($grupos as $g)<option>{{ $g }}</option>@endforeach<option value="__nuevo">Nuevo grupo…</option></select></label>
                        @if ($nuevo['grupo'] === '__nuevo')<label>Nombre del grupo<input wire:model="nuevo.grupoNuevo" placeholder="Ej.: Recargas"></label>@endif</div>
                    <div class="two"><label>Precio (S/)<input data-solo="monto" wire:model="nuevo.precio" inputmode="decimal" placeholder="0.00"></label><label>Stock (opcional)<input data-solo="cantidad" wire:model="nuevo.stock" inputmode="numeric" placeholder="—"></label></div>
                    <div class="two"><label>Costo de compra (opcional)<input data-solo="costo" wire:model="nuevo.costo" inputmode="decimal" placeholder="0.00"></label><label>Código de barras (opcional)<input wire:model="nuevo.cb" placeholder="Escanéalo aquí"></label></div>
                    <label class="check" style="flex-direction:row;color:var(--ink)"><input type="checkbox" wire:model="nuevo.rapido"> Un toque suma 1 (ideal para útiles)</label>
                </div>
                <p class="err">{{ $error }}</p>
                <div class="actions"><button type="button" class="btn ghost big" wire:click="cerrar">Cancelar</button><button class="btn big" type="submit">Agregar</button></div>
            </form></div>
        @endif
        @if ($ventana === 'masivo')
            <div class="scrim on" wire:click="cerrar"></div>
            <div class="sheet on" role="dialog" aria-modal="true">
                <h3>Cambiar precios por grupo</h3><p class="hint">Por ejemplo, si subió el papel: «Copias e impresiones A4», subir 10%. Revisa la vista previa antes de aplicar.</p>
                <div class="set two"><label style="grid-column:1/-1">Grupo<select wire:model.live="masivo.grupo">@foreach ($grupos as $g)<option>{{ $g }}</option>@endforeach</select></label>
                    <label>Cambio<select wire:model.live="masivo.modo"><option value="pct">Porcentaje (%)</option><option value="sol">Monto fijo (S/)</option></select></label>
                    <label>Cuánto (negativo para bajar)<input wire:model.live.debounce.300ms="masivo.valor" inputmode="decimal" placeholder="Ej.: 10"></label>
                    <label style="grid-column:1/-1">Redondear a<select wire:model.live="masivo.redondeo"><option value="1">Céntimo exacto</option><option value="5">0.05</option><option value="10">0.10</option><option value="50">0.50</option></select></label></div>
                <div style="max-height:260px;overflow:auto;margin:8px 0">
                    @if ($vistaMasivo)
                        <table><thead><tr><th>Producto</th><th class="r">Antes</th><th class="r">Ahora</th></tr></thead><tbody>
                            @foreach ($vistaMasivo as [, $n, $e, $a, $b])<tr><td>{{ $n }}{{ $e ? ' · '.$e : '' }}</td><td class="r">{{ Dinero::n($a) }}</td><td class="r"><b>{{ Dinero::n($b) }}</b></td></tr>@endforeach
                        </tbody></table>
                    @else<p class="cap">Escribe cuánto cambiar para ver la vista previa.</p>@endif
                </div>
                <p class="err">{{ $error }}</p>
                <div class="actions paybar"><button type="button" class="btn big" wire:click="aplicarMasivo" wire:confirm="¿Cambiar {{ count($vistaMasivo) }} precios de «{{ $masivo['grupo'] }}»?">Aplicar cambios</button></div>
            </div>
        @endif
        @if ($ventana === 'grupo')
            <div class="scrim on" wire:click="cerrar"></div>
            <div class="sheet on" role="dialog" aria-modal="true"><form wire:submit="renombrarGrupo">
                <h3>Renombrar un grupo</h3>
                <div class="set"><label>Grupo<select wire:model="renombrar.grupo">@foreach ($grupos as $g)<option>{{ $g }}</option>@endforeach</select></label><label>Nombre nuevo<input wire:model="renombrar.nombre" placeholder="Ej.: Impresiones a color"></label></div>
                <p class="err">{{ $error }}</p><div class="actions"><button type="button" class="btn ghost big" wire:click="cerrar">Cancelar</button><button class="btn big" type="submit">Renombrar</button></div>
            </form></div>
        @endif
    @endif

    {{-- ============================ DATOS ============================ --}}
    @if ($pestana === 'datos')
        <h2>Traer la copia del sistema anterior</h2>
        <div class="card">
            <p class="cap" style="margin:0 0 10px">En el sistema anterior ve a <b>Configuración › Datos y copias › Descargar copia</b> y elige aquí el archivo. Se agregan y actualizan ventas, caja, clientes, productos, pedidos, compras, documentos y usuarios. Lo que ya está no se duplica y no se borra nada. Pide el PIN de un administrador.</p>
            <form class="row" style="margin:0" wire:submit="importar">
                <input type="file" wire:model="copia" accept=".json,application/json">
                <button class="btn" type="submit" wire:loading.attr="disabled" wire:target="importar,copia"><span wire:loading.remove wire:target="importar,copia">Importar copia</span><span wire:loading wire:target="importar,copia">Trabajando…</span></button>
            </form>
            <p class="err">{{ $error }}</p>
            @if ($log)<ul class="importlog">@foreach ($log as $l)<li>{{ $l }}</li>@endforeach</ul>@endif
            <p class="cap" style="margin:8px 0 0">Si la copia es muy grande, el técnico puede importarla en el servidor con <code>php artisan ojitos:importar archivo.json</code>.</p>
        </div>
        <h2>Copias de seguridad</h2>
        <div class="card"><p class="cap" style="margin:0">Ahora todo se guarda en el servidor. Las copias de seguridad se hacen allí (la guía de instalación explica cómo programarlas cada día).</p></div>
        <h2>Este equipo <small>solo cambia en esta PC o celular</small></h2>
        <div class="card set two">
            <label>Nombre del equipo<input wire:model="equipo" x-on:change="$wire.guardarEquipo()" placeholder="Ej.: PC caja 1, Laptop Alex"></label>
            <label>Ancho del ticket en este equipo<select wire:model.live="equipoAncho"><option value="">Igual que el negocio</option><option value="80">80 mm</option><option value="58">58 mm</option></select></label>
            <p class="cap" style="grid-column:1/-1;margin:0">El nombre del equipo aparece en la actividad (quién entró y desde dónde).</p>
        </div>
    @endif

    @include('livewire.partials.autorizacion')
</section>
