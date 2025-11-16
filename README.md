# Epic Charaktersheet (D&D 5e)

Ziel: Ein vollwertiges, session-taugliches D&D-5e-Charaktersheet mit
- Mehrbenutzer-Login, mehreren Charakteren pro Benutzer
- Vollständigem Inventar-/Ausrüstungs- und Notizen-System
- Level-Up-Mechanik basierend auf Klassen/Rassen/Features (SRD-konform)
- Snapshot/Restore („Hard“-Snapshots einer Session)
- Optionaler Foundry VTT-Integration (Würfe, Sync)

Verbindliche Requirements: siehe `Requirements/` (ERD, Spezifikation, Regeln).

## 🚀 Schnellstart

### Option 1: Docker (Empfohlen)
```bash
# Windows
start.bat

# Linux/Mac
chmod +x start.sh
./start.sh
```

Dann:
1. Öffne http://localhost:8080 in deinem Browser
2. Fertig! 🎉

### Option 2: Manuell (Compose direkt)
```bash
docker compose up -d --build
```
Warte 10-15 Sekunden, dann öffne http://localhost:8080

## 📁 Projekt-Struktur (Auszug)

```
dnd-character-sheet/
├── docker-compose.yml      # Docker Konfiguration
├── Dockerfile              # PHP Container Definition
├── backend/                # PHP Backend
│   ├── api/               # REST API Endpoints
│   ├── config/            # Konfiguration
│   └── models/            # Datenbank-Models
├── frontend/              # Frontend (HTML/CSS/JS)
│   ├── js/               # JavaScript Dateien
│   └── css/              # Stylesheets
├── database/              # MySQL-Skripte (Legacy – Migration zu Postgres geplant)
│   ├── schema.sql        # aktuelles Schema
│   └── init.sql          # Initial-Daten
├── Requirements/          # Verbindliche Projekt-Requirements
│   ├── db-schema.mmd     # Mermaid ERD
│   ├── spec.md           # technische Spezifikation
│   └── rules.md          # Projektregeln
├── .github/workflows/     # Branch-Guard CI (verhindert Direct-Pushes)
└── docker/               # Docker-spezifische Configs
```

## 🔧 Technologie-Stack

- **Backend**: PHP 8.2 mit Apache
- **Datenbank**: MySQL 8.0
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Container**: Docker + Docker Compose
- **API**: RESTful JSON API

## 📡 API Endpoints (Auszug)

### Characters
- `GET /backend/api/characters.php?id=1` - Charakter laden
- `GET /backend/api/characters.php` - Alle Charaktere auflisten
- `POST /backend/api/characters.php` - Neuen Charakter erstellen
- `PUT /backend/api/characters.php?id=1` - Charakter aktualisieren
- `DELETE /backend/api/characters.php?id=1` - Charakter löschen

### Beispiel Request
```javascript
// Charakter laden
const character = await fetch('/backend/api/characters.php?id=1')
    .then(r => r.json());

// Charakter speichern
await fetch('/backend/api/characters.php?id=1', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        stats: { current_hp: 15, max_hp: 20 },
        equipment: { armor: 1, mainhand: 2 }
    })
});
```

## 🗄️ Datenbank

### Zugriff
- **Host**: localhost
- **Port**: 3306
- **Datenbank**: dnd_charsheet
- **Benutzer**: dnd_user
- **Passwort**: dnd_password
- **Root-Passwort**: root_password

### phpMyAdmin
- URL: http://localhost:8081
- Server: `db`
- Benutzer: `root`
- Passwort: `root_password`

## 📝 Migration von localStorage

Die Anwendung unterstützt **hybriden Modus**:
1. Versucht zuerst die API zu verwenden
2. Fallback zu localStorage bei API-Fehlern
3. Automatischer Wechsel zwischen den Modi

Um vollständig auf API umzustellen:
1. Frontend-Datei öffnen: `Bar-Iton_CharacterSheet.html`
2. Character ID konfigurieren (Standard: 1)
3. API-Fallback deaktivieren falls gewünscht

## 🔐 Sicherheit & Branching

- Branching
  - `main`/`master`: stabil – keine direkten Pushes (Branch-Guard CI). Arbeiten über `feature/<name>` und Pull Requests.
  - GitHub Branchschutz aktivieren: Required status checks (Branch Guard), Review erforderlich.

- Sicherheit

**Hinweis**: Dieses Setup ist für **Entwicklung** gedacht!

Für Produktion:
- `.env` Datei verwenden für Passwörter
- CORS einschränken (nicht `*`)
- HTTPS verwenden
- Authentication implementieren
- SQL Injection Schutz (bereits durch Prepared Statements)
- Input Validation erweitern

## 🐛 Fehlerbehebung

Siehe `README_DOCKER.md` für detaillierte Troubleshooting-Anleitung.

### Häufige Probleme

**Container startet nicht**
```bash
docker-compose logs
```

**Datenbank-Verbindung fehlgeschlagen**
```bash
# Prüfe ob MySQL läuft
docker-compose ps db

# Warte länger (MySQL braucht 10-15 Sekunden zum Starten)
docker-compose logs -f db
```

**Port bereits belegt**
```bash
# Ändere Ports in docker-compose.yml
ports:
  - "8081:80"  # Statt 8080
```

## 📚 Weitere Dokumentation & Requirements

- `README_DOCKER.md` - Docker Setup & Verwaltung
- `README_FRAMEWORK.md` - Framework Migration Plan
- `Requirements/` - maßgebliche Vorgaben (immer zuerst dort pflegen)

## 🎮 Features

- ✅ Vollständiges Character Sheet für D&D 5e
- ✅ Ausrüstungs-System mit Drag & Drop
- ✅ Inventar-Verwaltung (Ausrüstung, Verbrauchsgegenstände, Werkzeuge, Schätze)
- ✅ Zauberplätze-Tracking
- ✅ HP Management mit temporären HP
- ✅ Bardic Inspiration Tracking
- ✅ Rasten (Kurze & Lange Rast)
- ✅ Level-Up System
- ✅ Persistent Speicherung (MySQL)
- ✅ Multi-Character Support (vorbereitet)

## 🚧 Geplante Features

- [ ] Multi-User Support mit Authentication
- [ ] Charakterauswahl-UI
- [ ] Export/Import Funktionen
- [ ] Offline-Modus mit Service Worker
- [ ] Mobile Optimierung
- [ ] Dark/Light Mode Toggle

## 📄 Lizenz

Private Projekt - Keine öffentliche Lizenz

## 👤 Autor

Bar-iton Character Sheet Projekt

