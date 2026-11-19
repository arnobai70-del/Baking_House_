# Baking House — Laravel platform

Server-side application (Phase 2 onward). Product decisions live in [`../docs`](../docs).

## Run locally

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed     # roles/permissions + local demo accounts
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

Views use interim CSS copied from the prototype (`public/css`); the Tailwind + Vite storefront replaces it in Phase 3.
