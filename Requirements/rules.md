# Projektregeln (verbindlich)

Diese Regeln sind ab jetzt verpflichtend. Änderungen an Architektur/Datenmodell nur nach Update der `Requirements` (Review).

## Designprinzipien
- Single Source of Truth: Kanonische Inhalte in `content_*`; Charakterbesitz in `items` (Instanzen).
- Ad-hoc-Items: `items.is_custom=true`, `content_item_id=NULL`; wiederverwendbare Custom-Katalogeinträge über `content_*` mit `source='custom'`/`homebrew'`.
- Queries auf JSONB nie für zentrale Logik – nur für optionale/zusätzliche Eigenschaften.
- „Erlaubt“ wird aus Ableitungen: Klasse/Subklasse/Rasse/Background/Level → Features/Proficiencies/Spells. Entscheidungen werden in `character_choices` protokolliert.

## Sicherheit
- Sessions: Cookies nur mit `Access-Control-Allow-Credentials`, Origin-Whitelist. Keine `*` bei Credentials.
- Admin sieht alle Charaktere; User nur eigene. Admin darf Katalog/Canon pflegen.

## Snapshots
- `session_snapshots.character_data` enthält den vollständigen Zustand (Reset-fähig). Restore überschreibt abgeleitete Tabellen (stats, items, slots, notes, skills, money …) konsistent.

## Migration & Testing
- Schema-Änderungen zuerst in `Requirements` dokumentieren, dann Migration (SQL/DDL), dann API/Model-Updates. Keine stillen DB-Änderungen.
- Jede neue Tabelle: PK, FK, notwendige Indizes, ON DELETE-Strategie definiert.
- API-Änderungen: konsistentes Fehlerformat, Contract-Tests.

## Foundry-Integration
- Token-basierte Auth; keine offenen Endpunkte. Events/Sync protokollieren.

## Review-Checkliste
- Entspricht Änderung dem ERD (`db-schema.mmd`) und `spec.md`?
- Sind Canon/Homebrew/Custom-Regeln eingehalten?
- Ist der Snapshot-Restore weiterhin konsistent?


