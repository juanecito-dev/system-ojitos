/* Cada campo solo deja escribir lo que le corresponde (data-solo="…"): un DNI solo acepta 8 números, un RUC 11,
   un celular 9, un PIN hasta 6 y un monto números con un punto y 2 decimales. Si pegan algo con letras o espacios,
   queda solo lo que sirve. El servidor vuelve a revisar todo al guardar. */
(function () {
    const digitos = (v, max) => v.replace(/\D/g, '').slice(0, max);
    const numero = (v, enteros, decimales) => {
        v = v.replace(/,/g, '.').replace(/[^\d.]/g, '');
        const i = v.indexOf('.');
        let a = i < 0 ? v : v.slice(0, i), b = i < 0 ? null : v.slice(i + 1).replace(/\./g, '');
        a = a.slice(0, enteros);
        if (b === null || !decimales) return a;
        return a + '.' + b.slice(0, decimales);
    };
    const REGLAS = {
        dni: v => digitos(v, 8),
        ruc: v => digitos(v, 11),
        doc: v => digitos(v, 11),                 // DNI (8) o RUC (11)
        pin: v => digitos(v, 6),
        entero: v => digitos(v, 9),
        cel: v => { let d = v.replace(/\D/g, ''); if (d.length > 9 && d.startsWith('51')) d = d.slice(2); return d.slice(0, 9); },
        monto: v => numero(v, 8, 2),
        soles: v => v.replace(/[^\d.,]/g, '').slice(0, 16),   // montos de contratos: se puede escribir 1,500.00
        costo: v => numero(v, 8, 4),
        cantidad: v => numero(v, 7, 3),
        serie: v => v.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 4),
    };
    window.OJITOS_REGLAS = REGLAS;
    document.addEventListener('input', e => {
        const el = e.target, r = el && el.dataset && REGLAS[el.dataset.solo];
        if (!r) return;
        const antes = el.value, v = r(antes);
        if (v === antes) return;
        let p = null;
        try { p = el.selectionStart; } catch (x) {}
        el.value = v;
        if (p !== null) { const q = Math.max(0, p - (antes.length - v.length)); try { el.setSelectionRange(q, q); } catch (x) {} }
    }, true);
})();
