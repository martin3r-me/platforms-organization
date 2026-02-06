<?php

return [
    'name' => 'Organization',
    'description' => 'Organization Module',
    'version' => '1.0.0',
    
    // Scope-Type: 'parent' = root-scoped (immer Root-Team-ID), 'single' = team-spezifisch
    'scope_type' => 'parent',
    
    'routing' => [
        'prefix' => 'organization',
        'middleware' => ['web', 'auth'],
    ],
    
    'guard' => 'web',
    
    'navigation' => [
        'main' => [
            'organization' => [
                'title' => 'Organization',
                'icon' => 'heroicon-o-building-office',
                'route' => 'organization.dashboard',
            ],
        ],
    ],
    
    'sidebar' => [
        'organization' => [
            'title' => 'Organization',
            'icon' => 'heroicon-o-building-office',
            'items' => [
                'dimensions' => [
                    'title' => 'Dimensionen',
                    'icon' => 'heroicon-o-adjustments-horizontal',
                    'items' => [
                        'cost-centers' => [
                            'title' => 'Kostenstellen',
                            'route' => 'organization.cost-centers.index',
                            'icon' => 'heroicon-o-currency-dollar',
                        ],
                        'vsm-systems' => [
                            'title' => 'VSM Systeme',
                            'route' => 'organization.vsm-systems.index',
                            'icon' => 'heroicon-o-rectangle-group',
                        ],
                    ],
                ],
                'dashboard' => [
                    'title' => 'Dashboard',
                    'route' => 'organization.dashboard',
                    'icon' => 'heroicon-o-home',
                ],
                'companies' => [
                    'title' => 'Unternehmen',
                    'route' => 'organization.companies.index',
                    'icon' => 'heroicon-o-building-office',
                ],
                'departments' => [
                    'title' => 'Abteilungen',
                    'route' => 'organization.departments.index',
                    'icon' => 'heroicon-o-squares-2x2',
                ],
                'cost-centers' => [
                    'title' => 'Kostenstellen',
                    'route' => 'organization.cost-centers.index',
                    'icon' => 'heroicon-o-currency-dollar',
                ],
                'locations' => [
                    'title' => 'Standorte',
                    'route' => 'organization.locations.index',
                    'icon' => 'heroicon-o-map-pin',
                ],
                'settings' => [
                    'title' => 'Einstellungen',
                    'icon' => 'heroicon-o-cog-6-tooth',
                    'items' => [
                        'entity-types' => [
                            'title' => 'Entity Types',
                            'route' => 'organization.settings.entity-types.index',
                            'icon' => 'heroicon-o-cube',
                        ],
                        'entity-type-groups' => [
                            'title' => 'Entity Type Groups',
                            'route' => 'organization.settings.entity-type-groups.index',
                            'icon' => 'heroicon-o-rectangle-group',
                        ],
                        'relation-types' => [
                            'title' => 'Relation Types',
                            'route' => 'organization.settings.relation-types.index',
                            'icon' => 'heroicon-o-arrows-right-left',
                        ],
                    ],
                ],
            ],
        ],
    ],
];
