# Roles & Permissions

| Actor | Allowed | Protected from |
|-------|---------|----------------|
| Guest | browse, search, cart, optional guest checkout | dashboards, confidential prices |
| Retail customer | retail order/account actions | commercial/reseller private data |
| Commercial buyer | authorized business account + company orders | unrelated orgs, reseller back office |
| Reseller | assigned reseller catalog/pricing/orders | commercial quotation records |
| Admin | catalog, orders, support, CMS, CRM as granted | owner-only secrets, role escalation |
| Super admin | system settings, roles, approvals, integrations | still audited; cannot bypass logs |

## Enforcement
- RBAC **plus** policy/ownership checks (`organization_id`, `reseller_account_id`, memberships). Never menu visibility alone.
- Role upgrades are requested, then reviewed. One login with multiple account types → explicit context switch.
- Every sensitive action logs actor, timestamp, before/after, reason.
- Web: secure sessions. API: scoped, expiring tokens. MFA for privileged users. Rate limiting, session revoke.
- PII least-privilege, encrypted at rest where appropriate, documented retention.

## Required negative tests
Coupon misuse · fake payment success · org ID swapping · reseller price leakage · changing grams after quote · duplicate submit ·
stock oversell · webhook replay · admin bulk pricing mistakes.
