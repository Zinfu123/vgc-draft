# ADR 002: Shared Database Data Preservation

## Status

Accepted

## Context

V2 is a code restructure, not a data migration. Production data must remain intact across module cutovers.

## Decision

- **Single MySQL database** for v1 and v2
- V2 Eloquent models use **identical table and column names** as v1
- **No schema changes** in cutover PRs
- Cutover deletes PHP/Vue code only, never drops tables
- `module:audit` commands verify row counts and FK integrity before/after cutover
- Optional DB snapshot before first production cutover

## Consequences

- No ETL or dual-write required
- Rollback = revert cutover PR; v1 code reads same rows
- Write-path parity tests required before cutover merge
