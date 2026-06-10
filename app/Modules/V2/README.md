# V2 backend modules

Each module lives in `app/Modules/V2/{Name}/` with:

- `Http/` — controllers, form requests, resources
- `Domain/` — models, actions, services (internal)
- `Providers/` — service provider
- `routes.php` — loaded by `routes/v2.php` when enabled in `config/modules.php`

Register the module name in `config('modules.v2.enabled')` to expose `/v2/*` preview routes.
