<?php

namespace Platform\Organization\Contracts;

use Illuminate\Support\Carbon;

/**
 * Wer das Gedächtnis eines Agenten führt (Spiegel der Vault-Dateien, Regeln, Kuratier-Aufträge).
 *
 * Die Organisation hält nur die Agent-Schnittstelle (Adresse, Token, Heartbeat) und reicht weiter;
 * gespeichert, gezeigt und kuratiert wird im Modul, das sich hierfür bindet (people: Entwicklung des
 * Mitarbeiters). Ohne Bindung nimmt die Organisation kein Gedächtnis an.
 *
 * Alle Methoden sind über die Entity-ID des Agenten-Knotens geschlüsselt.
 */
interface AgentMemoryStore
{
    /** Erlaubte Arten gespiegelter Dateien. */
    public const DOC_KINDS = ['routine', 'index', 'questions', 'playbook'];

    /**
     * Vollständiger Spiegel der Vault-Dateien; was fehlt, ist im Vault weg.
     *
     * @param  list<array{path:string,kind:string,title:?string,sha:string,content:?string,modified_at:?int}>  $docs
     * @return array{docs:int,removed:int}
     */
    public function storeDocs(int $entityId, array $docs): array;

    /**
     * Vollständiger Spiegel der Regeln (inkl. abgelöster), Felder wie vom Engram gemeldet.
     *
     * @return array{rules:int,removed:int}
     */
    public function storeRules(int $entityId, array $rules): array;

    /**
     * Offene Aufträge, älteste zuerst.
     *
     * @return list<array{id:int,path:string,op:string,base_sha:string,content:?string,note:?string}>
     */
    public function pendingCurations(int $entityId, int $limit = 20): array;

    /** Quittung des Engrams (applied | stale | rejected). Liefert den Status danach, null = kein solcher Auftrag. */
    public function acknowledgeCuration(int $entityId, int $curationId, string $status, ?string $result): ?string;

    /**
     * Angewendete Korrekturen durch Menschen je Agent im Zeitraum (Wirksamkeit).
     *
     * @param  list<int>  $entityIds
     * @return array<int,int>
     */
    public function correctionCounts(array $entityIds, Carbon $from, Carbon $to): array;

    /** Wo man das Gedächtnis dieses Agenten ansieht und kuratiert (null = noch kein Mitarbeiter). */
    public function memoryUrl(int $entityId): ?string;
}
