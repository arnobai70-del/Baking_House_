/**
 * Baking House prototype shell — header, mega-menu, search suggestions, drawers, cart store, toasts.
 * Shared by every page. Pages import helpers from here.
 */
import { CATEGORIES, PRODUCTS, COMBOS, productById } from './data.js';
import { formatTaka, formatWeight } from './weight-pricing.js';
import { addLine, repriceCart } from './cart-core.js';

/* ---------------- Icons (simple stroke icons — UI graphics, not product photos) ---------------- */
const ICONS = {
  search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
  cart: '<path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h9.2a1 1 0 0 0 1-.8L20 8H6.2"/><circle cx="9" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/>',
  user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>',
  heart: '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>',
  menu: '<path d="M4 6h16M4 12h16M4 18h16"/>',
  x: '<path d="M6 6l12 12M18 6 6 18"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  minus: '<path d="M5 12h14"/>',
  check: '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
  chevron: '<path d="m9 6 6 6-6 6"/>',
  down: '<path d="m6 9 6 6 6-6"/>',
  truck: '<path d="M2 6h11v10H2zM13 10h4l3 3v3h-7z"/><circle cx="6" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>',
  card: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>',
  quote: '<path d="M5 3h10l4 4v14H5z"/><path d="M14 3v5h5M8 12h8M8 16h6"/>',
  pallet: '<path d="M4 4h7v7H4zM13 4h7v7h-7zM8.5 11v0M3 15h18M5 15v4M12 15v4M19 15v4"/>',
  shield: '<path d="M12 3 4 6v6c0 4.5 3.4 8 8 9 4.6-1 8-4.5 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
  phone: '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
  whisk: '<path d="M14 10 21 3M9 7c-3 3-4 8-2 10s7 1 10-2-4-11-8-8z"/>',
  wheat: '<path d="M12 21V8M12 8c-3 0-4-3-4-5 2 0 4 1 4 5zm0 0c3 0 4-3 4-5-2 0-4 1-4 5zM12 13c-3 0-4-3-4-5M12 13c3 0 4-3 4-5M12 18c-3 0-4-3-4-5M12 18c3 0 4-3 4-5"/>',
  cake: '<path d="M4 21h16v-8H4zM4 16c2 1.5 4 1.5 6 0s4-1.5 6 0 3 1 4 0M12 13V9M12 6a1.5 1.5 0 0 0 0-3"/>',
  wok: '<path d="M3 10h18a9 9 0 0 1-18 0zM21 10l2-2M8 6c0-1 1-1 1-2M12 6c0-1 1-1 1-2"/>',
  oven: '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 8h18M7 5.5h.01M10 5.5h.01"/><rect x="7" y="11" width="10" height="6" rx="1"/>',
  box: '<path d="m3 7 9-4 9 4v10l-9 4-9-4z"/><path d="m3 7 9 4 9-4M12 11v10"/>',
  gift: '<rect x="3" y="8" width="18" height="13" rx="1"/><path d="M12 8v13M3 12h18M12 8S10 3 7.5 4 9 8 12 8zm0 0s2-5 4.5-4S15 8 12 8z"/>',
  mixer: '<path d="M6 21h12M9 21v-4h6v4M7 9h10l-1 8H8zM8 9V5a3 3 0 0 1 3-3h4"/>',
  tray: '<rect x="2" y="7" width="20" height="10" rx="1"/><path d="M5 10h14"/>',
  bottle: '<path d="M11 2h2v3l2 2v14H9V7l2-2z"/><path d="M9 12h6"/>',
  bowl: '<path d="M3 11h18a9 9 0 0 1-18 0z"/><path d="M8 20h8"/>',
  turntable: '<ellipse cx="12" cy="8" rx="9" ry="3"/><path d="M12 11v7M7 21h10"/>',
  photo: '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-8 8"/>',
  store: '<path d="M3 9 5 4h14l2 5M3 9h18v2a3 3 0 0 1-6 0 3 3 0 0 1-6 0 3 3 0 0 1-6 0zM5 13v8h14v-8M10 21v-5h4v5"/>',
  chart: '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
  users: '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.5 3-5.5 7-5.5s7 2 7 5.5M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5c2.5.5 4 2.5 4 5.5"/>',
  tag: '<path d="M3 3h8l10 10-8 8L3 11z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
  repeat: '<path d="M4 9a7 7 0 0 1 12-3l3 3M20 15a7 7 0 0 1-12 3l-3-3M19 4v5h-5M5 20v-5h5"/>',
  map: '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
  settings: '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1"/>',
  lock: '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
  alert: '<path d="M12 3 2 21h20z"/><path d="M12 10v5M12 18h.01"/>',
  info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5h.01"/>',
  home: '<path d="m3 11 9-8 9 8M5 9v12h14V9"/>',
  grid: '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
  list: '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
  filter: '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
  file: '<path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6"/>',
  seo: '<circle cx="10" cy="10" r="6"/><path d="m20 20-5.5-5.5M7.5 10h5M10 7.5v5"/>',
  logo: '<path d="M12 21v-6M8.5 12.5a3.5 3.5 0 1 1 3.5-6 3.5 3.5 0 1 1 3.5 6 3.5 3.5 0 1 1-3.5 3 3.5 3.5 0 1 1-3.5-3z"/>',
};

export function icon(name, cls = 'icon') {
  return `<svg class="${cls}" viewBox="0 0 24 24" aria-hidden="true" focusable="false">${ICONS[name] ?? ICONS.photo}</svg>`;
}

/** Neutral photo-pending placeholder (image policy: no fake product photos). */
export function placeholder(iconName = 'photo', label = 'Photo pending', cls = '') {
  return `<div class="ph ${cls}" role="img" aria-label="${esc(label)}">${icon(iconName)}<span class="ph-tag">${esc(label)}</span></div>`;
}

export function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

/* ---------------- Safe storage (per-viewer convenience only) ---------------- */
export const store = {
  get(key, fallback) {
    try { const v = localStorage.getItem(key); return v == null ? fallback : JSON.parse(v); } catch { return fallback; }
  },
  set(key, value) {
    try { localStorage.setItem(key, JSON.stringify(value)); } catch { /* storage unavailable: state lives in memory only */ }
  },
};

/* ---------------- Prototype role context ---------------- */
// Real app: role comes from the authenticated server session. Here a switcher simulates it.
export const ROLES = { guest: 'Guest', retail: 'Retail customer', business: 'Business account', reseller: 'Reseller', admin: 'Admin' };
export function getRole() { return store.get('bh-role', 'guest'); }
export function pricingRole() {
  const r = getRole();
  return r === 'business' || r === 'reseller' ? r : 'retail';
}

/* ---------------- Catalog accessor ---------------- */
export const catalog = {
  getProduct: (id) => productById(id),
  getCombo: (slug) => COMBOS.find((c) => c.slug === slug),
};

/* ---------------- Cart store ---------------- */
const cartListeners = new Set();
let memCart = store.get('bh-cart', []);
export const cart = {
  lines: () => memCart,
  priced: () => repriceCart(memCart, catalog, { role: pricingRole() }),
  add(add) { memCart = addLine(memCart, add); this.save(); },
  update(key, patch) { memCart = memCart.map((l) => (l.key === key ? { ...l, ...patch } : l)); this.save(); },
  remove(key) { memCart = memCart.filter((l) => l.key !== key); this.save(); },
  clear() { memCart = []; this.save(); },
  save() { store.set('bh-cart', memCart); cartListeners.forEach((fn) => fn()); },
  onChange(fn) { cartListeners.add(fn); },
};

/* ---------------- Toasts (announced via aria-live) ---------------- */
export function toast(message, link) {
  let wrap = document.querySelector('.toast-wrap');
  if (!wrap) {
    wrap = document.createElement('div');
    wrap.className = 'toast-wrap';
    wrap.setAttribute('role', 'status');
    wrap.setAttribute('aria-live', 'polite');
    document.body.append(wrap);
  }
  const el = document.createElement('div');
  el.className = 'toast';
  el.innerHTML = `${icon('check')}<span>${esc(message)}</span>${link ? `<a href="${link.href}">${esc(link.label)}</a>` : ''}`;
  wrap.append(el);
  setTimeout(() => el.remove(), 3200);
}

/* ---------------- Product helpers ---------------- */
export function productPriceHtml(p) {
  if (p.pricingMode === 'QUOTE_ONLY') return '<span class="muted small">Price on request</span>';
  if (p.pricingMode === 'WEIGHT') return `${formatTaka(p.weight.perKgPaisa)} <span class="muted small">/ kg</span>`;
  return `${formatTaka(p.pricePaisa)}${p.compareAtPaisa ? `<s>${formatTaka(p.compareAtPaisa)}</s>` : ''}`;
}

export function isInStock(p) {
  return p.pricingMode === 'WEIGHT' ? p.weight.stockGrams >= p.weight.minGrams : p.stock > 0;
}

export function productCard(p) {
  const cat = CATEGORIES.find((c) => c.slug === p.category);
  const inStock = isInStock(p);
  const badges = [...p.badges];
  if (p.compareAtPaisa) badges.unshift('Sale');
  let action;
  if (p.pricingMode === 'QUOTE_ONLY') action = `<a class="btn btn-outline btn-sm btn-block" href="product.html?slug=${p.slug}#quote">${icon('quote')} Request quote</a>`;
  else if (!inStock) action = `<button class="btn btn-outline btn-sm btn-block" disabled>Out of stock</button>`;
  else if (p.pricingMode === 'WEIGHT') action = `<a class="btn btn-primary btn-sm btn-block" href="product.html?slug=${p.slug}">Choose weight</a>`;
  else action = `<button class="btn btn-primary btn-sm btn-block" data-quick-add="${p.id}">${icon('cart')} Add to cart</button>`;

  return `<article class="card pcard" data-id="${p.id}">
    <div class="pcard-badges">${badges.map((b) => `<span class="chip-badge ${b === 'Sale' ? 'orange' : b === 'Quote only' ? 'navy' : ''}">${esc(b)}</span>`).join('')}</div>
    <div class="media">${placeholder(p.icon, 'Photo pending')}</div>
    <div class="body">
      <div class="meta">${esc(cat?.name ?? '')}</div>
      <h3><a href="product.html?slug=${p.slug}">${esc(p.name)}</a></h3>
      <div class="price tnum">${productPriceHtml(p)}</div>
      ${action}
    </div>
  </article>`;
}

/** Event delegation for quick-add buttons (FIXED products only). */
export function bindQuickAdd(root = document) {
  root.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-quick-add]');
    if (!btn) return;
    e.preventDefault();
    const p = productById(Number(btn.dataset.quickAdd));
    const before = cart.priced().lines.find((l) => l.key === `p:${p.id}`);
    if ((before?.qty ?? 0) + 1 > p.stock) { toast(`Only ${p.stock} in stock.`); return; }
    cart.add({ type: 'product', productId: p.id, qty: 1 });
    const card = btn.closest('.pcard');
    card?.classList.add('added');
    btn.innerHTML = `${icon('check')} Added`;
    setTimeout(() => { card?.classList.remove('added'); btn.innerHTML = `${icon('cart')} Add to cart`; }, 1400);
    toast(`${p.name} added to cart`, { href: 'cart.html', label: 'View cart' });
  });
}

/* ---------------- Header / footer ---------------- */
const NAV_LINKS = [
  ['baking-tools', 'Baking Tools'], ['ingredients', 'Ingredients'], ['restaurant-supply', 'Restaurant Supply'],
  ['kitchen-equipment', 'Kitchen Equipment'], ['packaging', 'Packaging'], ['bulk-wholesale', 'Bulk & Wholesale'], ['combos', 'Combo Offers'],
];

function logoHtml() {
  return `<a class="logo" href="index.html" aria-label="Baking House home">
    <span class="logo-mark"><svg viewBox="0 0 24 24" aria-hidden="true">${ICONS.logo}</svg></span>
    <span class="logo-text"><strong>Baking House</strong><span>Bakery · Restaurant · Café · Food Supply</span></span>
  </a>`;
}

function headerHtml(active) {
  const role = getRole();
  const accountHref = { business: 'business.html', reseller: 'reseller.html', admin: 'admin.html' }[role] ?? 'account.html';
  return `
  <a class="skip-link" href="#main">Skip to content</a>
  <div class="proto-bar"><div class="container">
    <span>${icon('info', 'icon')} <strong>Prototype</strong> — mock data & photo-pending placeholders. Prices, stock and contacts are illustrative.</span>
    <label>View as <select id="role-switch" aria-label="Simulate signed-in role">
      ${Object.entries(ROLES).map(([k, v]) => `<option value="${k}" ${k === role ? 'selected' : ''}>${v}</option>`).join('')}
    </select></label>
  </div></div>
  <div class="topbar"><div class="container">
    <span>Need help? <a href="contact.html">Contact support</a></span>
    <div class="links"><a href="order-track.html">Track order</a><a href="reseller.html">Become a reseller</a><a href="business.html">For business (bulk / wholesale)</a></div>
  </div></div>
  <div class="header-wrap">
  <header class="masthead"><div class="container">
    <button class="icon-btn menu-toggle" id="menu-open" aria-label="Open menu" aria-controls="nav-drawer" aria-expanded="false">${icon('menu')}</button>
    ${logoHtml()}
    <div class="search" id="search">
      <form action="shop.html" role="search" autocomplete="off">
        <label class="sr-only" for="q">Search products</label>
        <input id="q" name="q" type="search" placeholder="Search ingredients, equipment, packaging, tools…"
          role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="suggest-list" value="${esc(new URLSearchParams(location.search).get('q') ?? '')}">
        <button type="submit" aria-label="Search">${icon('search')}</button>
      </form>
      <div class="suggest" id="suggest-list" role="listbox" aria-label="Suggestions"></div>
    </div>
    <div class="head-actions">
      <button class="icon-btn" id="search-mobile" aria-label="Search" style="display:none">${icon('search')}</button>
      <a class="icon-btn" href="${role === 'guest' ? 'login.html' : accountHref}" aria-label="${role === 'guest' ? 'Login or register' : 'My dashboard'}">${icon('user')}</a>
      <span class="label">${role === 'guest' ? '<a href="login.html">Login</a> /<br><a href="login.html#register">Register</a>' : esc(ROLES[role])}</span>
      <a class="icon-btn" href="account.html#wishlist" aria-label="Wishlist">${icon('heart')}</a>
      <button class="icon-btn" id="cart-open" aria-label="Open cart" aria-controls="cart-drawer" aria-expanded="false">${icon('cart')}<span class="badge-count" id="cart-count" aria-live="polite">0</span></button>
    </div>
  </div></header>
  <nav class="catnav" aria-label="Categories"><div class="container">
    <button class="all" id="mega-toggle" aria-expanded="false" aria-controls="mega">${icon('menu')} All Categories</button>
    ${NAV_LINKS.map(([slug, label]) => `<a href="shop.html?cat=${slug}" ${active === slug ? 'aria-current="page"' : ''}>${label}</a>`).join('')}
    <a class="deals" href="combos.html">${icon('tag')} Deals</a>
  </div></nav>
  <div class="mega" id="mega" role="region" aria-label="All categories"><div class="container">
    ${CATEGORIES.map((c) => `<div><h3>${icon(c.icon)}<a href="shop.html?cat=${c.slug}">${esc(c.name)}</a></h3>
      <ul>${c.subs.map((s) => `<li><a href="shop.html?cat=${c.slug}&q=${encodeURIComponent(s.split(' ')[0])}">${esc(s)}</a></li>`).join('')}</ul></div>`).join('')}
  </div></div>
  </div>

  <div class="drawer-overlay" id="overlay"></div>
  <aside class="drawer left" id="nav-drawer" aria-label="Menu" aria-hidden="true" inert>
    <div class="drawer-head">${logoHtml()}<button class="icon-btn" data-close aria-label="Close menu">${icon('x')}</button></div>
    <div class="drawer-body"><nav>
      <a href="shop.html">${icon('grid')} All products</a>
      ${CATEGORIES.map((c) => `<a href="shop.html?cat=${c.slug}">${icon(c.icon)} ${esc(c.name)}</a>`).join('')}
      <a href="business.html">${icon('store')} For business</a>
      <a href="reseller.html">${icon('users')} Reseller program</a>
      <a href="order-track.html">${icon('truck')} Track order</a>
    </nav></div>
  </aside>
  <aside class="drawer right" id="cart-drawer" aria-label="Cart" aria-hidden="true" inert>
    <div class="drawer-head"><h2>Your cart</h2><button class="icon-btn" data-close aria-label="Close cart">${icon('x')}</button></div>
    <div class="drawer-body" id="mini-cart"></div>
    <div class="drawer-foot" id="mini-cart-foot"></div>
  </aside>`;
}

function footerHtml() {
  return `
  <section class="trust-strip" aria-label="Why Baking House"><div class="container">
    <div class="trust">${icon('truck')}<div><strong>Nationwide delivery</strong><span>Courier partners to be confirmed</span></div></div>
    <div class="trust">${icon('card')}<div><strong>bKash, Nagad, card & bank</strong><span>Provider integration pending</span></div></div>
    <div class="trust">${icon('store')}<div><strong>Wholesale & business pricing</strong><span>For verified accounts</span></div></div>
    <div class="trust">${icon('quote')}<div><strong>Request a quote</strong><span>Large & regular orders</span></div></div>
  </div></section>
  <footer class="site-footer">
    <div class="container">
      <div>${logoHtml()}<p>Complete supply marketplace for bakery, restaurant, café, Chinese kitchen and food businesses in Bangladesh.</p></div>
      <div><h3>Shop</h3><ul>${CATEGORIES.slice(0, 6).map((c) => `<li><a href="shop.html?cat=${c.slug}">${esc(c.name)}</a></li>`).join('')}</ul></div>
      <div><h3>Business</h3><ul><li><a href="business.html">Business accounts</a></li><li><a href="reseller.html">Reseller program</a></li><li><a href="business.html#quotes">Request a quote</a></li><li><a href="combos.html">Combo offers</a></li></ul></div>
      <div><h3>Help</h3><ul><li><a href="contact.html">Contact</a></li><li><a href="order-track.html">Track order</a></li><li><a href="#">Delivery policy (draft)</a></li><li><a href="#">Returns policy (draft)</a></li><li><a href="#">Privacy</a></li></ul></div>
    </div>
    <div class="footer-base"><div class="container" style="display:block;padding:0 var(--bh-gutter)">© Baking House · Prototype build — not for publication</div></div>
  </footer>
  <nav class="mobile-tabbar" aria-label="Quick navigation">
    <a href="index.html">${icon('home')}Home</a>
    <a href="shop.html">${icon('grid')}Categories</a>
    <a href="cart.html">${icon('cart')}Cart</a>
    <a href="account.html">${icon('user')}Account</a>
  </nav>`;
}

/* ---------------- Drawer + focus trap ---------------- */
let lastFocus = null;
function openDrawer(id, trigger) {
  const d = document.getElementById(id);
  lastFocus = trigger ?? document.activeElement;
  d.classList.add('open');
  d.removeAttribute('inert');
  d.setAttribute('aria-hidden', 'false');
  document.getElementById('overlay').classList.add('open');
  document.body.classList.add('no-scroll');
  trigger?.setAttribute('aria-expanded', 'true');
  d.querySelector('button, a, input')?.focus();
}
function closeDrawers() {
  document.querySelectorAll('.drawer.open').forEach((d) => {
    d.classList.remove('open');
    d.setAttribute('inert', '');
    d.setAttribute('aria-hidden', 'true');
  });
  document.getElementById('overlay')?.classList.remove('open');
  document.body.classList.remove('no-scroll');
  document.querySelectorAll('[aria-controls$="drawer"]').forEach((b) => b.setAttribute('aria-expanded', 'false'));
  lastFocus?.focus?.();
}
function trapFocus(e) {
  const d = document.querySelector('.drawer.open');
  if (!d || e.key !== 'Tab') return;
  const f = [...d.querySelectorAll('a[href], button:not([disabled]), input, select')].filter((el) => el.offsetParent);
  if (!f.length) return;
  const first = f[0], last = f[f.length - 1];
  if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
  else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
}

/* ---------------- Mini cart ---------------- */
function renderMiniCart() {
  const priced = cart.priced();
  const count = document.getElementById('cart-count');
  if (count) {
    const prev = count.textContent;
    count.textContent = String(priced.itemCount);
    count.dataset.n = String(priced.itemCount);
    if (prev !== count.textContent) { count.classList.remove('bump'); void count.offsetWidth; count.classList.add('bump'); }
  }
  const body = document.getElementById('mini-cart');
  const foot = document.getElementById('mini-cart-foot');
  if (!body) return;
  if (!priced.lines.length) {
    body.innerHTML = `<div class="empty-state">${icon('cart')}<p><strong>Your cart is empty</strong></p><p class="muted small">Browse bakery, restaurant and kitchen supplies.</p><a class="btn btn-primary" href="shop.html">Start shopping</a></div>`;
    foot.innerHTML = '';
    return;
  }
  body.innerHTML = priced.lines.map((l) => `
    <div class="line" style="grid-template-columns:56px 1fr auto">
      ${placeholder(l.product?.icon ?? 'gift', 'Photo pending')}
      <div><h3>${esc(l.name)}</h3><div class="muted small">${esc(l.qtyLabel ?? '')}</div>
        ${l.ok ? '' : `<div class="alert alert-error" style="margin-top:6px">${icon('alert')}<span>${esc(l.error)}</span></div>`}</div>
      <div class="line-total tnum">${l.ok ? formatTaka(l.linePaisa) : '—'}</div>
    </div>`).join('');
  foot.innerHTML = `<div class="sum-row total"><span>Subtotal</span><span class="tnum">${formatTaka(priced.subtotalPaisa)}</span></div>
    <p class="muted small">Shipping calculated at checkout. Prices re-checked on the server before payment.</p>
    <div class="row"><a class="btn btn-outline" style="flex:1" href="cart.html">View cart</a><a class="btn btn-primary" style="flex:1" href="checkout.html">Checkout</a></div>`;
}

/* ---------------- Search suggestions (debounced, keyboard navigable) ---------------- */
export function searchProducts(q) {
  const terms = q.toLowerCase().trim().split(/\s+/).filter(Boolean);
  if (!terms.length) return [];
  return PRODUCTS.filter((p) => {
    const hay = `${p.name} ${p.sku} ${p.tags.join(' ')} ${p.category}`.toLowerCase();
    return terms.every((t) => hay.includes(t));
  });
}

function bindSearch() {
  const input = document.getElementById('q');
  const list = document.getElementById('suggest-list');
  if (!input) return;
  let timer, active = -1;
  const close = () => { list.classList.remove('open'); input.setAttribute('aria-expanded', 'false'); active = -1; };
  const highlight = (text, q) => {
    const safe = esc(text);
    const t = q.trim().split(/\s+/)[0];
    if (!t) return safe;
    const re = new RegExp(`(${t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'ig');
    return safe.replace(re, '<mark>$1</mark>');
  };
  input.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      const q = input.value;
      if (q.trim().length < 2) return close();
      const hits = searchProducts(q).slice(0, 6);
      list.innerHTML = hits.length
        ? hits.map((p, i) => `<a id="sg-${i}" role="option" aria-selected="false" href="product.html?slug=${p.slug}">${icon(p.icon)}<span>${highlight(p.name, q)}</span><span class="cat">${esc(CATEGORIES.find((c) => c.slug === p.category)?.name)}</span></a>`).join('')
          + `<a role="option" aria-selected="false" href="shop.html?q=${encodeURIComponent(q)}">${icon('search')}<span>See all results for “${esc(q)}”</span></a>`
        : `<div class="empty">No matches. Try “chocolate”, “wok” or “GN pan”.</div>`;
      list.classList.add('open');
      input.setAttribute('aria-expanded', 'true');
      active = -1;
    }, 160);
  });
  input.addEventListener('keydown', (e) => {
    const opts = [...list.querySelectorAll('[role="option"]')];
    if (!opts.length || !list.classList.contains('open')) return;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      active = (active + (e.key === 'ArrowDown' ? 1 : -1) + opts.length) % opts.length;
      opts.forEach((o, i) => o.setAttribute('aria-selected', String(i === active)));
      input.setAttribute('aria-activedescendant', opts[active].id || '');
    } else if (e.key === 'Enter' && active >= 0) {
      e.preventDefault();
      location.href = opts[active].href;
    } else if (e.key === 'Escape') close();
  });
  document.addEventListener('click', (e) => { if (!e.target.closest('#search')) close(); });
}

/* ---------------- Init ---------------- */
export function initShell({ active = '', tabbar = true } = {}) {
  const head = document.getElementById('app-header');
  const foot = document.getElementById('app-footer');
  if (head) head.innerHTML = headerHtml(active);
  if (foot) foot.innerHTML = footerHtml();
  if (tabbar) document.body.classList.add('has-tabbar');

  const here = location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.mobile-tabbar a').forEach((a) => { if (a.getAttribute('href') === here) a.setAttribute('aria-current', 'page'); });

  document.getElementById('role-switch')?.addEventListener('change', (e) => { store.set('bh-role', e.target.value); location.reload(); });

  const mega = document.getElementById('mega');
  const megaBtn = document.getElementById('mega-toggle');
  megaBtn?.addEventListener('click', () => {
    const open = !mega.classList.contains('open');
    mega.classList.toggle('open', open);
    megaBtn.setAttribute('aria-expanded', String(open));
    if (open) mega.querySelector('a')?.focus();
  });
  document.addEventListener('click', (e) => {
    if (mega?.classList.contains('open') && !e.target.closest('#mega, #mega-toggle')) { mega.classList.remove('open'); megaBtn.setAttribute('aria-expanded', 'false'); }
  });

  const menuBtn = document.getElementById('menu-open');
  menuBtn?.addEventListener('click', () => openDrawer('nav-drawer', menuBtn));
  const cartBtn = document.getElementById('cart-open');
  cartBtn?.addEventListener('click', () => { renderMiniCart(); openDrawer('cart-drawer', cartBtn); });
  document.getElementById('overlay')?.addEventListener('click', closeDrawers);
  document.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', closeDrawers));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (mega?.classList.contains('open')) { mega.classList.remove('open'); megaBtn.setAttribute('aria-expanded', 'false'); megaBtn.focus(); }
      if (document.querySelector('.drawer.open')) closeDrawers();
    }
    trapFocus(e);
  });

  bindSearch();
  bindQuickAdd();
  cart.onChange(renderMiniCart);
  renderMiniCart();
}

export { formatTaka, formatWeight, CATEGORIES, PRODUCTS, COMBOS };
