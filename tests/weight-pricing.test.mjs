import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  parseQuantity,
  formatWeight,
  formatTaka,
  quoteWeightLine,
  validateGrams,
} from '../prototype/assets/js/weight-pricing.js';

// Illustrative rule: ৳650/kg, min 100 g, step 50 g. Not real catalog data.
const chocolate = {
  perKgPaisa: 65000,
  minGrams: 100,
  maxGrams: 25000,
  stepGrams: 50,
  tiers: { business: [{ fromGrams: 5000, perKgPaisa: 60000 }] },
};

test('flat ৳650/kg examples', () => {
  const flat = { perKgPaisa: 65000 };
  const cases = [
    [100, 6500],
    [700, 45500],
    [900, 58500],
    [1000, 65000],
    [1250, 81250],
  ];
  for (const [grams, paisa] of cases) {
    const q = quoteWeightLine(flat, grams);
    assert.equal(q.ok, true);
    assert.equal(q.linePaisa, paisa, `${grams} g`);
  }
  assert.equal(formatTaka(45500), '৳455');
  assert.equal(formatTaka(81250), '৳812.50');
});

test('999 g → 1000 g changes label only', () => {
  const flat = { perKgPaisa: 65000 };
  assert.equal(formatWeight(999), '999 g');
  assert.equal(formatWeight(1000), '1 kg');
  assert.equal(quoteWeightLine(flat, 999).linePaisa, 64935);
  assert.equal(quoteWeightLine(flat, 1000).linePaisa, 65000);
});

test('1250 g display styles', () => {
  assert.equal(formatWeight(1250), '1.25 kg');
  assert.equal(formatWeight(1250, 'mixed'), '1 kg 250 g');
  assert.equal(formatWeight(2005), '2.005 kg');
});

test('below minimum is rejected', () => {
  const r = validateGrams({ ...chocolate, minGrams: 250 }, 100);
  assert.equal(r.ok, false);
  assert.equal(r.code, 'below_min');
});

test('off-step quantity is rejected with suggestions, not silently changed', () => {
  const r = quoteWeightLine(chocolate, 725);
  assert.equal(r.ok, false);
  assert.equal(r.code, 'step');
  assert.deepEqual(r.suggestions, [700, 750]);
});

test('insufficient stock is rejected with available suggestion', () => {
  const r = quoteWeightLine(chocolate, 700, { stockGrams: 650 });
  assert.equal(r.ok, false);
  assert.equal(r.code, 'stock');
  assert.deepEqual(r.suggestions, [650]);
});

test('business tier applies from 5 kg', () => {
  const below = quoteWeightLine(chocolate, 4950, { role: 'business' });
  const at = quoteWeightLine(chocolate, 5000, { role: 'business' });
  assert.equal(below.perKgPaisa, 65000);
  assert.equal(at.perKgPaisa, 60000);
  assert.equal(at.rateSource, 'business-tier');
  assert.equal(at.linePaisa, 300000);
});

test('retail never receives business tier', () => {
  const q = quoteWeightLine(chocolate, 6000, { role: 'retail' });
  assert.equal(q.perKgPaisa, 65000);
});

test('contract price wins precedence', () => {
  const q = quoteWeightLine(chocolate, 6000, { role: 'business', contractPerKgPaisa: 58000 });
  assert.equal(q.rateSource, 'contract');
  assert.equal(q.perKgPaisa, 58000);
});

test('expired campaign falls back to standard', () => {
  const rule = { ...chocolate, campaign: { perKgPaisa: 55000, endsAt: '2026-01-01T00:00:00Z' } };
  const q = quoteWeightLine(rule, 1000, { now: new Date('2026-11-20T00:00:00Z') });
  assert.equal(q.rateSource, 'standard');
  const active = quoteWeightLine(rule, 1000, { now: new Date('2025-12-01T00:00:00Z') });
  assert.equal(active.rateSource, 'campaign');
});

test('parseQuantity handles g and kg without floats', () => {
  assert.deepEqual(parseQuantity('700', 'g'), { ok: true, grams: 700 });
  assert.deepEqual(parseQuantity('1.25', 'kg'), { ok: true, grams: 1250 });
  assert.deepEqual(parseQuantity('0.7', 'kg'), { ok: true, grams: 700 });
  assert.equal(parseQuantity('1.2345', 'kg').ok, false);
  assert.equal(parseQuantity('70.5', 'g').ok, false);
  assert.equal(parseQuantity('-5', 'g').ok, false);
  assert.equal(parseQuantity('', 'g').ok, false);
});
