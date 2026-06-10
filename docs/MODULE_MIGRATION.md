# V2 module migration tracker

Agent and human handoff file for the parallel v2 rebuild. See [architecture/adr/001-parallel-v2-modules.md](architecture/adr/001-parallel-v2-modules.md).

## Current focus

**Integration branch:** `Dev` (Laravel Cloud dev environment)

**Next PR:** Phase 3 — `v2/matches-cutover` (after QA on `v2/matches-add`)

**Blocked:** none

## Dev environment workflow

| Branch | Environment | Deploy |
|--------|-------------|--------|
| `Dev` | Laravel Cloud **dev** | GitHub Actions → `LARAVEL_CLOUD_DEV_DEPLOY_HOOK` |
| `main` | Laravel Cloud **production** | GitHub Actions → `LARAVEL_CLOUD_DEPLOY_HOOK` |

**Day-to-day v2 work:**

1. `git checkout Dev && git pull origin Dev`
2. `git checkout -b v2/{module}-{add|cutover}`
3. Open PR **into `Dev`** (not `main`)
4. QA on the dev environment after merge to `Dev`
5. When a release batch is ready, merge `Dev` → `main`

**Dev env checklist (Laravel Cloud):**

- `APP_ENV=staging` (enables v2 preview nav by default)
- Optional: `V2_PREVIEW_NAV=true` to force on; `false` to hide
- Run migrations after deploy: `php artisan migrate --force`
- Health check: `GET /v2` returns enabled modules JSON

## PR queue

| # | Branch | Status | PR | Notes |
|---|--------|--------|-----|-------|
| 1 | `v2/phase-0-scaffold` | merged | #13 | |
| 2 | `v2/kernel-showdown-backend` | merged | #14 | Kernel ShowdownFormatHelper + frontend shims |
| 3 | `v2/pokedex-add` | merged | #15 | Preview routes + ability/move filters |
| 4 | `v2/pokedex-cutover` | merged | #16 | Production `/pokedex` serves v2 |
| 5 | `v2/team-coverage-add` | merged | #17 | Preview at `/v2/team-coverage` |
| 6 | `v2/team-coverage-cutover` | merged | #18 | Production `/team-coverage` serves v2 |
| 7 | `v2/teams-add` | merged | #19 | Preview at `/v2/teams` |
| 8 | `v2/teams-cutover` | merged | #20 | Production `/teams` serves v2 |
| 9 | `v2/draft-add` | merged | #21 | Preview at `/v2/draft` |
| 10 | `v2/draft-cutover` | merged | #22 | Production `/draft` serves v2 |
| 11 | `v2/matches-add` | in progress | — | Preview at `/v2/match`, `/v2/pools` |

## Module status

| Module | Phase | Add PR | Cutover PR | Audit passed | v1 deleted | Preview URLs |
|--------|-------|--------|------------|--------------|------------|--------------|
| Kernel | 1 | #13 | — | in progress | — | — |
| Pokedex | 2 | #15 | #16 | ☐ | partial | `/pokedex` |
| TeamCoverage | 2 | #17 | #18 | ☐ | partial | `/team-coverage` |
| Teams | 3 | #19 | #20 | ☐ | partial | `/teams` |
| Draft | 3 | #21 | #22 | ☐ | partial | `/draft` |
| Matches | 3 | in progress | — | ☐ | ☐ | `/v2/match`, `/v2/pools` |
| Trade | 3 | — | — | ☐ | ☐ | `/v2/leagues/{id}/trades` |
| Playoffs | 3 | — | — | ☐ | ☐ | `/v2/leagues/{id}/admin/playoffs` |
| League | 3 | — | — | ☐ | ☐ | `/v2/leagues` |
| Pokepaste | 4 | — | — | ☐ | ☐ | `/v2/pokepaste/{id}` |
| MatchPrep | 4 | — | — | ☐ | ☐ | `/v2/match-prep` |
| Dashboard | 4 | — | — | ☐ | ☐ | `/v2/dashboard` |
| Calendar | 4 | — | — | ☐ | ☐ | `/v2/calendar` |
| Stats | 5 | — | — | ☐ | ☐ | `/v2/usage-stats` |

## V2 preview nav

Visible by default when `APP_ENV` is `local` or `staging`. Override with `V2_PREVIEW_NAV=true|false` in `.env` / Laravel Cloud env vars.

Health check: `GET /v2` returns JSON with enabled modules.
