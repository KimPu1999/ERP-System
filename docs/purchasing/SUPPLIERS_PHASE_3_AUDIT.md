# Suppliers Phase 3 — Financial Management Audit

## Existing capability review

| Capability | Result |
|---|---|
| Supplier master and company scope | Reused from Suppliers Phase 1 |
| Supplier onboarding and approval | Reused from Suppliers Phase 2 |
| Company bank/cash accounts | Existing accounting model; kept separate |
| Supplier payment and AP ledger | Existing Purchasing Phase 5 services reused |
| Supplier financial profiles | Added |
| Supplier beneficiary accounts | Added as a separate encrypted model |
| Beneficiary verification | Added with maker-checker review history |
| Payment holds | Added and enforced at payment posting |
| Mixed-currency AP totals | Kept unavailable without an FX policy |

## Implemented controls

- Supplier beneficiary accounts are distinct from company bank accounts.
- Account number, IBAN, and routing identifiers use Laravel encrypted casts.
- Ordinary list/profile responses contain only masked references.
- New accounts start as Draft and cannot be used in payment preparation unless verified and active.
- The submitter cannot verify the same beneficiary.
- Default beneficiary changes require a verified active account and are transactional.
- Payment posting revalidates beneficiary status and applicable supplier holds.
- Posted payments capture a masked beneficiary snapshot; existing payments are not redirected.
- AP balances are derived from existing posted bills, posted allocations, and posted supplier credits.
- Credit facilities are represented separately from AP liabilities.
- Financial profile updates and sensitive beneficiary actions are audit logged.
- Dedicated supplier financial permission keys are seeded additively; existing role assignments must be reviewed before granting them.

## Known gaps

- No external bank ownership verification integration exists; verification is explicitly internal/manual.
- Payment-term catalogs and installment schedules are not present in the existing ERP and were not duplicated.
- Supplier-specific financial document UI and expiry notifications remain open.
- Full financial profile and bank-account management UI is not yet complete; the directory and secure APIs are implemented.
- Credit exposure beyond posted AP requires a documented commitment/FX policy before it can be calculated safely.
- Database-backed tests remain blocked when PostgreSQL test service is unavailable.

