<?php

namespace Platform\Organization\Contracts;

/**
 * Wer die WIRKUNG eines Agenten kennt (Score, Teile, Teilwerte, Handlungsbedarf).
 *
 * Die Organisation hält nur die Agent-Schnittstelle und reicht weiter; gerechnet wird im Modul,
 * das sich hierfür bindet (people: dieselbe Brille für Mensch und Agent). Ohne Bindung keine Wirkung.
 */
interface AgentImpactProvider
{
    /**
     * Wirkung des Agenten-Knotens im rollierenden Quartal, oder null, wenn er dort kein Mitarbeiter ist.
     *
     * @return array{score:float, previous_score:float, trend:float, parts:array, indicators:array, counts:array,
     *               needs:list<array{key:string,severity:string,text:string}>, window:array, sources:list<string>}|null
     */
    public function impactFor(int $entityId): ?array;
}
