# V2 module migration tracker

Agent and human handoff file for the parallel v2 rebuild. See [architecture/adr/001-parallel-v2-modules.md](architecture/adr/001-parallel-v2-modules.md).

## Current focus

**Next PR:** Phase 3 — `v2/draft-cutover` (after QA on `v2/draft-add`)

**Blocked:** none

## PR queue

| # | Branch | Status | PR | Notes |
|---|--------|--------|-----|-------|
| 1 | `v2/phase-0-scaffold` | merged | #13 | |
| 2 | `v2/kernel-showdown-backend` | merged | #14 | Kernel ShowdownFormatHelper + frontend shims |
| 3 | `v2/pokedex-add` | merged | #15 | Preview routes + ability/move filters |
| 4 | `v2/pokedex-cutover` | merged | #16 | Production `/pokedex` serves v2 |
| 5 | `v2/team-coverage-add` | merged | #17 | Preview at `/v2/team-coverage` |
| 6 | `v2/team-coverage-cutover` | merged | #18 | Production `/team-coverage` serves v2 |
| 7 | `v2/teams-add` | merged/pending | #19 | Preview at `/v2/teams` |
| 8 | `v2/teams-cutover` | merged/pending | #20 | Production `/teams` serves v2 |
| 9 | `v2/draft-add` | in progress | — | Preview at `/v2/draft` |

## Module status

| Module | Phase | Add PR | Cutover PR | Audit passed | v1 deleted | Preview URLs |
|--------|-------|--------|------------|--------------|------------|--------------|
| Kernel | 1 | #13 | — | in progress | — | — |
| Pokedex | 2 | #15 | #16 | ☐ | partial | `/pokedex` |
| TeamCoverage | 2 | #17 | #18 | ☐ | partial | `/team-coverage` |
| Teams | 3 | #19 | #20 | ☐ | partial | `/teams` |
| Draft | 3 | in progress | — | ☐ | ☐ | `/v2/draft` |
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
