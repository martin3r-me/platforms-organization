<?php

return [
    'name' => 'Organization',
    'description' => 'Organization Module',
    'version' => '1.0.0',
    
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
            ],
        ],
    ],
];
