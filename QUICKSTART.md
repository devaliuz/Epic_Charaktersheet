# 🚀 Quick Start Guide

## Docker Setup starten

### Windows
```cmd
start.bat
```

### Linux/Mac
```bash
chmod +x start.sh
./start.sh
```

### Oder manuell
```bash
docker-compose up -d
```

## ⏱️ Warte 10-15 Sekunden

MySQL braucht etwas Zeit zum Starten. Prüfe die Logs:
```bash
docker-compose logs -f db
```

## 🌐 Öffne die Anwendung

1. **Frontend (aktuelle HTML-Datei):**
   - Öffne `Bar-Iton_CharacterSheet.html` im Browser
   - Diese nutzt automatisch API mit localStorage-Fallback
   - Funktioniert weiterhin ohne Docker (localStorage)

2. **API Test-Seite:**
   - Öffne: http://localhost:8080/index.html
   - Teste die API-Verbindung

3. **Direkter API-Zugriff:**
   - http://localhost:8080/backend/api/characters.php?id=1

4. **phpMyAdmin (Datenbank-Verwaltung):**
   - http://localhost:8081
   - Server: `db`
   - Benutzer: `root`
   - Passwort: `root_password`

## ✅ Prüfen ob alles läuft

```bash
# Container-Status
docker-compose ps

# Logs anzeigen
docker-compose logs -f

# API testen (im Browser)
http://localhost:8080/backend/api/characters.php?id=1
```

## 🔧 Container stoppen

```bash
docker-compose stop
```

## 🔄 Container neu starten

```bash
docker-compose start
```

## 🗑️ Alles entfernen (inkl. Daten!)

```bash
docker-compose down -v
```

## 📊 Datenbank-Backup

```bash
# Backup erstellen
docker exec dnd_char_db mysqldump -u root -proot_password dnd_charsheet > backup.sql

# Wiederherstellen
docker exec -i dnd_char_db mysql -u root -proot_password dnd_charsheet < backup.sql
```

## 🐛 Probleme?

Siehe `README_DOCKER.md` für detaillierte Fehlerbehebung.

### Container startet nicht?
```bash
docker-compose logs
```

### API funktioniert nicht?
1. Prüfe ob Container läuft: `docker-compose ps`
2. Prüfe PHP Logs: `docker-compose logs php`
3. Prüfe MySQL Logs: `docker-compose logs db`
4. Warte 15 Sekunden - MySQL braucht Zeit zum Starten

### Port bereits belegt?
Ändere Ports in `docker-compose.yml`:
```yaml
ports:
  - "8081:80"  # Statt 8080
```

## 🎮 Verwendung

### Aktuelle HTML-Datei (Bar-Iton_CharacterSheet.html)
- Nutzt **automatisch API** wenn verfügbar
- **Fallback zu localStorage** wenn API nicht erreichbar
- Funktioniert **mit und ohne Docker**
- Alle Features bleiben erhalten

### API-Integration
- API wird automatisch erkannt
- Bei Fehlern wird localStorage verwendet
- Seamless Übergang zwischen den Modi

