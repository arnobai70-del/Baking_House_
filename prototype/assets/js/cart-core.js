/**
 * Cart pricing core — pure functions, no DOM, no storage. Unit tested in tests/cart-core.test.mjs.
 *
 * Rules (blueprint §05, §06, §09):
 *  - Stored cart lines hold only identity + quantity (productId + grams|qty, or combo slug + qty).
 *    Prices are ALWAYS recomputed from the catalog — a stored/client total is never trusted.
 *  - One line per product+unit: adding the same weight product again merges grams,
 *    so tier thresholds apply on total grams, not on number of rows.
 *  - Bundle discount never stacks with weight-tier discounts: bundle children are priced at standard rate.
 */
import { quoteWeightLine, formatWeight, validateGrams } from './weight-pricing.js';

const roundHalfUp = (num, den) => Math.floor((num + Math.floor(den / 2)) / den);

/** Placeholder shipping table — NOT a real courier rate. Owner/courier must confirm (decisions.md). */
export const SHIPPING_PLACEHOLDER = {
  inside_dhaka: { label: 'Inside Dhaka', paisa: 8000 },
  outside_dhaka: { label: 'Outside Dhaka', paisa: 15000 },
};

/** Price one fixed or weight product line. */
export function priceProductLine(product, line, ctx = {}) {
  if (!product) return { ok: false, error: 'This product is no longer available.' };

  if (product.pricingMode === 'QUOTE_ONLY') {
    return { ok: false, error: 'This item is quote-only and cannot be bought from the cart.' };
  }

  if (product.pricingMode === 'WEIGHT') {
    const q = quoteWeightLine(product.weight, line.grams, { ...ctx, stockGrams: product.weight.stockGrams });
    if (!q.ok) return { ok: false, error: q.error, suggestions: q.suggestions ?? [], code: q.code };
    return { ok: true, linePaisa: q.linePaisa, qtyLabel: q.label, perKgPaisa: q.perKgPaisa, rateSource: q.rateSource };
  }

  // FIXED
  const qty = line.qty;
  if (!Number.isInteger(qty) || qty < 1) return { ok: false, error: 'Quantity must be at least 1.' };
  if (qty > product.stock) {
    return { ok: false, error: product.stock === 0 ? 'Out of stock.' : `Only ${product.stock} in stock.`, suggestions: product.stock ? [product.stock] : [], code: 'stock' };
  }
  return { ok: true, linePaisa: product.pricePaisa * qty, qtyLabel: `× ${qty}`, unitPaisa: product.pricePaisa };
}

/** Normal (undiscounted) price of a combo and its bundle price. Children priced at standard retail rate. */
export function priceCombo(combo, getProduct, comboQty = 1) {
  let normal = 0;
  for (const item of combo.items) {
    const p = getProduct(item.id);
    if (!p) return { ok: false, error: 'A bundle item is unavailable.' };
    if (p.pricingMode === 'WEIGHT') {
      const grams = item.grams * comboQty;
      const v = validateGrams({ ...p.weight, minGrams: 1, stepGrams: 1 }, grams, p.weight.stockGrams);
      if (!v.ok) return { ok: false, error: `${p.name}: ${v.error}` };
      normal += roundHalfUp(item.grams * p.weight.perKgPaisa, 1000) * comboQty;
    } else {
      const need = item.qty * comboQty;
      if (need > p.stock) return { ok: false, error: `${p.name}: only ${p.stock} in stock.` };
      normal += p.pricePaisa * need;
    }
  }
  const discount = roundHalfUp(normal * combo.discountPct, 100);
  return { ok: true, normalPaisa: normal, discountPaisa: discount, linePaisa: normal - discount };
}

/** Describe a combo item for display, e.g. "Flour (5 kg)" or "Turntable × 1". */
export function describeComboItem(item, product) {
  return item.grams ? `${product.name} (${formatWeight(item.grams)})` : `${product.name}${item.qty > 1 ? ` × ${item.qty}` : ''}`;
}

/**
 * Add or merge a line into a stored cart (immutable). Returns the new lines array.
 * @param {Array} lines stored lines
 * @param {{type:'product', productId:number, grams?:number, qty?:number} | {type:'combo', slug:string, qty:number}} add
 */
export function addLine(lines, add) {
  const key = add.type === 'combo' ? `combo:${add.slug}` : `p:${add.productId}`;
  const existing = lines.find((l) => l.key === key);
  if (!existing) return [...lines, { ...add, key }];
  return lines.map((l) => {
    if (l.key !== key) return l;
    if (add.grams != null) return { ...l, grams: (l.grams ?? 0) + add.grams };
    return { ...l, qty: (l.qty ?? 0) + (add.qty ?? 1) };
  });
}

/**
 * Re-price the whole cart from the catalog.
 * @returns {{lines: Array, subtotalPaisa: number, valid: boolean, itemCount: number}}
 */
export function repriceCart(lines, catalog, ctx = {}) {
  const out = lines.map((l) => {
    if (l.type === 'combo') {
      const combo = catalog.getCombo(l.slug);
      if (!combo) return { ...l, ok: false, error: 'This bundle is no longer offered.' };
      const r = priceCombo(combo, catalog.getProduct, l.qty);
      return { ...l, ...r, name: combo.name, qtyLabel: `× ${l.qty}` };
    }
    const p = catalog.getProduct(l.productId);
    return { ...l, ...priceProductLine(p, l, ctx), name: p?.name ?? 'Unavailable item', product: p };
  });
  const subtotalPaisa = out.reduce((s, l) => s + (l.ok ? l.linePaisa : 0), 0);
  return { lines: out, subtotalPaisa, valid: out.length > 0 && out.every((l) => l.ok), itemCount: out.length };
}
