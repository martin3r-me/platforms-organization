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
                'people' => [
                    'title' => 'Personen',
                    'icon' => 'heroicon-o-user-group',
                    'items' => [
                        'roles' => [
                            'title' => 'Rollen',
                            'route' => 'organization.roles.index',
                            'icon' => 'heroicon-o-user-circle',
                        ],
                    ],
                ],
                'dashboard' => [
                    'title' => 'Dashboard',
                    'route' => 'organization.dashboard',
                    'icon' => 'heroicon-o-home',
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
                        'interlink-categories' => [
                            'title' => 'Interlink-Kategorien',
                            'route' => 'organization.settings.interlink-categories.index',
                            'icon' => 'heroicon-o-tag',
                        ],
                        'interlink-types' => [
                            'title' => 'Interlink-Typen',
                            'route' => 'organization.settings.interlink-types.index',
                            'icon' => 'heroicon-o-link',
                        ],
                    ],
                ],
            ],
        ],
    ],
    // Wie wird der Score einer Dimension berechnet?
    //   'sum'     = Summe aller Metriken der Dimension (Legacy, mischt potenziell Einheiten)
    //   'primary' = nur die als is_dimension_primary markierte Metrik (vergleichbarer Score)
    //               Fallback auf 'sum', wenn keine Primary fuer die Dimension deklariert ist.
    'dimension_score_method' => env('ORGANIZATION_DIMENSION_SCORE_METHOD', 'sum'),

    'billables' => [
        [
            'model' => \Platform\Organization\Models\OrganizationEntity::class,
            'type' => 'per_item',
            'label' => 'Organisations-Einheit',
            'description' => 'Jede angelegte Organisations-Einheit verursacht tägliche Kosten nach Nutzung.',
            'pricing' => [
                ['cost_per_day' => 0.005, 'start_date' => '2025-01-01', 'end_date' => null]
            ],
            'free_quota' => null,
            'min_cost' => null,
            'max_cost' => null,
            'billing_period' => 'daily',
            'start_date' => '2026-01-01',
            'end_date' => null,
            'trial_period_days' => 0,
            'discount_percent' => 0,
            'exempt_team_ids' => [],
            'priority' => 100,
            'active' => true,
        ],
    ],

    // Kommunikation im Kontext läuft in Parlan (TRAAN rooms/0.2): das ERP chattet nicht, es löst
    // aus (signierter Absprung) und hält den Ref. Leer = Reiter „Gespräch" ausgeblendet.
    'parlan' => [
        'url' => env('PARLAN_URL'),
        'secret' => env('PARLAN_CONTEXT_SECRET'),
        // Huddle-Kalender-Spiegel (M365): leer = aus, "all" = alle Organisatoren,
        // sonst kommagetrennte E-Mails (Pilot). Ohne Wert läuft der Zeitplan nicht los.
        'huddle_mirror' => env('PARLAN_HUDDLE_MIRROR', ''),
    ],
];
