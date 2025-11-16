<?php
/**
 * Error Handler für API
 */

header('Content-Type: application/json');

$code = $_GET['code'] ?? 500;
http_response_code((int)$code);

echo json_encode([
    'success' => false,
    'error' => 'API Fehler',
    'code' => $code,
    'message' => match((int)$code) {
        404 => 'Ressource nicht gefunden',
        500 => 'Interner Server-Fehler',
        default => 'Unbekannter Fehler'
    }
]);

