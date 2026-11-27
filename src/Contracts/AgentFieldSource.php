<?php

namespace Platform\Organization\Contracts;

use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Support\AgentDomain;

/**
 * Wer einem Agenten sein ARBEITSFELD zeigt: die Arbeitsobjekte (Projekte, Aufgaben, Tickets …), in denen
 * er arbeitet, jeweils mit der Einheit, an der sie hängen.
 *
 * Die Organisation hält nur das Feld (GET/POST /api/org/agent/field) und reicht weiter; welche Objekte es
 * gibt und wie man eines anlegt, weiß das Modul, das sich hier registriert (AgentFieldSources::register).
 * Der Daemon kennt nur allgemeine Objekte (type:id) — eine neue Quelle braucht keine Änderung am Daemon.
 */
interface AgentFieldSource
{
    /** Stabiler Name der Quelle, z. B. 'planner'. */
    public function key(): string;

    /**
     * Die Arbeitsobjekte im Feld dieses Agenten.
     *
     * type:     Alias der ObjectTypeRegistry (z. B. 'project', 'planner_task')
     * state:    Lebenszyklus in den Worten des Moduls ('aktiv', 'erledigt' …); open = noch zu tun
     * parent:   das übergeordnete Objekt (Aufgabe → Projekt), sonst null
     * unit:     die Einheit, an der das Objekt hängt (nur Wurzel-Objekte; Kinder erben über parent)
     * mine:     ich bin zuständig (Aufgabe) bzw. habe es angelegt (Projekt)
     *
     * @return list<array{type:string, id:int, title:string, state:string, open:bool, parent?:?array{type:string,id:int},
     *     unit?:?array{id:int,name:string}, mine:bool, assignee?:?string, due?:?string, overdue?:bool, points?:?string,
     *     updated_at?:?string}>
     */
    public function fieldFor(OrganizationEntity $agent, AgentDomain $domain): array;

    /**
     * Welche Objektarten diese Quelle anlegen kann, je mit der Art ihres Elternobjekts
     * ('entity' = eine Einheit der Organisation), z. B. ['project' => 'entity', 'planner_task' => 'project'].
     *
     * @return array<string, string>
     */
    public function creatable(): array;

    /**
     * Ein Arbeitsobjekt anlegen — oder das bestehende zurückgeben, wenn es dieselbe Arbeit schon offen gibt
     * (AgentFieldSources::sameWork). Ein Elternobjekt vom Typ 'entity' hat die Organisation schon gegen den
     * Bereich des Agenten geprüft. Ungültige Eingaben: \InvalidArgumentException mit Meldung für den Agenten.
     *
     * @param  array{title:string, parent:array{type:string,id:int}, description?:?string, due_date?:?string, story_points?:?string}  $input
     * @return array{object: array<string,mixed>, existing: bool}
     */
    public function create(OrganizationEntity $agent, string $type, array $input): array;
}
