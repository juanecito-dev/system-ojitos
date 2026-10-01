/* Punto de venta: el pedido se arma en el navegador (sin esperar al servidor), igual que en caja-rapida.html */
function pos(catalogo, orden, codigoInicial, docLineas) {
    const S = c => 'S/ ' + (c / 100).toFixed(2);
    const N = c => (c / 100).toFixed(2);
    const toC = v => { const n = parseFloat(String(v).replace(',', '.')); return isFinite(n) ? Math.round(n * 100) : NaN; };
    const norm = s => String(s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const guardar = (k, v) => { try { localStorage.setItem('ojitos:' + k, JSON.stringify(v)); } catch (e) {} };
    const leer = k => { try { return JSON.parse(localStorage.getItem('ojitos:' + k)); } catch (e) { return null; } };

    return {
        cat: catalogo,
        orden: orden,
        q: '',
        tab: leer('postab') || null,
        favMode: false,
        it: null,          // ventana del producto: {p, sel, custom, cant, desc, tasa}
        deshacer: null,
        S, N,

        init() {
            if ((!this.orden || !this.orden.length) && Array.isArray(leer('orden')) && leer('orden').length) this.orden = leer('orden');
            this.$watch('orden', v => guardar('orden', v || []));
            if (!this.tab || (this.tab !== 'fav' && this.tab !== 'todo' && !this.grupos().includes(this.tab))) this.tab = this.favs().length ? 'fav' : 'todo';
            if (codigoInicial) this.$nextTick(() => this.escaneo(codigoInicial));
            if (docLineas && docLineas.length) this.$nextTick(() => this.agregarDocumento(docLineas));
        },
        get byId() { const m = {}; this.cat.forEach(p => { m[p.id] = p; }); return m; },
        grupos() { return [...new Set(this.cat.map(p => p.g))]; },
        delGrupo(g) { return this.cat.filter(p => p.g === g); },
        favs() { return this.cat.filter(p => p.fav); },
        hits() { const n = norm(this.q.trim()); if (!n) return []; const c = this.porCodigo(this.q); return c ? [c] : this.cat.filter(p => p.k.includes(n)); },
        porCodigo(code) { const c = String(code).trim(); return c ? this.cat.find(p => (p.cb || []).includes(c)) : null; },
        elegirTab(t) { this.tab = t; if (t !== 'fav') this.favMode = false; guardar('postab', t); if (this.$refs.tiles.getBoundingClientRect().top < 0) window.scrollTo(0, 0); },
        precioTxt(p) { const ps = p.ops.map(o => o.p); if (!ps.length) return 'Monto libre'; const mn = Math.min(...ps), mx = Math.max(...ps); return mn === mx ? S(mn) : S(mn) + ' a ' + N(mx); },
        bajo(p) { if (typeof p.s !== 'number') return ''; const r = p.s - this.enPedido(p.id); return r <= 0 ? 'Agotado' : r <= p.min ? 'Quedan ' + r : ''; },
        enPedido(id) { return (this.orden || []).filter(l => l.pid === id).reduce((a, l) => a + l.cant, 0); },
        total() { return (this.orden || []).reduce((a, l) => a + l.cant * l.precio, 0); },
        resumen() { return this.orden.length ? this.orden.map(l => l.cant + ' ' + l.nombre).join(', ') : 'Toca un servicio para empezar'; },
        clase(p) { return 'tile ' + (p.c || '') + (this.favMode && p.fav ? ' fav-on' : ''); },

        tocar(p) {
            if (this.favMode) { p.fav = !p.fav; this.$wire.alternarFavorito(p.id); return; }
            if (p.q && p.ops.length) this.rapido(p); else this.abrir(p);
        },
        rapido(p) {
            this.sumar({ pid: p.id, nombre: p.n, det: '', cant: 1, precio: p.ops[0].p });
            try { navigator.vibrate && navigator.vibrate(12); } catch (e) {}
            if (typeof p.s === 'number' && p.s - this.enPedido(p.id) < 0) this.avisar('Ojo: ' + p.n + ' figura sin stock');
        },
        sumar(l) {
            const o = this.orden || [];
            const same = o.find(x => x.pid === l.pid && x.precio === l.precio && (x.det || '') === (l.det || '') && !!x.tercero === !!l.tercero);
            if (same) same.cant += l.cant; else o.push(l);
            this.orden = [...o];
        },
        // documento de Redacción: se suma a lo que ya había y se abre el cobro (cobrarDoc del sistema anterior)
        agregarDocumento(ls) {
            try { history.replaceState(null, '', location.pathname); } catch (e) {}
            const o = this.orden || [];
            if (o.some(l => l.doc && l.doc === ls[0].doc)) this.avisar('Este documento ya está en el cobro');
            else { this.orden = [...o, ...ls]; this.avisar('Agregado al cobro'); }
            this.$nextTick(() => this.cobrar());
        },
        abrir(p) { this.it = { p, sel: p.ops.length ? 0 : -1, custom: '', cant: 1, desc: '', tasa: '' }; if (!p.ops.length) this.$nextTick(() => this.$refs.custom && this.$refs.custom.focus()); },
        unidad() { const it = this.it; return it.custom !== '' ? toC(it.custom) : (it.sel >= 0 ? it.p.ops[it.sel].p : NaN); },
        tasaC() { const t = toC(this.it.tasa); return t > 0 ? t : 0; },
        itemOk() { const u = this.unidad(); return isFinite(u) && u > 0 && this.it.cant > 0; },
        itemSub() { return this.itemOk() ? S(this.unidad() * this.it.cant + this.tasaC() * this.it.cant) : '—'; },
        ayuda() {
            const p = this.it.p;
            const t = p.ops.some(o => o.l === 'Poco') ? 'Elige según cuánto color o tinta lleva la hoja.' : p.ops.some(o => o.l) ? 'Elige el tipo.' : p.f ? 'Elige un precio o escribe el monto.' : 'Precio ' + S(p.ops[0].p) + ' por unidad.';
            return t + (typeof p.s === 'number' ? ' Stock: ' + p.s + '.' : '');
        },
        agregarItem(cobrarYa) {
            if (!this.itemOk()) return;
            const it = this.it, u = this.unidad(), o = it.custom === '' && it.sel >= 0 && it.p.ops.length > 1 ? it.p.ops[it.sel] : null;
            const det = [o && o.l, it.desc.trim()].filter(Boolean).join(' ');
            this.sumar({ pid: it.p.id, nombre: it.p.n, det, cant: it.cant, precio: u });
            const t = this.tasaC();
            if (t) this.sumar({ pid: null, nombre: 'Tasa pagada por el cliente', det: det || it.p.n, cant: it.cant, precio: t, tercero: true });
            this.it = null;
            if (cobrarYa) this.cobrar(); else this.avisar('Agregado al pedido');
        },
        mas(i) { this.orden[i].cant++; this.orden = [...this.orden]; },
        menos(i) { if (this.orden[i].cant > 1) { this.orden[i].cant--; this.orden = [...this.orden]; } else { this.orden.splice(i, 1); this.orden = [...this.orden]; } },
        vaciar() {
            const bak = JSON.parse(JSON.stringify(this.orden));
            this.orden = [];
            this.deshacer = bak;
            window.dispatchEvent(new CustomEvent('toast', { detail: { texto: 'Pedido vaciado', accion: { label: 'Deshacer', evento: 'pos-restaurar' } } }));
        },
        restaurar() { if (this.deshacer) { this.orden = this.deshacer; this.deshacer = null; } },
        cobrar() { if (this.orden.length) this.$wire.abrirCobro(); },
        buscarEnter() {
            const q = this.q.trim(); if (!q) return;
            const p = this.porCodigo(q) || this.hits()[0];
            if (p) { this.q = ''; this.tocar(p); } else this.avisar('No encontré «' + q + '»');
        },
        escaneo(code) {
            const p = this.porCodigo(code);
            if (!p) return this.avisar('Código ' + code + ' no registrado. Agrégalo en Configuración › Productos');
            this.tocar(p);
        },
        teclas(e) {
            if (e.key === 'F9') {
                e.preventDefault();
                if (this.$wire.cobrando) this.$wire.cobrar(false);
                else if (!this.it && this.orden.length) this.cobrar();
            } else if (e.key === 'Escape' && this.it) this.it = null;
        },
        async refrescar() { this.cat = await this.$wire.catalogoFresco(); },
        avisar(t) { window.dispatchEvent(new CustomEvent('toast', { detail: { texto: t } })); },
    };
}
