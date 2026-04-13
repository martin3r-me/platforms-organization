---
title: Prozesse
order: 4
---

# Prozesse

Prozesse dokumentieren, **wie** Arbeit in deiner Organisation erledigt wird — Schritt für Schritt, mit Verantwortlichkeiten und Optimierungspotenzial.

---

## Was ist ein Prozess?

Ein Prozess ist eine wiederholbare Abfolge von Schritten, die ein definiertes Ergebnis erzeugt. Beispiele:

- "Onboarding neuer Mitarbeiter"
- "IT-Störung beheben"
- "Modul-Dokumentation erstellen"
- "Monatsabschluss Buchhaltung"

---

## Einen Prozess anlegen

Auf der Prozess-Übersicht klickst du **"Neuer Prozess"** und gibst an:

- **Name** — Wie heißt der Prozess?
- **Code** — Eindeutige Kennung (z.B. "PROC-DOC-MODULE")
- **Beschreibung** — Was macht dieser Prozess?
- **Status** — Draft, Active oder Deprecated
- **Version** — Versionsnummer
- **Owner Entity** — Welche Organisationseinheit ist verantwortlich?
- **VSM-System** — Welchem Wertstrom gehört er an?

---

## Die Prozess-Detail-Seite

### Details
Stammdaten des Prozesses bearbeiten. Hier findest du auch den **Stundensatz** — wird für die COREFIT-Kostenkalkulation verwendet.

### COREFIT-Analyse
Strategische Bewertung des Prozesses mit Metriken und Canvas-Feldern:

- **Metriken** — Automatisch berechnet aus den Steps: Wie viel ist Core, Context, No-Fit? Was kostet der Prozess?
- **Zielbild** — Wo soll der Prozess hin?
- **Kundennutzen & Wertbeitrag** — Was bringt er?
- **Kosten & Break-Even** — Lohnt sich Optimierung?
- **Risiko & Resilienz** — Was passiert bei Ausfall?
- **Hebel & Lösungsdesign** — Wo ansetzen?
- **Maßnahmenplan** — Konkrete nächste Schritte
- **Standardisierung & Kontrolle** — Wie sicherstellen, dass es läuft?

### Steps
Die einzelnen Schritte des Prozesses:

| Feld | Bedeutung |
|------|-----------|
| Name | Was passiert in diesem Schritt? |
| Beschreibung | Details zur Durchführung |
| Typ | Manuell, Automatisiert, Entscheidung, Wartezeit |
| Dauer | Wie lange dauert der Schritt? (Minuten) |
| Wartezeit | Wie lange wartet man danach? |
| COREFIT | Core, Context oder No-Fit? |
| Entities | Wer ist beteiligt? |
| Interlinks | Welche Schnittstellen werden genutzt? |

### Flows
Verbindungen zwischen Steps — zeigt, in welcher Reihenfolge die Schritte ablaufen und wo Verzweigungen sind.

### Triggers
Was löst den Prozess aus? Events, Zeitpläne oder manuelle Starts. Triggers können auf bestimmte Entity-Typen oder einzelne Entities eingeschränkt werden.

### Outputs
Was produziert der Prozess? Dokumente, Daten, Entscheidungen, Statusänderungen.

### Verbesserungen
Vorgeschlagene und umgesetzte Optimierungen — kategorisiert nach: Kosten, Qualität, Geschwindigkeit, Risiko, Standardisierung.

### Snapshots
Historische Versionen des Prozesses. Snapshots können verglichen werden, um Veränderungen nachzuvollziehen.

---

## COREFIT-Klassifikation

Jeder Prozess-Step wird nach COREFIT klassifiziert:

| Klasse | Bedeutung | Ziel |
|--------|-----------|------|
| **Core** | Wertschöpfend — der Kunde würde dafür bezahlen | Optimieren |
| **Context** | Notwendig, aber nicht wertschöpfend (z.B. Compliance, Admin) | Standardisieren |
| **No-Fit** | Weder wertschöpfend noch notwendig | Eliminieren |

Im COREFIT-Tab siehst du automatisch, wie sich die Steps verteilen — nach Anzahl, Zeit und Kosten.
