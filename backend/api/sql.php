<?php
/**
 * SQL Query Endpoint
 * Ermöglicht SQL-Abfragen über die API (nur für Entwicklung!)
 * WARNUNG: Dies ist ein Sicherheitsrisiko in Produktion!
 */

header('Content-Type: application/json');
// Dynamischer Origin für Cookies
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:8080';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS request (CORS Preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

// Nur POST erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Nur POST erlaubt']);
    exit();
}

// JSON Input lesen
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['query'])) {
    http_response_code(400);
    echo json_encode(['error' => 'SQL Query erforderlich']);
    exit();
}

$query = $input['query'];

// Sicherheitsprüfung: Nur SELECT erlauben (für sichere Abfragen)
$queryUpper = trim(strtoupper($query));
if (strpos($queryUpper, 'SELECT') !== 0 && strpos($queryUpper, 'SHOW') !== 0 && strpos($queryUpper, 'DESCRIBE') !== 0 && strpos($queryUpper, 'DESC') !== 0 && strpos($queryUpper, 'EXPLAIN') !== 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Nur SELECT, SHOW, DESCRIBE und EXPLAIN Queries erlaubt']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Query ausführen
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    // Ergebnis abrufen
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'rows' => $results,
        'count' => count($results)
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'SQL Fehler',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server-Fehler',
        'message' => $e->getMessage()
    ]);
}

