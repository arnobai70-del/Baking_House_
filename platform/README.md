# Baking House — Laravel platform

Server-side application (Phase 2 onward). Product decisions live in [`../docs`](../docs).

## Run locally

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed     # roles/permissions + local demo accounts + development catalog
php artisan serve                    # http://127.0.0.1:8000
php artisan test                     # full suite
vendor/bin/pint                      # code style
```

Local demo logins (local environment only, password `password`):
`superadmin@example.test`, `admin@example.test`, `retail@example.test`, `business@example.test`,
`reseller@example.test`, `applicant@example.test` (pending business application).

## What exists (Phase 2 core)

| Area | Where |
|------|-------|
| Staff RBAC (super admin / admin) | `App\Enums\StaffPermission`, `roles`/`permissions` tables, Gates in `AppServiceProvider` |
| Business / reseller organizations, approval | `App\Models\Organization`, `EnsureOrganizationAccess` middleware, `OrganizationPolicy` |
| Organization context (no ID swapping) | `App\Services\OrganizationContext` |
| Append-only audit log with redaction | `App\Services\AuditLogger`, `App\Models\AuditLog` |
| Auth: email or BD mobile, throttled, deactivation | `Auth\*Controller`, `App\Support\BangladeshPhone` |
| Weight pricing engine (server authoritative) | `App\Services\Pricing\WeightPricing` — same matrix as the JS prototype |
| Money / weight helpers | `App\Support\Taka`, `App\Support\Weight` |
| API auth (Sanctum) | `App\Services\ApiTokenIssuer`, `routes/api.php`, `App\Enums\ApiAbility` |
| Catalog (Phase 3) | `Category`, `Brand`, `Product`, `ProductVariant`, `ProductImage`; `App\Services\Catalog\ProductListing` / `ProductWriter` |
| Storefront + SEO | `App\Http\Controllers\Storefront\*`, `App\Support\Seo`, `config/seo.php` |
| Admin catalog | `/admin/products`, `/admin/categories`, `/admin/brands` |

The storefront uses the approved design system from the prototype (`public/css/tokens.css`, `app.css`) plus `storefront.css`.
`public/js/weight-pricing.js` must stay identical to `prototype/assets/js/weight-pricing.js`, which a test enforces.

Search engines: indexing stays **off** until `SEO_INDEXING_ENABLED=true` is set for the approved launch.
