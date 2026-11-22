/**
 * NadimShop.com storefront — progressive enhancement over server-rendered pages.
 * Everything works without JS except live price previews and search suggestions.
 * Motion: CSS transform/opacity only; prefers-reduced-motion handled in tokens.css.
 */
import { parseQuantity, formatWeight, formatTaka, quoteWeightLine } from './weight-pricing.js';

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const ALERT_ICON = '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2 21h20z"/><path d="M12 10v5M12 18h.01"/></svg>';

/* ---------------- Mega-menu ---------------- */
function initMegaMenu() {
  const mega = $('#mega');
  const toggle = $('#mega-toggle');
  if (!mega || !toggle) return;
  const setOpen = (open) => {
    mega.classList.toggle('open', open);
    toggle.setAttribute('aria-expanded', String(open));
    if (open) $('a', mega)?.focus();
  };
  toggle.addEventListener('click', () => setOpen(!mega.classList.contains('open')));
  document.addEventListener('click', (event) => {
    if (mega.classList.contains('open') && !event.target.closest('#mega, #mega-toggle')) setOpen(false);
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && mega.classList.contains('open')) { setOpen(false); toggle.focus(); }
  });
}

/* ---------------- Drawers (mobile menu, filters) with focus trap ---------------- */
let lastFocus = null;
function openPanel(panel, overlay, trigger) {
  lastFocus = trigger ?? document.activeElement;
  panel.classList.add('open');
  panel.removeAttribute('inert');
  panel.setAttribute('aria-hidden', 'false');
  overlay?.classList.add('open');
  document.body.classList.add('no-scroll');
  trigger?.setAttribute('aria-expanded', 'true');
  $('a, button, input, select', panel)?.focus();
}
function closePanels() {
  $$('.drawer.open, .filters.open').forEach((panel) => {
    panel.classList.remove('open');
    if (panel.classList.contains('drawer')) { panel.setAttribute('inert', ''); panel.setAttribute('aria-hidden', 'true'); }
  });
  $$('.drawer-overlay.open').forEach((overlay) => overlay.classList.remove('open'));
  document.body.classList.remove('no-scroll');
  $$('[aria-expanded="true"][data-drawer-open], [data-filters-open]').forEach((button) => button.setAttribute('aria-expanded', 'false'));
  lastFocus?.focus?.();
}
function initDrawers() {
  $$('[data-drawer-open]').forEach((button) => button.addEventListener('click', () => openPanel($(`#${button.dataset.drawerOpen}`), $('#overlay'), button)));
  $$('[data-drawer-close], [data-filters-close]').forEach((button) => button.addEventListener('click', closePanels));
  $$('.drawer-overlay').forEach((overlay) => overlay.addEventListener('click', closePanels));
  $$('[data-filters-open]').forEach((button) => button.addEventListener('click', () => openPanel($('#filters'), $('#filters-overlay'), button)));
  document.addEventListener('keydown', (event) => {
    const open = $('.drawer.open, .filters.open');
    if (!open) return;
    if (event.key === 'Escape') { closePanels(); return; }
    if (event.key !== 'Tab') return;
    const focusable = $$('a[href], button:not([disabled]), input, select', open).filter((el) => el.offsetParent !== null);
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
}

/* ---------------- Search suggestions (debounced, keyboard navigable) ---------------- */
function initSearch() {
  const box = $('#search');
  const input = $('#site-q');
  const list = $('#suggest-list');
  if (!box || !input || !list) return;
  let timer;
  let active = -1;
  let controller;
  const close = () => { list.classList.remove('open'); input.setAttribute('aria-expanded', 'false'); active = -1; };

  input.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(async () => {
      const term = input.value.trim();
      if (term.length < 2) { close(); return; }
      controller?.abort();
      controller = new AbortController();
      /* Skeleton rows while suggestions load (fixed height: no layout shift). */
      list.innerHTML = '<div class="suggest-skeleton" aria-hidden="true"><span class="skeleton"></span><span class="skeleton"></span><span class="skeleton"></span></div>';
      list.classList.add('open');
      try {
        const response = await fetch(`${box.dataset.suggestUrl}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
        if (!response.ok) { close(); return; }
        const { data, all_results_url: allUrl } = await response.json();
        list.innerHTML = (data.length
          ? data.map((item, i) => `<a id="sg-${i}" role="option" aria-selected="false" href="${esc(item.url)}"><span>${esc(item.name)}</span><span class="cat">${esc(item.category)}</span></a>`).join('')
          : '<div class="empty">No matches. Try “chocolate”, “wok” or “GN pan”.</div>')
          + `<a role="option" aria-selected="false" href="${esc(allUrl)}">See all results for “${esc(term)}”</a>`;
        list.classList.add('open');
        input.setAttribute('aria-expanded', 'true');
        active = -1;
      } catch (error) { if (error.name !== 'AbortError') close(); /* offline: the form still submits normally */ }
    }, 180);
  });
  input.addEventListener('keydown', (event) => {
    const options = $$('[role="option"]', list);
    if (!options.length || !list.classList.contains('open')) return;
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      active = (active + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
      options.forEach((option, i) => option.setAttribute('aria-selected', String(i === active)));
      input.setAttribute('aria-activedescendant', options[active].id || '');
    } else if (event.key === 'Enter' && active >= 0) {
      event.preventDefault();
      window.location.href = options[active].href;
    } else if (event.key === 'Escape') {
      close();
    }
  });
  document.addEventListener('click', (event) => { if (!event.target.closest('#search')) close(); });
}

/* ---------------- Form feedback: busy state + double-submit guard ----------------
   The pressed button shows a spinner (it is NOT disabled, so its name/value is still sent), a
   second submit of the same POST form is ignored, and filter forms dim the results they replace.
   Everything resets when the page is restored from the back/forward cache. */
function initBusyForms() {
  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
    if (form.dataset.submitting === '1' && form.method.toLowerCase() === 'post') {
      event.preventDefault();
      return;
    }
    form.dataset.submitting = '1';
    const button = event.submitter;
    if (button) {
      button.classList.add('is-busy');
      button.setAttribute('aria-busy', 'true');
    }
    const target = form.dataset.busyTarget ? $(form.dataset.busyTarget) : null;
    target?.setAttribute('aria-busy', 'true');
  });
  window.addEventListener('pageshow', () => {
    $$('form[data-submitting]').forEach((form) => delete form.dataset.submitting);
    $$('.is-busy').forEach((el) => { el.classList.remove('is-busy'); el.removeAttribute('aria-busy'); });
    $$('[aria-busy="true"]').forEach((el) => el.removeAttribute('aria-busy'));
  });
}

/* ---------------- Sort select auto-submit ---------------- */
function initAutoSubmit() {
  $$('form[data-autosubmit] select').forEach((select) => select.addEventListener('change', () => select.form.requestSubmit()));
}

/* ---------------- Tabs (keyboard arrows) ---------------- */
function initTabs() {
  $$('[data-tabs]').forEach((root) => {
    const tabs = $$('[role="tab"]', root);
    const select = (tab) => {
      tabs.forEach((t) => {
        const on = t === tab;
        t.setAttribute('aria-selected', String(on));
        t.tabIndex = on ? 0 : -1;
        $(`#${t.getAttribute('aria-controls')}`).hidden = !on;
      });
      tab.focus();
    };
    tabs.forEach((tab, i) => {
      tab.addEventListener('click', () => select(tab));
      tab.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowRight') select(tabs[(i + 1) % tabs.length]);
        if (event.key === 'ArrowLeft') select(tabs[(i - 1 + tabs.length) % tabs.length]);
      });
    });
  });
}

/* ---------------- Product buy box: weight calculator + fixed quantity ---------------- */
function initBuyBox() {
  const root = $('#buy-box');
  if (!root) return;
  const variants = JSON.parse(root.dataset.product);
  const els = {
    rate: $('#rate'), stock: $('#stock-line'), sku: $('#p-sku'), weightBox: $('#weight-box'), fixedBox: $('#fixed-box'),
    chips: $('#weight-chips'), input: $('#custom-qty'), unit: $('#qty-unit'), hint: $('#qty-hint'), error: $('#qty-error'),
    total: $('#calc-total'), totalFor: $('#calc-for'), fixedQty: $('#fixed-qty'), fixedTotal: $('#fixed-total'),
    fixedError: $('#fixed-error'), actions: $('#buy-actions'),
  };
  let variant = variants[0];

  const showError = (target, message, suggestions = []) => {
    target.innerHTML = message ? `<div class="alert alert-error" style="margin-top:6px">${ALERT_ICON}<div>${esc(message)}${suggestions.length
      ? `<div class="suggest-btns">${suggestions.map((g) => `<button type="button" class="btn btn-outline btn-sm" data-use="${g}">Use ${esc(formatWeight(g))}</button>`).join('')}</div>` : ''}</div></div>` : '';
  };

  const tick = (el) => { el.classList.remove('tick'); void el.offsetWidth; el.classList.add('tick'); };

  const rule = () => ({ ...variant.weight, stockGrams: variant.weight.stockGrams });

  const showWeight = (grams) => {
    $$('.wchip[data-g]', els.chips).forEach((chip) => chip.setAttribute('aria-pressed', String(Number(chip.dataset.g) === grams)));
    if (!Number.isInteger(grams)) { els.total.textContent = '—'; els.totalFor.textContent = 'Choose a quantity'; showError(els.error, ''); return; }
    const quote = quoteWeightLine(rule(), grams, { stockGrams: variant.weight.stockGrams, role: variant.audience });
    if (!quote.ok) {
      els.input.setAttribute('aria-invalid', 'true');
      showError(els.error, quote.error, quote.suggestions);
      els.total.textContent = '—';
      els.totalFor.textContent = 'Fix the quantity above';
      return;
    }
    els.input.removeAttribute('aria-invalid');
    showError(els.error, '');
    els.total.textContent = formatTaka(quote.linePaisa);
    tick(els.total);
    const mixed = grams >= 1000 && grams % 1000 ? ` (${formatWeight(grams, 'mixed')})` : '';
    els.totalFor.textContent = `for ${formatWeight(grams)}${mixed} at ${formatTaka(quote.perKgPaisa)}/kg`;
  };

  const fromInput = () => {
    if (els.input.value.trim() === '') { showWeight(null); return; }
    const parsed = parseQuantity(els.input.value, els.unit.value);
    if (!parsed.ok) { els.input.setAttribute('aria-invalid', 'true'); showError(els.error, parsed.error); els.total.textContent = '—'; return; }
    showWeight(parsed.grams);
  };

  /* Unit price for a quantity: the caller's own highest tier not above qty, else the standard price. */
  const unitRate = (qty) => (variant.tiers ?? [])
    .filter((tier) => qty >= tier.minQuantity)
    .sort((a, b) => b.minQuantity - a.minQuantity)[0]?.ratePaisa ?? variant.pricePaisa;

  const showFixed = () => {
    const qty = Number.parseInt(els.fixedQty.value, 10);
    const min = variant.minQuantity ?? 1;
    let message = '';
    if (!Number.isInteger(qty) || qty < min) message = `Minimum order is ${min}.`;
    else if (qty > variant.stock) message = variant.stock > 0 ? `Only ${variant.stock} available.` : 'Out of stock.';
    showError(els.fixedError, message, []);
    els.fixedTotal.textContent = message ? '—' : formatTaka(unitRate(qty) * qty);
    if (!message) tick(els.fixedTotal);
  };

  const selectVariant = (next) => {
    variant = next;
    if (els.sku) els.sku.textContent = variant.sku;
    const isWeight = variant.pricingMode === 'weight';
    const isFixed = variant.pricingMode === 'fixed';
    els.weightBox.hidden = !isWeight;
    els.fixedBox.hidden = !isFixed;
    els.actions.hidden = variant.pricingMode === 'quote_only';

    if (variant.pricingMode === 'quote_only') {
      els.rate.innerHTML = '<span style="font-size:var(--bh-fs-xl)">Price on request</span>';
    } else {
      const price = isWeight ? variant.weight.perKgPaisa : variant.pricePaisa;
      els.rate.innerHTML = `${esc(formatTaka(price))} <small>${esc(variant.unitLabel)}</small>${variant.compareAtPaisa ? ` <small><s>${esc(formatTaka(variant.compareAtPaisa))}</s></small>` : ''}`;
    }
    els.stock.innerHTML = variant.inStock
      ? `<span class="stock-ok">In stock${isWeight ? ` — ${esc(formatWeight(variant.weight.stockGrams))} available` : ''}</span>`
      : '<span class="stock-low">Out of stock</span>';

    if (isWeight) {
      const w = variant.weight;
      els.hint.textContent = `Min ${formatWeight(w.minGrams)} · steps of ${formatWeight(w.stepGrams)} · max ${formatWeight(w.maxGrams)}`;
      els.chips.innerHTML = (w.presets ?? []).map((g) => {
        const q = quoteWeightLine(w, g, { role: variant.audience });
        return `<button type="button" class="wchip" data-g="${g}" aria-pressed="false"><strong>${esc(formatWeight(g))}</strong><span class="tnum">${q.ok ? esc(formatTaka(q.linePaisa)) : '—'}</span></button>`;
      }).join('');
      const initial = (w.presets ?? []).includes(700) ? 700 : w.minGrams;
      els.unit.value = 'g';
      els.input.value = String(initial);
      showWeight(initial);
    }
    if (isFixed) { els.fixedQty.value = String(variant.minQuantity ?? 1); showFixed(); }
  };

  els.chips?.addEventListener('click', (event) => {
    const chip = event.target.closest('.wchip[data-g]');
    if (!chip) return;
    els.unit.value = 'g';
    els.input.value = chip.dataset.g;
    showWeight(Number(chip.dataset.g));
  });
  els.input?.addEventListener('input', fromInput);
  els.unit?.addEventListener('change', fromInput);
  els.error?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-use]');
    if (!button) return;
    els.unit.value = 'g';
    els.input.value = button.dataset.use;
    showWeight(Number(button.dataset.use));
    els.input.focus();
  });
  $$('[data-step]', els.fixedBox).forEach((button) => button.addEventListener('click', () => {
    const min = variant.minQuantity ?? 1;
    els.fixedQty.value = String(Math.max(min, (Number.parseInt(els.fixedQty.value, 10) || min) + Number(button.dataset.step)));
    showFixed();
  }));
  els.fixedQty?.addEventListener('input', showFixed);
  $$('input[name="variant"]', root).forEach((radio) => radio.addEventListener('change', () => {
    $$('.variant-chip', root).forEach((chip) => chip.setAttribute('aria-pressed', String($('input', chip).checked)));
    selectVariant(variants.find((v) => String(v.id) === radio.value));
  }));
  $$('.variant-chip', root).forEach((chip) => chip.setAttribute('aria-pressed', String($('input', chip).checked)));

  selectVariant(variant);
}

/* ---------------- Quote links (Phase 9.5) ---------------- */
/** "Request a quote" links open the quote disclosure instead of scrolling to a closed box. */
function initQuoteLinks() {
  const box = document.getElementById('quote');
  if (!(box instanceof HTMLDetailsElement)) return;
  const open = () => { box.open = true; };
  if (window.location.hash === '#quote') open();
  $$('a[href="#quote"]').forEach((link) => link.addEventListener('click', open));
}

initMegaMenu();
initDrawers();
initSearch();
initBusyForms();
initAutoSubmit();
initTabs();
initBuyBox();
initQuoteLinks();
