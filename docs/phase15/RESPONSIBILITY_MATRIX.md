# RESPONSIBILITY_MATRIX.md — Phase 15 (concise RACI + ownership register)

Statuses are `Unassigned` by design until real owners are appointed; see `OPERATIONS_HANDOVER.md` for full role definitions, escalation routes and approval authority.

| Area | Accountable (A) | Responsible (R) | Consulted (C) | Informed (I) | Status |
|---|---|---|---|---|---|
| Release approval | Release Owner: Unassigned | Release Owner: Unassigned | all | all | **NOT HANDED OVER** |
| Platform operations | Release Owner: Unassigned | Platform Admin: Unassigned | Backend Maintainer | Support Lead | **NOT HANDED OVER** |
| Backend code & migrations | Release Owner: Unassigned | Backend Maintainer: Unassigned | DBA | — | **NOT HANDED OVER** |
| Database & backups | Release Owner: Unassigned | DBA: Unassigned | Backend Maintainer, Security Owner | Platform Admin | **NOT HANDED OVER** |
| Deployment & rollback | Release Owner: Unassigned | Deployment Operator: Unassigned | Backend Maintainer, DBA | all | **NOT HANDED OVER** |
| Security & tenant isolation | Release Owner: Unassigned | Security Owner: Unassigned | Backend Maintainer | Support Lead | **NOT HANDED OVER** |
| Support & SLA | Release Owner: Unassigned | Support Lead: Unassigned | Platform Admin | Platform Admin | **NOT HANDED OVER** |
| Business/tenant administration | Platform Admin: Unassigned | Business Admin (per tenant): Unassigned | Platform Admin | Support Lead | **NOT HANDED OVER** |

## Contact register (kept minimal, secrets-free; fill from identity system at assignment)

| Role | Responder (P) | Responder (B) | Channel | Hours/on-call |
|---|---|---|---|---|
| Platform Admin | Unassigned | Unassigned | Unassigned | Unassigned |
| Release Owner | Unassigned | Unassigned | Unassigned | Unassigned |
| DBA | Unassigned | Unassigned | Unassigned | Unassigned |
| Deploy Op | Unassigned | Unassigned | Unassigned | Unassigned |
| Security Owner | Unassigned | Unassigned | Unassigned | Unassigned |
| Support Lead | Unassigned | Unassigned | Unassigned | Unassigned |

**Rule enforcement:** hypercare, cutover, and GO/NO-GO documentation automatically remain INCOMPLETE while roles above say Unassigned.
