import { test } from 'node:test';
import assert from 'node:assert/strict';
import { addLine, repriceCart, priceCombo, priceProductLine } from '../prototype/assets/js/cart-core.js';

// Illustrative fixtures — not real catalog data.
const choc = {
  id: 1, name: 'Chocolate', pricingMode: 'WEIGHT',
  weight: { perKgPaisa: 65000, minGrams: 100, maxGrams: 25000, stepGrams: 50, stockGrams: 10000,
    tiers: { business: [{ fromGrams: 5000, perKgPaisa: 60000 }] } },
};
const pan = { id: 2, name: 'Pan', pricingMode: 'FIXED', pricePaisa: 245000, stock: 3 };
const oven = { id: 3, name: 'Oven', pricingMode: 'QUOTE_ONLY', stock: 1 };
const products = [choc, pan, oven];
const combo = { slug: 'kit', name: 'Kit', items: [{ id: 2, qty: 1 }, { id: 1, grams: 1000 }], discountPct: 10 };
const catalog = { getProduct: (id) => products.find((p) => p.id === id), getCombo: (s) => (s === 'kit' ? combo : undefined) };

test('same weight product merges into one line (tiers apply on total grams)', () => {
  let lines = addLine([], { type: 'product', productId: 1, grams: 3000 });
  lines = addLine(lines, { type: 'product', productId: 1, grams: 2000 });
  assert.equal(lines.length, 1);
  assert.equal(lines[0].grams, 5000);
  const priced = repriceCart(lines, catalog, { role: 'business' });
  assert.equal(priced.lines[0].perKgPaisa, 60000);
  assert.equal(priced.subtotalPaisa, 300000);
});

test('retail role never gets business tier in cart', () => {
  const lines = addLine([], { type: 'product', productId: 1, grams: 6000 });
  assert.equal(repriceCart(lines, catalog, { role: 'retail' }).subtotalPaisa, 390000);
});

test('700 g line prices ৳455 through the cart', () => {
  const lines = addLine([], { type: 'product', productId: 1, grams: 700 });
  assert.equal(repriceCart(lines, catalog).subtotalPaisa, 45500);
});

test('stored line is re-validated against stock (no oversell)', () => {
  const r = priceProductLine(pan, { qty: 4 });
  assert.equal(r.ok, false);
  assert.equal(r.code, 'stock');
  assert.deepEqual(r.suggestions, [3]);
  const priced = repriceCart([{ key: 'p:2', type: 'product', productId: 2, qty: 4 }], catalog);
  assert.equal(priced.valid, false);
  assert.equal(priced.subtotalPaisa, 0);
});

test('quote-only items cannot be bought from the cart', () => {
  assert.equal(priceProductLine(oven, { qty: 1 }).ok, false);
});

test('removed product invalidates the cart', () => {
  const priced = repriceCart([{ key: 'p:99', type: 'product', productId: 99, qty: 1 }], catalog);
  assert.equal(priced.valid, false);
});

test('combo shows normal price, discount and checks child stock', () => {
  const c = priceCombo(combo, catalog.getProduct);
  assert.equal(c.normalPaisa, 245000 + 65000);
  assert.equal(c.discountPaisa, 31000);
  assert.equal(c.linePaisa, 279000);
  const tooMany = priceCombo(combo, catalog.getProduct, 4); // needs 4 pans, 3 in stock
  assert.equal(tooMany.ok, false);
});

test('combo children use standard rate even for business (no discount stacking)', () => {
  const lines = addLine([], { type: 'combo', slug: 'kit', qty: 1 });
  assert.equal(repriceCart(lines, catalog, { role: 'business' }).subtotalPaisa, 279000);
});

test('empty cart is not valid for checkout', () => {
  assert.equal(repriceCart([], catalog).valid, false);
});
