# Phase 15 — UAT Defect Register

**Process:** Open → Assigned → In Progress → Fixed → Ready for Retest → Verified → Closed. Failed retest ⇒ Reopen. **A code change alone never closes a defect** — closure requires tester retest evidence.

Severity: **P0** security/data/ops blocker · **P1** major workflow failure · **P2** noncritical functional/UI defect · **P3** improvement.

Register start state: carries forward the open Phase 14 defects verified by audit, and the new environment defect found in the Phase 15 log inspection. All UAT-discovered rows are added during testing.

| Defect ID | Severity | Module | Summary / Evidence | Expected | Actual | Environment | Reproduction steps | Owner | Root cause | Fix reference | Retest status | Process state |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| DEV-15-001 | P2 | Auth / Email | SMTP verification delivery failure observed: `storage/logs/laravel.log` 2026-11-04 03:50 `testing.WARNING: Email verification delivery failed ... "status":"failed","exception":"RuntimeException"`; log-transport fallback worked (mail content written, hashed address only) | Verification email delivered on configured transport; delivery state surfaced to admin | SMTP attempt throws; falls back to log transport | Test env (`testing`) | Configure SMTP in testing env → send verification → observe warning | Unassigned | Unconfirmed — suspected SMTP transport config/credentials in test env | Unassigned | Not retested | Open |
| ERP-P3-1 | P3 (was P3 open in Ph14) | Database | Redundant composite indexes (`stock_movements`, `role_assignments`) + unindexed non-tenant FKs, most critically `journal_lines.journal_entry_id` | Forward-only index-cleanup migration | Currently redundant/unindexed | All | n/a (schema) | Unassigned | Historical growth | Proposed in Ph14 BUG_FIX_REPORT batch 2 | Not started | Open |
| ERP-P3-2 | P3 | i18n | ~1500 hardcoded UI literals; Burmese coverage below parity; checker misses `business.*` block (false negatives) | Full EN/MM parity without `as never` casts | Raw literals render in some flows | Frontend | Traverse any untranslated page | Unassigned | Accumulated drift | Unassigned | Not started | Open |
| ERP-P3-3 | P3 | Repo hygiene | `backend/mfa-repro-seed.php`, `mfa-repro-seed2.php`, root `mfa-repro-flow.sh` contain hardcoded test passwords; no-op placeholder `main.test.tsx` | No hardcoded credentials in repo | Present | Repo | n/a | Unassigned | Debug leftovers | Removal requires owner confirmation | Not started | Open |
| ERP-P3-4 | P3 | Infra | No FORCE cron entry for backups; nginx lacks gzip; Dockerfile uses `artisan serve` + PHP 8.3 vs CI PHP 8.4; deploy.yml builds artifacts only; ~14 env keys undocumented | Documented, wired plan | Present | Infrastructure | n/a | Unassigned | Early-stage ops | Unassigned | Not started | Open |
| DB-14-05 | P2 | Database / RBAC | `role_assignments` lacks unique-constraint preventing duplicate role grant at same scope (Ph14 DATABASE_AUDIT item 5) | Duplicate grant rejected | Silent duplicate possible | All | Create membership twice same scope | Unassigned | Needs duplicate-cleanup step before constraint (rows must not be attenuated without review) | Unassigned | Not started | Open |
| SUP-14-09 | P3 | Support | `support_tickets_number_unique` is globally unique rather than tenant-scoped (collision across tenants) (Ph14 DATABASE_AUDIT item 9) | Per-tenant numbering | Global collide risk | All | n/a (schema) | Unassigned | Historical schema | Unassigned | Not started | Open |

## Rules for UAT-discovered defects (to be added during testing)

1. One row per defect; ID format `DEF-15-NNN`.
2. Severity by impact, not by effort: tenant isolation & money-correctness issues are **at least P0/P1** even if hard to hit.
3. Every P0/P1 blocks module acceptance and is listed in the release gate linkage.
4. Reopen history is appended in rows (do not overwrite).
5. Regression requirement: after each fix, the specific automated suites touching that module must be re-run and the run recorded in `UAT_EXECUTION_REPORT.md` §1-style table before requesting retest.

## Open P0/P1 count at register start

**0 open P0/P1.** ERP-P0-1 (RLS bypass) and ERP-P1-1/2/3/4 were fixed and test-verified in Phase 14 (`docs/phase14/BUG_FIX_REPORT.md`) — retained here only as evidence anchors, not as open items.
