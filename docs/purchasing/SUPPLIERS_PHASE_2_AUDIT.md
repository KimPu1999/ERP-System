# Suppliers Phase 2 — Audit and Implementation

## Scope

Supplier onboarding, company-scoped document requirements, private supplier documents, manual document review, approval-engine integration, eligibility checks, and the onboarding workspace.

## Existing implementation audit

| Capability | Result | Evidence |
|---|---|---|
| Supplier master, contacts, addresses | Implemented | Phase 1 SupplierController, Supplier, contact/address models |
| Tenant/company authorization | Implemented | AuthorizationService, TenantContext, controller scope checks |
| Audit logging | Implemented | AuditLogger and PlatformAuditLog |
| Generic approval engine | Implemented and reused | ApprovalService, ApprovalRequest, ApprovalDefinition |
| Private local document storage | Implemented and reused | Laravel local disk and company document pattern |
| Supplier onboarding applications | Missing before Phase 2 | Added in Phase 2 |
| Supplier document requirements | Missing before Phase 2 | Added in Phase 2 |
| Supplier document review history | Missing before Phase 2 | Added in Phase 2 |
| Expiry/eligibility API | Missing before Phase 2 | Added in Phase 2 |
| Notifications and expiry scheduler | Unverified/partial | Existing notification queue exists; supplier-specific scheduled reminders remain open |

## Implemented controls

- Applications reference an existing supplier; starting onboarding does not create a second supplier.
- Application and document queries are tenant/company authorized server-side.
- Submission fails closed when required documents are absent, expired, or not manually verified.
- Approval submission delegates to the existing ApprovalService; no local approval table was created.
- Approval decisions use the shared approval history and transaction locking.
- Documents use the private Laravel disk, with MIME/extension/size validation and authorized download.
- Document review decisions are append-only in supplier_document_reviews; uploads are never implicitly verified.
- Eligibility is reported separately from supplier master status and includes blocking reasons.
- Existing suppliers with no company association are not globally deactivated; document uploads require an explicit authorized company.

## Known gaps

- A full multi-section onboarding edit form and document uploader UI are not yet exposed in React; the dashboard is implemented and secure APIs are available.
- Supplier-specific notification events and expiry reminder jobs are not yet wired to the scheduler.
- A supplier_onboarding approval policy must exist before submission can succeed; this is intentional fail-closed behavior.
- Malware scanning is not claimed because no configured scanner was verified.
- Database-backed tests could not execute because the configured PostgreSQL test service was unavailable.

