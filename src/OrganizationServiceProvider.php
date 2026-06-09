<?php

namespace Platform\Organization;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Organization\Console\Commands\CleanupInferenceRunsCommand;
use Platform\Organization\Console\Commands\DimensionCoverageCommand;
use Platform\Organization\Console\Commands\DimensionScoreDiffCommand;
use Platform\Organization\Console\Commands\EvaluateSignalsCommand;
use Platform\Organization\Console\Commands\GenerateReportsCommand;
use Platform\Organization\Console\Commands\ProcessInferenceTriggersCommand;
use Platform\Organization\Console\Commands\ScheduleInferenceCommand;
use Platform\Organization\Console\Commands\ScheduleSynthesisCommand;
use Platform\Organization\Console\Commands\SeedOrganizationData;
use Platform\Organization\Console\Commands\DecayEnvironmentRelevanceCommand;
use Platform\Organization\Console\Commands\SeedEnvironmentSourcesCommand;
use Platform\Organization\Console\Commands\PullEnvironmentSourcesCommand;
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
                EvaluateSignalsCommand::class,
                ProcessInferenceTriggersCommand::class,
                ScheduleInferenceCommand::class,
                ScheduleSynthesisCommand::class,
                CleanupInferenceRunsCommand::class,
                PullEnvironmentSourcesCommand::class,
                DecayEnvironmentRelevanceCommand::class,
                SeedEnvironmentSourcesCommand::class,
                DimensionCoverageCommand::class,
                DimensionScoreDiffCommand::class,
                \Platform\Organization\Console\Commands\EscalateSignalsCommand::class,
            ]);
        }

        $this->app->singleton(\Platform\Organization\Services\EntityLinkRegistry::class);
        $this->app->singleton(\Platform\Organization\Services\PersonActivityRegistry::class);
        $this->app->singleton(\Platform\Organization\Services\InferencePromptService::class);
    }

    public function boot(): void
    {
        // Morph-Map-Aliase für JobProfile-, Role- und Process-Modelle
        Relation::morphMap([
            'organization_job_profile'        => \Platform\Organization\Models\OrganizationJobProfile::class,
            'organization_person_job_profile' => \Platform\Organization\Models\OrganizationPersonJobProfile::class,
            'organization_role'               => \Platform\Organization\Models\OrganizationRole::class,
            'organization_role_assignment'    => \Platform\Organization\Models\OrganizationRoleAssignment::class,
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
        
        // Observer: Entity ↔ DimensionValue sync
        \Platform\Organization\Models\OrganizationEntity::observe(
            \Platform\Organization\Observers\OrganizationEntityDimensionObserver::class
        );

        // Entity-Komponenten manuell registrieren (für Sicherheit)
        Livewire::component('organization.entity.modal-relations', \Platform\Organization\Livewire\Entity\ModalRelations::class);
        Livewire::component('organization.entity.person-activity', \Platform\Organization\Livewire\Entity\PersonActivity::class);

        // Tools registrieren (loose gekoppelt - für AI/Chat)
        $this->registerTools();

        // Error Reporter Registration
        try {
            resolve(\Platform\Core\Services\ErrorReporterRegistry::class)
                ->register('organization', 'Platform\\Organization');
        } catch (\Throwable $e) {}

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
        // Snapshots: 2x daily
        Schedule::command('organization:snapshot-entities --period=morning')
            ->dailyAt('08:00')
            ->withoutOverlapping();

        Schedule::command('organization:snapshot-entities --period=evening')
            ->dailyAt('18:00')
            ->withoutOverlapping();

        // Rule-based signal evaluation: 2x daily
        Schedule::command('organization:evaluate-signals')
            ->dailyAt('08:15')
            ->withoutOverlapping();

        Schedule::command('organization:evaluate-signals')
            ->dailyAt('18:15')
            ->withoutOverlapping();

        // Inference scheduling: daily poll, individual prompt intervals decide what's due
        Schedule::command('organization:schedule-inference')
            ->dailyAt('08:05')
            ->withoutOverlapping();

        // Signal-Eskalation: stuendlich pruefen ob Deadlines ueberschritten sind
        // und ggf. VSM-Ebene hochsetzen oder in aeussere Perspektive aggregieren.
        Schedule::command('organization:escalate-signals')
            ->hourly()
            ->withoutOverlapping();

        // Process inference triggers: every minute (cheap polling)
        Schedule::command('organization:process-inference-triggers')
            ->everyMinute()
            ->withoutOverlapping();

        // Weekly synthesis report: Friday 16:00
        Schedule::command('organization:schedule-synthesis --type=weekly')
            ->weeklyOn(5, '16:00')
            ->withoutOverlapping();

        // Monthly synthesis report: 1st of month 08:00
        Schedule::command('organization:schedule-synthesis --type=monthly')
            ->monthlyOn(1, '08:00')
            ->withoutOverlapping();

        // Environment data pulling: hourly
        Schedule::command('organization:pull-environment-sources')
            ->hourly()
            ->withoutOverlapping();

        // Environment relevance decay: weekly
        Schedule::command('organization:decay-environment-relevance')
            ->weekly()
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

            // Planned Time Tools
            $registry->register(new \Platform\Organization\Tools\CreatePlannedTimeTool());
            $registry->register(new \Platform\Organization\Tools\ListPlannedTimeTool());
            $registry->register(new \Platform\Organization\Tools\UpdatePlannedTimeTool());
            $registry->register(new \Platform\Organization\Tools\DeletePlannedTimeTool());

            // Planned Period Tools (Soll-Zeiträume)
            $registry->register(new \Platform\Organization\Tools\CreatePlannedPeriodTool());
            $registry->register(new \Platform\Organization\Tools\ListPlannedPeriodTool());
            $registry->register(new \Platform\Organization\Tools\UpdatePlannedPeriodTool());
            $registry->register(new \Platform\Organization\Tools\DeletePlannedPeriodTool());

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

            // Entity Traversal & Aggregation Tools
            $registry->register(new \Platform\Organization\Tools\GetEntityDetailTool());
            $registry->register(new \Platform\Organization\Tools\GetEntitySummaryTool());
            $registry->register(new \Platform\Organization\Tools\ResolveContextTool());
            $registry->register(new \Platform\Organization\Tools\LinkEntityTool());

            // Dimension Link Tools (generisch für alle Dimensionen)
            $registry->register(new \Platform\Organization\Tools\ListDimensionValuesTool());
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


            // Movement & Metric Definition Tools
            $registry->register(new \Platform\Organization\Tools\EntityMovementTool());
            $registry->register(new \Platform\Organization\Tools\MetricDefinitionsTool());

            // Perspective Tools (Perspektiven-Layer)
            $registry->register(new \Platform\Organization\Tools\ListPerspectivesTool());
            $registry->register(new \Platform\Organization\Tools\SwitchPerspectiveTool());

            // VSM-Assignments (S1-S5 Besetzung pro Carrier-Perspektive)
            $registry->register(new \Platform\Organization\Tools\ListVsmAssignmentsTool());
            $registry->register(new \Platform\Organization\Tools\CreateVsmAssignmentTool());
            $registry->register(new \Platform\Organization\Tools\UpdateVsmAssignmentTool());
            $registry->register(new \Platform\Organization\Tools\DeleteVsmAssignmentTool());
            $registry->register(new \Platform\Organization\Tools\ListVsmVacanciesTool());
            $registry->register(new \Platform\Organization\Tools\ListVsmActorCoverageTool());

            // Skill-Katalog Tools
            $registry->register(new \Platform\Organization\Tools\ListSkillsTool());
            $registry->register(new \Platform\Organization\Tools\CreateSkillTool());
            $registry->register(new \Platform\Organization\Tools\UpdateSkillTool());
            $registry->register(new \Platform\Organization\Tools\DeleteSkillTool());

            // Soft-Skill-Katalog Tools
            $registry->register(new \Platform\Organization\Tools\ListSoftSkillsTool());
            $registry->register(new \Platform\Organization\Tools\CreateSoftSkillTool());
            $registry->register(new \Platform\Organization\Tools\UpdateSoftSkillTool());
            $registry->register(new \Platform\Organization\Tools\DeleteSoftSkillTool());

            // JobProfile ↔ Skill Zuordnung
            $registry->register(new \Platform\Organization\Tools\ListJobProfileSkillsTool());
            $registry->register(new \Platform\Organization\Tools\AssignJobProfileSkillTool());
            $registry->register(new \Platform\Organization\Tools\UpdateJobProfileSkillTool());
            $registry->register(new \Platform\Organization\Tools\RemoveJobProfileSkillTool());

            // JobProfile ↔ Soft-Skill Zuordnung
            $registry->register(new \Platform\Organization\Tools\ListJobProfileSoftSkillsTool());
            $registry->register(new \Platform\Organization\Tools\AssignJobProfileSoftSkillTool());
            $registry->register(new \Platform\Organization\Tools\UpdateJobProfileSoftSkillTool());
            $registry->register(new \Platform\Organization\Tools\RemoveJobProfileSoftSkillTool());

            // Person ↔ Skill Zuordnung
            $registry->register(new \Platform\Organization\Tools\ListPersonSkillsTool());
            $registry->register(new \Platform\Organization\Tools\AssignPersonSkillTool());
            $registry->register(new \Platform\Organization\Tools\UpdatePersonSkillTool());
            $registry->register(new \Platform\Organization\Tools\RemovePersonSkillTool());

            // Person ↔ Soft-Skill Zuordnung
            $registry->register(new \Platform\Organization\Tools\ListPersonSoftSkillsTool());
            $registry->register(new \Platform\Organization\Tools\AssignPersonSoftSkillTool());
            $registry->register(new \Platform\Organization\Tools\UpdatePersonSoftSkillTool());
            $registry->register(new \Platform\Organization\Tools\RemovePersonSoftSkillTool());

            // Signal (Algedonic) Tools
            $registry->register(new \Platform\Organization\Tools\ListSignalDefinitionsTool());
            $registry->register(new \Platform\Organization\Tools\CreateSignalDefinitionTool());
            $registry->register(new \Platform\Organization\Tools\UpdateSignalDefinitionTool());
            $registry->register(new \Platform\Organization\Tools\DeleteSignalDefinitionTool());
            $registry->register(new \Platform\Organization\Tools\ListSignalsTool());
            $registry->register(new \Platform\Organization\Tools\AcknowledgeSignalTool());
            $registry->register(new \Platform\Organization\Tools\CommentSignalTool());
            $registry->register(new \Platform\Organization\Tools\SnoozeSignalTool());

            // Signal Inference (Semantic Layer)
            $registry->register(new \Platform\Organization\Tools\ListSignalInferencePromptsTool());
            $registry->register(new \Platform\Organization\Tools\CreateSignalInferencePromptTool());
            $registry->register(new \Platform\Organization\Tools\UpdateSignalInferencePromptTool());
            $registry->register(new \Platform\Organization\Tools\DeleteSignalInferencePromptTool());
            $registry->register(new \Platform\Organization\Tools\EvaluateSignalInferenceTool());
            $registry->register(new \Platform\Organization\Tools\CreateInferenceSignalTool());
            $registry->register(new \Platform\Organization\Tools\DoNothingInferenceTool());

            // Organizational Memory (Layer 3)
            $registry->register(new \Platform\Organization\Tools\ListMemoryEntriesTool());
            $registry->register(new \Platform\Organization\Tools\CreateMemoryEntryTool());
            $registry->register(new \Platform\Organization\Tools\UpdateMemoryEntryTool());
            $registry->register(new \Platform\Organization\Tools\DeleteMemoryEntryTool());

            // Inquiry System (Formular-Engine)
            $registry->register(new \Platform\Organization\Tools\ListInquiriesTool());
            $registry->register(new \Platform\Organization\Tools\CreateInquiryTool());
            $registry->register(new \Platform\Organization\Tools\RespondInquiryTool());
            $registry->register(new \Platform\Organization\Tools\CancelInquiryTool());
            $registry->register(new \Platform\Organization\Tools\RemindInquiryTool());
            $registry->register(new \Platform\Organization\Tools\InquiryComplianceTool());

            // Inference Runs
            $registry->register(new \Platform\Organization\Tools\ListInferenceRunsTool());
            $registry->register(new \Platform\Organization\Tools\ExecuteInferenceRunTool());

            // Synthesis Reports
            $registry->register(new \Platform\Organization\Tools\ListSynthesisReportsTool());
            $registry->register(new \Platform\Organization\Tools\GenerateSynthesisReportTool());

            // Prompt Precision Stats
            $registry->register(new \Platform\Organization\Tools\ListPromptStatsTool());

            // Inference Debug Tools
            $registry->register(new \Platform\Organization\Tools\InferenceHealthCheckTool());
            $registry->register(new \Platform\Organization\Tools\InferenceLogsTool());

            // Environment Data Layer
            $registry->register(new \Platform\Organization\Tools\ListEnvironmentSourcesTool());
            $registry->register(new \Platform\Organization\Tools\CreateEnvironmentSourceTool());
            $registry->register(new \Platform\Organization\Tools\UpdateEnvironmentSourceTool());
            $registry->register(new \Platform\Organization\Tools\DeleteEnvironmentSourceTool());
            $registry->register(new \Platform\Organization\Tools\ListEnvironmentSnapshotsTool());
            $registry->register(new \Platform\Organization\Tools\RateEnvironmentSourceTool());

        } catch (\Throwable $e) {
            \Log::warning('Organization: Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }
    }
}
