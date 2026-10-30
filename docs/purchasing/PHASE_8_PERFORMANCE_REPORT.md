# Purchasing Phase 8 Performance Report

## Executed measurements

- Laravel regression suite: 78 tests, 317 assertions, 37.99 seconds with local PostgreSQL access. This is a test-suite duration, not an API SLA.
- Frontend production build: 12.53 seconds; generated JavaScript is 1,916.86 kB minified / 582.92 kB gzip.
- Vite emitted a chunk-size warning for chunks above 500 kB.
- No representative large-dataset load test, SQL plan capture, memory profile, queue throughput test, or export benchmark was executed.

## Acceptance thresholds to establish in staging

Before production approval, measure p95 authenticated list API latency, p95 detail/post latency, query count per request, export queue time, and queue throughput against the target hosting tier. Thresholds must be agreed by the owner; no arbitrary SLA is claimed here.

## Recommendations

Use server-side pagination for all registers, capture `EXPLAIN (ANALYZE, BUFFERS)` for purchasing analytics and AP aging, benchmark 10k+ supplier/PO datasets, and split the frontend bundle by purchasing route before a performance-sensitive release.
