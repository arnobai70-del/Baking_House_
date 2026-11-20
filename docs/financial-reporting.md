# Financial reporting — definitions and verification

Source: `platform/app/Services/Reports/ReportService.php`. All amounts are integer paisa (৳1 = 100 paisa);
each order line is already rounded half-up to the paisa when the order is placed (decision D-032).

## Who can see money
| Permission | Who has it by default | What it shows |
|---|---|---|
| `reports.view` | Admin, Super admin, Finance role | Report pages with counts and quantities only |
| `reports.financial` | **Super admin only**; staff only through the explicit **Finance** role | Sales, discounts, refunds, revenue, promotion value, CRM customer spend |
| `orders.manage` | Admin, Super admin | Individual orders (not aggregated spend) |
| `crm.access` | Admin, Super admin | CRM without money figures unless the person also holds `reports.financial` |

Contract / organization price lists are never shown in the CRM or reports.

## Formulas (orders counted by the Asia/Dhaka date they were placed)
| Figure | Formula |
|---|---|
| Orders | non-cancelled orders placed in the period |
| **Gross sales** | Σ order subtotal (sum of rounded line amounts) of non-cancelled orders |
| **Discounts** | Σ order-level promotion / coupon discount of those orders |
| **Net sales** | gross sales − discounts |
| **Delivery charges** | Σ delivery charged on those orders |
| **Billed** | net sales + delivery charges (= Σ order totals) |
| **Refunds** | Σ refunds *recorded* in the period (any order, by refund date) |
| **Net revenue** | billed − refunds |
| Paid | Σ totals of non-cancelled orders whose payment status is paid / partially refunded / refunded |
| Unpaid / pending | billed − paid |
| Cancelled (not sales) | Σ totals of cancelled orders (reported separately, never counted as sales) |
| Average order | billed ÷ orders (integer paisa, rounded down) |

VAT is not included or reported: VAT invoicing is disabled until the owner supplies verified BIN / legal details.
Cash on delivery counts as paid only once staff record the cash.

## Worked accounting example (automated: `tests/Feature/ReportsTest.php`)
| Order | Lines | Discount | Delivery | Total | Payment |
|---|---|---|---|---|---|
| A retail, COD | 2 × ৳1,000 = ৳2,000 | 10% coupon −৳200 | ৳80 | ৳1,880 | unpaid |
| B retail, bKash | 1 × ৳500 | — | ৳80 | ৳580 | paid, then ৳100 refunded |
| C business, COD | 1 × ৳800 (business tier) | — | ৳80 | ৳880 | unpaid |
| D retail, COD | 1 × ৳1,000 | — | ৳80 | ৳1,080 | **cancelled** |

| Figure | Working | Result |
|---|---|---|
| Orders | A, B, C (D cancelled) | 3 |
| Gross sales | 2,000 + 500 + 800 | ৳3,300 |
| Discounts | 200 | ৳200 |
| Net sales | 3,300 − 200 | ৳3,100 |
| Delivery | 80 × 3 | ৳240 |
| Billed | 3,100 + 240 = 1,880 + 580 + 880 | ৳3,340 |
| Refunds | 100 | ৳100 |
| **Net revenue** | 3,340 − 100 | **৳3,240** |
| Paid | B | ৳580 |
| Unpaid | 3,340 − 580 | ৳2,760 |
| Cancelled | D | ৳1,080 (1 order) |
| Average order | 3,340 ÷ 3 | ৳1,113.33 |

The test asserts every one of these numbers against the live report code, plus the retail / business split
(retail 2 orders ৳2,460 billed, business 1 order ৳880), top products (PAN: 3 units, ৳2,800) and the
promotion report (1 use, ৳200 discount, ৳1,880 billed). Owner / accountant sign-off on these definitions is
still required before they are used for bookkeeping.
