<?php

namespace Platform\Organization;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Organization\Console\Commands\GenerateReportsCommand;
use Platform\Organization\Console\Commands\SeedOrganizationData;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class OrganizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Falls in Zukunft Artisan Commands o.ä. nötig sind, hier rein
        if ($this->app->runningInConsole()) {
            $this->commands([
                SeedOrganizationData::class,
                GenerateReportsCommand::class,
            ]);
        }
        // Keine Services in Drip vorhanden
    }

    public function boot(): void
    {
        // Schritt 1: Config laden
        $this->mergeConfigFrom(__DIR__.'/../config/organization.php', 'organization');
        
        // Schritt 2: Existenzprüfung (config jetzt verfügbar)
        if (
            config()->has('organization.routing') &&
            config()->has('organization.navigation') &&
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key'        => 'organization',
                'title'      => 'Organization',
                'routing'    => config('organization.routing'),
                'guard'      => config('organization.guard'),
                'navigation' => config('organization.navigation'),
                'sidebar'    => config('organization.sidebar'),
            ]);
        }

        // Schritt 3: Wenn Modul registriert, Routes laden
        if (PlatformCore::getModule('organization')) {
            ModuleRouter::group('organization', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/guest.php');
            }, requireAuth: false);

            ModuleRouter::group('organization', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });

            // API-Routen registrieren
            ModuleRouter::apiGroup('organization', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
            });
        }

        // Schritt 4: Migrationen laden
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Schritt 5: Config veröffentlichen
        $this->publishes([
            __DIR__.'/../config/organization.php' => config_path('organization.php'),
        ], 'config');

        // Schritt 6: Views & Livewire
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'organization');
        $this->registerLivewireComponents();
        
        // Entity-Komponenten manuell registrieren (für Sicherheit)
        Livewire::component('organization.entity.modal-relations', \Platform\Organization\Livewire\Entity\ModalRelations::class);

        // Tools registrieren (loose gekoppelt - für AI/Chat)
        $this->registerTools();
    }

    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\Organization\\Livewire';
        $prefix = 'organization';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            if (!class_exists($class)) {
                continue;
            }

            // organization.entity.modal-relations aus organization + Entity/ModalRelations.php
            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            Livewire::component($alias, $class);
        }
    }

    /**
     * Registriert Organization-Tools für die AI/Chat-Funktionalität.
     */
    protected function registerTools(): void
    {
        try {
            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);
            $registry->register(new \Platform\Organization\Tools\ListCostCentersTool());
            $registry->register(new \Platform\Organization\Tools\OrganizationLookupsTool());
            $registry->register(new \Platform\Organization\Tools\GetOrganizationLookupTool());
            $registry->register(new \Platform\Organization\Tools\CreateCostCenterTool());
            $registry->register(new \Platform\Organization\Tools\UpdateCostCenterTool());
            $registry->register(new \Platform\Organization\Tools\DeleteCostCenterTool());

            // VSM System Tools
            $registry->register(new \Platform\Organization\Tools\ListVsmSystemsTool());
            $registry->register(new \Platform\Organization\Tools\CreateVsmSystemTool());
            $registry->register(new \Platform\Organization\Tools\UpdateVsmSystemTool());
            $registry->register(new \Platform\Organization\Tools\DeleteVsmSystemTool());

            // VSM Function Tools
            $registry->register(new \Platform\Organization\Tools\ListVsmFunctionsTool());
            $registry->register(new \Platform\Organization\Tools\CreateVsmFunctionTool());
            $registry->register(new \Platform\Organization\Tools\UpdateVsmFunctionTool());
            $registry->register(new \Platform\Organization\Tools\DeleteVsmFunctionTool());

            // Time Tracking Tools
            $registry->register(new \Platform\Organization\Tools\CreateTimeEntryTool());
            $registry->register(new \Platform\Organization\Tools\ListTimeEntriesTool());
            $registry->register(new \Platform\Organization\Tools\UpdateTimeEntryTool());
            $registry->register(new \Platform\Organization\Tools\DeleteTimeEntryTool());
            $registry->register(new \Platform\Organization\Tools\SummarizeTimeEntriesTool());

            // Entity Type Group Tools
            $registry->register(new \Platform\Organization\Tools\ListEntityTypeGroupsTool());
            $registry->register(new \Platform\Organization\Tools\CreateEntityTypeGroupTool());
            $registry->register(new \Platform\Organization\Tools\UpdateEntityTypeGroupTool());
            $registry->register(new \Platform\Organization\Tools\DeleteEntityTypeGroupTool());

            // Entity Type Tools
            $registry->register(new \Platform\Organization\Tools\ListEntityTypesTool());
            $registry->register(new \Platform\Organization\Tools\CreateEntityTypeTool());
            $registry->register(new \Platform\Organization\Tools\UpdateEntityTypeTool());
            $registry->register(new \Platform\Organization\Tools\DeleteEntityTypeTool());

            // Entity Tools (Organisationseinheiten)
            $registry->register(new \Platform\Organization\Tools\ListEntitiesTool());
            $registry->register(new \Platform\Organization\Tools\CreateEntityTool());
            $registry->register(new \Platform\Organization\Tools\UpdateEntityTool());
            $registry->register(new \Platform\Organization\Tools\DeleteEntityTool());

            // Dimension Link Tools (generisch für alle Dimensionen)
            $registry->register(new \Platform\Organization\Tools\ListDimensionLinksTool());
            $registry->register(new \Platform\Organization\Tools\LinkDimensionTool());
            $registry->register(new \Platform\Organization\Tools\UnlinkDimensionTool());

            // Relation Type Tools (Beziehungstypen - global)
            $registry->register(new \Platform\Organization\Tools\ListRelationTypesTool());
            $registry->register(new \Platform\Organization\Tools\CreateRelationTypeTool());
            $registry->register(new \Platform\Organization\Tools\UpdateRelationTypeTool());
            $registry->register(new \Platform\Organization\Tools\DeleteRelationTypeTool());

            // Entity Relationship Tools (Beziehungen zwischen Entities - team-scoped)
            $registry->register(new \Platform\Organization\Tools\ListEntityRelationshipsTool());
            $registry->register(new \Platform\Organization\Tools\CreateEntityRelationshipTool());
            $registry->register(new \Platform\Organization\Tools\UpdateEntityRelationshipTool());
            $registry->register(new \Platform\Organization\Tools\DeleteEntityRelationshipTool());

            // Interlink Category Tools (global)
            $registry->register(new \Platform\Organization\Tools\ListInterlinkCategoriesTool());
            $registry->register(new \Platform\Organization\Tools\CreateInterlinkCategoryTool());
            $registry->register(new \Platform\Organization\Tools\UpdateInterlinkCategoryTool());
            $registry->register(new \Platform\Organization\Tools\DeleteInterlinkCategoryTool());

            // Interlink Type Tools (global)
            $registry->register(new \Platform\Organization\Tools\ListInterlinkTypesTool());
            $registry->register(new \Platform\Organization\Tools\CreateInterlinkTypeTool());
            $registry->register(new \Platform\Organization\Tools\UpdateInterlinkTypeTool());
            $registry->register(new \Platform\Organization\Tools\DeleteInterlinkTypeTool());

            // Interlink Tools (team-scoped)
            $registry->register(new \Platform\Organization\Tools\ListInterlinksTool());
            $registry->register(new \Platform\Organization\Tools\CreateInterlinkTool());
            $registry->register(new \Platform\Organization\Tools\UpdateInterlinkTool());
            $registry->register(new \Platform\Organization\Tools\DeleteInterlinkTool());

            // Entity Relationship Interlink Tools (Link/Unlink - team-scoped)
            $registry->register(new \Platform\Organization\Tools\ListEntityRelationshipInterlinksTool());
            $registry->register(new \Platform\Organization\Tools\LinkInterlinkToRelationshipTool());
            $registry->register(new \Platform\Organization\Tools\UnlinkInterlinkFromRelationshipTool());

            // Report Type Tools (Berichtstypen)
            $registry->register(new \Platform\Organization\Tools\ListReportTypesTool());
            $registry->register(new \Platform\Organization\Tools\CreateReportTypeTool());
            $registry->register(new \Platform\Organization\Tools\UpdateReportTypeTool());
            $registry->register(new \Platform\Organization\Tools\DeleteReportTypeTool());

            // Report Tools (Berichte)
            $registry->register(new \Platform\Organization\Tools\ListReportsTool());
            $registry->register(new \Platform\Organization\Tools\GenerateReportTool());
        } catch (\Throwable $e) {
            \Log::warning('Organization: Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }
    }
}
