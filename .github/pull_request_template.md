## V2 migration checklist

- [ ] Base branch: **`Dev`** (integration / Laravel Cloud dev). Production merges go `Dev` → `main`.
- [ ] PR type: additive / cutover / kernel / scaffold
- [ ] Module(s):
- [ ] Tests added or ported
- [ ] Deptrac / ESLint boundary rules pass
- [ ] No duplicated logic (moved to Kernel, not copied)
- [ ] v2 preview routes registered under `/v2/*` (list URLs in description)
- [ ] Feature tests cover `/v2/...` paths
- [ ] Inertia page resolves (no missing .vue)
- [ ] v2 models use same table/column names as v1 (no schema change in cutover PR)
- [ ] `php artisan module:audit {module}` passes (attach JSON output for cutover)
- [ ] Parity tests pass: `php artisan test --group=parity --filter={Module}`
- [ ] Manual QA checklist completed (link to `docs/audits/{module}.md`)

## Summary



## Test plan


