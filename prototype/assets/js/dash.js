/**
 * Shared dashboard layout for Retail / Business / Reseller portals.
 * Role isolation here is VISUAL ONLY — the real gate is server-side policy (docs/roles-permissions.md).
 */
import { icon, esc, formatTaka } from './app.js';
import { SAMPLE_ORDERS } from './data.js';

/**
 * @param {HTMLElement} root
 * @param {{theme:string, initials:string, name:string, sub:string, headTitle:string, headText:string,
 *          sections: Array<{id:string, label:string, icon:string, render:()=>string, bind?:(el:HTMLElement)=>void}>}} cfg
 */
export function renderDashboard(root, cfg) {
  root.innerHTML = `<div class="dash">
    <aside class="card dash-side" aria-label="Dashboard navigation">
      <div class="dash-id"><span class="avatar" aria-hidden="true">${esc(cfg.initials)}</span><div><strong>${esc(cfg.name)}</strong><div class="muted small">${esc(cfg.sub)}</div></div></div>
      <nav>${cfg.sections.map((s) => `<a href="#${s.id}" data-sec="${s.id}">${icon(s.icon)} ${esc(s.label)}</a>`).join('')}
        <a href="index.html" id="signout">${icon('lock')} Sign out</a></nav>
    </aside>
    <section>
      <div class="role-head ${cfg.theme}">${icon('user', 'icon icon-lg')}<div><h1>${esc(cfg.headTitle)}</h1><p>${esc(cfg.headText)}</p></div></div>
      <div id="dash-body"></div>
    </section>
  </div>`;
  const body = root.querySelector('#dash-body');
  const show = () => {
    const id = location.hash.slice(1);
    const sec = cfg.sections.find((s) => s.id === id) ?? cfg.sections[0];
    root.querySelectorAll('[data-sec]').forEach((a) => a.setAttribute('aria-current', String(a.dataset.sec === sec.id)));
    body.innerHTML = sec.render();
    sec.bind?.(body);
  };
  window.addEventListener('hashchange', show);
  root.querySelector('#signout').addEventListener('click', () => { try { localStorage.setItem('bh-role', '"guest"'); } catch { /* ignore */ } });
  show();
}

export function panel(title, inner, action = '') {
  return `<div class="card" style="margin-bottom:16px"><div class="panel-title"><h2>${esc(title)}</h2>${action}</div>${inner}</div>`;
}

export function kpis(items) {
  return `<div class="kpis">${items.map(([label, val, note]) => `<div class="card kpi"><div class="k-label">${esc(label)}</div><div class="k-val tnum">${esc(val)}</div>${note ? `<div class="muted small">${esc(note)}</div>` : ''}</div>`).join('')}</div>`;
}

const statusClass = (s) => ({ Delivered: 'ok', Shipped: 'info', Processing: 'warn', Approved: 'ok', 'Quote issued': 'info', Requested: 'warn' })[s] ?? '';

export function ordersTable(orders = SAMPLE_ORDERS) {
  if (!orders.length) return emptyBlock('cart', 'No orders yet', 'Your orders will appear here.');
  return `<div class="table-wrap"><table class="table"><thead><tr><th>Order</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
    ${orders.map((o) => `<tr><td class="tnum"><strong>${o.ref}</strong></td><td>${o.date}</td><td>${o.items}</td><td class="tnum">${formatTaka(o.totalPaisa)}</td>
      <td><span class="status ${statusClass(o.status)}">${o.status}</span></td>
      <td><a class="btn btn-outline btn-sm" href="order-track.html?ref=${o.ref}">Track</a> <button class="btn btn-ghost btn-sm" data-reorder="${o.ref}">Reorder</button></td></tr>`).join('')}
  </tbody></table></div>`;
}

export function emptyBlock(ic, title, text, cta = '') {
  return `<div class="empty-state">${icon(ic)}<p><strong>${esc(title)}</strong></p><p class="muted small">${esc(text)}</p>${cta}</div>`;
}

export function statusPill(s) { return `<span class="status ${statusClass(s)}">${esc(s)}</span>`; }

/** Shown when the simulated role cannot access this portal. */
export function deniedHtml(portal, extra = '') {
  return `<div class="card denied" style="margin:24px 0">${icon('lock')}
    <h1 style="font-size:var(--bh-fs-xl)">${esc(portal)} is restricted</h1>
    <p class="muted">This area is only for approved ${esc(portal.toLowerCase().replace(' dashboard', ''))} accounts. Your current account cannot see its prices or records.</p>
    ${extra}
  </div>`;
}
