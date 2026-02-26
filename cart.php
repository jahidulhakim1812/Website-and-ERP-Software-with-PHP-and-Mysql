<?php
// cart.php — universal slide-out cart (markup + client-side logic)
// Put this file in the same folder as index.php and product_details.php.

if (!defined('CART_STORAGE_KEY')) define('CART_STORAGE_KEY', 'ali_hair_cart_universal_v1');
if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', '₹');
?>
<!-- CART BACKDROP + DRAWER -->
<div id="cart-backdrop" class="cart-backdrop" aria-hidden="true"></div>

<aside id="cart-drawer" class="cart-drawer" aria-hidden="true" role="dialog" aria-label="Shopping cart">
  <div class="p-4 border-b border-gray-200 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="text-lg font-semibold">Your Cart</div>
      <div id="cart-mini-count" class="text-sm text-gray-500">(<span id="cart-count-mini">0</span> items)</div>
    </div>
    <div class="flex items-center gap-2">
      <button id="cart-clear" class="text-sm text-gray-600 hover:underline">Clear</button>
      <button id="cart-close" aria-label="Close cart" class="p-2 rounded-md hover:bg-gray-100">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
  </div>

  <div id="cart-items" class="p-4 overflow-auto" style="flex:1 1 auto;">
    <div id="cart-empty" class="text-center text-gray-500 py-12">Your cart is empty.</div>
  </div>

  <div class="cart-footer cart-footer-sticky" style="padding:14px;border-top:1px solid #eef2f6;background:linear-gradient(180deg,#fff,#fbfbfb);">
    <div class="flex items-center justify-between mb-1">
      <div class="text-sm text-gray-600">Subtotal</div>
      <div id="cart-subtotal" class="text-lg font-bold text-[var(--primary)]"><?php echo CURRENCY_SYMBOL ?> 0.00</div>
    </div>
    <div class="checkout-cta" style="display:flex;gap:10px;">
      <a href="checkout.php" id="checkout-btn" class="checkout-btn">Checkout</a>
      <button id="continue-shopping" class="secondary-btn">Continue</button>
    </div>
    <div class="text-xs text-gray-500 mt-1">Shipping calculated at checkout</div>
  </div>
</aside>

<script>
(function(){
  const STORAGE_KEY = '<?php echo addslashes(constant('CART_STORAGE_KEY')); ?>';
  const CURRENCY = '<?php echo addslashes(constant('CURRENCY_SYMBOL')); ?>';

  const cartDrawer = document.getElementById('cart-drawer');
  const cartBackdrop = document.getElementById('cart-backdrop');
  const cartItemsEl = document.getElementById('cart-items');
  const cartCountEl = document.getElementById('cart-count');
  const cartCountMiniEl = document.getElementById('cart-count-mini');
  const cartSubtotalEl = document.getElementById('cart-subtotal');
  const cartClearBtn = document.getElementById('cart-clear');
  const cartClose = document.getElementById('cart-close');
  const continueBtn = document.getElementById('continue-shopping');

  let cart = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');

  function saveCart(){ localStorage.setItem(STORAGE_KEY, JSON.stringify(cart)); window.dispatchEvent(new Event('storage')); }
  function getTotalItems(){ return Object.values(cart).reduce((s,it)=> s + (it.qty||0), 0); }
  function getSubtotalCents(){ return Object.values(cart).reduce((s,it)=> { const p = Number.isInteger(it.price_cents) ? it.price_cents : Math.round((parseFloat(it.price||0)||0)*100); return s + p*(it.qty||0); }, 0); }
  function formatMoneyCents(c){ return CURRENCY + ' ' + (c/100).toFixed(2); }

  function renderCart(){
    const items = Object.entries(cart || {});
    const total = getTotalItems();
    if (cartCountEl) cartCountEl.textContent = total;
    if (cartCountMiniEl) cartCountMiniEl.textContent = total;

    if (!items.length) {
      cartItemsEl.innerHTML = '<div id="cart-empty" class="text-center text-gray-500 py-12">Your cart is empty.</div>';
      if (cartSubtotalEl) cartSubtotalEl.textContent = formatMoneyCents(0);
      return;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'cart-grid';

    items.forEach(([id,it])=>{
      const row = document.createElement('div');
      row.className = 'cart-row';
      row.dataset.pid = id;

      const link = document.createElement('a');
      link.href = `product_details.php?id=${encodeURIComponent(id)}`;
      link.className = 'cart-link';
      link.style.display = 'flex';
      link.style.gap = '12px';
      link.style.alignItems = 'center';

      const img = document.createElement('img');
      img.className = 'cart-item-img';
      img.src = it.img || 'https://placehold.co/80x80/7b3f00/f7e0c4?text=Img';
      img.alt = it.name || 'Product';
      img.loading = 'lazy';
      img.onerror = function(){ this.onerror=null; this.src='https://placehold.co/80x80/ffffff/000000?text=Img'; };
      link.appendChild(img);

      const meta = document.createElement('div');
      meta.className = 'cart-meta';
      meta.style.minWidth = '0';
      const nameEl = document.createElement('div');
      nameEl.className = 'cart-name';
      nameEl.textContent = it.name;
      const subEl = document.createElement('div');
      subEl.className = 'cart-sub';
      const per = Number.isInteger(it.price_cents) ? (it.price_cents/100) : (parseFloat(it.price)||0);
      subEl.textContent = formatMoneyCents(Math.round(per*100)) + ' each';
      meta.appendChild(nameEl);
      meta.appendChild(subEl);
      link.appendChild(meta);

      const actions = document.createElement('div');
      actions.className = 'cart-actions';
      const priceEl = document.createElement('div');
      priceEl.className = 'cart-price';
      const perItemCents = Number.isInteger(it.price_cents) ? it.price_cents : Math.round((parseFloat(it.price)||0)*100);
      priceEl.textContent = formatMoneyCents(perItemCents * (it.qty || 0));

      const qtyWrap = document.createElement('div');
      qtyWrap.className = 'cart-qty-controls';
      qtyWrap.style.display = 'flex';
      qtyWrap.style.gap = '8px';
      qtyWrap.style.alignItems = 'center';
      const dec = document.createElement('button'); dec.type='button'; dec.textContent='−';
      const qtyBadge = document.createElement('div'); qtyBadge.className='qty-badge'; qtyBadge.textContent = it.qty || 1;
      const inc = document.createElement('button'); inc.type='button'; inc.textContent='+';
      qtyWrap.appendChild(dec); qtyWrap.appendChild(qtyBadge); qtyWrap.appendChild(inc);

      const remove = document.createElement('button'); remove.type='button'; remove.className='cart-remove'; remove.textContent='Remove';
      const view = document.createElement('a'); view.className='cart-view'; view.href = `product_details.php?id=${encodeURIComponent(id)}`; view.textContent='View';

      actions.appendChild(priceEl); actions.appendChild(qtyWrap); actions.appendChild(remove); actions.appendChild(view);

      row.appendChild(link); row.appendChild(actions);
      wrapper.appendChild(row);

      dec.addEventListener('click',(e)=>{ e.preventDefault(); e.stopPropagation(); cart[id].qty = Math.max(1,(cart[id].qty||1)-1); saveCart(); renderCart(); });
      inc.addEventListener('click',(e)=>{ e.preventDefault(); e.stopPropagation(); cart[id].qty = (cart[id].qty||0)+1; saveCart(); renderCart(); });
      remove.addEventListener('click',(e)=>{ e.preventDefault(); e.stopPropagation(); delete cart[id]; saveCart(); renderCart(); });
    });

    cartItemsEl.innerHTML = ''; cartItemsEl.appendChild(wrapper);
    if (cartSubtotalEl) cartSubtotalEl.textContent = formatMoneyCents(getSubtotalCents());
  }

  function openCart(){ if(!cartDrawer) return; cartDrawer.classList.add('open'); cartBackdrop.classList.add('visible'); cartDrawer.setAttribute('aria-hidden','false'); renderCart(); setTimeout(()=>cartDrawer.querySelector('button, a')?.focus(),160); document.documentElement.style.overflow='hidden'; document.body.style.overflow='hidden'; }
  function closeCart(){ if(!cartDrawer) return; cartDrawer.classList.remove('open'); cartBackdrop.classList.remove('visible'); cartDrawer.setAttribute('aria-hidden','true'); document.documentElement.style.overflow=''; document.body.style.overflow=''; }

  window.addToCart = function(details){
    if (!details || !details.id) return;
    const id = String(details.id);
    const qty = Number.isInteger(details.qty) && details.qty>0 ? details.qty : Math.max(1, parseInt(details.quantity||1,10));
    const price_cents = Number.isInteger(details.price_cents) ? details.price_cents : Math.round((parseFloat(details.price||0)||0)*100);
    if (!cart[id]) cart[id] = { id, name: details.name || 'Product', price_cents, qty: 0, img: details.img || '' };
    cart[id].qty = (cart[id].qty||0) + qty;
    cart[id].price_cents = price_cents;
    if (details.img) cart[id].img = details.img;
    saveCart(); renderCart(); openCart();
  };

  window.addToCartFromButton = function(el, explicitQty){
    if(!el) return;
    if (el.nodeType === 1) {
      const id = el.getAttribute('data-product-id') || el.dataset.productId;
      if (!id) return;
      const name = el.getAttribute('data-product-name') || el.dataset.productName || 'Product';
      const priceCentsAttr = el.getAttribute('data-product-price-cents') || el.dataset.productPriceCents;
      const priceAttr = el.getAttribute('data-product-price') || el.dataset.productPrice;
      const img = el.getAttribute('data-product-img') || el.dataset.productImg || '';
      const price_cents = priceCentsAttr && !isNaN(parseInt(priceCentsAttr,10)) ? parseInt(priceCentsAttr,10) : (priceAttr ? Math.round(parseFloat(priceAttr)*100) : 0);
      const qty = Number.isInteger(explicitQty) && explicitQty>0 ? explicitQty : (()=>{ const dq = el.getAttribute('data-product-qty')||el.dataset.productQty; return dq?Math.max(1,parseInt(dq,10)):1; })();
      window.addToCart({ id, name, price_cents, img, qty });
      return;
    }
    if (typeof el === 'object' && el.id) {
      const d = Object.assign({},el); if (explicitQty && Number.isInteger(explicitQty)) d.qty = explicitQty; window.addToCart(d);
    }
  };

  document.addEventListener('click', function(e){
    const btn = e.target.closest('.add-to-cart, [data-product-id].add-to-cart');
    if (btn) {
      e.preventDefault();
      const qtyInput = document.getElementById('qty');
      let qty = null;
      if (qtyInput && qtyInput.value) qty = parseInt(qtyInput.value,10);
      window.addToCartFromButton(btn, Number.isInteger(qty) ? qty : undefined);
    }
  });

  cartClose && cartClose.addEventListener('click', closeCart);
  cartBackdrop && cartBackdrop.addEventListener('click', closeCart);
  continueBtn && continueBtn.addEventListener('click', closeCart);
  cartClearBtn && cartClearBtn.addEventListener('click', ()=>{ if(confirm('Clear all items from your cart?')){ cart = {}; saveCart(); renderCart(); } });

  window.addEventListener('storage', function(e){
    if (e.key === STORAGE_KEY || e.key === null) {
      cart = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
      renderCart();
    }
  });

  renderCart();
})();
</script>
