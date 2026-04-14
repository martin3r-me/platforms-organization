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
                'processes' => [
                    'title' => 'Prozesse',
                    'route' => 'organization.processes.index',
                    'icon' => 'heroicon-o-arrow-path',
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
                'connections' => [
                    'title' => 'Verbindungen',
                    'icon' => 'heroicon-o-link',
                    'items' => [
                        'interlinks' => [
                            'title' => 'Interlinks',
                            'route' => 'organization.interlinks.index',
                            'icon' => 'heroicon-o-arrows-right-left',
                        ],
                        'sla-contracts' => [
                            'title' => 'SLA-Verträge',
                            'route' => 'organization.sla-contracts.index',
                            'icon' => 'heroicon-o-shield-check',
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
];
