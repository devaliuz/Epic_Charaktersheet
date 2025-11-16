# D&D Character Sheet - Docker Setup

## Voraussetzungen
- Docker Desktop installiert (Windows/Mac) oder Docker + Docker Compose (Linux)
- Git (optional)

## Schnellstart

### 1. Docker Container starten
```bash
docker-compose up -d
```

### 2. Container-Status prüfen
```bash
docker-compose ps
```

### 3. Logs anzeigen
```bash
# Alle Logs
docker-compose logs -f

# Nur PHP
docker-compose logs -f php

# Nur MySQL
docker-compose logs -f db
```

### 4. Zugriff auf die Anwendung
- **Frontend**: http://localhost:8080
- **Backend API**: http://localhost:8080/backend/api/characters.php?id=1
- **phpMyAdmin**: http://localhost:8081
  - Server: `db`
  - Benutzer: `root`
  - Passwort: `root_password`

## Datenbank-Zugriff

### Direkter MySQL-Zugriff
```bash
# Verbinde zu MySQL Container
docker exec -it dnd_char_db mysql -u dnd_user -pdnd_password dnd_charsheet

# Oder als Root
docker exec -it dnd_char_db mysql -u root -proot_password dnd_charsheet
```

### Externe Tools
- **Host**: localhost
- **Port**: 3306
- **Datenbank**: dnd_charsheet
- **Benutzer**: dnd_user
- **Passwort**: dnd_password
- **Root-Passwort**: root_password

## Container-Verwaltung

### Container stoppen
```bash
docker-compose stop
```

### Container starten
```bash
docker-compose start
```

### Container neu bauen (nach Änderungen)
```bash
docker-compose build
docker-compose up -d
```

### Container komplett entfernen (inkl. Daten!)
```bash
docker-compose down -v
```

### Container ohne Daten entfernen
```bash
docker-compose down
```

## Backup & Restore

### Datenbank-Backup erstellen
```bash
docker exec dnd_char_db mysqldump -u root -proot_password dnd_charsheet > backup.sql
```

### Datenbank wiederherstellen
```bash
docker exec -i dnd_char_db mysql -u root -proot_password dnd_charsheet < backup.sql
```

## Entwicklung

### PHP Dateien bearbeiten
- Dateien im `backend/` Ordner werden automatisch in den Container gemountet
- Änderungen sind sofort sichtbar (Apache lädt neu)

### Frontend Dateien bearbeiten
- Dateien im `frontend/` Ordner werden automatisch in den Container gemountet
- Änderungen sind sofort sichtbar

### Datenbank-Schema ändern
1. Schema in `database/schema.sql` anpassen
2. Container neu erstellen:
```bash
docker-compose down -v
docker-compose up -d
```

## Fehlerbehebung

### Container startet nicht
```bash
# Prüfe Logs
docker-compose logs

# Prüfe ob Ports bereits belegt sind
netstat -ano | findstr :8080  # Windows
lsof -i :8080  # Linux/Mac
```

### Datenbank-Verbindung fehlgeschlagen
```bash
# Prüfe ob MySQL läuft
docker-compose ps db

# Prüfe MySQL Logs
docker-compose logs db

# Versuche manuelle Verbindung
docker exec -it dnd_char_db mysql -u dnd_user -pdnd_password dnd_charsheet
```

### PHP Fehler
```bash
# Prüfe PHP Logs
docker-compose logs php

# Prüfe Apache Error Log
docker exec dnd_char_php tail -f /var/log/apache2/error.log
```

### Permissions-Probleme
```bash
# Setze korrekte Permissions
docker exec dnd_char_php chown -R www-data:www-data /var/www/html
docker exec dnd_char_php chmod -R 755 /var/www/html
```

## Umgebungsvariablen anpassen

Erstelle eine `.env` Datei im Root-Verzeichnis:
```env
DB_HOST=db
DB_DATABASE=dnd_charsheet
DB_USERNAME=dnd_user
DB_PASSWORD=dein_sicheres_passwort
DB_PORT=3306
```

Dann Container neu starten:
```bash
docker-compose up -d
```

## Nächste Schritte

1. **Frontend anpassen**: `Bar-Iton_CharacterSheet.html` sollte die API verwenden
2. **Character ID konfigurieren**: Aktuell wird Character ID 1 verwendet
3. **Multi-Character Support**: UI für Charakterauswahl hinzufügen
4. **Authentication**: Optional - Benutzer-Login hinzufügen

## Troubleshooting

### "Connection refused" Fehler
- Prüfe ob MySQL Container läuft: `docker-compose ps`
- Warte 10-15 Sekunden nach Start - MySQL braucht Zeit zum Initialisieren
- Prüfe MySQL Logs: `docker-compose logs db`

### "Table doesn't exist" Fehler
- Schema wird beim ersten Start automatisch erstellt
- Falls nicht: `docker-compose down -v && docker-compose up -d`

### CORS Fehler im Browser
- CORS Headers sind bereits in Apache konfiguriert
- Prüfe ob `mod_headers` aktiviert ist: `docker exec dnd_char_php a2enmod headers`

