/**
 * Cake sizes and outlines shared by the 3D preview, the 2D fallback and the top-design editor.
 * The size maths mirrors App\Services\Cakes\CakeGeometry on the server (plain arithmetic, no AI).
 */
export const DEFAULT_GEOMETRY = { cmPerSqrtKg: 20, tierScale: [1, 0.72, 0.52], dpiGood: 150, dpiFair: 100 };

export const TIER_HEIGHT_CM = 9;

export function baseDiameterCm(grams, tiers, g = DEFAULT_GEOMETRY) {
  const n = Math.max(1, Math.min(3, tiers | 0));
  const share = g.tierScale.slice(0, n).reduce((sum, s) => sum + s * s, 0);
  return Math.round(g.cmPerSqrtKg * Math.sqrt(Math.max(1, grams) / share / 1000) * 10) / 10;
}

export function topDiameterCm(grams, tiers, g = DEFAULT_GEOMETRY) {
  const n = Math.max(1, Math.min(3, tiers | 0));
  return Math.round(baseDiameterCm(grams, n, g) * g.tierScale[n - 1] * 10) / 10;
}

/** Effective print resolution (dots per inch) and a plain-language level. */
export function printQuality(sourcePixels, printWidthCm, g = DEFAULT_GEOMETRY) {
  const dpi = printWidthCm <= 0 ? 0 : Math.floor(sourcePixels / (printWidthCm / 2.54));
  return { dpi, level: dpi >= g.dpiGood ? 'good' : dpi >= g.dpiFair ? 'fair' : 'low' };
}

/** Width : depth of each shape's outline (width = 1). */
const SHAPE_SIZE = { round: [1, 1], square: [1, 1], rectangle: [1, 0.68], heart: [1, 0.92], number: [1, 0.7] };

export function shapeAspect(shape) {
  return SHAPE_SIZE[shape] || SHAPE_SIZE.round;
}

function roundedRect(w, h, r, steps) {
  const pts = [];
  const corners = [[w / 2 - r, h / 2 - r, 0], [-w / 2 + r, h / 2 - r, Math.PI / 2], [-w / 2 + r, -h / 2 + r, Math.PI], [w / 2 - r, -h / 2 + r, Math.PI * 1.5]];
  for (const [cx, cy, start] of corners) {
    for (let i = 0; i <= steps; i++) {
      const a = start + (i / steps) * (Math.PI / 2);
      pts.push([cx + r * Math.cos(a), cy + r * Math.sin(a)]);
    }
  }
  return pts;
}

/**
 * Closed outline of a shape, centred on 0,0, width 1 (counter-clockwise, y up), resampled to `count`
 * points spaced evenly along its length so borders and drips are evenly placed.
 */
export function outline(shape, count = 160) {
  let raw;
  if (shape === 'square') raw = roundedRect(1, 1, 0.07, 8);
  else if (shape === 'rectangle') raw = roundedRect(1, 0.68, 0.06, 8);
  else if (shape === 'number') raw = roundedRect(1, 0.7, 0.16, 10);
  else if (shape === 'heart') {
    raw = [];
    for (let i = 0; i < 240; i++) {
      const t = (i / 240) * Math.PI * 2;
      raw.push([16 * Math.sin(t) ** 3, 13 * Math.cos(t) - 5 * Math.cos(2 * t) - 2 * Math.cos(3 * t) - Math.cos(4 * t)]);
    }
    const xs = raw.map((p) => p[0]);
    const ys = raw.map((p) => p[1]);
    const w = Math.max(...xs) - Math.min(...xs);
    const cy = (Math.max(...ys) + Math.min(...ys)) / 2;
    raw = raw.map(([x, y]) => [x / w, (y - cy) / w]).reverse();
  } else {
    raw = [];
    for (let i = 0; i < 128; i++) {
      const a = (i / 128) * Math.PI * 2;
      raw.push([0.5 * Math.cos(a), 0.5 * Math.sin(a)]);
    }
  }
  return resample(raw, count);
}

function resample(points, count) {
  const closed = [...points, points[0]];
  const lengths = [0];
  for (let i = 1; i < closed.length; i++) {
    lengths.push(lengths[i - 1] + Math.hypot(closed[i][0] - closed[i - 1][0], closed[i][1] - closed[i - 1][1]));
  }
  const total = lengths[lengths.length - 1];
  const out = [];
  let j = 1;
  for (let k = 0; k < count; k++) {
    const target = (k / count) * total;
    while (lengths[j] < target && j < lengths.length - 1) j++;
    const t = (target - lengths[j - 1]) / (lengths[j] - lengths[j - 1] || 1);
    out.push([closed[j - 1][0] + (closed[j][0] - closed[j - 1][0]) * t, closed[j - 1][1] + (closed[j][1] - closed[j - 1][1]) * t]);
  }
  return out;
}

export function perimeter(points) {
  let sum = 0;
  for (let i = 0; i < points.length; i++) {
    const a = points[i];
    const b = points[(i + 1) % points.length];
    sum += Math.hypot(b[0] - a[0], b[1] - a[1]);
  }
  return sum;
}

/** SVG path data for an outline scaled to `size` and centred at cx, cy (y down). */
export function outlinePath(shape, size, cx, cy) {
  return outline(shape, 120).map(([x, y], i) => `${i ? 'L' : 'M'}${(cx + x * size).toFixed(1)} ${(cy - y * size).toFixed(1)}`).join(' ') + ' Z';
}

/** Small deterministic random numbers, so drips look the same on every render. */
export function seeded(seed) {
  let s = seed >>> 0 || 1;
  return () => {
    s = (s * 1664525 + 1013904223) >>> 0;
    return s / 4294967296;
  };
}

/** Draws one tile of a side pattern on a 2D canvas context (transparent background). */
export function drawPatternTile(ctx, style, colour, size) {
  ctx.clearRect(0, 0, size, size);
  ctx.fillStyle = colour;
  ctx.strokeStyle = colour;
  const s = size;
  const star = (x, y, r) => {
    ctx.beginPath();
    for (let i = 0; i < 10; i++) {
      const a = -Math.PI / 2 + (i * Math.PI) / 5;
      const rr = i % 2 ? r * 0.45 : r;
      ctx.lineTo(x + rr * Math.cos(a), y + rr * Math.sin(a));
    }
    ctx.closePath();
    ctx.fill();
  };
  const heart = (x, y, r) => {
    ctx.beginPath();
    ctx.moveTo(x, y + r * 0.9);
    ctx.bezierCurveTo(x - r * 1.4, y, x - r * 0.9, y - r * 1.1, x, y - r * 0.35);
    ctx.bezierCurveTo(x + r * 0.9, y - r * 1.1, x + r * 1.4, y, x, y + r * 0.9);
    ctx.fill();
  };
  if (style === 'dots') {
    for (const [x, y] of [[0.25, 0.25], [0.75, 0.75]]) { ctx.beginPath(); ctx.arc(x * s, y * s, s * 0.08, 0, Math.PI * 2); ctx.fill(); }
  } else if (style === 'stripes') {
    ctx.lineWidth = s * 0.14;
    for (let i = -1; i <= 2; i++) { ctx.beginPath(); ctx.moveTo((i * 0.5) * s, s); ctx.lineTo((i * 0.5 + 1) * s, 0); ctx.stroke(); }
  } else if (style === 'zigzag') {
    ctx.lineWidth = s * 0.07;
    for (const y of [0.3, 0.75]) {
      ctx.beginPath();
      for (let i = 0; i <= 4; i++) ctx.lineTo((i / 4) * s, (y + (i % 2 ? -0.1 : 0.1)) * s);
      ctx.stroke();
    }
  } else if (style === 'hearts') {
    heart(0.27 * s, 0.3 * s, s * 0.12); heart(0.75 * s, 0.75 * s, s * 0.12);
  } else if (style === 'stars') {
    star(0.27 * s, 0.3 * s, s * 0.13); star(0.75 * s, 0.75 * s, s * 0.13);
  } else if (style === 'scallop') {
    ctx.lineWidth = s * 0.05;
    for (const y of [0.25, 0.75]) {
      for (let i = 0; i < 2; i++) { ctx.beginPath(); ctx.arc((i * 0.5 + 0.25) * s, y * s, s * 0.22, 0, Math.PI); ctx.stroke(); }
    }
  }
}
