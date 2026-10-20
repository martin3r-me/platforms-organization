---
title: Agent-Einstieg & Orientierungs-Schicht (Plan)
order: 98
---

# Agent-Einstieg & Orientierungs-Schicht (Plan)

Stand 2026-10-20. Ziel: Agenten (MCP-Sessions und Daemon-Claims) steigen über
organization ein, wenn sie eine Aufgabe haben. Modul-Tools (planner, helpdesk, …)
sind danach nur noch für CRUD da.

## Ausgangslage (Audit 2026-10-20)

- `core.context.GET` liefert Session-Kontext (User, Team, Root-Entity, OKR-Zyklus,
  Tool-Katalog) — **ohne Aufgaben-/Objekt-Input**.
- Daemon-Claims (dev `issuePayload`, planner `taskPayload`, helpdesk next-backlog)
  bauen Briefings modul-lokal — **ohne Org-Kontext** (Entity, Pfad, Zuständige, Strategie).
- Org-Lesetools (`context.resolve`, `entity.detail`, `dimension_links.GET`, `entities.GET`)
  sind **nicht objekt-authz-gegated** (§4.6 in [dimensionlink-haertung](dimensionlink-haertung));
  `entity.detail` liefert Links ungebremst.
- Kein Nachbarn-/Graph-Tool, kein „wer ist zuständig“ pro Objekt, keine Timeline pro Objekt.
- Perspektiv-Switch ist pro User, nicht pro Session → parallele Agenten stören sich.

## Ziel-Tools (authz-gegated, gebremst)

| Tool | Zweck |
|---|---|
| `organization.brief.GET(object \| entity)` | Objekt → Entity → Ancestor-Pfad (`EntityAncestorService`) → Zuständige (Rollen/Personen) → Carrier-Strategie kompakt → offene Signale → letzte Aktivität → Beziehungen inkl. Schnittstellen → Hinweis auf passende Modul-Tools. |
| `organization.search.GET` | Entities + verlinkte Objekte finden; später semantisch über `EmbeddingStoreContract` (Core). |
| `organization.neighbors.GET(id, depth≤2, edge_types)` | Kinder, Relationships, Interlinks, Links — mit Limits. |

Stand: `brief` und `neighbors` live (2026-10-20). Objekt-Lesezugriff in beiden wie
das Modul selbst: Policy `view` → `may`/`owns` → Team-Gleichheit
(`DimensionLinkAuthorizer::canReadObject`). `neighbors` liefert nodes + edges
(Limit je Kante/Knoten, max. 50 Knoten), konkrete Objekte nur fürs Zentrum.

Begleitend:
- `core.context.GET` verweist auf `brief` als nächsten Schritt.
- Claim-Payloads in dev/planner/helpdesk rufen `brief` mit.
- Konfigurations-CRUD-Tools aus dem Agent-Katalog nehmen (Sichtbarkeit, nicht löschen).
- Perspektive pro MCP-Session statt pro User.

## Schnittstellen (Interlinks) vereinfachen

Befund BHG: 82 Relationships / 37 Relation-Types leben (Beer-Channel-Semantik am
Relation-Type, `EntityChannelTraversalService` aggregiert). Interlinks dagegen:
2 Stück (März/April), 1 verwaiste Zuordnung, 23 Tools, eigene Kategorien/Typen.
SLA-Verträge: 0 → am 2026-10-20 entfernt.

Ziel: **Interlink = Verweis einer Relationship auf ein echtes Modul-Objekt**
(Helpdesk-Board, Vertrag, Dokument) — DimensionLink an der Relationship statt
Freitext+URL. Status/SLA liefert das Zielobjekt selbst (z. B. `HelpdeskBoardSla`),
keine Kopie in organization.

- Kategorien/Typen-Katalog entfällt; ~23 Tools → link/unlink + Anzeige in `brief`/`neighbors`.
- Die 2 Bestands-Interlinks händisch migrieren (Helpdesk-Board 4, Rahmenvertrag).
- Umsetzung zusammen mit `brief`, weil dort sichtbar wird, was ein Agent braucht.

## Leitplanken

- recruiting hängt an `EntityLinkRegistry`, `EntityLinkProvider`, `HasMetricDefinitions` —
  diese Contracts nicht brechen.
- Recursion immer mit Tiefenlimit (heute: `EntityHierarchyService` ohne Limit).

## Objekttypen (lose gekoppelt)

organization pflegt keine Typlisten fremder Module. `ObjectTypeRegistry` leitet
alles aus den registrierten `EntityLinkProvider`n ab: Typ = Morph-Alias,
Kurzname = Alias ohne Modul-Präfix, wenn eindeutig (`dev_issue` → `issue`;
`board` ist mehrdeutig → nur Alias), Label aus `linkTypeConfig()`.
Eltern-Verortung (Task → Projekt, Issue → Package, Ticket → Board) liefert das
Modul über das optionale `HasContextParent`; der Resolver geht bis 3 Schritte
hoch. `ObjectContextResolver::LEGACY_PARENTS` ist nur Übergangs-Rückfall.
Fest verdrahtet bleiben nur `sprint`/`board` in `ContextTypeRegistry` (ohne Morph-Alias).
