<?php

namespace Platform\Organization;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Organization\Console\Commands\GenerateReportsCommand;
use Platform\Organization\Console\Commands\NormalizeEntityLinkTypesCommand;
use Platform\Organization\Console\Commands\SeedOrganizationData;
use Platform\Organization\Console\Commands\SnapshotEntitiesCommand;
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
                SnapshotEntitiesCommand::class,
                NormalizeEntityLinkTypesCommand::class,
            ]);
        }

        $this->app->singleton(\Platform\Organization\Services\EntityLinkRegistry::class);
        $this->app->singleton(\Platform\Organization\Services\PersonActivityRegistry::class);
    }

    public function boot(): void
    {
        // Morph-Map-Aliase für JobProfile-, Role- und Process-Modelle
        Relation::morphMap([
            'organization_job_profile'        => \Platform\Organization\Models\OrganizationJobProfile::class,
            'organization_person_job_profile' => \Platform\Organization\Models\OrganizationPersonJobProfile::class,
            'organization_role'               => \Platform\Organization\Models\OrganizationRole::class,
            'organization_role_assignment'    => \Platform\Organization\Models\OrganizationRoleAssignment::class,
            'organization_process'            => \Platform\Organization\Models\OrganizationProcess::class,
            'organization_process_step'       => \Platform\Organization\Models\OrganizationProcessStep::class,
        ]);

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
                'group'      => 'admin',
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

        // Scheduler registrieren
        $this->registerSchedule();
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

    protected function registerSchedule(): void
    {
        Schedule::command('organization:snapshot-entities --period=morning')
            ->dailyAt('08:00')
            ->withoutOverlapping();

        Schedule::command('organization:snapshot-entities --period=evening')
            ->dailyAt('18:00')
            ->withoutOverlapping();
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

            // SLA Contract Tools
            $registry->register(new \Platform\Organization\Tools\ListSlaContractsTool());
            $registry->register(new \Platform\Organization\Tools\CreateSlaContractTool());
            $registry->register(new \Platform\Organization\Tools\UpdateSlaContractTool());
            $registry->register(new \Platform\Organization\Tools\DeleteSlaContractTool());

            // JobProfile Tools
            $registry->register(new \Platform\Organization\Tools\ListJobProfilesTool());
            $registry->register(new \Platform\Organization\Tools\CreateJobProfileTool());
            $registry->register(new \Platform\Organization\Tools\UpdateJobProfileTool());
            $registry->register(new \Platform\Organization\Tools\DeleteJobProfileTool());

            // Person ↔ JobProfile Tools
            $registry->register(new \Platform\Organization\Tools\ListPersonJobProfilesTool());
            $registry->register(new \Platform\Organization\Tools\CreatePersonJobProfileTool());
            $registry->register(new \Platform\Organization\Tools\UpdatePersonJobProfileTool());
            $registry->register(new \Platform\Organization\Tools\DeletePersonJobProfileTool());

            // Role Tools (Rollen-Katalog)
            $registry->register(new \Platform\Organization\Tools\ListRolesTool());
            $registry->register(new \Platform\Organization\Tools\CreateRoleTool());
            $registry->register(new \Platform\Organization\Tools\UpdateRoleTool());
            $registry->register(new \Platform\Organization\Tools\DeleteRoleTool());

            // Role Assignment Tools (Person ⇄ Rolle ⇄ Kontext)
            $registry->register(new \Platform\Organization\Tools\ListRoleAssignmentsTool());
            $registry->register(new \Platform\Organization\Tools\CreateRoleAssignmentTool());
            $registry->register(new \Platform\Organization\Tools\UpdateRoleAssignmentTool());
            $registry->register(new \Platform\Organization\Tools\DeleteRoleAssignmentTool());

            // Process Tools (Prozess-Definition)
            $registry->register(new \Platform\Organization\Tools\ListProcessesTool());
            $registry->register(new \Platform\Organization\Tools\CreateProcessTool());
            $registry->register(new \Platform\Organization\Tools\UpdateProcessTool());
            $registry->register(new \Platform\Organization\Tools\DeleteProcessTool());

            // Process Step Tools
            $registry->register(new \Platform\Organization\Tools\ListProcessStepsTool());
            $registry->register(new \Platform\Organization\Tools\CreateProcessStepTool());
            $registry->register(new \Platform\Organization\Tools\UpdateProcessStepTool());
            $registry->register(new \Platform\Organization\Tools\DeleteProcessStepTool());

            // Process Flow Tools (Verbindungen zwischen Steps)
            $registry->register(new \Platform\Organization\Tools\ListProcessFlowsTool());
            $registry->register(new \Platform\Organization\Tools\CreateProcessFlowTool());
            $registry->register(new \Platform\Organization\Tools\UpdateProcessFlowTool());
            $registry->register(new \Platform\Organization\Tools\DeleteProcessFlowTool());

            // Process Trigger Tools
            $registry->register(new \Platform\Organization\Tools\ListProcessTriggersTool());
            $registry->register(new \Platform\Organization\Tools\CreateProcessTriggerTool());
            $registry->register(new \Platform\Organization\Tools\UpdateProcessTriggerTool());
            $registry->register(new \Platform\Organization\Tools\DeleteProcessTriggerTool());

            // Process Output Tools
            $registry->register(new \Platform\Organization\Tools\ListProcessOutputsTool());
            $registry->register(new \Platform\Organization\Tools\CreateProcessOutputTool());
            $registry->register(new \Platform\Organization\Tools\UpdateProcessOutputTool());
            $registry->register(new \Platform\Organization\Tools\DeleteProcessOutputTool());

            // Process Step Entity Tools (wer macht was im Step)
            $registry->register(new \Platform\Organization\Tools\ListProcessStepEntitiesTool());
            $registry->register(new \Platform\Organization\Tools\CreateProcessStepEntityTool());
            $registry->register(new \Platform\Organization\Tools\UpdateProcessStepEntityTool());
            $registry->register(new \Platform\Organization\Tools\DeleteProcessStepEntityTool());

            // Process Step Interlink Tools (welche Interlinks am Step)
            $registry->register(new \Platform\Organization\Tools\ListProcessStepInterlinksTool());
            $registry->register(new \Platform\Organization\Tools\CreateProcessStepInterlinkTool());
            $registry->register(new \Platform\Organization\Tools\UpdateProcessStepInterlinkTool());
            $registry->register(new \Platform\Organization\Tools\DeleteProcessStepInterlinkTool());

            // Process Snapshot Tools
            $registry->register(new \Platform\Organization\Tools\CreateProcessSnapshotTool());
            $registry->register(new \Platform\Organization\Tools\ListProcessSnapshotsTool());
            $registry->register(new \Platform\Organization\Tools\GetProcessSnapshotTool());
            $registry->register(new \Platform\Organization\Tools\CompareProcessSnapshotsTool());

            // Process Improvement Tools
            $registry->register(new \Platform\Organization\Tools\CreateProcessImprovementTool());
            $registry->register(new \Platform\Organization\Tools\ListProcessImprovementsTool());
            $registry->register(new \Platform\Organization\Tools\UpdateProcessImprovementTool());
            $registry->register(new \Platform\Organization\Tools\DeleteProcessImprovementTool());

            // Process Group Tools (thematisches Clustering von Prozessen)
            $registry->register(new \Platform\Organization\Tools\ListProcessGroupsTool());
            $registry->register(new \Platform\Organization\Tools\CreateProcessGroupTool());
            $registry->register(new \Platform\Organization\Tools\UpdateProcessGroupTool());
            $registry->register(new \Platform\Organization\Tools\DeleteProcessGroupTool());
        } catch (\Throwable $e) {
            \Log::warning('Organization: Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }
    }
}
