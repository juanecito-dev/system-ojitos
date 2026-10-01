/* Ticket como imagen PNG para mandarlo por WhatsApp (igual que ticketPNG() del sistema anterior) */
window.ticketImagen = async function (urlFilas, nombre) {
    const rows = await (await fetch(urlFilas, { headers: { Accept: 'application/json' } })).json();
    const W = 576, pad = 28, lh = 30;
    const FONT = { title: 'bold 40px "Archivo", sans-serif', bold: 'bold 22px "Courier New", monospace', big: 'bold 28px "Courier New", monospace', small: '18px "Courier New", monospace' };
    const imgs = await Promise.all(rows.map(r => r.img ? new Promise(res => { const im = new Image(); im.onload = () => res(im); im.onerror = () => res(null); im.src = r.img; }) : null));
    const iw = r => Math.round((W - pad * 2) * (r.w || 0.5));
    const hOf = (r, i) => r.img ? (imgs[i] ? Math.round(iw(r) * imgs[i].height / imgs[i].width) + 12 : 0) : r.hr ? 18 : r.st === 'title' ? 52 : lh;
    const cv = document.createElement('canvas'); cv.width = W; cv.height = pad * 2 + rows.reduce((a, r, i) => a + hOf(r, i), 0);
    const x = cv.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, W, cv.height); x.fillStyle = '#111'; x.textBaseline = 'top';
    let y = pad;
    rows.forEach((r, i) => {
        if (r.img) { if (imgs[i]) { const w = iw(r), hh = hOf(r, i) - 12; x.drawImage(imgs[i], (W - w) / 2, y + 6, w, hh); } y += hOf(r, i); return; }
        if (r.hr) { x.save(); x.strokeStyle = '#777'; x.setLineDash([6, 5]); x.beginPath(); x.moveTo(pad, y + 8); x.lineTo(W - pad, y + 8); x.stroke(); x.restore(); y += 18; return; }
        x.font = FONT[r.st] || '22px "Courier New", monospace';
        if (r.a === 'c') { x.textAlign = 'center'; x.fillText(r.t, W / 2, y, W - pad * 2); }
        else { x.textAlign = 'left'; x.fillText(r.t, pad, y, r.r ? W - pad * 2 - 170 : W - pad * 2); if (r.r) { x.textAlign = 'right'; x.fillText(r.r, W - pad, y); } }
        y += hOf(r, i);
    });
    cv.toBlob(b => {
        const a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = nombre; document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 4000);
        window.dispatchEvent(new CustomEvent('toast', { detail: { texto: 'Ticket guardado' } }));
    }, 'image/png');
};
