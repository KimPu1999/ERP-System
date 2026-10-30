# Suppliers Phase 8 Security Report

## Review summary

- Sanctum and tenant middleware are present on internal APIs.
- Supplier portal routes require Sanctum authentication and supplier membership middleware.
- Portal purchase orders, messages, ASNs and invoice submissions are scoped by tenant, supplier and buyer company.
- Supplier bank account responses use masked references; encrypted casts protect stored account fields.
- Supplier document downloads use private local storage and company authorization.
- Automation actions are allowlisted and supplier automation rules are disabled on creation.
- Portal session-setting cleanup was hardened in this phase.

## Findings

| Severity | Finding | Status |
|---|---|---|
| High | Portal E2E/RLS isolation has not been executed with a restricted PostgreSQL role | Open verification blocker |
| High | Bank-change concurrency and raw-log redaction have not been executed | Open verification blocker |
| Medium | Supplier report export adapter coverage is limited to Supplier Directory CSV | Documented limitation |
| Medium | Malware scanning and short-lived document links are not evidenced | Open capability gap |
| Medium | Granular supplier Phase 7/8 permissions need direct API test coverage | Open verification item |
| Low | Frontend production bundle remains large | Optimization item |

No critical authorization bypass was claimed as verified. Human security review remains required.
