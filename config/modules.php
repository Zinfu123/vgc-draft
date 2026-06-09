<?php

return [

    /*
    |--------------------------------------------------------------------------
    | V2 module migration
    |--------------------------------------------------------------------------
    |
    | enabled: module folder names under app/Modules/V2/ with a routes.php file.
    | preview_nav: show /v2 preview links in the app shell (local/staging).
    |
    */

    'v2' => [
        'enabled' => [
            'Pokedex',
        ],

        'providers' => [
            App\Modules\V2\Pokedex\Providers\PokedexServiceProvider::class,
        ],

        'preview_nav' => env('V2_PREVIEW_NAV', null),

        'preview_routes' => [
            'Pokedex' => '/v2/pokedex',
            'TeamCoverage' => '/v2/team-coverage',
            'Teams' => '/v2/teams',
            'Draft' => '/v2/draft',
            'Matches' => '/v2/match',
            'Trade' => '/v2/leagues',
            'Playoffs' => '/v2/leagues',
            'League' => '/v2/leagues',
            'Pokepaste' => '/v2/pokepaste',
            'MatchPrep' => '/v2/match-prep',
            'Dashboard' => '/v2/dashboard',
            'Calendar' => '/v2/calendar',
            'Stats' => '/v2/usage-stats',
        ],
    ],

];
