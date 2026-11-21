<?php

namespace Platform\Organization\Contracts;

use Platform\Organization\Models\OrganizationEntity;

/**
 * Quelle der Strategie eines Knotens (Mission/Vision, Regnosen, Fokusräume, Meilensteine, Ausrichtung).
 *
 * Die Organisation hält selbst keinen Strategie-Layer mehr: die Strategie lebt im Management Book.
 * Das Modul, das die Strategie führt, registriert sich über StrategySources::register(); Brief,
 * Portfolio-API, Agenten-API und Entity-Seite lesen ausschließlich über diesen Vertrag.
 */
interface StrategySource
{
    /**
     * Strategie eines Knotens, oder null, wenn er keine trägt.
     *
     * Shape:
     * [
     *   'mission'            => ['title','content','version','valid_from'] | null,
     *   'vision'             => ['title','content','version','valid_from'] | null,
     *   'focus_areas'        => [[id,title,description,order,central_question_vision_images,central_question_obstacles,
     *                             central_question_milestones, vision_images => [{id,title,description,central_question}],
     *                             obstacles => [...], milestones => [{id,title,description,central_question,target_year,
     *                             target_quarter,order,status}]], ...],
     *   'transformation_map' => ['years' => [int], 'grid' => [fa_id => [year => [milestone]]], 'no_year' => [fa_id => [milestone]]],
     *   'forecasts'          => [[id,title,target_date,content,current_version], ...],
     *   'milestone_total'    => int,
     *   'has_any'            => true,
     *   'source'             => ['label','id','title','status','url'],   // wo die Strategie gepflegt wird
     * ]
     */
    public function forEntity(OrganizationEntity $entity): ?array;

    /**
     * Meilensteine eines Teams mit Zieltermin in den angegebenen Quartalen.
     *
     * @param  array<int, array{0:int,1:int}>  $slots  [[Jahr, Quartal], ...]
     * @return array<int, array{id:int,title:string,central_question:?string,entity_id:?int,entity:?string,focus_area:?string,target_year:?int,target_quarter:?int}>
     */
    public function milestonesForTeam(int $teamId, array $slots): array;

    /**
     * Ein Objekt (Morph-Alias + ID, z. B. okr_objective) zahlt auf einen Meilenstein ein. Idempotent.
     *
     * @return array{created:bool, alignment:array}|null  null = Meilenstein im Team nicht gefunden
     *
     * @throws \InvalidArgumentException bei ungültigen Werten (z. B. weight)
     */
    public function contribute(int $teamId, int $milestoneId, string $linkableType, int $linkableId, ?int $weight, ?int $userId): ?array;

    /**
     * Ausrichtungen (worauf zahlen diese Objekte ein?) je Morph-Alias und ID.
     *
     * @param  array<string, int[]>  $idsByAlias
     * @return array<string, array<int, array<int, array>>>  [alias][id] => [alignment, ...]
     */
    public function alignmentsFor(array $idsByAlias): array;
}
