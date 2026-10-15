---
title: DimensionLink – Härtung & zentrale UI (Plan)
order: 99
---

# DimensionLink – Härtungs- & Architektur-Plan

## Phase-0-Audit · Ergebnis (Team BHG.DIGITAL, 2026-10-15)

Gefahren über `organization.dimension_links.audit.GET` gegen Produktion.

| Check | Ergebnis |
|---|---|
| Entity-Values ohne `source_entity_id` | ✅ 0 |
| Entity-Values mit verwaister `source_entity_id` | ✅ 0 |
| Prozent-Summe > 100 (`multi_percent`) | ✅ 0 |
| Mehr als ein `is_primary` pro Gruppe | ✅ 0 |
| Links auf soft-gelöschte Values | ⚠️ **9** |
| Unbekannte `linkable_type` | ⚠️ **87** |

**Befund 0 — Schema-Drift:** Die Produktiv-Tabelle `organization_dimension_links`
hat **keine Spalte `perspective_id`**. Die Migration `2026_05_23_100003` legt sie an,
no-oppt aber per `if (Schema::hasTable(...)) return;` auf der vorbestehenden Tabelle.
→ Spalte **und** der `perspective_id`-haltige Unique-Index wurden nie angewendet.
`percentage`/`is_primary` existieren dagegen. Phase 0 braucht einen Abgleich
Produktiv-Schema ↔ Migrationen, bevor irgendeine Invariante erzwungen wird.

**Befund 1 — 9 Links auf soft-gelöschte Values:** Drei gelöschte Entity-Values
(`BHG.CONSULTING` #60, `BHG.IT` #221, `BROICH.AKADEMIE` #309) hängen noch an lebenden
Objekten (brands, canvas×3, commerce_catalog×2, okr×2, project). Beweist, dass §4.4
real auftritt → §8.4 muss entschieden werden (Cleanup + künftige Strategie).

**Befund 2 — 87 unauflösbare `linkable_type`:** `organization_entity` (79),
`correspondence_thread` (6), `commerce_article_category` (1), `okr_forecast` (1).
Keiner dieser Aliase ist in der `morphMap` dieses Deployments registriert (nur
`organization_role`/`_role_assignment`). Die 79 Entity-an-Entity-Links sind ein
reales Nutzungsmuster ohne registrierten Alias → **muss geklärt sein, bevor der
Morph-Guard (Phase 5) auf Reject geht**, sonst werden legitime Writes blockiert.

**Entlastung:** Die 4 grünen Checks zeigen — Entity-Auflösung (§4.1) ist datenseitig
sauber (Materialisierung §8.2 damit optional), und die Mode-Invarianten (§4.3) sind
heute erfüllt → DB-Constraints (§8.3) sind machbar, da kein Altbestand sie verletzt.

### Untersuchung der 87 Unknowns (per Dimensions-Aufschlüsselung)

| linkable_type | Dimension | Links | Zuständig |
|---|---|---|---|
| `organization_entity` | vsm-system | 79 | **organization selbst** — dokumentiertes Entity↔VSM-Muster, Alias nie registriert |
| `correspondence_thread` | entity | 6 | fremdes Modul (correspondence) |
| `commerce_article_category` | entity | 1 | fremdes Modul (commerce) |
| `okr_forecast` | entity | 1 | fremdes Modul (okr) |

→ Die 79 sind kein Dead Data. Alias `organization_entity` gehört dem Org-Paket und
wird registriert. Die restlichen 8 sind Follow-up für die jeweiligen Modul-Owner.

### Entscheidungen (2026-10-15)

- **#1 Entity-Links:** `organization_entity`-Alias in `OrganizationServiceProvider`
  registrieren (erledigt in Deploy B). Fremd-Modul-Aliase: Follow-up.
- **#2 Soft-Delete:** Bestehende 9 Geister-Links bereinigen (Deploy B); künftig
  Cascade-Observer (value soft-delete → Links mit-soft-deleten) in **Deploy C**,
  nach Charakterisierungs-Tests, inkl. `softDeletes` auf der Links-Tabelle.
- **#3 perspective_id:** additive Fix-Migration. Spalte + FK + Index in Deploy B;
  der perspective_id-haltige **Unique-Index erst nach Dubletten-Audit** (Deploy C).

### Deploy-Sequenz

- **Deploy B (korrektiv, kein Verhaltensrisiko) — ✅ erledigt & verifiziert:**
  Alias-Registrierung · `perspective_id`-Spalte additiv · Cleanup der 9 Geister-Links.
  Audit-Gegenprobe: soft-deleted-Links 9→0, Unknowns 87→8 (nur noch Fremd-Modul-
  Aliase correspondence_thread/commerce_article_category/okr_forecast).
  **Follow-up (fremde Pakete):** diese 3 Module müssen ihren morph-Alias registrieren.
- **Deploy C (Prävention/Erzwingung) — umgesetzt & live-verifiziert:**
  - **Hard-Cascade-Observer** (`DimensionValueObserver`): Value soft-delete →
    Links hart entfernen. Bewusst Hard-Delete statt softDeletes, weil Haupt-
    Consumer (`EntityPulseSubjectCollector`, `FeedService`) Links per roher
    `DB::table()` lesen und einen SoftDelete-Scope ignorieren würden; Hard-Delete
    ist konsistent mit dem bestehenden FK-Cascade und braucht keine core-Edits.
  - **Morph-Guard Warn-Mode** in `DimensionLinkService::link()`: unauflösbarer
    `linkable_type` wird geloggt statt still gespeichert (Reject später, Phase 5).
  - **Geparkt:** perspective_id-Unique-Index. Alle `perspective_id` sind aktuell
    NULL; ein Unique-Index mit NULL-Spalte verhindert in MySQL keine Dubletten
    (NULL ≠ NULL). Erst klären, ob das Perspective-Feature real genutzt wird.
  - **Regressionsnetz:** Audit-Check „Links auf soft-gelöschte Values" muss nach
    einem echten Value-Soft-Delete 0 bleiben.
  - **Live-Test (2026-10-15):** Wegwerf-Entity unter #5 angelegt, Aufgabe #3549
    daran verknüpft (1 Link), Entity gelöscht → Forward-Query 1→0 und Audit-Check
    blieb 0 (ohne Observer wäre er 1 gewesen). Cascade empirisch bestätigt.

**Sicherheitsnetz:** Package hat keine Test-Infrastruktur (nicht die Hauskonvention;
nur sovra-health hat Testbench). Statt ungetesteter Unit-Tests dient das Audit gegen
Produktion als Regressionsnetz — es hat Deploy B empirisch verifiziert (9→0, 87→8).

---

> **Status:** Vorschlag / Diskussionsgrundlage. Noch kein Code geändert.
> **Oberste Randbedingung:** Produktiv im Einsatz, mehrere Monate Echtdaten.
> **Jede Maßnahme ist strikt additiv und abwärtskompatibel** — keine
> Umbenennungen, keine gedroppten Spalten, keine retroaktiven Constraints, die
> bestehende Zeilen ablehnen. Alles läuft über Dual-Write + Backfill + Fallback-Read,
> nie über Hard-Cut.

---

## 1. Warum dieser Plan

DimensionLink ist das Rückgrat der organisatorischen Verknüpfung: **alle
Modul-Objekte** (Projekte, Aufgaben, Kontakte, Deals, …) hängen über diesen
Mechanismus an der Org-Struktur. Die Logik ist damit plattformkritisch — aber
sie ist heute weder durch harte Invarianten noch durch einen formalen Contract
abgesichert, und die zentrale Pflege-UI fehlt.

Zwei Ziele:

1. **Härten** — referenzielle Integrität, klare Invarianten, Tests, Kapselung
   der heute fragilen Stellen.
2. **Zentral verfügbar machen** — eine generische UI in `core`, die DimensionLinks
   modulübergreifend anzeigt und pflegt, ohne dass `core` die Domäne kennt.

---

## 2. Ist-Zustand (Kurzfassung)

| Bereich | Ort |
|---|---|
| Models (`OrganizationDimensionDefinition/Value/Link`) | `platforms-organization/src/Models/` |
| Service (`DimensionLinkService`) | `platforms-organization/src/Services/` |
| Tools (`Link-/Unlink-/ListDimensionLinksTool`) | `platforms-organization/src/Tools/` |
| UI-Komponente (`DimensionLinker`, Livewire) | `platforms-organization/src/Livewire/` |
| Tabelle `organization_dimension_links` | `platforms-organization/database/migrations/2026_05_23_100003_*` |

**Abhängigkeitsrichtung (sauber, soll so bleiben):**

```
core                     →  kennt platforms-organization NICHT (nur lose Registry-Kopplung)
platforms-organization   →  Domänenlogik + Daten
flynk / process / planner / sales / commerce  →  require platforms-organization (opt-in)
```

---

## 3. Zielbild der Architektur

Die Domänenlogik **bleibt in `platforms-organization`**. Die zentrale UI kommt
nach `core` — aber `core` redet **nie** mit den konkreten Dimension-Klassen,
sondern gegen einen **Contract (Interface) in `core`**, den `platforms-organization`
implementiert. Das ist das bestehende Registry-Muster (`EntityLinkRegistry`),
nur formalisiert und versioniert.

```
┌─────────────────────── core ───────────────────────┐
│  Contract:  DimensionLinkProvider (Interface)       │
│  UI:        zentrale DimensionLink-Verwaltung       │
│             (konsumiert nur den Contract)           │
└───────────────▲─────────────────────────────────────┘
                │ implements / registers
┌───────────────┴─────── platforms-organization ─────┐
│  DimensionLinkService  (implementiert Contract)     │
│  Models / Observer / Migrations / Tools             │
└─────────────────────────────────────────────────────┘
```

**Konsequenz:** Ist `platforms-organization` nicht geladen, findet die core-UI
keinen registrierten Provider und bleibt leer/deaktiviert. `core` bekommt
dadurch **keine** Pflicht-Abhängigkeit auf die Domäne. Essentiell + zentral ≠ im Kernel.

### Warum keine harten DB-FKs (bewusste Entscheidung)

- **`linkable_id` kann per Definition keine FK haben** — `morphTo()` zeigt mal auf
  `planner_projects`, mal auf `tasks`, mal auf `contacts`. Integrität bleibt hier
  zwangsläufig app-seitig.
- **Cross-Package-FK koppelt Schemas.** Eine DB-FK von `organization_dimension_links`
  auf z.B. `planner_projects` erzwingt Migrations-Reihenfolge über Paketgrenzen und
  zerstört die Optionalität der Module.
- Harte FK nur **innerhalb** von `platforms-organization` (gleiches Schema) ist ok —
  siehe `dimension_definition_id` / `dimension_value_id` unten.

Gehärtet wird also **referenzielle Integrität auf App-Ebene** (Observer, Validation,
Guards) + **Kapselung** der heute rohen Zugriffe — nicht durch FKs über Grenzen.

---

## 4. Schwachstellen & Maßnahmen

Jede Maßnahme mit explizitem Abwärtskompatibilitäts-Pfad.

### 4.1 `entity_id` vs. `id`/`dim_value_id` — die #1-Fehlerquelle

**Problem (verifiziert):** `organization_dimension_values` hat **keine** Spalte
`source_entity_id` — die Entity-Auflösung läuft ausschließlich über das
`json metadata`-Feld (`metadata.source_entity_id`). Der Schlüssel ist konsistent
geschrieben, wird aber an **~12 Stellen in `DimensionLinkService`** teils direkt
abgegriffen (`$meta['source_entity_id']` bzw. `metadata->source_entity_id`). Die
Doppeldeutigkeit „interne Link-/Value-ID vs. Entity-ID" hat nachweislich schon
gebissen (die ausführlichen LLM-Warnhinweise im `ListDimensionLinksTool` sind der Beleg).

**Maßnahme (additiv):**

1. **Resolver-Kapselung zuerst, ohne Schema-Änderung.** Eine Methode
   `resolveEntityId(OrganizationDimensionValue): ?int` als einzige erlaubte Quelle.
   Alle ~12 Direktzugriffe auf `metadata['source_entity_id']` darauf umstellen. Rein
   mechanisch, null Verhaltensänderung — und sofort wertvoll, weil der Zugriff heute
   verstreut ist.
2. **Optional danach:** additive, **nullable** Spalte `source_entity_id` an
   `organization_dimension_values`, per Backfill aus dem JSON gefüllt, per Observer
   dual-geschrieben. Resolver liest Spalte mit **Fallback auf JSON**. JSON bleibt
   bestehen → alte Daten & alter Code funktionieren weiter.
   **Achtung:** Values sind `softDeletes` — Backfill/Observer müssen auch
   soft-gelöschte Zeilen berücksichtigen bzw. bewusst ausklammern.
3. Erst wenn Spalte 100 % backfilled und verifiziert ist, kann der JSON-Lesepfad
   intern überflüssig werden — aber nicht entfernt werden (Abwärtskompatibilität).

> **Audit vor Backfill:** Wie viele Values mit entity-Bezug tragen keine gültige
> `metadata.source_entity_id` bzw. zeigen auf nicht (mehr) existente Entities?

### 4.2 Morph-Aliase sind verteilt & ungeprüft

**Problem (in Produktion bereits aufgetreten):** Jedes Modul registriert seine
Aliase selbst (`PlannerServiceProvider`, `CommerceServiceProvider`, …). `linkable_type`
wird persistiert und erst später beim `morphTo()`-Resolve bzw. in
Sidebar-/`EntityDimensionBridge`-Queries ausgewertet, die **nach Alias** suchen.

> **Beleg:** Es existieren **zwei produktive Reparatur-Migrationen** —
> `2026_06_01_200000_fix_dimension_link_linkable_types.php` und
> `2026_06_07_200000_normalize_dimension_link_linkable_types.php` —, die Zeilen
> korrigieren mussten, bei denen `linkable_type` als Kurzname (z.B. `process`)
> statt kanonischem Alias (`organization_process`) gespeichert war. Das Problem hat
> real gebissen und wurde bisher nur nachträglich geflickt.

**Heutiger Stand:** `DimensionLinkService::resolveContextType()` normalisiert
Schreibzugriffe *über den Service* inzwischen automatisch (Klassenname/Kurzname →
kanonischer Alias). Das **Restrisiko** ist damit zweigeteilt:
(a) ein `linkable_type`, der in **keiner** `morphMap` vorkommt (echter Tippfehler/
fehlende Registrierung), und (b) Zeilen, die **am Service vorbei** direkt in die DB
geschrieben werden.

**Maßnahme (additiv, mehrstufig):**

1. **Warn-Mode zuerst:** `link()` prüft den (bereits normalisierten) Alias gegen
   `Relation::morphMap()` und **loggt** eine Warnung bei komplett unbekanntem Typ —
   speichert aber weiterhin. Kein Bruch, nur Sichtbarkeit.
2. Nach Beobachtung der Logs: auf **Reject** umstellen (Exception/`false`), sobald
   sicher ist, dass kein produktiver Pfad einen legitimen, nur-später-registrierten
   Alias nutzt.
3. Die in den Fix-Migrationen bereits vorhandene Auflösungs-Heuristik (Suffix-/
   Klassennamen-Matching) gehört dauerhaft in den **Write-Pfad**, nicht in
   Cleanup-Migrationen.
4. Bestehende Zeilen mit „verwaisten" `linkable_type` **nie** löschen — stattdessen
   per Audit-Report sichtbar machen.

### 4.3 Mode-Semantik (`single` / `multi` / `multi_percent`) nur im Service

**Problem:** Die Regeln (single ersetzt, multi akkumuliert) leben nur in der
App-Logik. Der Unique-Index deckt nur einen Teil ab. Keine Garantie für
„nur ein `is_primary`" oder „Summe `percentage` ≤ 100".

**Maßnahme (additiv, NICHT als retroaktiver DB-Constraint):**

1. **Daten-Audit zuerst:** Wie viele bestehende Gruppen verletzen heute schon
   „≤ 100 %" oder „genau ein is_primary"? Report, kein Eingriff.
2. **Validation beim Schreiben** in `link()`/`DimensionLinker` ergänzen — greift nur
   für **neue/geänderte** Links, lässt Altbestand unberührt.
3. DB-Constraints (CHECK / partieller Unique-Index auf `is_primary`) **nur**, wenn
   das Audit zeigt, dass kein Altbestand sie verletzt — sonst würde die Migration an
   Produktivdaten scheitern. Im Zweifel: bei App-Validation bleiben.

### 4.4 Interne FKs & Integrität innerhalb organization

**Status (verifiziert): bereits vorhanden — hier ist nichts hinzuzufügen.** Die
Links-Migration `2026_05_23_100003_*` setzt bereits `constrained()`-FKs auf alle
internen Bezüge:

- `dimension_definition_id` → `organization_dimension_definitions` (`cascadeOnDelete`)
- `dimension_value_id` → `organization_dimension_values` (`cascadeOnDelete`)
- `perspective_id` → `organization_perspectives` (nullable, `cascadeOnDelete`)
- `team_id` → `teams` (nullable, `cascadeOnDelete`)
- `created_by_user_id` → `users` (nullable, `nullOnDelete`)

Nur `linkable_type`/`linkable_id` haben erwartungsgemäß keine FK (polymorph).

**Eine echte Integritäts-Nuance bleibt:** `dimension_value_id` ist
`cascadeOnDelete`, aber `organization_dimension_values` nutzt `softDeletes`. Ein
**hart** gelöschter Value räumt seine Links per Cascade ab; ein **soft** gelöschter
Value hinterlässt Links, die auf einen inaktiven/„gelöschten" Value zeigen.

**Maßnahme:** kein Schema-Eingriff. Stattdessen app-seitig entscheiden, wie Links
auf soft-deleted Values behandelt werden (ausblenden in Queries vs. aktiv
mit-soft-deleten per Observer) — und das als Invariante testen (§4.5).

### 4.5 Fehlende Tests für plattformkritische Invarianten

**Maßnahme:** Vor jedem Umbau eine **Charakterisierungs-Test-Suite** (Golden Master)
für das heutige Verhalten von `DimensionLinkService` — forward/reverse, single vs.
multi, Alias-Resolution, Entity-Auflösung. Diese Tests sichern Abwärtskompatibilität
ab: Sie müssen vor und nach jeder Phase grün sein.

---

## 5. Contract-Design in `core`

Neues Interface in `core`, z.B. `Platform\Core\Contracts\DimensionLinkProvider`,
das genau das abbildet, was die zentrale UI braucht — nichts Domänenspezifisches:

- `dimensions(): array` — verfügbare Dimensionen (key, label, mode)
- `linkedFor(string $contextType, int $contextId): Collection` — forward
- `contextsFor(string $dimension, int $dimensionItemId): Collection` — reverse
- `link(...) / unlink(...)` — mit denselben Metafeldern (percentage, is_primary, Zeitraum)

`platforms-organization` registriert seinen `DimensionLinkService` beim Boot als
Implementierung dieses Contracts (Binding im `OrganizationServiceProvider`). Die
bestehenden Tools/`DimensionLinker` bleiben unverändert — der Contract ist additiv
obendrauf, kein Ersatz.

**Abwärtskompatibilität:** bestehende direkte Importe von `DimensionLinkService`
(planner, sales, commerce) bleiben gültig; sie können später optional auf den
Contract migrieren, müssen aber nicht.

---

## 6. Zentrale UI in `core`

- Generische Livewire-Komponente in `core`, die **nur** gegen `DimensionLinkProvider`
  arbeitet.
- Rendert pro Kontext-Objekt die Dimensionen + Links; erlaubt Anhängen/Lösen,
  Prozent-/Primary-Pflege gemäß Mode.
- Kein Provider registriert → Komponente rendert leer/deaktiviert (keine harte
  Abhängigkeit).
- Die bestehende `DimensionLinker`-Komponente in organization bleibt bestehen; die
  core-UI ist der neue zentrale Einstieg, kein Breaking Replacement.

---

## 7. Migrationspfad in Phasen

Jede Phase ist eigenständig deploybar, reversibel und lässt Produktivdaten intakt.

| Phase | Inhalt | Risiko |
|---|---|---|
| **0** | Daten-Audits (entity-Values ohne `source_entity_id` · Mode-Verletzungen · soft-deleted Values mit aktiven Links) + Charakterisierungs-Tests (4.5) | keins (read-only) |
| **1** | Resolver-Kapselung `entity_id` (4.1.1), Morph-Guard im Warn-Mode (4.2.1) | niedrig (kein Verhaltenswechsel) |
| **2** | Contract in core (5) + Binding in organization | niedrig (additiv) |
| **3** | Zentrale UI in core (6) | niedrig (neue Oberfläche) |
| **4** | Write-Validation Mode-Invarianten (4.3.2), Umgang mit soft-deleted Values (4.4), optional additive Spalte `source_entity_id` nach Audit (4.1.2) | mittel (nur neue Writes betroffen) |
| **5** | Morph-Guard auf Reject (4.2.2) inkl. Heuristik im Write-Pfad (4.2.3), ggf. DB-Constraints für Mode-Invarianten — nur wenn Audit sauber | mittel (erst nach Beobachtung) |

> Hinweis: Interne FKs (§4.4) sind bereits gesetzt — keine FK-Migration nötig.

Reihenfolge-Prinzip: **erst sichtbar machen, dann kapseln, dann erzwingen.**

---

## 8. Offene Fragen / zu entscheiden

1. **Namespace/Ort des Contracts** in core (`Platform\Core\Contracts\…`?) — passt das
   zur bestehenden Core-Struktur?
2. **`source_entity_id` materialisieren?** (4.1.2) — oder reicht die Resolver-Kapselung
   plus JSON als Single Source? (Materialisierung erleichtert reverse-Queries/Joins,
   zieht aber Observer-Pflege inkl. softDeletes nach sich.)
3. **DB-Constraints für Mode-Invarianten** (4.3.3) ja/nein — hängt am Audit-Ergebnis.
4. **Soft-deleted Values mit aktiven Links** (4.4) — ausblenden in Queries oder aktiv
   mit-soft-deleten? Beeinflusst reverse-Queries und UI.
5. **Multi-Tenant:** Verhalten der Invarianten pro `team_id` korrekt abgegrenzt?
6. Soll die core-UI die org-`DimensionLinker` perspektivisch **ablösen** oder dauerhaft
   koexistieren?

---

## 9. Was dieser Plan ausdrücklich NICHT tut

- Keine Verlagerung der Domänenlogik nach core.
- Keine harten FKs über Paketgrenzen.
- Kein Entfernen/Umbenennen bestehender Spalten, Tabellen, Tools oder Komponenten.
- Keine Migration, die an bestehenden Produktivdaten scheitern könnte (jede riskante
  Migration hat ein vorgeschaltetes Audit).
