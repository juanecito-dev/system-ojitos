/* Ojitos Caja: comportamiento general de la pantalla (menú, avisos, teclas, lector de códigos, bloqueo) */
function ojitosApp(bloqueoMin) {
    return {
        menu: false,
        aviso: { on: false, texto: '', accion: null },
        _t: null,
        ultimo: Date.now(),
        scan: '', scanT: 0,
        init() {
            if (bloqueoMin > 0) {
                setInterval(() => {
                    if (Date.now() - this.ultimo > bloqueoMin * 60000) {
                        const f = document.getElementById('form-salir');
                        if (f) f.submit();
                    }
                }, 15000);
            }
        },
        actividad() { this.ultimo = Date.now(); },
        avisar(d) {
            d = Array.isArray(d) ? d[0] : d;
            this.aviso = { on: true, texto: d.texto || '', accion: d.accion || null };
            clearTimeout(this._t);
            this._t = setTimeout(() => { this.aviso.on = false; }, d.accion ? 6000 : 2200);
        },
        accionAviso() {
            const a = this.aviso.accion;
            this.aviso.on = false;
            if (a && a.evento) { window.dispatchEvent(new CustomEvent(a.evento, { detail: a.params || {} })); Livewire.dispatch(a.evento, a.params || {}); }
        },
        teclas(e) {
            const el = e.target, escribiendo = el && (['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName) || el.isContentEditable);
            const enVender = document.body.classList.contains('m-vender');
            if (e.key === 'F2' || (e.key === '/' && !escribiendo)) {
                e.preventDefault();
                if (enVender) { const q = document.getElementById('q'); if (q) { q.focus(); q.select(); } }
                else if (window.OJITOS_VENDER) location.href = window.OJITOS_VENDER;
                return;
            }
            if (escribiendo || document.querySelector('.sheet.on, .dlg')) return;
            // lector de código de barras: escribe muy rápido y termina con Enter
            const ahora = Date.now();
            if (/^[0-9A-Za-z]$/.test(e.key)) { if (ahora - this.scanT > 60) this.scan = ''; this.scan += e.key; this.scanT = ahora; return; }
            if (e.key === 'Enter' && this.scan.length >= 4 && ahora - this.scanT < 100) {
                e.preventDefault();
                const c = this.scan; this.scan = '';
                if (enVender || window.OJITOS_ESCANEO_AQUI) window.dispatchEvent(new CustomEvent('ojitos-escaneo', { detail: { codigo: c } }));
                else if (window.OJITOS_VENDER) location.href = window.OJITOS_VENDER + '?codigo=' + encodeURIComponent(c);
            }
        },
    };
}

/* copiar texto al portapapeles (para pegar en el portal de SUNAT) */
window.copiar = async function (texto, msg) {
    let ok = false;
    try { await navigator.clipboard.writeText(texto); ok = true; } catch (e) {
        const ta = document.createElement('textarea'); ta.value = texto; ta.setAttribute('readonly', ''); ta.style.cssText = 'position:fixed;opacity:0';
        document.body.appendChild(ta); ta.select();
        try { ok = document.execCommand('copy'); } catch (e2) {}
        ta.remove();
    }
    window.dispatchEvent(new CustomEvent('toast', { detail: { texto: ok ? msg : 'No se pudo copiar: mantén presionado el texto y cópialo' } }));
};

/* toque largo en los productos rápidos: abre la ventana para elegir cantidad */
window.toqueLargo = function (el, alMantener) {
    let t = null, hecho = false, x0 = 0, y0 = 0;
    el.addEventListener('pointerdown', e => { hecho = false; x0 = e.clientX; y0 = e.clientY; t = setTimeout(() => { hecho = true; try { navigator.vibrate && navigator.vibrate(12); } catch (er) {} alMantener(); }, 480); });
    el.addEventListener('pointermove', e => { if (Math.hypot(e.clientX - x0, e.clientY - y0) > 10) clearTimeout(t); });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach(ev => el.addEventListener(ev, () => clearTimeout(t)));
    el.addEventListener('click', e => { if (hecho) { e.preventDefault(); e.stopImmediatePropagation(); hecho = false; } }, true);
    el.addEventListener('contextmenu', e => e.preventDefault());
};

/* Hoja de Redacción: se corrige tocando el texto y se guarda sola (renderDocView del sistema anterior).
   La hoja se vuelve a escribir en el formato por líneas: [title], [p]…, [firma] y **negrita**. */
window.hojaEditable = function (guardar) {
    const segs = el => {
        let out = '';
        const walk = (n, b) => n.childNodes.forEach(c => {
            if (c.nodeType === 3) out += b ? '**' + c.nodeValue.replace(/\*\*/g, '') + '**' : c.nodeValue.replace(/\*\*/g, '');
            else if (c.nodeName === 'BR' || c.nodeName === 'DIV' || c.nodeName === 'P') { out += ' '; walk(c, b); }
            else if (c.nodeType === 1) walk(c, b || c.nodeName === 'B' || c.nodeName === 'STRONG');
        });
        walk(el, false);
        return out.replace(/\*\*(\s*)\*\*/g, '$1').replace(/\*\*\*\*/g, '').replace(/\s+/g, ' ').trim();
    };
    const texto = page => [...page.querySelectorAll('[data-k]')].map(el => {
        const k = el.dataset.k;
        if (k === 'sign') {
            let f = []; try { f = JSON.parse(el.dataset.f || '[]'); } catch (e) {}
            return f.map(x => '[firma] ' + [x.n, x.d, x.r].map(v => String(v || '').replace(/\|/g, '/')).join(' | ') + (x.nh ? ' | sin huella' : '')).join('\n');
        }
        if (k === 'title') return '[title] ' + el.textContent.replace(/\s+/g, ' ').trim();
        const t = segs(el);
        return t ? '[' + k + '] ' + t : '';
    }).filter(Boolean).join('\n');
    return {
        tmr: null,
        pendiente: false,
        cambio() { this.pendiente = true; clearTimeout(this.tmr); this.tmr = setTimeout(() => this.enviar(), 800); },
        enviar() { clearTimeout(this.tmr); if (!this.pendiente || !this.$refs.hoja) return Promise.resolve(); this.pendiente = false; return guardar(texto(this.$refs.hoja)); },
        pegar(e) { e.preventDefault(); document.execCommand('insertText', false, (e.clipboardData || window.clipboardData).getData('text/plain').replace(/\s+/g, ' ')); },
    };
};

/* Foto del currículum: se recorta a 300 × 378 (tamaño carnet) y se comprime en el navegador (leerFoto del sistema anterior) */
window.ojitosFoto = function (file) {
    return new Promise((res, rej) => {
        const fr = new FileReader();
        fr.onload = () => {
            const img = new Image();
            img.onload = () => {
                const tw = 300, th = 378, cv = document.createElement('canvas'); cv.width = tw; cv.height = th;
                const r = Math.max(tw / img.width, th / img.height), w = img.width * r, h = img.height * r;
                const x = cv.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, tw, th); x.drawImage(img, (tw - w) / 2, (th - h) / 2, w, h);
                res(cv.toDataURL('image/jpeg', 0.85));
            };
            img.onerror = rej; img.src = fr.result;
        };
        fr.onerror = rej; fr.readAsDataURL(file);
    });
};
