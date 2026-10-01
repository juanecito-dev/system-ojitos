@php use App\Support\Dinero; use App\Support\Texto; use App\Support\Catalogos; @endphp
<div x-data="pos(@js($catalogoJs), $wire.entangle('orden'), @js($codigoInicial), @js($docLineas))"
     x-on:keydown.window="teclas($event)" x-on:ojitos-escaneo.window="escaneo($event.detail.codigo)"
     x-on:pos-restaurar.window="restaurar()" x-on:venta-registrada.window="refrescar()"
     x-on:orden-restaurada.window="orden = $event.detail.orden">

    {{-- ===================== catálogo (se dibuja en el navegador) ===================== --}}
    <div class="pos" wire:ignore>
        <div>
            <div class="search">
                <input id="q" type="search" x-model="q" x-on:keydown.enter.prevent="buscarEnter()" x-on:keydown.escape="q = ''; $el.blur()"
                       placeholder="Buscar: lapicero, color A3, anillado…" autocomplete="off" aria-label="Buscar producto o servicio">
                <div class="gnav" x-show="!q">
                    <button type="button" x-on:click="elegirTab('fav')" :aria-pressed="tab === 'fav'">★ Favoritos <small x-show="favs().length" x-text="favs().length"></small></button>
                    <button type="button" x-on:click="elegirTab('todo')" :aria-pressed="tab === 'todo'">Todo</button>
                    <template x-for="g in grupos()" :key="g"><button type="button" x-on:click="elegirTab(g)" :aria-pressed="tab === g" x-text="g"></button></template>
                </div>
                <div class="kbd"><kbd>F2</kbd> buscar · <kbd>Enter</kbd> agrega el primero · <kbd>F9</kbd> cobrar · lector de código de barras listo</div>
            </div>

            <div x-ref="tiles">
                {{-- resultados de la búsqueda --}}
                <template x-if="q.trim()">
                    <div>
                        <template x-if="hits().length"><h2>Resultados <small>Enter agrega el primero</small></h2></template>
                        <div class="grid">
                            <template x-for="p in hits()" :key="'h' + p.id">@include('livewire.partials.tile')</template>
                        </div>
                        <div class="empty" x-show="!hits().length">No hay "<span x-text="q"></span>". Usa Otro monto o agrégalo en Configuración.</div>
                    </div>
                </template>

                <template x-if="!q.trim()">
                    <div>
                        {{-- favoritos --}}
                        <template x-if="tab === 'fav'">
                            <div>
                                <div class="favbar">
                                    <span class="cap" x-text="favMode ? 'Toca los productos para ponerlos o quitarlos de tus favoritos.' : (favs().length ? 'Tus productos de siempre, a un toque.' : 'Aún no tienes favoritos: elige los que más vendes y aparecerán aquí.')"></span>
                                    <button type="button" class="btn sm" :class="favMode ? '' : 'ghost'" x-on:click="favMode = !favMode" x-text="favMode ? 'Listo' : '★ Elegir favoritos'"></button>
                                </div>
                                <template x-if="!favMode">
                                    <div>
                                        <div class="grid" :class="favs().some(p => p.q) ? 'small' : ''"><template x-for="p in favs()" :key="'f' + p.id">@include('livewire.partials.tile')</template></div>
                                        @if ($top->count() >= 3)
                                            <template x-if="@js($top->pluck('id'))->filter(id => byId[id] && !byId[id].fav).length >= 3">
                                                <div><h2>Lo que más sale hoy</h2><div class="grid"><template x-for="p in @js($top->pluck('id')).map(id => byId[id]).filter(p => p && !p.fav)" :key="'t' + p.id">@include('livewire.partials.tile')</template></div></div>
                                            </template>
                                        @endif
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- todo: lo que más sale hoy --}}
                        @if ($top->count() >= 3)
                            <template x-if="tab === 'todo' && !favMode">
                                <div><h2>Lo que más sale hoy</h2><div class="grid"><template x-for="p in @js($top->pluck('id')).map(id => byId[id]).filter(Boolean)" :key="'t2' + p.id">@include('livewire.partials.tile')</template></div></div>
                            </template>
                        @endif

                        {{-- grupos --}}
                        <template x-for="g in grupos()" :key="'g' + g">
                            <div x-show="tab === 'todo' || tab === g || (tab === 'fav' && favMode)">
                                <h2 x-text="g"></h2>
                                <p class="hint-line" x-show="!favMode && delGrupo(g).some(p => p.q)">Un toque suma 1. Mantén presionado para elegir cantidad.</p>
                                <div class="grid" :class="delGrupo(g).some(p => p.q) ? 'small' : ''">
                                    <template x-for="p in delGrupo(g)" :key="'p' + p.id">@include('livewire.partials.tile')</template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- pedido (pantallas anchas) --}}
        <aside class="cart" aria-label="Pedido">
            <h3>Pedido</h3>
            <template x-if="orden.length">
                <div>
                    <ul class="lines">
                        <template x-for="(l, i) in orden" :key="i + '-' + l.pid + '-' + l.precio + '-' + l.det">
                            <li><span class="d"><b x-text="l.nombre"></b><small x-text="(l.det ? l.det + ', ' : '') + S(l.precio) + ' c/u'"></small></span>
                                <span class="stepper sm" x-show="!l.pedido"><button type="button" x-on:click="menos(i)" aria-label="Menos">−</button><span x-text="l.cant"></span><button type="button" x-on:click="mas(i)" aria-label="Más">+</button></span>
                                <b style="min-width:58px;text-align:right" x-text="N(l.cant * l.precio)"></b></li>
                        </template>
                    </ul>
                    <div class="sub"><span>Total</span><strong x-text="S(total())"></strong></div>
                    <div class="actions"><button type="button" class="btn ghost" x-on:click="vaciar()">Vaciar</button><button type="button" class="btn big" x-on:click="cobrar()">Cobrar</button></div>
                </div>
            </template>
            <p class="cap" x-show="!orden.length">Toca un servicio o producto para agregarlo. Los útiles suman de a uno con cada toque.</p>
        </aside>
    </div>

    {{-- barra del pedido (celular y pantallas medianas) --}}
    <div class="order" wire:ignore>
        <div class="order-in">
            <div class="order-sum" role="button" tabindex="0" x-on:click="cobrar()">
                <div class="order-items" x-text="resumen()"></div>
                <div class="order-total" x-text="S(total())"></div>
            </div>
            <button type="button" class="btn ghost" x-show="orden.length" x-on:click="vaciar()" aria-label="Vaciar pedido">Vaciar</button>
            <button type="button" class="btn big" :disabled="!orden.length" x-on:click="cobrar()">Cobrar</button>
        </div>
    </div>

    {{-- ventana del producto: opción, precio, cantidad --}}
    <template x-if="it">
        <div>
            <div class="scrim on" x-on:click="it = null"></div>
            <div class="sheet on" role="dialog" aria-modal="true">
                <h3 x-text="it.p.n"></h3>
                <p class="hint" x-text="ayuda()"></p>
                <div class="chips" x-show="(it.p.ops.length > 1 || it.p.f) && it.p.ops.length">
                    <template x-for="(o, i) in it.p.ops" :key="i">
                        <button type="button" class="chip" :aria-pressed="it.custom === '' && it.sel === i" x-on:click="it.sel = i; it.custom = ''"><b x-text="N(o.p)"></b><small x-show="o.l" x-text="o.l"></small></button>
                    </template>
                </div>
                <div class="row" x-show="it.p.f"><label x-text="it.p.ops.length ? 'Otro precio' : 'Precio'"></label><input class="field" x-ref="custom" x-model="it.custom" inputmode="decimal" placeholder="0.00"></div>
                <div class="row" x-show="it.p.d"><input class="field wide" x-model="it.desc" placeholder="Detalle (opcional)"></div>
                <div class="card" style="margin-bottom:14px;background:var(--paper)" x-show="it.p.t">
                    <b>¿Tú pagaste la tasa?</b>
                    <p class="cap" style="margin:2px 0 8px">Si el cliente trajo su voucher, déjalo en 0. Si la pagaste tú en pagalo.pe o el Banco de la Nación, escribe el monto y se suma aparte (no cuenta como tu ganancia).</p>
                    <div class="row" style="margin:0"><input class="field" x-model="it.tasa" inputmode="decimal" placeholder="0.00"><div class="quick"><button type="button" x-on:click="it.tasa = ''">Trajo voucher</button></div></div>
                </div>
                <div class="row">
                    <label>Cantidad</label>
                    <div class="stepper"><button type="button" x-on:click="it.cant = Math.max(1, it.cant - 1)" aria-label="Menos">−</button><input x-model.number="it.cant" inputmode="numeric" aria-label="Cantidad" x-on:focus="$el.select()"><button type="button" x-on:click="it.cant++" aria-label="Más">+</button></div>
                    <div class="quick"><template x-for="n in [2, 5, 10, 20, 50, 100]"><button type="button" x-on:click="it.cant = n" x-text="n"></button></template></div>
                </div>
                <div class="sub"><span>Subtotal</span><strong x-text="itemSub()"></strong></div>
                <div class="actions">
                    <button type="button" class="btn ghost big" :disabled="!itemOk()" x-on:click="agregarItem(false)">Agregar al pedido</button>
                    <button type="button" class="btn big" :disabled="!itemOk()" x-on:click="agregarItem(true)">Cobrar ya</button>
                </div>
            </div>
        </div>
    </template>

    {{-- ===================== cobrar (lo arma el servidor) ===================== --}}
    @if ($cobrando)
        @include('livewire.partials.cobro')
    @endif

    {{-- ===================== ticket ===================== --}}
    @if (! empty($ticket))
        <div class="scrim on" wire:click="cerrarTicket"></div>
        <div class="sheet on" role="dialog" aria-modal="true">
            <h3>Ticket</h3>
            <p class="hint">Descárgalo en PDF para imprimirlo, o como imagen para mandarlo por WhatsApp.</p>
            @include('pdf.ticket-html', ['v' => $ticket, 'neg' => $neg])
            <div class="actions" style="margin-bottom:10px">
                <a class="btn big" href="{{ route('ticket.pdf', $ticket->uid) }}" target="_blank">Descargar PDF</a>
                <button type="button" class="btn ghost big" x-on:click="ticketImagen(@js(route('ticket.filas', $ticket->uid)), @js('ticket-'.$ticket->numeroTicket().'.png'))">Guardar imagen</button>
            </div>
            <div style="text-align:center;margin-top:8px"><button type="button" class="link" wire:click="cerrarTicket">Cerrar</button></div>
        </div>
    @endif

    @include('livewire.partials.autorizacion')
</div>
