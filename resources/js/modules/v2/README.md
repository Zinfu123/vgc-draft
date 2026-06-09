# V2 frontend modules

Each module lives in `resources/js/modules/v2/{name}/` with:

- `pages/` — Inertia pages rendered as `v2/{name}/PageName`
- `components/` — private to the module
- `index.ts` — public exports only (other modules import from here)

Cross-module imports are forbidden except via `@/kernel/*` and `@/components/ui/*`.
