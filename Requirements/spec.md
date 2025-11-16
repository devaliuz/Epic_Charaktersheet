# Technische Spezifikation – Datenmodell und APIs

Diese Spezifikation beschreibt die Zielstruktur der Datenbank, relevante Constraints, sowie API-Grundsätze. PostgreSQL ist bevorzugt; MySQL kompatibel mit leichten Anpassungen.

## 1. Auth & User
- `users(id PK, username UK, password_hash, role ENUM(admin|user), created_at)`
- Sessions via PHP-Session; CORS mit Credentials. Admin sieht alle Charaktere, User nur eigene.

## 2. Charakter-Kern
- `characters(id PK, user_id FK nullable, name, level, class_id FK, subclass_id FK?, race_id FK, subrace_id FK?, background_id FK?, alignment, portrait_mode, created_at, updated_at)`
- `character_stats(character_id PK/FK, str,dex,con,int,wis,cha, current_hp,max_hp,temp_hp, armor_class, proficiency_bonus, current_xp, current_bi,max_bi, current_hd,max_hd)`

## 3. Inventar & Ausrüstung
- `items(id PK, character_id FK, content_item_id FK?, is_custom bool, created_by_user_id FK?, name, type, category, quantity, damage_text, to_hit_text, range_text, ac, dex_bonus, max_dex_bonus, value_text, properties JSONB)`
  - Zwei Modi: (A) Katalogbasiert (content_item_id != NULL, is_custom=false) (B) Ad-hoc-Instanz (content_item_id=NULL, is_custom=true).
- `equipment_slots(id PK, character_id FK, slot_type, item_id FK?, UNIQUE(character_id, slot_type))`
- `money(character_id PK/FK, gold, silver, copper)`

## 4. Notizen, Skills, Death Saves, Spell Slots
- `notes(id PK, character_id FK, type, content, updated_at)`
- `skills(id PK, character_id FK, skill_name, proficient bool, expertise bool, bonus int)`
- `death_saves(character_id PK/FK, successes, failures)`
- `spell_slots(id PK, character_id FK, slot_level, slot_number, used bool)`

## 5. Sessions & Snapshots (Reset-Fähig)
- `sessions(id PK, character_id FK, session_name, started_at, ended_at?, notes)`
- `session_snapshots(id PK, session_id FK?, character_id FK, snapshot_type, character_data JSONB, created_at)`
  - character_data speichert den vollständigen Zustand; Restore setzt alle relevanten Tabellen anhand dieses JSON zurück.

## 6. Content-Katalog (SRD/Canon & Custom)
Gemeinsame Felder pro Tabelle: `source ENUM('canon','homebrew','custom')`, `created_by_user_id FK?`, `is_public bool`.
- `content_weapons(id PK, name, weapon_category, combat_type, hands, damage_dice, damage_type, properties JSONB, source, created_by_user_id, is_public)`
- `content_armors(id PK, name, armor_category, base_ac, dex_bonus_allowed, max_dex_bonus?, stealth_disadvantage, strength_requirement?, properties JSONB, source, created_by_user_id, is_public)`
- `content_spells(id PK, name, level, school, casting_time, range, components, material_text, duration, concentration, ritual, description, higher_levels, source, created_by_user_id, is_public)`
- `content_classes(id PK, name, hit_die, primary_ability, saving_throws JSONB, spellcasting_progression JSONB)`
- `content_subclasses(id PK, class_id FK, name, description)`
- `content_races(id PK, name, ability_bonuses JSONB, speed, languages JSONB)`
- `content_subraces(id PK, race_id FK, name, overrides JSONB)`
- `content_backgrounds(id PK, name, proficiencies JSONB, equipment_pack JSONB)`
- `content_proficiencies(id PK, type, key, name, metadata JSONB)`
- `content_features(id PK, source_type, source_id, name, level_min?, rules JSONB)`

Mappings (N:M):
- `class_proficiencies(class_id FK, proficiency_id FK)`
- `spell_classes(spell_id FK, class_id FK)`
- `subclass_features(subclass_id FK, feature_id FK)`
- `race_features(race_id FK, feature_id FK)`
- `background_features(background_id FK, feature_id FK)`

Level-Progression:
- `class_level_progression(id PK, class_id FK, level, features_granted JSONB, spell_slots JSONB, proficiency_bonus_override?)`
- `subclass_level_progression(id PK, subclass_id FK, level, features_granted JSONB)`

## 7. Charakter-Leveling / Multiclass
- `character_class_state(id PK, character_id FK, class_id FK, current_level, hit_dice_spent, created_at)`
- `character_subclass_state(id PK, character_id FK, subclass_id FK, chosen_level)`
- `character_choices(id PK, character_id FK, at_level, choice_type, payload JSONB)`

Berechnung der Fähigkeiten:
- Features aus Klasse/Subklasse/Rasse/Background + Progression + Choices → berechneter Zustand (optional `character_computed` als Cache).

## 8. Foundry VTT (Integration)
- `foundry_connections(id PK, base_url, api_token, world_id, enabled bool)`
- Event-Bridge/Log optional für Rolls/Sync.

## 9. Cross-Cutting Constraints
- Foreign Keys mit ON DELETE CASCADE (wo der Charakter „Besitz“ ist) bzw. SET NULL für optionale Beziehungen.
- Eindeutige Constraints: `equipment_slots(character_id, slot_type)`, `users.username`.
- Indizes: häufige Filter (character_id, class_id, source, is_public), JSONB-GIN optional auf `properties`, `rules`.

## 10. API-Leitlinien
- Auth: Session-Cookie, CORS mit `Allow-Credentials` und Origin-Whitelist.
- Endpunkte (Auszug):
  - `POST /auth.php?action=login|logout|register`
  - `GET/POST/PUT/DELETE /characters.php` (CRUD und Detailupdates in Blöcken)
  - `GET/POST /sessions.php` (start), `PUT /sessions.php` (end), `POST /sessions.php?action=snapshot`, `GET latest_snapshot`
  - `GET/POST/PUT/DELETE /items` (Instanzen), `GET/POST /content/*` (Katalog)
- Fehlerformat konsistent: `{ success: false, error: string, code?: string }`

## 11. Migration & Betrieb
- Empfehlung: PostgreSQL in Docker parallel bereitstellen, Migration in Etappen (Tabellen anlegen, Daten übernehmen, Umschalten).
- Backups: Nightly Dump + Snapshots auf Objektspeicher.
- Tests: API-Contract-Tests, Datenbank-Constraints, Migrations-Smoke.

## 12. Custom vs. Canon Policy
- Canon: `source='canon'`, nur Admin änderbar.
- Homebrew/Custom: `source='homebrew'|'custom'`, `created_by_user_id` gesetzt; Sichtbarkeit via `is_public`.
- Ad-hoc-Instanz (ohne Katalog): `items.is_custom=true`, `content_item_id=NULL`.


