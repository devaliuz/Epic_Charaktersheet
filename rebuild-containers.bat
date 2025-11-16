@echo off
REM Skript zum Neuerstellen aller Docker-Container
REM Stoppt und entfernt alle Container und Volumes, dann erstellt sie neu

echo ========================================
echo Docker Container werden neu erstellt...
echo ========================================
echo.

REM Container stoppen und entfernen (inkl. Volumes)
echo [1/4] Stoppe und entferne bestehende Container...
docker-compose down -v
if %ERRORLEVEL% NEQ 0 (
    echo Warnung: Einige Container konnten nicht entfernt werden (möglicherweise existieren sie nicht)
)

REM Alte Container manuell entfernen (falls vorhanden)
echo [2/4] Entferne alte Container manuell...
docker stop dnd_char_php dnd_char_db dnd_char_pma 2>nul
docker rm dnd_char_php dnd_char_db dnd_char_pma 2>nul

REM Alte Volumes entfernen
echo [3/4] Entferne alte Volumes...
docker volume rm pp_dnd_db_data 2>nul

REM Container neu erstellen und starten
echo [4/4] Erstelle Container neu und starte sie...
docker-compose build --no-cache
docker-compose up -d

echo.
echo ========================================
echo Fertig!
echo ========================================
echo.
echo Container-Status:
docker-compose ps
echo.
echo Logs anzeigen mit: docker-compose logs -f
echo Container stoppen mit: docker-compose stop
echo.
pause



