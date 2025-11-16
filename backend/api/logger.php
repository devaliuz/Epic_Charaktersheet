<?php
/**
 * Sehr einfaches Logging für Diagnose im Container.
 * Schreibt JSON-Linien nach /tmp/app.log (stdout bleibt sauber).
 */
function app_log(string $event, array $context = []): void {
    $line = [
        'ts' => gmdate('c'),
        'event' => $event,
        'pid' => getmypid(),
        'session_id' => session_id(),
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'uri' => $_SERVER['REQUEST_URI'] ?? null,
        'origin' => $_SERVER['HTTP_ORIGIN'] ?? null,
        'host' => $_SERVER['HTTP_HOST'] ?? null,
        'cookies_present' => isset($_SERVER['HTTP_COOKIE']),
        'context' => $context
    ];
    file_put_contents('/tmp/app.log', json_encode($line, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
}


