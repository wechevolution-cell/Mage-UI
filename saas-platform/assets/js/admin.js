/* wechevo e-shop — admin interactions (vanilla, no build step) */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const csrf = () => $('meta[name="csrf-token"]')?.content || '';
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const post = async (url, data) => {
    const body = data instanceof FormData ? data : new URLSearchParams(data);
    if (!(data instanceof FormData)) body.append('_csrf', csrf()); else if (!body.has('_csrf')) body.append('_csrf', csrf());
    const r = await fetch(url, {method: 'POST', body, headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, credentials: 'same-origin'});
    let j = null; try { j = await r.json(); } catch (e) { j = {ok: false, error: 'Σφάλμα διακομιστή (' + r.status + ')'}; }
    return j;
  };
  window.adminPost = post;

  /* Sidebar (mobile) */
  const side = $('.side'), scrim = $('.scrim');
  $('.menu-btn')?.addEventListener('click', () => { side?.classList.add('open'); scrim?.classList.add('on'); });
  scrim?.addEventListener('click', () => { side?.classList.remove('open'); scrim.classList.remove('on'); });

  /* Toast */
  const toast = (msg, type = 'ok') => {
    let t = $('#admin-toast');
    if (!t) { t = document.createElement('div'); t.id = 'admin-toast'; t.setAttribute('role', 'status'); t.style.cssText = 'position:fixed;right:16px;bottom:16px;z-index:200;max-width:420px'; document.body.appendChild(t); }
    const el = document.createElement('div');
    el.className = 'alert ' + type; el.style.boxShadow = '0 10px 30px rgba(0,0,0,.15)'; el.style.marginTop = '8px';
    el.textContent = msg; t.appendChild(el); setTimeout(() => el.remove(), 5000);
  };
  window.adminToast = toast;

  /* Confirm dialogs (incl. two-step typed confirmation) */
  const dlg = document.createElement('dialog');
  dlg.className = 'modal';
  dlg.innerHTML = '<form method="dialog"><div class="hd"><h2 id="cf-title">Επιβεβαίωση</h2></div><div class="bd"><p id="cf-msg"></p><div id="cf-type" hidden><label class="field"><span class="lbl" id="cf-type-lbl"></span><input type="text" id="cf-input" autocomplete="off"></label></div></div><div class="ft"><button value="cancel" class="btn">Ακύρωση</button><button value="ok" class="btn btn-danger" id="cf-ok">Επιβεβαίωση</button></div></form>';
  document.body.appendChild(dlg);
  const confirmAsk = (msg, typed, okLabel) => new Promise(res => {
    $('#cf-msg', dlg).textContent = msg;
    $('#cf-ok', dlg).textContent = okLabel || 'Επιβεβαίωση';
    const box = $('#cf-type', dlg), inp = $('#cf-input', dlg), ok = $('#cf-ok', dlg);
    box.hidden = !typed; inp.value = '';
    if (typed) { $('#cf-type-lbl', dlg).textContent = 'Πληκτρολογήστε «' + typed + '» για επιβεβαίωση'; ok.disabled = true; inp.oninput = () => ok.disabled = inp.value.trim() !== typed; }
    else ok.disabled = false;
    dlg.onclose = () => res(dlg.returnValue === 'ok');
    dlg.showModal();
    if (typed) setTimeout(() => inp.focus(), 50);
  });
  window.adminConfirm = confirmAsk;
  document.addEventListener('submit', async e => {
    const f = e.target;
    const btn = e.submitter;
    const msg = btn?.dataset.confirm || f.dataset.confirm;
    if (!msg || f.dataset.confirmed) return;
    e.preventDefault();
    if (await confirmAsk(msg, btn?.dataset.confirmType || f.dataset.confirmType, btn?.dataset.confirmOk || f.dataset.confirmOk)) {
      f.dataset.confirmed = '1';
      if (btn && btn.name) { const h = document.createElement('input'); h.type = 'hidden'; h.name = btn.name; h.value = btn.value; f.appendChild(h); }
      const typedInp = $('#cf-input', dlg).value; if (f.dataset.confirmField) { let h = f.querySelector('[name="' + f.dataset.confirmField + '"]'); if (!h) { h = document.createElement('input'); h.type = 'hidden'; h.name = f.dataset.confirmField; f.appendChild(h); } h.value = typedInp; }
      f.submit();
    }
  }, true);

  /* Tabs */
  $$('[data-tabs]').forEach(tabs => {
    const btns = $$('[role="tab"]', tabs);
    const show = id => {
      btns.forEach(b => b.setAttribute('aria-selected', String(b.dataset.tab === id)));
      $$('[data-tab-panel]').filter(p => p.closest('[data-tabs-scope]') === tabs.closest('[data-tabs-scope]')).forEach(p => p.hidden = p.dataset.tabPanel !== id);
      try { sessionStorage.setItem('tab:' + location.pathname, id); } catch (e) {}
    };
    btns.forEach(b => b.addEventListener('click', () => show(b.dataset.tab)));
    let saved = null; try { saved = sessionStorage.getItem('tab:' + location.pathname); } catch (e) {}
    const hash = location.hash.slice(1);
    show(btns.some(b => b.dataset.tab === hash) ? hash : (btns.some(b => b.dataset.tab === saved) ? saved : btns[0]?.dataset.tab));
  });

  /* Wizard steps */
  $$('[data-wizard]').forEach(w => {
    const panels = $$('.step-panel', w), nav = $$('.steps button', w);
    let i = 0;
    const go = n => {
      i = Math.max(0, Math.min(panels.length - 1, n));
      panels.forEach((p, k) => p.hidden = k !== i);
      nav.forEach((b, k) => { b.setAttribute('aria-current', k === i ? 'step' : 'false'); b.classList.toggle('done', k < i); });
      $$('[data-next]', w).forEach(b => b.hidden = i === panels.length - 1);
      $$('[data-prev]', w).forEach(b => b.disabled = i === 0);
      $$('[data-finish]', w).forEach(b => b.hidden = i !== panels.length - 1);
      if (i === panels.length - 1) w.dispatchEvent(new CustomEvent('wizard:review'));
      w.scrollIntoView({block: 'start', behavior: 'smooth'});
    };
    const valid = () => { const fields = $$('input,select,textarea', panels[i]); for (const f of fields) { if (!f.checkValidity()) { f.reportValidity(); return false; } } return true; };
    $$('[data-next]', w).forEach(b => b.addEventListener('click', () => { if (valid()) go(i + 1); }));
    $$('[data-prev]', w).forEach(b => b.addEventListener('click', () => go(i - 1)));
    nav.forEach((b, k) => b.addEventListener('click', () => { if (k < i || valid()) go(k); }));
    go(0);
  });

  /* Bulk selection */
  $$('[data-bulk-table]').forEach(t => {
    const all = $('[data-bulk-all]', t), bar = $(t.dataset.bulkTable), count = bar && $('[data-bulk-count]', bar);
    const boxes = () => $$('input[data-bulk]', t);
    const upd = () => { const n = boxes().filter(b => b.checked).length; bar?.classList.toggle('on', n > 0); if (count) count.textContent = n; if (all) all.checked = n && n === boxes().length; };
    all?.addEventListener('change', () => { boxes().forEach(b => b.checked = all.checked); upd(); });
    t.addEventListener('change', e => { if (e.target.matches('input[data-bulk]')) upd(); });
    bar && $$('form', bar).forEach(f => f.addEventListener('submit', () => {
      $$('input[name="ids[]"]', f).forEach(x => x.remove());
      boxes().filter(b => b.checked).forEach(b => { const h = document.createElement('input'); h.type = 'hidden'; h.name = 'ids[]'; h.value = b.value; f.appendChild(h); });
    }, true));
  });

  /* Active toggles with quota validation */
  document.addEventListener('change', async e => {
    const sw = e.target.closest('input[data-toggle-active]');
    if (!sw) return;
    sw.disabled = true;
    const j = await post(sw.dataset.toggleActive, {active: sw.checked ? 1 : 0});
    sw.disabled = false;
    if (!j.ok) { sw.checked = !sw.checked; toast(j.error || 'Σφάλμα', 'bad'); }
    if (j.quota) { $$('[data-quota-text]').forEach(el => el.textContent = j.quota.active + ' / ' + j.quota.limit); $$('[data-quota-bar]').forEach(el => { el.style.width = j.quota.pct + '%'; el.parentElement.classList.toggle('full', j.quota.active >= j.quota.limit); el.parentElement.classList.toggle('warn', j.quota.pct >= 80 && j.quota.active < j.quota.limit); }); }
    if (j.ok) toast(sw.checked ? 'Το προϊόν ενεργοποιήθηκε.' : 'Το προϊόν απενεργοποιήθηκε.');
  });

  /* Repeaters */
  $$('[data-repeater]').forEach(rep => {
    const tpl = $('template', rep), list = $('.rep', rep);
    const reindex = () => $$('.rep-row', list).forEach((row, i) => $$('[data-name]', row).forEach(inp => inp.name = inp.dataset.name.replace('__i__', i)));
    $('[data-add]', rep)?.addEventListener('click', () => { list.insertAdjacentHTML('beforeend', tpl.innerHTML); reindex(); $$('.rep-row', list).at(-1)?.querySelector('input,textarea')?.focus(); });
    list.addEventListener('click', e => { if (e.target.closest('.rm')) { e.target.closest('.rep-row').remove(); reindex(); } });
    reindex();
  });

  /* Color fields */
  $$('.color-row').forEach(r => {
    const c = $('input[type=color]', r), t = $('input[type=text]', r);
    c?.addEventListener('input', () => t.value = c.value.toUpperCase());
    t?.addEventListener('input', () => { if (/^#[0-9a-f]{6}$/i.test(t.value)) c.value = t.value; });
  });

  /* Slug autogen */
  const gr = {'α':'a','ά':'a','β':'v','γ':'g','δ':'d','ε':'e','έ':'e','ζ':'z','η':'i','ή':'i','θ':'th','ι':'i','ί':'i','ϊ':'i','ΐ':'i','κ':'k','λ':'l','μ':'m','ν':'n','ξ':'x','ο':'o','ό':'o','π':'p','ρ':'r','σ':'s','ς':'s','τ':'t','υ':'y','ύ':'y','ϋ':'y','φ':'f','χ':'ch','ψ':'ps','ω':'o','ώ':'o'};
  const slugify = s => s.toLowerCase().replace(/ου/g, 'ou').replace(/μπ/g, 'b').replace(/ντ/g, 'nt').replace(/γκ/g, 'gk').split('').map(c => gr[c] ?? c).join('').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 90);
  $$('[data-slug-from]').forEach(slug => {
    const src = $(slug.dataset.slugFrom);
    let touched = slug.value !== '';
    slug.addEventListener('input', () => touched = slug.value !== '');
    src?.addEventListener('input', () => { if (!touched) slug.value = slugify(src.value); });
  });

  /* Media picker */
  let mediaDlg = null;
  const openMedia = (opts) => new Promise(async res => {
    if (!mediaDlg) {
      mediaDlg = document.createElement('dialog');
      mediaDlg.className = 'modal wide';
      mediaDlg.innerHTML = '<div class="hd"><h2>Βιβλιοθήκη πολυμέσων</h2><button class="btn btn-sm" data-close>Κλείσιμο</button></div><div class="bd"><div class="dropzone" data-drop><p>Σύρετε εικόνες εδώ ή <label class="link-btn">επιλέξτε αρχεία<input type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden data-file></label></p><small>JPG, PNG, WebP, GIF έως 10 MB · αυτόματη μετατροπή σε WebP</small></div><input type="search" placeholder="Αναζήτηση…" data-q><div class="media-grid" data-grid style="max-height:50vh;overflow:auto"></div></div><div class="ft"><span class="muted" data-sel-count></span><button class="btn btn-primary" data-use>Χρήση επιλεγμένων</button></div>';
      document.body.appendChild(mediaDlg);
      $('[data-close]', mediaDlg).onclick = () => mediaDlg.close();
    }
    const grid = $('[data-grid]', mediaDlg);
    let sel = new Map();
    const load = async (q = '') => {
      const r = await fetch(document.body.dataset.mediaUrl + '?json=1&q=' + encodeURIComponent(q), {headers: {'Accept': 'application/json'}});
      const j = await r.json();
      grid.innerHTML = j.items.map(m => '<div class="media-item" data-id="' + m.id + '" data-url="' + esc(m.url) + '" title="' + esc(m.title || '') + '"><img src="' + esc(m.thumb) + '" alt="' + esc(m.alt || '') + '" loading="lazy"></div>').join('') || '<p class="muted">Δεν υπάρχουν εικόνες ακόμη.</p>';
    };
    const upload = async files => {
      for (const f of files) {
        const fd = new FormData(); fd.append('file', f); fd.append('_csrf', csrf());
        const j = await post(document.body.dataset.mediaUrl + '/upload', fd);
        if (!j.ok) toast(j.error || 'Αποτυχία μεταφόρτωσης', 'bad');
      }
      await load();
    };
    grid.onclick = e => {
      const it = e.target.closest('.media-item'); if (!it) return;
      const id = it.dataset.id;
      if (!opts.multiple) { sel.clear(); $$('.media-item', grid).forEach(x => x.classList.remove('sel')); }
      if (sel.has(id)) { sel.delete(id); it.classList.remove('sel'); } else { sel.set(id, it.dataset.url); it.classList.add('sel'); }
      $('[data-sel-count]', mediaDlg).textContent = sel.size ? sel.size + ' επιλεγμένα' : '';
    };
    $('[data-file]', mediaDlg).onchange = e => upload(e.target.files);
    const dz = $('[data-drop]', mediaDlg);
    dz.ondragover = e => { e.preventDefault(); dz.classList.add('drag'); };
    dz.ondragleave = () => dz.classList.remove('drag');
    dz.ondrop = e => { e.preventDefault(); dz.classList.remove('drag'); upload(e.dataTransfer.files); };
    let t; $('[data-q]', mediaDlg).oninput = e => { clearTimeout(t); t = setTimeout(() => load(e.target.value), 250); };
    $('[data-use]', mediaDlg).onclick = () => { mediaDlg.close(); res([...sel.entries()].map(([id, url]) => ({id, url}))); };
    mediaDlg.onclose = () => res([]);
    await load();
    mediaDlg.showModal();
  });
  window.openMedia = openMedia;
  document.addEventListener('click', async e => {
    const b = e.target.closest('[data-media-pick]');
    if (b) {
      e.preventDefault();
      const wrap = b.closest('.img-pick');
      const items = await openMedia({multiple: false});
      if (items[0]) { $('input[type=hidden]', wrap).value = items[0].id; $('.prev', wrap).style.backgroundImage = 'url("' + items[0].url + '")'; }
      return;
    }
    const c = e.target.closest('[data-media-clear]');
    if (c) { const wrap = c.closest('.img-pick'); $('input[type=hidden]', wrap).value = ''; $('.prev', wrap).style.backgroundImage = ''; return; }
    const g = e.target.closest('[data-gallery-add]');
    if (g) {
      e.preventDefault();
      const wrap = g.closest('[data-gallery]');
      const items = await openMedia({multiple: true});
      items.forEach(it => g.insertAdjacentHTML('beforebegin', '<div class="media-item" draggable="true"><img src="' + esc(it.url) + '" alt=""><input type="hidden" name="' + wrap.dataset.gallery + '[]" value="' + esc(it.id) + '"><button type="button" class="x" aria-label="Αφαίρεση">✕</button></div>'));
      return;
    }
    const x = e.target.closest('[data-gallery] .x');
    if (x) { x.closest('.media-item').remove(); }
  });
  /* Gallery drag sort */
  let dragEl = null;
  document.addEventListener('dragstart', e => { const it = e.target.closest('[data-gallery] .media-item'); if (it) { dragEl = it; e.dataTransfer.effectAllowed = 'move'; } });
  document.addEventListener('dragover', e => { const it = e.target.closest('[data-gallery] .media-item'); if (dragEl && it && it !== dragEl) { e.preventDefault(); const r = it.getBoundingClientRect(); it.parentNode.insertBefore(dragEl, (e.clientX - r.left) > r.width / 2 ? it.nextSibling : it); } });
  document.addEventListener('dragend', () => dragEl = null);

  /* SEO / AEO live analyzer */
  $$('[data-seo-analyze]').forEach(form => {
    const out = $('[data-seo-out]', form) || $('[data-seo-out]');
    if (!out) return;
    let t;
    const run = async () => {
      const fd = new FormData(form);
      fd.set('type', form.dataset.seoType || 'page');
      if (window.tinyContent) fd.set('content', window.tinyContent());
      const j = await post(form.dataset.seoAnalyze, fd);
      if (!j.ok) return;
      const cls = s => s >= 75 ? 'good' : s >= 50 ? 'mid' : 'low';
      const list = arr => '<ul class="checks">' + arr.map(c => '<li class="' + (c.pass ? 'pass' : '') + '">' + esc(c.label) + '</li>').join('') + '</ul>';
      out.innerHTML = '<div class="scores"><div class="score ' + cls(j.seo) + '"><b>' + j.seo + '</b>SEO</div><div class="score ' + cls(j.aeo) + '"><b>' + j.aeo + '</b>AEO</div></div>'
        + '<details open><summary>Έλεγχοι SEO</summary>' + list(j.seo_checks) + '</details><details><summary>Έλεγχοι AEO</summary>' + list(j.aeo_checks) + '</details>'
        + (j.cannibal && j.cannibal.length ? '<div class="alert warn" style="margin-top:8px"><div><b>Πιθανή κανιβαλοποίηση λέξεων-κλειδιών:</b><br>' + j.cannibal.map(c => esc(c.type + ': ' + c.title + ' (' + c.keyword + ')')).join('<br>') + '</div></div>' : '');
    };
    form.addEventListener('input', () => { clearTimeout(t); t = setTimeout(run, 600); });
    run();
  });

  /* Import progress (batched processing, no timeouts) */
  $$('[data-import-progress]').forEach(box => {
    const url = box.dataset.importProgress;
    const bar = $('.bar > i', box), txt = $('[data-txt]', box), stats = $('[data-stats]', box);
    let running = true;
    const tick = async () => {
      if (!running) return;
      const j = await post(url, {});
      if (!j || j.ok === false) { txt.textContent = (j && j.error) || 'Σφάλμα'; return; }
      const p = j.progress;
      bar.style.width = p.pct + '%';
      txt.textContent = p.processed + ' / ' + p.total + ' γραμμές (' + p.pct + '%)';
      stats.textContent = 'Νέα: ' + p.created + ' · Ενημερώσεις: ' + p.updated + ' · Παραλείψεις: ' + p.skipped + ' · Σφάλματα: ' + p.errors + ' · Ενεργοποιήσεις: ' + p.activated + (p.quota_blocked ? ' · Ανενεργά λόγω ορίου: ' + p.quota_blocked : '');
      if (p.status === 'completed' || p.status === 'failed') { running = false; txt.textContent += ' — ' + (p.status === 'completed' ? 'Ολοκληρώθηκε' : 'Απέτυχε'); setTimeout(() => location.reload(), 1200); return; }
      setTimeout(tick, 300);
    };
    tick();
  });

  /* Menu builder */
  $$('[data-menu-builder]').forEach(mb => {
    const list = $('.mb', mb), tpl = $('template', mb), out = $('input[name="tree"]', mb.closest('form'));
    const opts = JSON.parse(mb.dataset.options || '{}');
    const refSelect = (type, val) => {
      const o = opts[type];
      if (!o) return type === 'url' ? '<input type="text" class="mb-url" placeholder="https://… ή /διαδρομή/" value="' + esc(val || '') + '">' : '<span class="muted">—</span>';
      return '<select class="mb-ref">' + o.map(x => '<option value="' + x.id + '"' + (String(x.id) === String(val) ? ' selected' : '') + '>' + esc(x.label) + '</option>').join('') + '</select>';
    };
    const types = {home: 'Αρχική', products: 'Όλα τα προϊόντα', category: 'Κατηγορία', product: 'Προϊόν', page: 'Σελίδα', post: 'Άρθρο', blog: 'Blog', url: 'Σύνδεσμος'};
    const row = (it, depth) => {
      const d = document.createElement('div');
      d.className = 'mb-item'; d.dataset.depth = depth;
      d.innerHTML = '<span class="muted">⋮⋮</span><input type="text" class="mb-label" value="' + esc(it.label || '') + '" placeholder="Ετικέτα" aria-label="Ετικέτα">'
        + '<select class="mb-type" aria-label="Τύπος">' + Object.entries(types).map(([k, v]) => '<option value="' + k + '"' + (k === (it.type || 'url') ? ' selected' : '') + '>' + v + '</option>').join('') + '</select>'
        + '<span class="mb-refwrap">' + refSelect(it.type || 'url', it.type === 'url' ? it.url : it.ref_id) + '</span>'
        + '<span class="tools"><button type="button" data-a="up" title="Πάνω">↑</button><button type="button" data-a="down" title="Κάτω">↓</button><button type="button" data-a="out" title="Αριστερά">←</button><button type="button" data-a="in" title="Δεξιά (υπομενού)">→</button><button type="button" data-a="rm" title="Αφαίρεση">✕</button></span>';
      return d;
    };
    const flat = (items, depth = 0) => items.forEach(it => { list.appendChild(row(it, depth)); if (it.children) flat(it.children, depth + 1); });
    flat(JSON.parse(mb.dataset.tree || '[]'));
    list.addEventListener('change', e => { if (e.target.matches('.mb-type')) { const it = e.target.closest('.mb-item'); $('.mb-refwrap', it).innerHTML = refSelect(e.target.value, ''); } });
    list.addEventListener('click', e => {
      const b = e.target.closest('button[data-a]'); if (!b) return;
      const it = b.closest('.mb-item'), d = +it.dataset.depth;
      if (b.dataset.a === 'up' && it.previousElementSibling) list.insertBefore(it, it.previousElementSibling);
      if (b.dataset.a === 'down' && it.nextElementSibling) list.insertBefore(it.nextElementSibling, it);
      if (b.dataset.a === 'in') { const prev = it.previousElementSibling; if (prev && d < 2 && +prev.dataset.depth >= d) it.dataset.depth = d + 1; }
      if (b.dataset.a === 'out' && d > 0) it.dataset.depth = d - 1;
      if (b.dataset.a === 'rm') it.remove();
    });
    $('[data-mb-add]', mb)?.addEventListener('click', () => list.appendChild(row({type: 'url', label: ''}, 0)));
    mb.closest('form').addEventListener('submit', () => {
      const root = [], stack = [];
      $$('.mb-item', list).forEach(it => {
        const type = $('.mb-type', it).value;
        const node = {label: $('.mb-label', it).value, type, ref_id: $('.mb-ref', it)?.value || null, url: $('.mb-url', it)?.value || null, children: []};
        const d = +it.dataset.depth;
        while (stack.length > d) stack.pop();
        (stack.length ? stack[stack.length - 1].children : root).push(node);
        stack.push(node);
      });
      out.value = JSON.stringify(root);
    });
  });

  /* Copy buttons */
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-copy]');
    if (!b) return;
    navigator.clipboard?.writeText(b.dataset.copy).then(() => toast('Αντιγράφηκε')).catch(() => {});
  });

  /* Auto-submit selects */
  document.addEventListener('change', e => { if (e.target.matches('[data-autosubmit]')) e.target.form.submit(); });

  /* Lightweight rich-text toolbar for [data-rte] textareas (HTML stays sanitized server-side) */
  $$('textarea[data-rte]').forEach(ta => {
    const bar = document.createElement('div');
    bar.className = 'row-flex';
    bar.style.cssText = 'gap:4px;margin-bottom:4px';
    const tools = [['<b>B</b>', '<strong>', '</strong>'], ['<i>I</i>', '<em>', '</em>'], ['H2', '<h2>', '</h2>'], ['H3', '<h3>', '</h3>'], ['• Λίστα', '<ul>\n<li>', '</li>\n</ul>'], ['¶', '<p>', '</p>'], ['🔗', '<a href="https://">', '</a>']];
    tools.forEach(([label, a, b]) => {
      const btn = document.createElement('button'); btn.type = 'button'; btn.className = 'btn btn-sm'; btn.innerHTML = label;
      btn.onclick = () => { const s = ta.selectionStart, en = ta.selectionEnd, v = ta.value; ta.value = v.slice(0, s) + a + v.slice(s, en) + b + v.slice(en); ta.focus(); ta.dispatchEvent(new Event('input', {bubbles: true})); };
      bar.appendChild(btn);
    });
    ta.parentNode.insertBefore(bar, ta);
  });
})();
