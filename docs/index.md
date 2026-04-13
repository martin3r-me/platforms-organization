---
title: Organization — Überblick
order: 1
---

# Organization

Das Organization-Modul bildet die Grundlage für alles, was mit Organisationsstruktur zu tun hat. Hier modellierst du, wie dein Unternehmen aufgebaut ist — von Abteilungen über Teams bis zu einzelnen Personen, externen Dienstleistern und Systemen.

---

## Die drei Kernkonzepte

### Entities (Organisationseinheiten)

Eine Entity ist alles, was in deiner Organisation eine eigenständige Rolle spielt:

- Abteilungen und Teams
- Personen und Rollen
- Externe Dienstleister
- Systeme und Plattformen
- Meeting-Serien und Gremien

Entities können hierarchisch verschachtelt werden — eine Abteilung enthält Teams, ein Team enthält Personen. Jede Entity hat einen **Typ** (z.B. "Abteilung", "Person", "Dienstleister"), der bestimmt, wie sie sich verhält.

> Denke an Entities als die Bausteine deines Organigramms — aber flexibler, weil du auch Systeme, Verträge und Meetings abbilden kannst.

### Beziehungen & Schnittstellen

**Beziehungen** beschreiben, wie zwei Entities zusammenhängen: "liefert an", "beauftragt", "ist Dienstleister für".

**Schnittstellen (Interlinks)** sind die konkreten Berührungspunkte einer Beziehung — das Ticketsystem, über das Anfragen laufen, der Rahmenvertrag, der die Zusammenarbeit regelt, oder das Helpdesk-Board, in dem Support-Tickets landen.

Pro Beziehung können **mehrere Schnittstellen** existieren.

| Frage | Antwort |
|-------|---------|
| Wer arbeitet mit wem? | **Beziehung** |
| Worüber läuft die Zusammenarbeit konkret? | **Schnittstelle** |

### Prozesse

Prozesse dokumentieren, **wie** Arbeit erledigt wird — Schritt für Schritt, mit Verantwortlichkeiten, Zeitaufwand und Optimierungspotenzial.

| Frage | Antwort |
|-------|---------|
| Wer macht es? | **Entity** |
| Worüber läuft es? | **Schnittstelle** |
| Wie wird es gemacht? | **Prozess** |

---

## Deine Ansichten

| Ansicht | Was du siehst |
|---------|--------------|
| [Einheiten](entities) | Alle Organisationseinheiten mit Hierarchie |
| [Beziehungen & Schnittstellen](beziehungen) | Wie Einheiten verbunden sind |
| [Prozesse](prozesse) | Dokumentierte Abläufe und Workflows |
| [Zeiterfassung](zeiterfassung) | Erfasste und geplante Stunden |
| [Personen & Rollen](personen) | Stellenprofile und Rollenzuweisungen |
| [Dimensionen](dimensionen) | VSM-Systeme und Kostenstellen |
| [SLA-Verträge](sla) | Service Level Agreements |

---

## Konfiguration

Im Bereich **Einstellungen** konfigurierst du die Grundlagen:

- **Entity-Typen** — Welche Arten von Einheiten gibt es?
- **Beziehungstypen** — Welche Arten von Beziehungen?
- **Schnittstellen-Kategorien & -Typen** — Wie werden Schnittstellen klassifiziert?
