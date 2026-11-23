/**
 * NadimShop.com — scroll-in motion (progressive enhancement, no library).
 * Cards and sections that start BELOW the first screen fade and rise into place when scrolled into view,
 * with a small stagger inside a grid. Content already on screen at load is never hidden, so the first paint and
 * LCP are unaffected; without JS or with prefers-reduced-motion nothing is hidden at all. Transform and opacity only.
 */
const TARGETS = '.section-head, .dept-tabs .tabs, .grid > .pcard, .commercial-panel, .b2b-x > *, .b2b-band > .card, .portal-steps > *, .combo, .shortcuts > a, .kpi, .category-head';
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

/* ---------------- Cinematic layer: hero depth, magnetic CTAs, header lift ----------------
   Pointer effects run only for a real mouse on a hover-capable device and never with reduced motion. Pointer
   input is coalesced into one requestAnimationFrame write of CSS variables; the CSS animates `translate`
   (compositor-only), so nothing here causes layout. */
const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const hasFinePointer = () => window.matchMedia('(hover: hover) and (pointer: fine)').matches;
const clamp = (value, limit) => Math.max(-limit, Math.min(limit, value));

/** Write CSS variables at most once per frame. */
function frameWriter(el) {
  let frame = 0;
  let pending = {};
  return (vars) => {
    pending = { ...pending, ...vars };
    if (frame) return;
    frame = requestAnimationFrame(() => {
      frame = 0;
      Object.entries(pending).forEach(([name, value]) => el.style.setProperty(name, `${value.toFixed(2)}px`));
      pending = {};
    });
  };
}

/** Hero: the scene drifts up to 8 px against the pointer (the stroke follows the other way in CSS); the looping
 *  float pauses while the hero is off screen. */
function initHeroDepth() {
  const hero = document.querySelector('.hero-x');
  if (!hero) return;
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(([entry]) => hero.classList.toggle('is-offscreen', !entry.isIntersecting)).observe(hero);
  }
  if (prefersReducedMotion() || !hasFinePointer()) return;

  const stage = hero.querySelector('.hero-x-stage') || hero;
  const write = frameWriter(hero);
  const MAX = 8;
  stage.addEventListener('pointermove', (event) => {
    if (event.pointerType !== 'mouse') return;
    const box = stage.getBoundingClientRect();
    const x = (event.clientX - box.left) / box.width - 0.5;
    const y = (event.clientY - box.top) / box.height - 0.5;
    write({ '--hx': clamp(-x * 2 * MAX, MAX), '--hy': clamp(-y * 2 * MAX * 0.6, MAX) });
  });
  stage.addEventListener('pointerleave', () => write({ '--hx': 0, '--hy': 0 }));
}

/** Magnetic CTAs ([data-magnetic]): within ~40 px of a button, it leans up to 4 px toward the pointer. */
function initMagnetic() {
  if (prefersReducedMotion() || !hasFinePointer()) return;
  const RANGE = 40;
  const MAX = 4;
  document.querySelectorAll('[data-magnetic]').forEach((button) => {
    const zone = button.closest('.hero-x') || button.parentElement;
    const write = frameWriter(button);
    zone.addEventListener('pointermove', (event) => {
      if (event.pointerType !== 'mouse') return;
      const box = button.getBoundingClientRect();
      const dx = event.clientX - (box.left + box.width / 2);
      const dy = event.clientY - (box.top + box.height / 2);
      const reachX = box.width / 2 + RANGE;
      const reachY = box.height / 2 + RANGE;
      if (Math.abs(dx) > reachX || Math.abs(dy) > reachY) {
        write({ '--mx': 0, '--my': 0 });
        return;
      }
      write({ '--mx': clamp((dx / reachX) * MAX, MAX), '--my': clamp((dy / reachY) * MAX * 0.75, MAX) });
    });
    zone.addEventListener('pointerleave', () => write({ '--mx': 0, '--my': 0 }));
    button.addEventListener('blur', () => write({ '--mx': 0, '--my': 0 }));
  });
}

/** Header: a soft shadow once the utility bar has scrolled away (IntersectionObserver, no scroll listener). */
function initHeaderLift() {
  const masthead = document.querySelector('.masthead');
  const marker = document.querySelector('.topbar');
  if (!masthead || !marker || !('IntersectionObserver' in window)) return;
  new IntersectionObserver(([entry]) => masthead.classList.toggle('is-raised', !entry.isIntersecting)).observe(marker);
}

initScrollReveal();
initCountUp();
initHeroDepth();
initMagnetic();
initHeaderLift();
