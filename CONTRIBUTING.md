# Beitragende – Workflow

Wir arbeiten strikt nach den Regeln in `Requirements/`. Änderungen an DB, API oder Architektur werden zuerst dort aktualisiert (PR), dann implementiert.

## Branching
- `main`/`master`: stabiler, lauffähiger Stand. Keine direkten Pushes.
- Feature-Branches: `feature/<kurzbeschreibung>`
- Fix-Branches: `fix/<kurzbeschreibung>`

## Pull Requests
- PR-Template: Beschreibung, betroffene Requirements-Abschnitte, Migrationsschritte.
- Checks müssen grün sein (Branch-Guard Workflow). Aktiviert Branchschutz in GitHub:
  - Require status checks to pass (Branch Guard)
  - Require PR reviews
  - Restrict who can push (optional)

## Commits
- Präfixe: `feat:`, `fix:`, `docs:`, `refactor:`, `chore:`, `db:`
- Kleine, atomare Commits mit klarer Message.

## Migrationen
- Jede Schemaänderung: Update `Requirements/db-schema.mmd` und `Requirements/spec.md`, Migrationsskripte hinzufügen.


