<?php

namespace Platform\Organization\Contracts;

use Platform\Organization\Models\OrganizationEntity;

/**
 * Wer einem Agenten ARBEIT hinlegt (Zustell-Aufträge, Entwürfe, Rückfragen …).
 *
 * Die Organisation hält nur den Arbeitseingang (GET /api/org/agent/work) und reicht weiter; was anliegt,
 * weiß das Modul, das sich hier registriert (AgentWorkSources::register). Der Daemon holt den Eingang
 * jeden Takt – eine Änderung weckt ihn wie eine neue Nachricht – und arbeitet ihn mit dem genannten
 * Tool ab. So braucht keine neue Quelle eine Änderung am Daemon.
 */
interface AgentWorkSource
{
    /** Stabiler Name der Quelle, z. B. 'signals'. */
    public function key(): string;

    /**
     * Was für diesen Agenten gerade anliegt – leer, wenn nichts.
     *
     * key:     stabil je Art von Arbeit (z. B. 'signals.deliveries'), Grundlage für Gewöhnung und Change-Gate
     * text:    eine Zeile Fakt („3 Zustell-Aufträge bereit, ältester seit 07:01“)
     * count:   wie viel jetzt abzuarbeiten ist (> 0)
     * weight:  0..1, wie dringend (blockiert/überfällig > nur offen)
     * tool:    das MCP-Tool, mit dem der Agent die Arbeit holt
     * how:     kurz, wie abzuarbeiten (ein, zwei Sätze; die volle Anleitung steht in der Tool-Beschreibung)
     *
     * @return list<array{key:string, text:string, count:int, weight:float, tool:string, how?:string, oldest_at?:?string}>
     */
    public function workFor(OrganizationEntity $agent): array;
}
