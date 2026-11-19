# Design Tokens

Implemented in [`prototype/assets/css/tokens.css`](../prototype/assets/css/tokens.css).

## Color

| Token | Value | Use |
|-------|-------|-----|
| `--bh-navy` | `#103B56` | masthead, footer, admin nav, headings |
| `--bh-navy-deep` | `#082D46` | high-contrast panels, sticky top bar |
| `--bh-teal` | `#087D88` | info labels, active filters, B2B context |
| `--bh-orange` | `#F18B0A` | primary CTA: Add to cart / Checkout / Quote |
| `--bh-bg` | `#F7FAFC` | shop page surfaces |
| `--bh-surface` | `#FFFFFF` | cards, forms |
| `--bh-text` | `#192B39` | body text |
| `--bh-muted` | `#617481` | secondary text |

**Red accent is prohibited.** Error states use a dark amber/brown (`--bh-error: #9A4A00`) plus an icon and text, never color alone.

Orange CTA buttons use navy-deep text (`#082D46` on `#F18B0A` ≈ 6.4:1) for WCAG AA.

## Typography
- English / numbers: **Inter**; Bangla: **Noto Sans Bengali** — subsets, `font-display: swap`.

## Layout
- 12-column grid, max width 1320 px, gutters 16–24 px.
- Product grid: desktop 4–5, tablet 2–3, mobile 2 (1 when too narrow).
- Touch targets ≥ 44 px.

## Motion

| Element | Duration |
|---------|----------|
| Hero entry / crossfade | 280–450 ms |
| Mega-menu / drawer | 160–220 ms |
| Product card hover | 120–180 ms |
| Cart badge / mini-cart / toast | 200–280 ms |
| Price total update | 100–180 ms (never delays the value) |
| Tabs / accordions | < 220 ms |
| Charts | one-time, < 350 ms |

Only `transform` and `opacity`. `prefers-reduced-motion: reduce` removes non-essential motion. No hero video, parallax or loops.
