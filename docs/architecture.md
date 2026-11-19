# Architecture

**Laravel modular monolith** with service interfaces and domain events.

## Modules
Catalog · Pricing · Inventory · Cart/Checkout · Orders · Payments · Fulfillment · Customers/Organizations · Resellers ·
CRM/Quotations · Promotions · CMS · Search/SEO · Notifications · Analytics/Reporting · Integrations · Admin/RBAC · Media (image provenance)

## Data flow
Browser SSR page → application service → SQL + Redis cache → payment/courier adapters.
Queues for notifications, imports, indexing, reports and webhooks. Media on private/public disks or S3-compatible storage.

## Principles
- Server is authoritative for price, permission and stock; client never supplies amounts.
- Provider-neutral adapters for SMS, payments (bKash/Nagad/card/bank/COD) and couriers; secrets in env/vault.
- Idempotency keys on order placement, payment verification, webhooks, state transitions.
- Versioned REST API `/api/v1/*` with OpenAPI spec.

## Core tables (Phase 2–3)
See blueprint §09: identity, organizations/resellers, catalog, units/stock ledger, pricing, bundles, carts, orders/payments,
logistics, CRM, CMS/SEO, audit/system, plus `media_assets` for the image policy.

## Order states
`draft → pending_payment → confirmed → processing → packed → shipped → delivered`; cancel/return/refund are separate controlled branches.
