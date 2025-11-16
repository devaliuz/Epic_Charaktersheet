@echo off
REM Start Script für D&D Character Sheet Docker Setup (Windows)

echo 🚀 Starte D&D Character Sheet...

REM Prüfe ob Docker läuft
docker info >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Docker ist nicht gestartet. Bitte starte Docker Desktop.
    pause
    exit /b 1
)

REM Starte Container
echo 📦 Starte Docker Container...
docker-compose up -d

REM Warte auf MySQL
echo ⏳ Warte auf MySQL (10 Sekunden)...
timeout /t 10 /nobreak >nul

REM Prüfe Container-Status
echo 📊 Container-Status:
docker-compose ps

echo.
echo ✅ Setup abgeschlossen!
echo.
echo Zugriff auf die Anwendung:
echo   Frontend: http://localhost:8080
echo   Backend API: http://localhost:8080/backend/api/characters.php?id=1
echo   phpMyAdmin: http://localhost:8081
echo.
echo Logs anzeigen: docker-compose logs -f
echo Container stoppen: docker-compose stop
pause

