<?php

return [

    'pokeapi_url' => env('POKEAPI_URL', 'https://pokeapi.co/api/v2'),

    'default_version_group_slug' => env('POKEMON_DEFAULT_VERSION_GROUP', 'scarlet-violet'),

    'default_league_generation' => (int) env('POKEMON_DEFAULT_LEAGUE_GENERATION', 9),

    'default_league_game' => env('POKEMON_DEFAULT_LEAGUE_GAME', 'scarlet_violet'),

    /*
    | When importing held items from PokeAPI, an item in the "held-items" category is kept if any
    | flavor_text_entries references one of these version_group slugs. Sword/Shield is included for
    | Scarlet/Violet because many competitive items only have SWSH flavor text in PokeAPI but exist in SV.
    | Items with no flavor text (e.g. some Gen IX entries) are still imported.
    */
    'version_group_held_item_flavor_slugs' => [
        'scarlet-violet' => [
            'scarlet-violet',
            'the-teal-mask',
            'the-indigo-disk',
            'sword-shield',
        ],
    ],

    /**
     * PokeAPI `item-category` id for "Held items" (misc pocket). Always merged when using pocket-based
     * enumeration (see `pokeapi_held_item_category_ids` and `pokeapi_held_item_pocket_ids`).
     */
    'pokeapi_held_item_category_id' => (int) env('POKEMON_POKEAPI_HELD_ITEM_CATEGORY_ID', 12),

    /**
     * When non-empty, only these `item-category` IDs are used to enumerate items (full override).
     * When empty, categories are the union of `pokeapi_held_item_category_id` and every category listed
     * on each `item-pocket` in `pokeapi_held_item_pocket_ids` (default: Berries pocket only).
     */
    'pokeapi_held_item_category_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('POKEMON_POKEAPI_HELD_ITEM_CATEGORY_IDS', ''))
    ))),

    /**
     * PokeAPI `item-pocket` ids merged into enumeration when `pokeapi_held_item_category_ids` is empty.
     * 5 = Berries. Add 7 for Battle Items (X items, etc.) if needed. Held items stay under category 12 above.
     */
    'pokeapi_held_item_pocket_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('POKEMON_POKEAPI_HELD_ITEM_POCKET_IDS', '5'))
    ))),

    /**
     * Extra `item-category` ids merged when `pokeapi_held_item_category_ids` is empty (not from pockets).
     * 13 = choice, 15 = bad-held-items (Flame Orb, Toxic Orb, Sticky Barb, …), 17 = plates,
     * 18 = species-specific, 19 = type-enhancement (Charcoal, Magnet, etc.).
     */
    'pokeapi_held_item_extra_category_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('POKEMON_POKEAPI_HELD_ITEM_EXTRA_CATEGORY_IDS', '13,15,17,18,19'))
    ))),

];
