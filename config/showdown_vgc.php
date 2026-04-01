<?php

return [

    /**
     * Base URL for monthly stats (Smogon mirror of Pokémon Showdown ladders).
     * Chaos JSON: {chaos_base_url}/{YYYY-MM}/chaos/{format_key}-{rating}.json
     */
    'chaos_base_url' => env('SHOWDOWN_VGC_CHAOS_BASE_URL', 'https://www.smogon.com/stats'),

    /**
     * When set (YYYY-MM), scheduled sync uses this month instead of probing.
     */
    'import_period' => env('SHOWDOWN_VGC_USAGE_PERIOD'),

    /**
     * Default ladder rating suffix in filenames when version_groups.showdown_ladder_rating is null.
     */
    'default_ladder_rating' => (int) env('SHOWDOWN_VGC_DEFAULT_LADDER_RATING', 1760),

    /**
     * A format key must contain one of these substrings (case-insensitive) to be ingested or queried.
     */
    'allowed_format_substrings' => [
        'vgc',
    ],

    /**
     * Optional exact allowlist merged with substring rules.
     *
     * @var list<string>
     */
    'allowed_format_keys' => [],

];
