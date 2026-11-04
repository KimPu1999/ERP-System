# Phase 16 — Product Adoption Measurement

Scope: what "adoption" means in this platform, who measures it, and what the Improvement Center deliberately does **not** claim.

## Ownership

Adoption analytics are owned by **Phase 13 growth analytics** (`PlatformGrowthController`, `/platform/growth/*`, onboarding progress tracking). The Improvement Center does not duplicate that pipeline.

## What the Improvement overview shows

The improvement overview displays an `adoption_summary` block that is a **pointer, not a measurement**: it names the source (`growth analytics`), the endpoint to consult, and the statement that adoption is not aggregated here. This is deliberate — the improvement KPI grid shows only counts it can compute from its own records (open feedback, open requests, open defects, releases).

## Stated limitation

The overview renders an explicit note: *page views alone do not establish adoption*. Module usage measurement (feature-level events per tenant) is not yet instrumented; until it is, no adoption percentage is shown anywhere in the product.

## What must exist before adoption numbers appear

1. Feature-level usage events recorded server-side (not inferred from HTTP access logs alone).
2. A defined activation metric per module (e.g. first posted sales document, first approved purchase order).
3. Agreement in the quarterly roadmap review on which metrics define "adopted".

Until then, any adoption figure would be fabricated, which this phase prohibits.

## Verification

Covered by `ImprovementPhase16Test.php`: the overview response contains the adoption pointer with the source/endpoint/not-aggregated fields, and contains no adoption percentage.
