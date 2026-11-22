/**
 * NadimShop.com — scroll-in motion (progressive enhancement, no library).
 * Cards and sections that start BELOW the first screen fade and rise into place when scrolled into view,
 * with a small stagger inside a grid. Content already on screen at load is never hidden, so the first paint and
 * LCP are unaffected; without JS or with prefers-reduced-motion nothing is hidden at all. Transform and opacity only.
 */
const TARGETS = '.section-head, .grid > .pcard, .commercial-panel, .b2b-x > *, .b2b-band > .card, .portal-steps > *, .combo, .shortcuts > a, .kpi';
const STAGGER_MS = 60;
const MAX_STAGGER = 5;

function initScrollReveal() {
  if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const fold = window.innerHeight;
  const pending = [...document.querySelectorAll(TARGETS)].filter((el) => el.getBoundingClientRect().top > fold);
  if (pending.length === 0) return;

  const observer = new IntersectionObserver((entries) => {
    entries.filter((entry) => entry.isIntersecting).forEach((entry) => {
      const el = entry.target;
      const siblings = el.parentElement ? [...el.parentElement.children].filter((child) => child.classList.contains('rv')) : [];
      const index = Math.min(Math.max(0, siblings.indexOf(el)), MAX_STAGGER);
      el.style.transitionDelay = `${index * STAGGER_MS}ms`;
      el.classList.add('rv-in');
      observer.unobserve(el);
      el.addEventListener('transitionend', () => { el.style.transitionDelay = ''; el.classList.remove('rv', 'rv-in'); }, { once: true });
    });
  }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

  pending.forEach((el) => { el.classList.add('rv'); observer.observe(el); });
}

/**
 * Dashboard KPIs: plain whole numbers count up once when first shown (never money, which keeps its server
 * formatting). The final text is always the server's value.
 */
function initCountUp() {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  document.querySelectorAll('.kpi .k-val').forEach((el) => {
    const text = el.textContent.trim();
    if (!/^\d{1,6}$/.test(text) || text === '0') return;
    const target = Number(text);
    const start = performance.now();
    const duration = 600;
    const step = (now) => {
      const t = Math.min(1, (now - start) / duration);
      el.textContent = String(Math.round(target * (1 - (1 - t) ** 3)));
      if (t < 1) requestAnimationFrame(step); else el.textContent = text;
    };
    requestAnimationFrame(step);
  });
}

initScrollReveal();
initCountUp();
