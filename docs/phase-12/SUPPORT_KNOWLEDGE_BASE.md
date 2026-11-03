# SUPPORT_KNOWLEDGE_BASE.md — Phase 12

## Status: NOT IMPLEMENTED (honest)

The Help Center (`/business/help`) and Knowledge Base (`/platform/support/knowledge-base`)
were scoped in the phase brief but were **not built** in this iteration. There is no
`knowledge_base_articles` table, no article endpoints, no draft/publish workflow.

Rationale: the support ticket system (lifecycle, SLA, threads, attachments, isolation,
auditing, tests) was prioritized as the core verifiable feature; a KB built without
translation content management would risk delivering fake placeholder articles, which
the project rules forbid.

## What exists today that partially covers the need

- Support ticket creation with categories helps route questions (`/business/support`).
- Bilingual (EN/my) UI language assets exist across the platform, including support strings.
- Platform admins can communicate planned downtime via the Phase 9 maintenance notice.

## Implementation plan for the follow-up phase

1. Migration `help_articles` (tenant-neutral platform table, `status: draft|published`, `version`, `category`, `locale` for EN/my content rows, audited editors).
2. Editor APIs + policy (authorized platform staff only; drafts invisible to business users).
3. `/platform/support/knowledge-base` management page and `/business/help` reader with search, categories, article links from the ticket form.
4. Content: write real EN+my onboarding/inventory/sales/accounting guides with the business owners — no Lorem-ipsum seeds.
5. Tests: publication status gating, editor authorization, EN/my availability.
