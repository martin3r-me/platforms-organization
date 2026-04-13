---
title: Einheiten (Entities)
order: 2
---

# Einheiten (Entities)

Entities sind die Bausteine deiner Organisationsstruktur. Jede Abteilung, jedes Team, jede Person, jeder Dienstleister und jedes System wird als Entity abgebildet.

---

## Eine Entity anlegen

Auf der Einheiten-Übersicht klickst du **"Neue Einheit"** und gibst mindestens an:

- **Name** — Wie heißt die Einheit?
- **Typ** — Was ist es? (Abteilung, Team, Person, Dienstleister, ...)

Optional:
- **Code** — Kurzbezeichnung oder Nummer (z.B. "IT-001")
- **Beschreibung** — Was macht diese Einheit?
- **Übergeordnete Einheit** — Wo hängt sie in der Hierarchie?
- **VSM-System** — Welchem Wertstrom gehört sie an?
- **Kostenstelle** — Für die Kostenrechnung
- **Verknüpfter User** — Wenn die Entity eine Person repräsentiert

---

## Hierarchie

Entities können beliebig verschachtelt werden:

```
Unternehmen (Root)
├── Geschäftsführung
├── IT-Abteilung
│   ├── Entwicklung
│   │   ├── Person: Max Mustermann
│   │   └── Person: Anna Schmidt
│   └── Support
└── Vertrieb
    └── Key Account Management
```

In der **Hierarchie-Ansicht** auf der Detail-Seite siehst du den gesamten Baum mit:
- Verknüpfungen pro Einheit (Projekte, Tasks, etc.)
- Zeiterfassung (kaskadiert über alle Kinder)
- Expand/Collapse für die gesamte Tiefe

---

## Detail-Seite

Die Detail-Seite einer Entity hat mehrere Tabs:

### Hierarchie
Der Entity-Baum mit allen Kindern und deren Verknüpfungen. Hier siehst du auf einen Blick, was unter dieser Einheit passiert — inklusive Zeiterfassung und offener Stunden.

### Daten
Stammdaten bearbeiten: Name, Code, Typ, VSM-System, Kostenstelle, übergeordnete Einheit.

### Relations
Beziehungen zu anderen Entities verwalten — ausgehende und eingehende. Hier können auch Schnittstellen an Beziehungen gehängt werden. Siehe [Beziehungen & Schnittstellen](beziehungen).

### Person
Nur sichtbar wenn die Entity einen verknüpften User hat. Zeigt die Aktivitäten und Zuordnungen der Person.

---

## Verknüpfungen

Entities werden automatisch mit anderen Modulen verknüpft:

| Modul | Beispiel |
|-------|---------|
| Planner | Projekte und Tasks einer Abteilung |
| Helpdesk | Support-Boards einer Einheit |
| OKR | Objectives einer Organisationseinheit |
| CRM | Kontakte und Unternehmen |

Diese Verknüpfungen erscheinen in der Hierarchie-Ansicht als aufklappbare Gruppen.

---

## Mindmap

Über den Button **"Mindmap"** in der Actionbar öffnet sich eine visuelle Netzwerk-Darstellung der Entity und ihrer Beziehungen — nützlich für einen schnellen Überblick über Zusammenhänge.

---

## Snapshots

Im Hintergrund werden regelmäßig Snapshots erstellt, die den Zustand der Entity festhalten (Anzahl Items, Zeiterfassung, etc.). Auf der Detail-Seite siehst du:

- **Snapshot-Analyse** — Fortschritt, Trend, Restlaufzeit
- **Trend-Chart** — Entwicklung über 14 Tage
- **Zeitverlauf** — Monatliche Stunden (12 Monate)
