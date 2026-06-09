# V2 module migration tracker

Agent and human handoff file for the parallel v2 rebuild. See [architecture/adr/001-parallel-v2-modules.md](architecture/adr/001-parallel-v2-modules.md).

## Current focus

**Next PR:** `v2/pokedex-add`

**Blocked:** none

## PR queue

| # | Branch | Status | PR | Notes |
|---|--------|--------|-----|-------|
| 1 | `v2/phase-0-scaffold` | merged | #13 | |
| 2 | `v2/kernel-showdown-backend` | in progress | — | Kernel ShowdownFormatHelper + frontend shims |
| 3 | `v2/kernel-showdown-frontend` | pending | — | |
| 4 | `v2/pokedex-add` | pending | — | |
| 5 | `v2/pokedex-cutover` | pending | — | Requires manual QA on `/v2/pokedex/*` |

## Module status

| Module | Phase | Add PR | Cutover PR | Audit passed | v1 deleted | Preview URLs |
|--------|-------|--------|------------|--------------|------------|--------------|
| Kernel | 1 | #13 | — | in progress | — | — |
| Pokedex | 2 | — | — | ☐ | ☐ | `/v2/pokedex` |
| TeamCoverage | 2 | — | — | ☐ | ☐ | `/v2/team-coverage` |
| Teams | 3 | — | — | ☐ | ☐ | `/v2/teams` |
| Draft | 3 | — | — | ☐ | ☐ | `/v2/draft`, `/v2/leagues/{id}/draft` |
| Matches | 3 | — | — | ☐ | ☐ | `/v2/match`, `/v2/pools` |
| Trade | 3 | — | — | ☐ | ☐ | `/v2/leagues/{id}/trades` |
| Playoffs | 3 | — | — | ☐ | ☐ | `/v2/leagues/{id}/admin/playoffs` |
| League | 3 | — | — | ☐ | ☐ | `/v2/leagues` |
| Pokepaste | 4 | — | — | ☐ | ☐ | `/v2/pokepaste/{id}` |
| MatchPrep | 4 | — | — | ☐ | ☐ | `/v2/match-prep` |
| Dashboard | 4 | — | — | ☐ | ☐ | `/v2/dashboard` |
| Calendar | 4 | — | — | ☐ | ☐ | `/v2/calendar` |
| Stats | 5 | — | — | ☐ | ☐ | `/v2/usage-stats` |

## V2 preview nav

Set `V2_PREVIEW_NAV=true` in `.env` (local/staging) to show preview links when modules are enabled in `config/modules.php`.

Health check: `GET /v2` returns JSON with enabled modules.
