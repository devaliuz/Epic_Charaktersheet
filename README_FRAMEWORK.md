# D&D Character Sheet - Framework Migration Plan

## Aktuelle Situation
- Statische HTML-Datei mit JavaScript
- localStorage für Datenspeicherung
- Alles clientseitig

## Vorgeschlagene Lösung: PHP + Docker + MySQL/PostgreSQL

### Struktur
```
dnd-character-sheet/
├── docker-compose.yml
├── Dockerfile
├── .env
├── backend/
│   ├── api/
│   │   ├── characters.php
│   │   ├── inventory.php
│   │   ├── spells.php
│   │   └── equipment.php
│   ├── config/
│   │   └── database.php
│   ├── models/
│   │   ├── Character.php
│   │   ├── Inventory.php
│   │   ├── Equipment.php
│   │   └── SpellSlot.php
│   └── controllers/
│       ├── CharacterController.php
│       └── EquipmentController.php
├── frontend/
│   ├── index.html
│   ├── css/
│   │   └── styles.css
│   ├── js/
│   │   ├── CharacterManager.js
│   │   ├── EquipmentManager.js
│   │   └── API.js
│   └── assets/
├── database/
│   └── schema.sql
└── README.md
```

### Technologie-Stack
- **Backend**: PHP 8.2+ (Laravel oder Plain PHP)
- **Datenbank**: MySQL 8.0 oder PostgreSQL 15
- **Frontend**: HTML5, CSS3, Vanilla JS (oder React/Vue)
- **Container**: Docker + Docker Compose
- **API**: RESTful JSON API

### Vorteile
1. **Persistente Speicherung**: Datenbank statt localStorage
2. **Multi-User**: Mehrere Charaktere pro Benutzer
3. **Backup**: Einfache Datenbank-Backups
4. **Skalierbar**: Kann später um Features erweitert werden
5. **Offline-Fähigkeit**: Service Worker für Offline-Modus möglich

### Migration-Schritte

1. **Docker-Setup erstellen**
2. **Datenbankschema definieren**
3. **Backend-API entwickeln**
4. **Frontend anpassen (AJAX statt localStorage)**
5. **Migration der bestehenden Daten (optional)**

### Alternative: Einfacheres Setup

Falls Docker zu komplex ist, könnte man auch:
- **SQLite** statt MySQL (kein separater Server nötig)
- **Laravel Sail** (einfaches Docker-Setup für Laravel)
- **PHP Built-in Server** für Entwicklung (kein Docker nötig)

## Nächste Schritte

Soll ich:
1. **Vollständige Docker-Struktur** mit PHP + MySQL erstellen?
2. **Einfacheres Setup** mit SQLite + Plain PHP?
3. **Laravel Framework** nutzen (mehr Features, aber komplexer)?

Bitte wähle eine Option!

