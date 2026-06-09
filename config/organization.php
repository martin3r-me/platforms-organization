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
                    ],
                ],
                'people' => [
                    'title' => 'Personen',
                    'icon' => 'heroicon-o-user-group',
                    'items' => [
                        'job-profiles' => [
                            'title' => 'JobProfiles',
                            'route' => 'organization.job-profiles.index',
                            'icon' => 'heroicon-o-identification',
                        ],
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
                'ops-room' => [
                    'title' => 'Ops-Room',
                    'route' => 'organization.ops-room',
                    'icon' => 'heroicon-o-command-line',
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
                        'signal-definitions' => [
                            'title' => 'Signaldefinitionen',
                            'route' => 'organization.settings.signal-definitions.index',
                            'icon' => 'heroicon-o-bell-alert',
                        ],
                    ],
                ],
            ],
        ],
    ],
    'inference' => [
        'max_inquiry_depth' => 3,
        'escalation_user_id' => null,
        'inquiry_check_interval_hours' => 6,
        'default_interval_hours' => 72,
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

    /*
    |--------------------------------------------------------------------------
    | Signal-Deadlines pro VSM-Ebene (in Stunden)
    |--------------------------------------------------------------------------
    |
    | Wie lange darf ein Signal auf einer Ebene "offen" liegen, bevor der
    | Eskalations-Cron es eine Stufe hoch zieht. Algedonic ist ein Sonderfall:
    | extrem kurze Deadline, weil das Signal per Beer-Konvention sofort vom
    | Top-Level wahrgenommen werden muss.
    */
    'signal_deadlines' => [
        'default' => 168, // 7 Tage
        's1' => 24,
        's2' => 48,
        's3' => 72,
        's3_star' => 72,
        's4' => 168,
        's5' => 336, // 14 Tage — strategisch, langsamer Takt
        'algedonic' => 1, // 1h — Schmerz-Kanal nach Beer
    ],
];
