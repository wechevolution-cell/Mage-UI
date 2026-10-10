/* wechevo e-shop — storefront interactions shared by all 10 templates (vanilla JS, no build step). */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const S = window.SHOP || {};
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
  const store = {
    get(k, d) { try { const v = localStorage.getItem(k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }
  };
  const send = async (url, body, method = 'POST') => {
    const opts = {method, headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, credentials: 'same-origin'};
    if (method === 'POST') { const fd = body instanceof FormData ? body : new URLSearchParams(body || {}); if (!fd.has('_csrf')) fd.append('_csrf', S.csrf); opts.body = fd; }
    const r = await fetch(url, opts);
    let j; try { j = await r.json(); } catch (e) { j = {ok: false, error: 'Παρουσιάστηκε σφάλμα. Δοκιμάστε ξανά.'}; }
    return j;
  };
  const toast = (msg, type = 'ok') => {
    const t = $('#toast'); if (!t) return;
    t.textContent = msg; t.dataset.type = type; t.classList.add('on');
    clearTimeout(t._t); t._t = setTimeout(() => t.classList.remove('on'), 3200);
  };
  document.documentElement.classList.add('js');

  /* ---------- overlays (menu, search, cart, filters) with focus return ---------- */
  let lastFocus = null;
  const open = el => { if (!el) return; lastFocus = document.activeElement; el.hidden = false; requestAnimationFrame(() => el.classList.add('on')); document.body.classList.add('locked'); setTimeout(() => (el.querySelector('input:not([type=hidden]),button,a') || el).focus(), 60); };
  const close = el => { if (!el || el.hidden) return; el.classList.remove('on'); document.body.classList.remove('locked'); setTimeout(() => { el.hidden = true; }, reduce ? 0 : 280); lastFocus?.focus?.(); };
  const overlay = (sel, openAttr, closeAttr) => {
    const el = $(sel); if (!el) return;
    document.addEventListener('click', e => {
      if (e.target.closest(openAttr)) { e.preventDefault(); open(el); if (sel === '[data-cart]') loadCart(); }
      else if (e.target.closest(closeAttr) || e.target === el) close(el);
    });
  };
  overlay('[data-menu]', '[data-menu-open]', '[data-menu-close]');
  overlay('[data-search]', '[data-search-open]', '[data-search-close]');
  overlay('[data-cart]', '[data-cart-open]', '[data-cart-close]');
  document.addEventListener('keydown', e => { if (e.key === 'Escape') { $$('[data-menu],[data-search],[data-cart],[data-popup]').forEach(close); $('[data-filters]')?.classList.remove('on'); } });

  /* sticky header state */
  const hdr = $('[data-header]');
  const onScroll = () => hdr?.classList.toggle('scrolled', scrollY > 8);
  addEventListener('scroll', onScroll, {passive: true}); onScroll();

  /* mega menu */
  const mega = $('[data-mega]');
  $('[data-mega-toggle]')?.addEventListener('click', e => { const b = e.currentTarget; const o = mega.hidden; mega.hidden = !o; b.setAttribute('aria-expanded', String(o)); });
  document.addEventListener('click', e => { if (mega && !mega.hidden && !e.target.closest('[data-mega],[data-mega-toggle]')) { mega.hidden = true; $('[data-mega-toggle]')?.setAttribute('aria-expanded', 'false'); } });

  /* ---------- live search ---------- */
  $$('[data-live-search]').forEach(form => {
    const input = $('input[type=search]', form), box = $('.suggest', form);
    if (!input || !box) return;
    let t, ctrl;
    input.addEventListener('input', () => {
      clearTimeout(t);
      const q = input.value.trim();
      if (q.length < 2) { box.hidden = true; return; }
      t = setTimeout(async () => {
        ctrl?.abort(); ctrl = new AbortController();
        try {
          const r = await fetch('/api/search/?q=' + encodeURIComponent(q), {signal: ctrl.signal, headers: {'Accept': 'application/json'}});
          const j = await r.json();
          const items = (j.items || []).map(p => '<a class="sg-item" href="' + esc(p.url) + '">' + (p.img ? '<img src="' + esc(p.img) + '" alt="" width="44" height="44" loading="lazy">' : '<span class="ph"></span>') + '<span>' + esc(p.name) + '</span><b>' + esc(p.price) + '</b></a>').join('');
          const cats = (j.categories || []).map(c => '<a class="sg-cat" href="' + esc(c.url) + '">' + esc(c.name) + '</a>').join('');
          box.innerHTML = (cats ? '<div class="sg-cats">' + cats + '</div>' : '') + (items || '<p class="sg-empty">Δεν βρέθηκαν προϊόντα.</p>') + (j.total > 6 ? '<a class="sg-all" href="/search/?q=' + encodeURIComponent(q) + '">Όλα τα αποτελέσματα (' + j.total + ')</a>' : '');
          box.hidden = false;
        } catch (e) {}
      }, 220);
    });
    input.addEventListener('keydown', e => { if (e.key === 'Escape') box.hidden = true; });
    document.addEventListener('click', e => { if (!form.contains(e.target)) box.hidden = true; });
  });

  /* ---------- cart ---------- */
  const setCount = n => $$('[data-cart-count]').forEach(b => { b.textContent = n; b.hidden = !n; });
  const fillCart = j => { const body = $('[data-cart-body]'); if (body && j.html !== undefined) body.innerHTML = j.html; if (j.count !== undefined) setCount(j.count); };
  const loadCart = async () => { const j = await send('/cart/drawer/', null, 'GET'); fillCart(j); };
  const fly = (from) => {
    if (reduce || !from) return;
    const target = $$('.cart-btn, .tabbar [data-cart-open]').find(el => el.offsetParent !== null); if (!target) return;
    const a = from.getBoundingClientRect(), b = target.getBoundingClientRect();
    const dot = document.createElement('span'); dot.className = 'fly-dot'; document.body.appendChild(dot);
    dot.animate([{transform: `translate(${a.left + a.width / 2}px, ${a.top + a.height / 2}px) scale(1)`}, {transform: `translate(${b.left + b.width / 2}px, ${b.top + b.height / 2}px) scale(.3)`, opacity: .4}], {duration: 650, easing: 'cubic-bezier(.5,-.3,.7,1)'}).onfinish = () => { dot.remove(); target.animate([{transform: 'scale(1)'}, {transform: 'scale(1.18)'}, {transform: 'scale(1)'}], {duration: 260}); };
  };
  document.addEventListener('submit', async e => {
    const f = e.target.closest('form[data-ajax-cart]');
    if (!f) return;
    e.preventDefault();
    if (f.classList.contains('pdp-buy')) {
      const need = $$('fieldset.opt', f).find(fs => !fs.querySelector('input:checked'));
      if (need) { const m = $('.form-msg', f); if (m) m.textContent = 'Επιλέξτε ' + need.querySelector('legend').firstChild.textContent.replace(':', '').trim() + '.'; need.classList.add('err'); return; }
    }
    const btn = e.submitter || $('button:not([type=button])', f);
    btn?.setAttribute('aria-busy', 'true');
    const fd = new FormData(f);
    if (e.submitter?.name) fd.set(e.submitter.name, e.submitter.value);
    const j = await send(f.action, fd);
    btn?.removeAttribute('aria-busy');
    fillCart(j);
    if (f.action.includes('/cart/add/')) {
      if (!j.ok) { toast(j.error || 'Δεν ήταν δυνατή η προσθήκη.', 'bad'); return; }
      const mode = document.body.dataset.cartMode || 'drawer';
      fly(btn);
      if (mode === 'drawer' || f.classList.contains('pdp-buy')) open($('[data-cart]'));
      else toast('Προστέθηκε στο καλάθι ✓');
      f.dispatchEvent(new CustomEvent('cart:added', {bubbles: true, detail: j}));
    }
  });
  /* quantity steppers */
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-qty] [data-step]');
    if (!b) return;
    const inp = $('input', b.closest('[data-qty]'));
    const min = +inp.min || 0, max = +inp.max || 99;
    inp.value = Math.max(min, Math.min(max, (+inp.value || 0) + +b.dataset.step));
    inp.dispatchEvent(new Event('change', {bubbles: true}));
  });
  document.addEventListener('change', e => {
    if (e.target.matches('[data-autosubmit-qty]')) { const f = e.target.form; clearTimeout(f._t); f._t = setTimeout(() => f.requestSubmit(), 350); }
    if (e.target.matches('[data-autosubmit]')) e.target.form.submit();
  });

  /* ---------- wishlist ---------- */
  const WK = 'wish:' + location.host;
  let wish = new Set(store.get(WK, []).map(Number));
  const paintWish = () => {
    $$('[data-wish]').forEach(b => { const on = wish.has(+b.dataset.wish); b.setAttribute('aria-pressed', String(on)); b.classList.toggle('on', on); });
    $$('[data-wish-count]').forEach(c => { c.textContent = wish.size; c.hidden = !wish.size; });
  };
  const saveWish = () => store.set(WK, [...wish]);
  if (S.loggedIn) send('/api/wishlist/', {merge: [...wish].join(',')}).then(j => { if (j.ids) { wish = new Set(j.ids.map(Number)); saveWish(); paintWish(); } });
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-wish]');
    if (!b) return;
    e.preventDefault();
    const id = +b.dataset.wish, on = !wish.has(id);
    on ? wish.add(id) : wish.delete(id);
    saveWish(); paintWish();
    if (S.loggedIn) send('/api/wishlist/', {product_id: id, remove: on ? 0 : 1});
    toast(on ? 'Προστέθηκε στα αγαπημένα' : 'Αφαιρέθηκε από τα αγαπημένα');
    if (!on && $('[data-wishlist-grid]')) { b.closest('.pc')?.remove(); if (!$('[data-wishlist-grid] .pc')) $('[data-wishlist-empty]').hidden = false; }
  });
  const wg = $('[data-wishlist-grid]');
  if (wg && !S.loggedIn && wish.size) {
    fetch('/api/products/?ids=' + [...wish].join(','), {headers: {'Accept': 'application/json'}}).then(r => r.json()).then(j => { wg.innerHTML = j.html; $('[data-wishlist-empty]').hidden = !!j.count; paintWish(); });
  }
  paintWish();

  /* ---------- product page ---------- */
  const gal = $('.pdp-gallery');
  if (gal) {
    const mainImg = $('.gal-main img', gal);
    $$('.gal-thumb', gal).forEach(t => t.addEventListener('click', () => {
      if (!mainImg) return;
      mainImg.src = t.dataset.src; if (t.dataset.srcset) mainImg.srcset = t.dataset.srcset; else mainImg.removeAttribute('srcset');
      $$('.gal-thumb', gal).forEach(x => x.removeAttribute('aria-current')); t.setAttribute('aria-current', 'true');
    }));
    const zoom = $('[data-zoom]', gal);
    if (zoom && matchMedia('(hover: hover)').matches) {
      zoom.addEventListener('mousemove', e => { const r = zoom.getBoundingClientRect(); zoom.style.setProperty('--zx', ((e.clientX - r.left) / r.width * 100) + '%'); zoom.style.setProperty('--zy', ((e.clientY - r.top) / r.height * 100) + '%'); zoom.classList.add('zooming'); });
      zoom.addEventListener('mouseleave', () => zoom.classList.remove('zooming'));
    }
  }
  const buy = $('form.pdp-buy');
  if (buy) {
    const variants = JSON.parse(buy.dataset.variants || '[]');
    const money = n => n.toLocaleString('el-GR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' €';
    const base = +buy.dataset.basePrice, baseSale = buy.dataset.baseSale ? +buy.dataset.baseSale : null;
    const update = () => {
      const chosen = {};
      $$('input[data-opt]:checked', buy).forEach(i => { chosen[i.dataset.opt] = i.value; i.closest('fieldset').querySelector('[data-opt-label]').textContent = i.value; i.closest('fieldset').classList.remove('err'); });
      // disable unavailable values given other choices
      $$('input[data-opt]', buy).forEach(i => {
        const test = {...chosen, [i.dataset.opt]: i.value};
        const ok = variants.some(v => v.available && Object.entries(test).every(([k, val]) => v.options[k] === val));
        i.closest('.opt-val').classList.toggle('na', !ok);
      });
      const v = variants.find(v => Object.keys(v.options).every(k => chosen[k] === v.options[k]));
      buy.elements.variant_id.value = v ? v.id : '';
      const reg = v && v.price !== null ? v.price : base;
      const sale = v && v.price !== null ? (v.sale && v.sale < v.price ? v.sale : null) : (baseSale && baseSale < base ? baseSale : null);
      const pr = $('[data-price]');
      if (pr) pr.innerHTML = '<span class="price' + (sale ? ' price--sale' : '') + '"><span class="price-now">' + money(sale || reg) + '</span>' + (sale ? ' <s class="price-was">' + money(reg) + '</s> <span class="price-off">-' + Math.round((1 - sale / reg) * 100) + '%</span>' : '') + '</span>';
      if (v && v.sku) $$('[data-sku]').forEach(s => s.textContent = v.sku);
      const btn = $('[data-buy]', buy);
      if (btn && variants.length) { const can = !v || v.available; btn.disabled = !can; $('span', btn).textContent = can ? 'Προσθήκη στο καλάθι' : 'Μη διαθέσιμο'; }
    };
    buy.addEventListener('change', e => { if (e.target.matches('[data-opt]')) update(); });
    if (variants.length) update();
    // sticky buy bar
    const bar = $('[data-sticky-buy]'), btn = $('[data-buy]', buy);
    if (bar && btn && 'IntersectionObserver' in window) {
      new IntersectionObserver(([en]) => { const show = !en.isIntersecting && en.boundingClientRect.top < 0; bar.classList.toggle('on', show); bar.setAttribute('aria-hidden', String(!show)); }).observe(btn);
      $('[data-sticky-add]', bar)?.addEventListener('click', () => { if (variants.length && !buy.elements.variant_id.value) { buy.scrollIntoView({behavior: reduce ? 'auto' : 'smooth', block: 'center'}); return; } buy.requestSubmit(); });
    }
  }

  /* ---------- listing filters ---------- */
  const filters = $('[data-filters]');
  if (filters) {
    document.addEventListener('click', e => { if (e.target.closest('[data-filters-open]')) { filters.classList.add('on'); document.body.classList.add('locked'); } if (e.target.closest('[data-filters-close]')) { filters.classList.remove('on'); document.body.classList.remove('locked'); } });
    if (matchMedia('(min-width: 961px)').matches) filters.addEventListener('change', e => { if (e.target.type === 'checkbox') e.target.form.submit(); });
  }

  /* ---------- checkout live totals ---------- */
  const co = $('form[data-checkout]');
  if (co) {
    const money = n => n.toLocaleString('el-GR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' €';
    const sub = +($('[data-subtotal]')?.dataset.subtotal || 0);
    const free = +(co.dataset.free || 0);
    const calc = () => {
      const ship = co.querySelector('input[name=shipping_zone]:checked');
      const pay = co.querySelector('input[name=payment_method]:checked');
      const gift = co.querySelector('input[data-gift]');
      const pickup = ship && ship.hasAttribute('data-pickup');
      const addr = $('[data-address]', co); if (addr) addr.hidden = pickup;
      let s = ship ? +ship.dataset.ship : 0;
      const freeTxt = ship?.closest('label')?.querySelector('.rc-price')?.textContent.trim();
      if (freeTxt === 'Δωρεάν') s = 0;
      const fee = pay ? +pay.dataset.fee : 0, g = gift && gift.checked ? +gift.dataset.gift : 0;
      $('[data-ship-out]').textContent = s > 0 ? money(s) : 'Δωρεάν';
      $('[data-fee-row]').hidden = !fee; $('[data-fee-out]').textContent = money(fee);
      $('[data-gift-row]').hidden = !g; $('[data-gift-out]').textContent = money(g);
      $('[data-total-out]').textContent = money(sub + s + fee + g);
    };
    co.addEventListener('change', calc); calc();
  }

  /* ---------- AJAX forms (newsletter, popup) ---------- */
  document.addEventListener('submit', async e => {
    const f = e.target.closest('form[data-ajax-form]');
    if (!f) return;
    e.preventDefault();
    const msg = $('.form-msg', f);
    const j = await send(f.action, new FormData(f));
    if (msg) { msg.textContent = j.ok ? (j.message || 'Ευχαριστούμε!') : (j.error || 'Σφάλμα'); msg.dataset.type = j.ok ? 'ok' : 'bad'; }
    if (j.ok) { f.reset(); if (f.hasAttribute('data-popup-form')) { store.set('popup-done:' + location.host, 1); setTimeout(() => close($('[data-popup]')), 1600); } }
  });

  /* ---------- popup ---------- */
  const pop = $('[data-popup]');
  if (pop) {
    const key = 'popup:' + location.host + ':' + pop.dataset.popupId;
    const last = store.get(key, 0), freq = (+pop.dataset.freq || 0) * 864e5;
    const blocked = store.get('popup-done:' + location.host, 0) || (last && Date.now() - last < freq);
    const show = () => { if (pop.hidden && !document.body.classList.contains('locked')) { open(pop); store.set(key, Date.now()); } };
    if (!blocked) {
      const tr = pop.dataset.trigger;
      if (tr === 'exit' && matchMedia('(hover: hover)').matches) document.addEventListener('mouseout', function h(e) { if (!e.relatedTarget && e.clientY < 10) { show(); document.removeEventListener('mouseout', h); } });
      else if (tr === 'scroll') addEventListener('scroll', function h() { if (scrollY > (document.body.scrollHeight - innerHeight) * .5) { show(); removeEventListener('scroll', h); } }, {passive: true});
      else setTimeout(show, (+pop.dataset.delay || 5) * 1000);
    }
    document.addEventListener('click', e => { if (e.target.closest('[data-popup-close]') || e.target === pop) close(pop); });
  }

  /* ---------- cookie consent + GA4 (loaded only after consent) ---------- */
  const CK = 'cc:' + location.host;
  const cookie = $('[data-cookie]');
  const loadGA = () => {
    const id = document.body.dataset.ga;
    if (!id || window.__ga) return;
    window.__ga = 1;
    const s = document.createElement('script'); s.async = true; s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id); document.head.appendChild(s);
    window.dataLayer = window.dataLayer || []; window.gtag = function () { dataLayer.push(arguments); };
    gtag('js', new Date()); gtag('config', id, {anonymize_ip: true});
  };
  const choice = store.get(CK, null);
  if (choice === 'all') loadGA(); else if (!choice && cookie) cookie.hidden = false;
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-cookie-choice]');
    if (b) { store.set(CK, b.dataset.cookieChoice); cookie.hidden = true; if (b.dataset.cookieChoice === 'all') loadGA(); }
    if (e.target.closest('[data-cookie-settings]') && cookie) cookie.hidden = false;
  });

  /* ---------- shelves, hotspots, reveal ---------- */
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-shelf]');
    if (b) { const sh = b.closest('.sec')?.querySelector('.shelf'); sh?.scrollBy({left: +b.dataset.shelf * sh.clientWidth * .8, behavior: reduce ? 'auto' : 'smooth'}); }
    const dot = e.target.closest('.spot-dot');
    if (dot) { const sp = dot.closest('.spot'); const on = !sp.classList.contains('on'); $$('.spot').forEach(s => { s.classList.remove('on'); s.querySelector('.spot-dot')?.setAttribute('aria-expanded', 'false'); }); sp.classList.toggle('on', on); dot.setAttribute('aria-expanded', String(on)); }
  });
  if (!reduce && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver(es => es.forEach(en => { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } }), {rootMargin: '0px 0px -8% 0px'});
    $$('.sec, .story, .banner, .benefit').forEach(el => { if (el.getBoundingClientRect().top > innerHeight) { el.classList.add('rv'); io.observe(el); } });
  }
})();
