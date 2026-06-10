# Manual QA checklist — template for v2 module cutover

Copy this file to `{module}.md` when migrating a module.

## Module: {ModuleName}

## Preview URLs

- [ ] `GET /v2/...` (list all routes)

## Read paths

- [ ] Load index/list page
- [ ] Load detail page for an existing record (oldest)
- [ ] Load detail page for a recent record
- [ ] Load edge-case record (nullable fields, dropped team, bye set, etc.)

## Write paths

- [ ] Create (or update) via v2 route
- [ ] Confirm row in database matches v1 shape
- [ ] Delete or revert test data if applicable

## Integration

- [ ] Related modules still work (list dependencies)
- [ ] No new Telescope/log errors on preview routes

## Audit

- [ ] `php artisan module:audit {Module}` exits 0
- [ ] Row counts unchanged vs pre-cutover snapshot

## Sign-off

- [ ] QA complete — ready for cutover PR
- Date:
- Tester:
