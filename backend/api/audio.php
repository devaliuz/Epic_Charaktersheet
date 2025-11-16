<?php
/**
 * Audio API
 * Liefert Liste aller verfügbaren MP3-Dateien
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Audio-Verzeichnis (im Container: /var/www/html/public/audio)
// Da frontend als Volume nach /var/www/html/public gemappt ist
$audioDir = __DIR__ . '/../../public/audio';

// Fallback: Prüfe auch alternativen Pfad
if (!is_dir($audioDir)) {
    $audioDir = __DIR__ . '/../../../frontend/audio';
}

// Prüfe ob Verzeichnis existiert
if (!is_dir($audioDir)) {
    http_response_code(404);
    echo json_encode(['error' => 'Audio-Verzeichnis nicht gefunden']);
    exit;
}

// Sammle alle MP3-Dateien
$songs = [];
$files = scandir($audioDir);

foreach ($files as $file) {
    // Ignoriere Verzeichnisse und versteckte Dateien
    if ($file === '.' || $file === '..' || is_dir($audioDir . '/' . $file)) {
        continue;
    }
    
    // Nur MP3-Dateien
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if ($extension !== 'mp3') {
        continue;
    }
    
    // Song-Informationen
    $filename = pathinfo($file, PATHINFO_FILENAME); // Dateiname ohne Endung
    $fullPath = $audioDir . '/' . $file;
    $fileSize = filesize($fullPath);
    $lastModified = filemtime($fullPath);
    
    $songs[] = [
        'filename' => $file,
        'title' => $filename, // Titel = Dateiname ohne Endung
        'path' => '/audio/' . rawurlencode($file), // URL-encoded Pfad (rawurlencode für korrekte Kodierung)
        'size' => $fileSize,
        'sizeFormatted' => formatBytes($fileSize),
        'lastModified' => date('Y-m-d H:i:s', $lastModified)
    ];
}

// Sortiere nach Dateiname
usort($songs, function($a, $b) {
    return strcasecmp($a['filename'], $b['filename']);
});

// JSON-Antwort
echo json_encode([
    'success' => true,
    'songs' => $songs,
    'count' => count($songs)
]);

/**
 * Formatiere Bytes in lesbare Größe
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    
    if ($bytes === 0) {
        return '0 B';
    }
    
    $exp = floor(log($bytes) / log(1024));
    $exp = min($exp, count($units) - 1);
    
    return round($bytes / pow(1024, $exp), $precision) . ' ' . $units[$exp];
}

