# ADR 001: Parallel V2 Modules

## Status

Accepted

## Context

The application uses folder-based modules under `app/Modules/` with direct imports and Eloquent cross-module relationships. We need decoupled module boundaries without a big-bang rewrite or separate database.

## Decision

- Introduce `app/Modules/V2/{Module}/` alongside existing v1 modules
- Shared contracts and utilities live in `app/Kernel/`
- V1 and V2 share the **same database tables**
- Preview routes under `/v2/*` with `v2.*` route names until cutover
- Cross-module communication in v2: Kernel contracts + domain events only
- Deptrac enforces: `ModulesV2` may depend on `Kernel` only

## Consequences

- Additive PRs are low risk (v1 routes unchanged)
- Cutover PRs switch production routes and delete v1 code
- Parity tests compare v1 vs v2 behavior before cutover
- `module:audit-{name}` commands verify data integrity
