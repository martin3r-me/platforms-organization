---
title: Personen & Rollen
order: 6
---

# Personen & Rollen

Das Organization-Modul unterscheidet zwischen **Stellenprofilen** (Job Profiles) und **Rollen** (Roles) — zwei komplementäre Konzepte für die Personalstruktur.

---

## Stellenprofile (Job Profiles)

Ein Stellenprofil beschreibt eine **Position** in der Organisation — unabhängig davon, wer sie gerade besetzt:

- **Name** — z.B. "Software Engineer", "Projektmanager"
- **Level** — Junior, Senior, Lead, etc.
- **Skills** — Erforderliche Fähigkeiten
- **Verantwortlichkeiten** — Was die Position umfasst
- **Gültigkeit** — Von wann bis wann gilt das Profil?

Stellenprofile werden **Personen zugewiesen** — so entsteht die Verbindung zwischen Position und Mitarbeiter.

> Denke an ein Stellenprofil als die Stellenanzeige — es beschreibt die Rolle, nicht die Person.

---

## Rollen (Roles)

Rollen sind **kontextbezogene Funktionen**, die eine Person in einer bestimmten Entity ausübt:

- "Scrum Master" im Team Entwicklung
- "Datenschutzbeauftragter" für die gesamte Organisation
- "Projektleiter" für ein bestimmtes Vorhaben

Eine Rolle hat:
- **Name** und **Slug** — Eindeutige Bezeichnung
- **Beschreibung** — Was umfasst die Rolle?
- **Status** — Aktiv/Inaktiv

Rollen werden über **Rollenzuweisungen** an Personen im Kontext einer Entity vergeben.

---

## Unterschied: Stellenprofil vs. Rolle

| | Stellenprofil | Rolle |
|---|---|---|
| Was? | Die Position | Die Funktion |
| Beispiel | "Software Engineer" | "Scrum Master" |
| Kontext | Organisationsweit | Pro Entity |
| Änderungshäufigkeit | Selten | Kann wechseln |
