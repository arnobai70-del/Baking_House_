/**
 * 2D fallback preview (plain SVG) for phones without WebGL, low-memory devices and reduced-motion users:
 * a front view of the tiers with cream colour, side pattern, drip and borders, plus a top view carrying the
 * hand-made top design. Same inputs as the 3D preview, no animation.
 */
import { baseDiameterCm, drawPatternTile, outlinePath, seeded, TIER_HEIGHT_CM } from './geometry.js';

const NS = 'http://www.w3.org/2000/svg';
const el = (name, attrs = {}) => {
  const node = document.createElementNS(NS, name);
  for (const [k, v] of Object.entries(attrs)) node.setAttribute(k, v);
  return node;
};

export function createPreview2d(container, { geometry, label, topLabel }) {
  const svg = el('svg', { viewBox: '0 0 400 400', class: 'cake-2d-svg', role: 'img', 'aria-label': label });
  container.replaceChildren(svg);
  let spec = null;
  let topUrl = '';
  let topCanvas = null;

  const patternUrl = (style, colour) => {
    const c = document.createElement('canvas');
    c.width = c.height = 64;
    drawPatternTile(c.getContext('2d'), style, colour, 64);
    return c.toDataURL();
  };

  const draw = () => {
    svg.replaceChildren();
    if (!spec) return;
    const defs = el('defs');
    svg.append(defs);
    const tiers = Math.max(1, Math.min(3, spec.tiers || 1));
    const base = baseDiameterCm(spec.grams, tiers, geometry);
    const unit = 240 / Math.max(base, 20); // px per cm; the bottom tier is at most 240 px wide
    const round = spec.shape === 'round' || spec.shape === 'heart';
    // Centre the whole stack vertically (one small tier should not sit in a corner).
    const stack = Array.from({ length: tiers }, (_, t) => (TIER_HEIGHT_CM - t * 0.6) * unit).reduce((a, b) => a + b, 0);
    let bottom = Math.max(250, Math.min(352, 215 + stack / 2));
    svg.append(el('ellipse', { cx: 140, cy: bottom + 6, rx: 132, ry: 14, fill: '#e6eaee' }));
    if (spec.pattern) {
      const p = el('pattern', { id: 'cake-pat', width: 18, height: 18, patternUnits: 'userSpaceOnUse' });
      p.append(el('image', { href: patternUrl(spec.pattern.style, spec.pattern.colour), width: 18, height: 18 }));
      defs.append(p);
    }
    const random = seeded(Math.round(base * 10));
    for (let t = 0; t < tiers; t++) {
      const width = base * geometry.tierScale[t] * unit;
      const height = (TIER_HEIGHT_CM - t * 0.6) * unit;
      const x = 140 - width / 2;
      const top = bottom - height;
      const ry = round ? width * 0.12 : width * 0.08;
      const g = el('g');
      g.append(el('path', { d: `M${x} ${top} L${x} ${bottom} A${width / 2} ${ry} 0 0 0 ${x + width} ${bottom} L${x + width} ${top} Z`, fill: spec.base, stroke: 'rgba(16,59,86,.18)' }));
      if (spec.pattern) g.append(el('path', { d: `M${x} ${top} L${x} ${bottom} A${width / 2} ${ry} 0 0 0 ${x + width} ${bottom} L${x + width} ${top} Z`, fill: 'url(#cake-pat)', opacity: 0.9 }));
      g.append(el('ellipse', { cx: 140, cy: top, rx: width / 2, ry, fill: spec.base, stroke: 'rgba(16,59,86,.18)' }));
      if (spec.drip && t === tiers - 1) {
        g.append(el('ellipse', { cx: 140, cy: top, rx: width / 2, ry, fill: spec.drip }));
        // Drips hang from the front edge of the top (y on the ellipse below its centre line).
        const drops = Math.max(6, Math.round(width / 11));
        for (let i = 0; i <= drops; i++) {
          const xi = x + 4 + (i / drops) * (width - 8);
          const edge = top + ry * Math.sqrt(Math.max(0, 1 - ((xi - 140) / (width / 2)) ** 2));
          const len = 6 + random() * (height * 0.45);
          const r = 3.2;
          g.append(el('path', { d: `M${xi - r} ${edge - 2} L${xi - r} ${edge + len} Q${xi} ${edge + len + r * 1.8} ${xi + r} ${edge + len} L${xi + r} ${edge - 2} Z`, fill: spec.drip }));
        }
      }
      const dots = (y, b) => {
        const n = Math.round(width / (b.style === 'dots' ? 10 : 7));
        for (let i = 0; i <= n; i++) {
          const r = b.style === 'dots' ? 2.2 : b.style === 'bead' ? 3.2 : 3.6;
          g.append(el(b.style === 'shell' ? 'ellipse' : 'circle', { cx: x + (i / n) * width, cy: y, r, rx: r * 1.4, ry: r, fill: b.colour }));
        }
      };
      if (spec.borderBottom) {
        if (['rope', 'ruffle'].includes(spec.borderBottom.style)) g.append(el('rect', { x, y: bottom - 5, width, height: 6, rx: 3, fill: spec.borderBottom.colour }));
        else dots(bottom - 2, spec.borderBottom);
      }
      if (spec.borderTop) {
        if (['rope', 'ruffle'].includes(spec.borderTop.style)) g.append(el('rect', { x, y: top - 3, width, height: 6, rx: 3, fill: spec.borderTop.colour }));
        else dots(top, spec.borderTop);
      }
      svg.append(g);
      bottom = top + ry * 0.4;
    }

    // Top view with the hand-made design.
    const size = 110;
    const cx = 330;
    const cy = 90;
    const clip = el('clipPath', { id: 'cake-top-clip' });
    clip.append(el('path', { d: outlinePath(spec.shape, size, cx, cy) }));
    defs.append(clip);
    const caption = el('text', { x: cx, y: 22, 'text-anchor': 'middle', 'font-size': 12, fill: '#5b6b78' });
    caption.textContent = topLabel;
    svg.append(caption);
    svg.append(el('path', { d: outlinePath(spec.shape, size, cx, cy), fill: spec.drip || spec.base, stroke: 'rgba(16,59,86,.25)' }));
    if (topUrl) svg.append(el('image', { href: topUrl, x: cx - size / 2, y: cy - size / 2, width: size, height: size, 'clip-path': 'url(#cake-top-clip)' }));
  };

  return {
    update(next) { spec = next; draw(); },
    setTop(canvasEl) { topCanvas = canvasEl; topUrl = canvasEl ? canvasEl.toDataURL('image/png') : ''; draw(); },
    refreshTop() { if (topCanvas) { topUrl = topCanvas.toDataURL('image/png'); draw(); } },
    setView() {},
    zoom() {},
    /** PNG of the drawing (for "Download preview" and the order). */
    snapshot() {
      return new Promise((resolve) => {
        const img = new Image();
        // data: (not blob:) URLs, which the site's Content-Security-Policy allows for images.
        const url = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(new XMLSerializer().serializeToString(svg))}`;
        img.onload = () => {
          const c = document.createElement('canvas');
          c.width = c.height = 800;
          const ctx = c.getContext('2d');
          ctx.fillStyle = '#ffffff';
          ctx.fillRect(0, 0, 800, 800);
          ctx.drawImage(img, 0, 0, 800, 800);
          resolve(c.toDataURL('image/png'));
        };
        img.onerror = () => resolve('');
        img.src = url;
      });
    },
    dispose() { svg.remove(); },
  };
}
