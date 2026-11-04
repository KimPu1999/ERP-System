# Phase 15 — Operational Ownership & Handover

**Status:** Ownership structure defined; **all individual owners are `Unassigned`** — real names/contacts must be filled in by the actual accountable parties. Inventing people here would violate audit integrity (Step 12 rule).
**A critical operational area may not be marked "handed over" without a named owner.** All areas are therefore NOT handed over yet.

## 1. Role definitions & requirements

| Role | Responsibility summary | Required permission/access | Primary | Backup | Escalation route |
|---|---|---|---|---|---|
| Product/Release Owner | Release go/no-go authority, roadmap, acceptance ownership | Release approval authority (human, authenticated; audit-logged) | Unassigned | Unassigned | → Executive sponsor (unassigned) |
| Platform Administrator | Super Admin operations, business approvals, settings | Platform Super Admin (`platform:provision-admin`), MFA enrolled | Unassigned | Unassigned | → Release Owner |
| Backend Maintainer | Laravel API, bug fixes, migrations review | Repo write, staging deploy access | Unassigned | Unassigned | → Release Owner |
| Database Administrator | PostgreSQL health, migrations on prod, restore drills | Postgres admin role (non-app), backup system | Unassigned | Unassigned | → Security Owner (data incidents) |
| Deployment Operator | Cutover execution, rollback, environment config | CI/CD runner access, server shell, DNS authority (human-gated) | Unassigned | Unassigned | → Release Owner |
| Security Owner | Tenant isolation, auth incidents, audit integrity, incident containment | Platform admin + security settings + server logs | Unassigned | Unassigned | → Release Owner + Executive |
| Support Lead | Ticket triage/SLA, support access grants oversight | Support station role | Unassigned | Unassigned | → Release Owner |
| Business Administrator | Tenant-facing ops (per tenant), onboarding support | Tenant admin role (per tenant) | Unassigned (per tenant) | Unassigned | → Platform Admin |

## 2. Coverage windows

| Role | Business hours | On-call |
|---|---|---|
| Platform Admin | Unassigned | Unassigned |
| Backend Maintainer | Unassigned | Unassigned |
| DBA | Unassigned | Unassigned |
| Deployment Operator | Unassigned | Unassigned |
| Security Owner | Unassigned | Unassigned |
| Support Lead | Unassigned | Unassigned |

Hypercare plan (§`HYPERCARE_PLAN.md`) requires a filled on-call row for Security Owner + Deployment Operator + DBA before launch week.

## 3. Approval & recovery authority

| Action | Authority required | Logged how |
|---|---|---|
| Production deployment | Release Owner (explicit written/+audit-logged approval) | Release gate sign-off + platform audit log entry |
| Rollback initiation | Deployment Operator OR Security Owner (P0 containment) | incident record + audit log |
| Production data restore | DBA + Release Owner jointly; never one-person | restore drill/runbook record |
| Suspicious-admin containment (session revoke) | Security Owner alone may act immediately, reports to Release Owner | `/audit-logs` + security events |
| Tenant data exposure declaration | Security Owner declares; Release Owner approves external communication | incident record |
| Live payment processor authorization | Release Owner + Executive (not implemented yet) | written authorization required (none exists) |

## 4. RACI matrix (per operational area)

R = Responsible, A = Accountable, C = Consulted, I = Informed. `—` until owners assigned.

| Area | Release Owner | Platform Admin | Backend Maint. | DBA | Deploy Op. | Security Owner | Support Lead |
|---|---|---|---|---|---|---|---|
| Release go/no-go | A/R | C | C | C | C | C | I |
| Business approvals & settings | I | A/R | — | — | — | C | I |
| API development & fixes | I | I | A/R | C | C | C | I |
| DB migrations & performance | I | — | R | A/R | C | C | — |
| Backup & restore drills | I | — | — | A/R | C | C | — |
| Cutover execution | A | I | C | C | A/R | C | I |
| Security incidents & containment | I | C | C | C | R | A/R | I |
| Support tickets & SLA | I | C | C | — | — | C | A/R |
| Monitoring & alert recipients | I | R | C | C | C | C | I |
| Training & documentation | I | R | C | C | C | C | C |

## 5. Handover checklist (all items must be checked for handover to be complete)

- [ ] Every role above has a named primary AND backup
- [ ] All owners have credentials + MFA enrolled (no shared logins)
- [ ] `INCIDENT_RUNBOOKS.md` walkthrough done with on-call owners
- [ ] Restore drill (BLK-1) executed by the named DBA
- [ ] Cutover/rollback rehearsal acknowledged by Deployment Operator
- [ ] Alert recipient lists verified live (not just configured)
- [ ] `GO_NO_GO_DECISION.md` signed by Release Owner
