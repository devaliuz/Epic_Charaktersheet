#!/bin/bash
# Start Script für D&D Character Sheet Docker Setup

echo "🚀 Starte D&D Character Sheet..."

# Prüfe ob Docker läuft
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker ist nicht gestartet. Bitte starte Docker Desktop."
    exit 1
fi

# Starte Container
echo "📦 Starte Docker Container..."
docker-compose up -d

# Warte auf MySQL
echo "⏳ Warte auf MySQL (10 Sekunden)..."
sleep 10

# Prüfe Container-Status
echo "📊 Container-Status:"
docker-compose ps

echo ""
echo "✅ Setup abgeschlossen!"
echo ""
echo "Zugriff auf die Anwendung:"
echo "  Frontend: http://localhost:8080"
echo "  Backend API: http://localhost:8080/backend/api/characters.php?id=1"
echo "  phpMyAdmin: http://localhost:8081"
echo ""
echo "Logs anzeigen: docker-compose logs -f"
echo "Container stoppen: docker-compose stop"

