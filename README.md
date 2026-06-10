# VGC Draft

A league management and team drafting application for competitive Pokémon VGC (Video Game Championships). Organizers and players can manage league pools, run snake drafts, track match results, execute trades, manage playoff brackets, and prep match strategies — all in one place.

## Stack

- **Backend:** Laravel 12, PHP 8.4
- **Frontend:** Vue 3, Inertia.js v2, TypeScript, Tailwind CSS v4
- **Database:** SQLite (dev), compatible with MySQL/PostgreSQL
- **Real-time:** Laravel Reverb + Echo (optional)
- **Auth:** Email/password + Discord OAuth (Socialite)
- **Testing:** Pest v3

## Requirements

- PHP 8.4+
- Composer
- Node.js 20+
- SQLite (default) or MySQL/PostgreSQL

## Setup

```bash
# Clone and install dependencies
git clone <repo-url> vgc-draft
cd vgc-draft
composer install
npm install

# Environment
cp .env.example .env
php artisan key:generate

# Database
touch database/database.sqlite
php artisan migrate

# Seed the Pokédex (fetches from PokeAPI)
php artisan pokemon:import-version-group scarlet-violet

# Build frontend assets
npm run build

# Start the dev server
composer run dev
```

## Development

```bash
# Full dev environment (server + Vite + queue worker)
composer run dev

# Run tests
php artisan test

# Format PHP code
vendor/bin/pint --dirty

# Lint & format frontend
npm run lint
npm run format
```

## Discord OAuth (optional)

To enable Discord login and account linking, add to `.env`:

```env
DISCORD_CLIENT_ID=your_client_id
DISCORD_CLIENT_SECRET=your_client_secret
DISCORD_REDIRECT_URI="${APP_URL}/auth/discord/callback"
```

Create an app at [discord.com/developers](https://discord.com/developers/applications) and add the redirect URI.

## Real-time Broadcasts (optional)

To enable live draft pick notifications and match updates via Laravel Reverb:

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your_app_id
REVERB_APP_KEY=your_app_key
REVERB_APP_SECRET=your_app_secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

Start the Reverb server:

```bash
php artisan reverb:start
```

## Key Artisan Commands

| Command | Description |
|---|---|
| `pokemon:import-version-group {version}` | Import Pokémon data from PokeAPI |
| `pokemon:import-held-items {version}` | Import held item data |
| `league:import-draft-csv {file}` | Import league draft from CSV |
| `league:import-pokemon-template {file}` | Import a Pokémon pool template |
| `league:template-from-league {league}` | Export a league's pool as a template |
| `stats:rebuild` | Rebuild Pokémon usage statistics |
| `db:import-csv {file}` | Import a raw database CSV dump |

## Application Structure

```
app/
├── Console/Commands/       # Artisan commands
├── Models/                 # Eloquent models
├── Modules/                # Feature modules (13 total)
│   ├── Dashboard/
│   ├── Draft/
│   ├── League/
│   ├── Matches/
│   ├── MatchPrep/
│   ├── Playoffs/
│   ├── Pokedex/
│   ├── Pokepaste/
│   ├── Settings/
│   ├── Stats/
│   ├── Teams/
│   └── Trade/
└── Policies/               # Authorization policies

resources/
├── js/
│   ├── pages/              # Inertia page components
│   ├── components/         # Reusable Vue components
│   ├── composables/        # Vue composition functions
│   ├── layouts/            # Page layouts
│   └── types/              # TypeScript definitions
└── css/

database/
├── migrations/             # 40+ schema migrations
├── factories/              # Model factories
└── seeders/
```

Each module under `app/Modules/` follows the same structure:

```
ModuleName/
├── Actions/        # Single-responsibility action classes
├── Controllers/    # HTTP controllers
├── Requests/       # Form request validation
└── Services/       # Business logic services
```

## In-App Documentation

A full user-facing documentation page is available at `/docs` once the app is running.

## License

Private — all rights reserved.
