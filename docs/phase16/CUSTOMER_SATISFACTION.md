# Phase 16 — Customer Satisfaction Policy

Scope: `satisfaction_responses` — how satisfaction is collected and what its numbers mean.

## Collection rules

- Satisfaction is collected **only after** a feedback item reaches `resolved` (or `closed` from `resolved`). The business UI hides the rating prompt otherwise, and the API rejects early ratings with 422.
- Responses are **voluntary**. No prompt is auto-fired, no rating is implied by silence.
- One response per tenant user per feedback item; a second attempt is rejected (`already rated`).
- Score scale is 1–5 with an optional comment. Submission is throttled (10/min per user).

## Denominator policy (stated, not hidden)

The number of business users who **did not** respond is not measured — there is no delivery/read-receipt concept for the resolution message. Therefore:

- The average score is reported **with its response count** (`n=12 responses`, never presented as "customer satisfaction: 4.6/5" without n).
- A response rate is **never computed or displayed** — the denominator is unknown.
- With zero responses, the overview reports "No responses recorded yet" rather than a score.

`GET /platform/improvement/satisfaction` returns the summary, breakdown by context (feedback module/category) and by score, plus an explicit `denominator_policy` string. The UI renders it.

## What satisfaction is not used for

- It is not a performance metric for individual support agents or developers.
- It is not averaged into any "health score" — the enterprise maturity assessment (`ENTERPRISE_MATURITY.md`) treats customer support as a dimension assessed with evidence, not as a CSAT-derived number.

## Verification

Covered by `ImprovementPhase16Test.php`: rating before resolution rejected, duplicate rating rejected, aggregation by context/score, and the zero-response state reporting `unknown`.
