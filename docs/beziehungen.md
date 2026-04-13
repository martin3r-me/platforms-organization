---
title: Beziehungen & Schnittstellen
order: 3
---

# Beziehungen & Schnittstellen

Beziehungen und Schnittstellen dokumentieren, wie Organisationseinheiten zusammenarbeiten — und über welche konkreten Kanäle.

---

## Das Konzept

### Beziehungen

Eine **Beziehung** verbindet zwei Entities und beschreibt die Art der Verbindung:

- "Offline AG **liefert an** BHG.DIGITAL"
- "IT-Abteilung **beauftragt** externen Dienstleister"
- "Geschäftsführung **genehmigt für** Einkauf"

Jede Beziehung hat:
- **Von-Entity** — Wer geht die Beziehung ein?
- **Zu-Entity** — Mit wem?
- **Beziehungstyp** — Wie ist die Verbindung charakterisiert?
- **Gültigkeit** — Optional: von wann bis wann?

### Schnittstellen (Interlinks)

Eine **Schnittstelle** ist der konkrete Berührungspunkt innerhalb einer Beziehung — das "Worüber":

- Das **Helpdesk-Board**, über das IT-Anfragen laufen
- Der **Rahmenvertrag**, der die Zusammenarbeit regelt
- Das **ERP-System**, über das Daten ausgetauscht werden

Schnittstellen sind eigenständige Objekte mit:
- **Name** — z.B. "IT Support Helpdesk BHG.DIGITAL"
- **Kategorie** — Technisch, Vertraglich, Organisatorisch, Finanziell, Regulatorisch
- **Typ** — Abhängigkeit, Zusammenarbeit, Informationsfluss, Lieferbeziehung, Genehmigung, Eskalation
- **URL** — Direktlink zum System/Dokument (optional)
- **Referenz** — Kennung wie Vertragsnummer oder Queue-ID (optional)

> Pro Beziehung können **mehrere Schnittstellen** verknüpft werden. Und dieselbe Schnittstelle kann an mehreren Beziehungen hängen.

---

## Beziehungen verwalten

Auf der **Detail-Seite einer Entity** im Tab **"Relations"** siehst du:

### Ausgehende Beziehungen
Beziehungen, die von dieser Entity ausgehen. Hier kannst du:
- Neue Beziehung erstellen (Ziel-Einheit + Beziehungstyp wählen)
- Bestehende Beziehungen löschen
- Schnittstellen pro Beziehung verwalten

### Eingehende Beziehungen
Beziehungen, die von anderen Entities auf diese zeigen. Auch hier kannst du Schnittstellen verwalten und Beziehungen entfernen.

---

## Schnittstellen verwalten

Klicke auf den **Puzzle-Button** an einer Beziehung, um die Schnittstellen-Verwaltung aufzuklappen:

1. **Bestehende Schnittstellen** — Liste aller verknüpften Interlinks mit Kategorie, Typ und Notiz
2. **Schnittstelle hinzufügen** — Aus dem Pool vorhandener Interlinks auswählen, optional mit Notiz
3. **Schnittstelle entfernen** — Vom X-Button neben dem Eintrag

Schnittstellen mit URL werden als klickbare Badges angezeigt — ein Klick führt direkt zum verlinkten System.

---

## Beispiel

> **Offline AG** *erbringt Leistung für* **BHG.DIGITAL**
>
> Schnittstellen:
> - IT Support Helpdesk BHG.DIGITAL → [office.bhgdigital.de/helpdesk/boards/4]
> - Rahmenvertrag → Ref: RV-2024-0042

---

## Konfiguration

### Beziehungstypen
Unter **Einstellungen > Beziehungstypen** definierst du, welche Arten von Beziehungen möglich sind (z.B. "liefert an", "beauftragt", "ist Dienstleister für").

### Schnittstellen-Kategorien
Unter **Einstellungen > Schnittstellen-Kategorien** legst du fest, in welche Bereiche Schnittstellen fallen: Technisch, Vertraglich, Organisatorisch, Finanziell, Regulatorisch.

### Schnittstellen-Typen
Unter **Einstellungen > Schnittstellen-Typen** definierst du die Art der Verbindung: Abhängigkeit, Zusammenarbeit, Informationsfluss, etc.
