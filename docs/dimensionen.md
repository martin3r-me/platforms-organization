---
title: Dimensionen
order: 7
---

# Dimensionen

Entities können gleichzeitig in mehreren Dimensionen organisiert werden — neben der Hierarchie (Parent/Child) gibt es **VSM-Systeme**. Die **Kostenstelle** ist keine eigene Dimension mehr, sondern eine Fremd-ID an der Entity (siehe unten).

---

## VSM-Systeme (Value Stream Mapping)

Ein VSM-System repräsentiert einen **Wertstrom** in der Organisation — den Weg, auf dem Wert für den Kunden entsteht:

- "Auftragsabwicklung"
- "Produktentwicklung"
- "Kundenservice"

Jede Entity kann einem VSM-System zugeordnet werden. Innerhalb eines VSM-Systems gibt es **VSM-Funktionen** — die konkreten Aufgaben im Wertstrom.

> Hierarchie zeigt, **wem** eine Einheit untersteht. VSM zeigt, **wozu** sie beiträgt.

---

## Kostenstellen (als Fremd-ID)

Kostenstellen sind **keine eigene Struktur/Dimension** mehr. Grundgedanke:

> Jede Entity **ist** faktisch ihre eigene Kostenstelle — der Ort, an dem Zeit,
> Links und Kinder hängen, ist genau das, dem man Kosten zurechnet.

Die Kostenstelle ist deshalb nur ein **Kürzel/eine Nummer an der Entity** (z.B.
`KST-4200`) — eine **Fremd-ID**: die Identität der Einheit im Rechnungswesen. Sie
lebt in `organization_entity_external_ids` (`system = 'kostenstelle'`), zusammen
mit weiteren Fremd-IDs derselben Familie:

- `kostenstelle` — Controlling/Kostenrechnung
- `datev` — DATEV-ID
- `buchungskonto` — Sach-/Buchungskonto
- `kreditor` — Kreditorennummer
- … neue Typen brauchen nur einen neuen `system`-String, nie eine Migration

Gesetzt wird der Wert auf der Entity-Detail-Seite im Tab "Daten" (Feld
"Kostenstelle") oder per MCP (`organization.entity_external_ids.POST`).

**Verlinkung/Zurechnung** läuft immer gegen die **Entity** (Baum + entity-
Dimension). "Hänge X an Kostenstelle KST-4200" wird über die Fremd-ID zur
zugehörigen Entity aufgelöst und dann verlinkt — ein Mechanismus für jede
Fremd-ID. Die **Kosten-Aggregation** (Dimension 5) folgt dem Entity-Baum; die
KST-Nummer ist das Export-/Kommunikations-Etikett obendrauf.

---

## Mehrere Dimensionen gleichzeitig

Eine Entity kann gleichzeitig:
- In der **Hierarchie** unter "IT-Abteilung" hängen
- Dem **VSM-System** "Kundenservice" zugeordnet sein
- Die **Kostenstelle** "KST-4200" als Fremd-ID tragen

So entsteht eine mehrdimensionale Sicht auf die Organisation.
