<button type="button" :class="clase(p)" x-on:click="tocar(p)" x-init="if (p.q) toqueLargo($el, () => { if (!favMode) abrir(p) })">
    <span class="n" x-text="p.n"></span><span class="p"><span x-text="precioTxt(p)"></span><em class="low" x-show="bajo(p)" x-text="bajo(p)"></em></span>
    <span class="star" x-show="favMode" x-text="p.fav ? '★' : '☆'"></span>
    <span class="badge" x-show="!favMode && enPedido(p.id)" x-text="enPedido(p.id)"></span>
</button>
