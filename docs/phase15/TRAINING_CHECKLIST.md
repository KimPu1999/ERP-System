# Phase 15 — Training Checklist & Completion Tracking

**Rule:** Training completion is evidence for Gate 10 (Training & Documentation) contributing to release **confidence** — it is **never** equivalent to production release approval (Gate 12).

## 1. Curriculum map

Materials (EN + MM):

| # | Material | Audience | File |
|---|---|---|---|
| T1 | Platform Super Admin Guide | Platform Super Admin | `SUPER_ADMIN_USER_GUIDE_EN.md` / `_MM.md` |
| T2 | Business Administrator Guide | Business Admin | `BUSINESS_ADMIN_GUIDE_EN.md` / `_MM.md` |
| T3 | Incident runbooks | Ops roles | `INCIDENT_RUNBOOKS.md` |
| T4 | Cutover & rollback briefing | Deployment Operator + approver | `CUTOVER_PLAN.md`, `ROLLBACK_PLAN.md` |

Screenshot placeholders: guides intentionally mark figures as edge-flagged `Fig. tN` positions where the coordinator inserts real screenshots during delivery; no fictional screenshots shipped.

## 2. Session record (to be completed during delivery)

| Module | Participant role | Format (session / self-paced) | Participant (name — to be assigned) | Started | Completed | Trainer/reviewer | Issues raised | Acceptance |
|---|---|---|---|---|---|---|---|---|
| T1 | Platform Admin | — | Unassigned | — | — | Unassigned | — | **None yet** |
| T2 | Business Admin | — | Unassigned | — | — | Unassigned | — | **None yet** |
| T3 | Ops roles | — | Unassigned | — | — | Unassigned | — | **None yet** |
| T4 | Deploy Operator | — | Unassigned | — | — | Unassigned | — | **None yet** |

## 3. Mandatory practical exercises (pass criteria: exercise performed against a real synthetic record, evidence attached)

### Platform Admin

| # | Exercise | Evidence expected | Result (Unassigned) |
|---|---|---|---|
| P-1 | Approve a test business application (approved synthetic tenant) | audit-log row + tenant status | Not attempted |
| P-2 | Assign a scoped admin membership (company/branch scope) | `role_assignments` row visible on tenant detail | Not attempted |
| P-3 | Cloud-proof audit review: locate your own P-1/P-2 audit records | `/platform/audit-logs` ticker items | Not attempted |
| P-4 | Review a simulated alert from end-to-end | acknowledge + resolve state transitions | Not attempted |

### Business Admin

| # | Exercise | Evidence expected | Result (Unassigned) |
|---|---|---|---|
| B-1 | Create company/branch | organization structure reflecting | Not attempted |
| B-2 | Create product + reference data and a supplier | catalog + supplier records | Not attempted |
| B-3 | Complete an approved sample workflow (purchase or sales) | consistent chain PO→GR→bill / quotation→order→invoice | Not attempted |
| B-4 | Generate a permitted report (TB or P&L) | report output tied to BT totals | Not attempted |
| B-5 | Open a support ticket | ticket row + SLA timer | Not attempted |

## 4. Completion acceptance gate

Training module counts as **Accepted** when:

1. Participant completed the practical exercises above (both sets relevant to their role),
2. Trainer/reviewer reviewed evidence and signed the acceptance column,
3. Issues identified are filed in `UAT_DEFECT_REGISTER.md` where they were real defects (or in the FAQ if user-error).

Completion state is reviewed again during Phase 15 Step 11 sign-off and links to Gate 10 in `RELEASE_GATES.md`. Unassigned participants can never complete this gate.
