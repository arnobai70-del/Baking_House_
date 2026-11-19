/**
 * Baking House — weight pricing engine (client-side preview).
 *
 * Contract: docs/pricing-rules.md
 *  - Quantity is integer grams. Money is integer paisa (1 BDT = 100 paisa).
 *  - The server recalculates authoritatively; this module only previews.
 */

const PAISA_PER_TAKA = 100;

/** Integer division of a non-negative integer, rounding half up. */
function divRoundHalfUp(numerator, denominator) {
  return Math.floor((numerator + Math.floor(denominator / 2)) / denominator);
}

/**
 * Parse buyer input into integer grams.
 * @param {string|number} value
 * @param {'g'|'kg'} unit
 * @returns {{ok: true, grams: number} | {ok: false, error: string}}
 */
export function parseQuantity(value, unit = 'g') {
  const text = String(value ?? '').trim();
  if (text === '') return { ok: false, error: 'Enter a quantity.' };
  if (!/^\d+(\.\d+)?$/.test(text)) return { ok: false, error: 'Enter a valid positive number.' };

  if (unit === 'g') {
    if (text.includes('.')) return { ok: false, error: 'Grams must be a whole number.' };
    return { ok: true, grams: Number.parseInt(text, 10) };
  }
  if (unit === 'kg') {
    const [whole, frac = ''] = text.split('.');
    if (frac.length > 3) return { ok: false, error: 'Use at most 3 decimal places for kg.' };
    const grams = Number.parseInt(whole, 10) * 1000 + Number.parseInt(frac.padEnd(3, '0') || '0', 10);
    return { ok: true, grams };
  }
  return { ok: false, error: 'Unknown unit.' };
}

/**
 * Display a weight. < 1000 g in grams, ≥ 1000 g in kg.
 * @param {number} grams
 * @param {'decimal'|'mixed'} style  decimal → "1.25 kg", mixed → "1 kg 250 g"
 */
export function formatWeight(grams, style = 'decimal') {
  if (grams < 1000) return `${grams} g`;
  const kg = Math.floor(grams / 1000);
  const rest = grams % 1000;
  if (rest === 0) return `${kg} kg`;
  if (style === 'mixed') return `${kg} kg ${rest} g`;
  const frac = String(rest).padStart(3, '0').replace(/0+$/, '');
  return `${kg}.${frac} kg`;
}

/** Format paisa as "৳1,234" or "৳812.50". */
export function formatTaka(paisa) {
  const taka = Math.floor(paisa / PAISA_PER_TAKA);
  const rem = paisa % PAISA_PER_TAKA;
  const whole = taka.toLocaleString('en-IN');
  return rem === 0 ? `৳${whole}` : `৳${whole}.${String(rem).padStart(2, '0')}`;
}

/**
 * Pick the effective per-kg rate using precedence:
 * contract → role tier → campaign → standard.
 * Tiers apply on total grams (highest `fromGrams` ≤ grams wins).
 *
 * @param {object} rule product weight rule
 * @param {number} grams
 * @param {{role?: 'retail'|'business'|'reseller', contractPerKgPaisa?: number, now?: Date}} ctx
 * @returns {{perKgPaisa: number, source: string}}
 */
export function resolvePerKgRate(rule, grams, ctx = {}) {
  const now = ctx.now ?? new Date();

  if (Number.isInteger(ctx.contractPerKgPaisa)) {
    return { perKgPaisa: ctx.contractPerKgPaisa, source: 'contract' };
  }

  const role = ctx.role ?? 'retail';
  const roleTiers = rule.tiers?.[role];
  if (role !== 'retail' && roleTiers?.length) {
    const tier = pickTier(roleTiers, grams);
    if (tier) return { perKgPaisa: tier.perKgPaisa, source: `${role}-tier` };
  }

  const c = rule.campaign;
  if (c && (!c.startsAt || new Date(c.startsAt) <= now) && (!c.endsAt || now < new Date(c.endsAt))) {
    return { perKgPaisa: c.perKgPaisa, source: 'campaign' };
  }

  const retailTier = rule.tiers?.retail?.length ? pickTier(rule.tiers.retail, grams) : null;
  if (retailTier) return { perKgPaisa: retailTier.perKgPaisa, source: 'retail-tier' };

  return { perKgPaisa: rule.perKgPaisa, source: 'standard' };
}

function pickTier(tiers, grams) {
  return [...tiers]
    .filter((t) => grams >= t.fromGrams)
    .sort((a, b) => b.fromGrams - a.fromGrams)[0] ?? null;
}

/**
 * Validate grams against min / max / step / stock. Never silently changes the quantity.
 * @returns {{ok: true} | {ok: false, code: string, error: string, suggestions: number[]}}
 */
export function validateGrams(rule, grams, stockGrams = Infinity) {
  const { minGrams = 1, maxGrams = Infinity, stepGrams = 1 } = rule;

  if (!Number.isInteger(grams) || grams <= 0) {
    return { ok: false, code: 'invalid', error: 'Quantity must be a positive whole number of grams.', suggestions: [] };
  }
  if (grams < minGrams) {
    return { ok: false, code: 'below_min', error: `Minimum order is ${formatWeight(minGrams)}.`, suggestions: [minGrams] };
  }
  if (grams > maxGrams) {
    return { ok: false, code: 'above_max', error: `Maximum per order is ${formatWeight(maxGrams)}.`, suggestions: [maxGrams] };
  }
  if (grams % stepGrams !== 0) {
    const lower = Math.floor(grams / stepGrams) * stepGrams;
    const upper = lower + stepGrams;
    const suggestions = [lower, upper].filter((g) => g >= minGrams && g <= maxGrams);
    return {
      ok: false,
      code: 'step',
      error: `Quantity must be in steps of ${formatWeight(stepGrams)}.`,
      suggestions,
    };
  }
  if (grams > stockGrams) {
    const available = Math.floor(stockGrams / stepGrams) * stepGrams;
    return {
      ok: false,
      code: 'stock',
      error: `Only ${formatWeight(stockGrams)} in stock.`,
      suggestions: available >= minGrams ? [available] : [],
    };
  }
  return { ok: true };
}

/**
 * Full price quote for a weight line.
 * @returns {{ok: true, grams, label, perKgPaisa, rateSource, linePaisa} | {ok: false, ...validation}}
 */
export function quoteWeightLine(rule, grams, ctx = {}) {
  const v = validateGrams(rule, grams, ctx.stockGrams ?? rule.stockGrams ?? Infinity);
  if (!v.ok) return v;

  const { perKgPaisa, source } = resolvePerKgRate(rule, grams, ctx);
  const linePaisa = divRoundHalfUp(grams * perKgPaisa, 1000);

  return {
    ok: true,
    grams,
    label: formatWeight(grams, ctx.weightStyle),
    perKgPaisa,
    rateSource: source,
    linePaisa,
  };
}
