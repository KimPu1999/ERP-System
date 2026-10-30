# Suppliers Phase 6 — Supplier Portal Audit

## Architecture decision

Supplier portal access uses the existing Sanctum User principal but does not create internal tenant, company, role, or staff memberships. Access is granted only by an explicit supplier portal membership linking one user, one supplier, and one buyer company. Portal endpoints use the membership resolver on every request.

## Implemented

- Hashed, expiring, one-time supplier portal invitations with mail delivery failure reporting.
- Explicit portal roles and membership status.
- Supplier portal dashboard and responsive layout.
- Released/approved PO visibility through a supplier-safe response shape.
- PO acknowledgement and discrepancy/clarification types.
- Delivery commitment proposals with remaining-quantity validation.
- ASN submission with PO-line validation and idempotency keys.
- Supplier invoice document-intake records without AP posting.
- Supplier/company-scoped conversations and external-visible messages.
- Supplier corrective-action responses without internal closure authority.
- Internal portal access listing and invitation revocation.
- Tenant RLS on portal tables.

## Security controls

- No arbitrary supplier, tenant, or buyer-company IDs are trusted from the portal.
- Draft/unapproved POs are excluded.
- Supplier users cannot post GRNs, approve POs, modify PO approvals, post bills, or change inventory.
- ASN and invoice submissions remain supplier claims until internal processing.
- Portal API responses avoid internal approval, risk, banking and accounting details.
- Invitation tokens are hashed and invalidated on acceptance or revocation.

## Remaining gaps

- Supplier portal login/reset UI is not separate from the existing Sanctum login surface.
- Full document upload/download, notification center, team-management UI, shipment detail UI and buyer-side ASN receiving integration remain follow-up work.
- Invitation acceptance requires an authenticated User account; a dedicated external registration flow is not yet implemented.
- Mail delivery is reported as accepted by the mailer, not as confirmed recipient delivery.
