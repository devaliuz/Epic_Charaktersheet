<?php
/**
 * Auth API
 * - POST /api/auth.php?action=login   { username, password }
 * - POST /api/auth.php?action=logout
 * - POST /api/auth.php?action=register   { username, password, role } (nur Admin)
 * - GET  /api/auth.php                -> aktueller Benutzer
 *
 * Verwendet PHP Sessions und password_hash/password_verify.
 */

// Session früh starten (vor jeglicher Ausgabe)
if (session_status() === PHP_SESSION_NONE) {
    // Sichere, kompatible Cookie-Parameter (SameSite=Lax für Navigations-Redirects)
    if (function_exists('session_set_cookie_params')) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => false, // in Prod per HTTPS auf true setzen
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    session_start();
}

header('Content-Type: application/json');
// Dynamischer Origin für Cookies
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8090';
$computedOrigin = $scheme . '://' . $host;
$origin = $_SERVER['HTTP_ORIGIN'] ?? $computedOrigin;
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Vary: Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once __DIR__ . '/logger.php';

class AuthAPI {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->ensureDefaultAdmin();
    }

    // Stelle sicher, dass mindestens ein Admin existiert
    private function ensureDefaultAdmin() {
        try {
            app_log('auth.ensureDefaultAdmin.begin');
            // PostgreSQL: Schema und Tabellen werden über docker/postgres/init/* erstellt.
            // Hier nur sicherstellen, dass mindestens ein Admin existiert.
            $stmt = $this->db->query("SELECT COUNT(*) AS cnt FROM users");
            $cnt = (int)$stmt->fetch()['cnt'];
            if ($cnt === 0) {
                $username = 'admin';
                $password = 'admin123'; // Bitte nach dem ersten Login ändern!
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins = $this->db->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'admin')");
                $ins->execute([$username, $hash]);
                app_log('auth.ensureDefaultAdmin.created_admin');
            }
        } catch (Throwable $e) {
            // Lasse Fehler nach außen gehen, Handler antwortet mit JSON
            app_log('auth.ensureDefaultAdmin.error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getCurrentUser() {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(null);
            return;
        }
        echo json_encode([
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role']
        ]);
    }

    public function login($username, $password) {
        if (!$username || !$password) {
            http_response_code(400);
            echo json_encode(['error' => 'username und password erforderlich']);
            return;
        }

        app_log('auth.login.begin', ['user' => $username]);
        $stmt = $this->db->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $valid = false;
        if ($user) {
            // Primär: PHP password_hash() kompatibel prüfen
            $valid = password_verify($password, $user['password_hash']);
            // Fallback: pgcrypto/crypt()-Hashes akzeptieren
            if (!$valid) {
                $cryptCheck = @crypt($password, $user['password_hash']);
                if (is_string($cryptCheck) && hash_equals($cryptCheck, $user['password_hash'])) {
                    $valid = true;
                }
            }
        }

        if (!$user || !$valid) {
            app_log('auth.login.fail', ['user' => $username]);
            http_response_code(401);
            echo json_encode(['error' => 'Ungültige Zugangsdaten']);
            return;
        }

        // Nach erfolgreicher Verifikation: Session stabilisieren
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        app_log('auth.login.success', ['user' => $username]);

        echo json_encode([
            'success' => true,
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ]
        ]);
    }

    public function logout() {
        $_SESSION = [];
        if (session_id() !== '' || isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
        echo json_encode(['success' => true]);
    }

    public function register($username, $password, $role = 'user') {
        // Nur Admin darf Benutzer anlegen
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Nur Admins dürfen Benutzer anlegen']);
            return;
        }
        if (!$username || !$password) {
            http_response_code(400);
            echo json_encode(['error' => 'username und password erforderlich']);
            return;
        }
        if (!in_array($role, ['admin', 'user'])) {
            $role = 'user';
        }
        // Prüfe ob existiert
        $check = $this->db->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Benutzer existiert bereits']);
            return;
        }
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $ins = $this->db->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
        $ins->execute([$username, $hash, $role]);
        echo json_encode(['success' => true, 'id' => (int)$this->db->lastInsertId()]);
    }
}

$api = new AuthAPI();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

try {
    switch ($method) {
        case 'GET':
            $api->getCurrentUser();
            break;
        case 'POST':
            if ($action === 'login') {
                $data = json_decode(file_get_contents('php://input'), true);
                $api->login($data['username'] ?? null, $data['password'] ?? null);
            } elseif ($action === 'logout') {
                $api->logout();
            } elseif ($action === 'register') {
                $data = json_decode(file_get_contents('php://input'), true);
                $api->register($data['username'] ?? null, $data['password'] ?? null, $data['role'] ?? 'user');
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Unbekannte Aktion']);
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Methode nicht erlaubt']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}


