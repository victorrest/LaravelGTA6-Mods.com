<?php

return [
    'asset_source' => env('GTA6_ASSET_SOURCE', base_path('..')),
    'brand' => [
        'name' => 'GTA6-Mods.com',
        'tagline' => 'Discover the latest GTA 6 PC mods created by our community.',
    ],
    'navigation' => [
        [
            'slug' => 'tools',
            'label' => 'Tools',
            'icon' => 'fa-screwdriver-wrench',
        ],
        [
            'slug' => 'scripts',
            'label' => 'Scripts',
            'icon' => 'fa-code',
        ],
        [
            'slug' => 'vehicles',
            'label' => 'Vehicles',
            'icon' => 'fa-car',
        ],
        [
            'slug' => 'weapons',
            'label' => 'Weapons',
            'icon' => 'fa-gun',
        ],
        [
            'slug' => 'graphics',
            'label' => 'Graphics',
            'icon' => 'fa-wand-magic-sparkles',
        ],
        [
            'slug' => 'maps',
            'label' => 'Maps',
            'icon' => 'fa-map',
        ],
        [
            'slug' => 'player',
            'label' => 'Player',
            'icon' => 'fa-user-astronaut',
        ],
        [
            'slug' => 'misc',
            'label' => 'Misc',
            'icon' => 'fa-puzzle-piece',
        ],
    ],
];
