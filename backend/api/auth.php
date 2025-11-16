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
    session_start();
}

header('Content-Type: application/json');
// Dynamischer Origin für Cookies
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:8080';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

class AuthAPI {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->ensureDefaultAdmin();
    }

    // Stelle sicher, dass mindestens ein Admin existiert
    private function ensureDefaultAdmin() {
        try {
            // Stelle sicher, dass Tabelle existiert (idempotent)
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    username VARCHAR(100) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    role ENUM('admin','user') NOT NULL DEFAULT 'user',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_role (role)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            // characters.user_id ggf. nachrüsten (idempotent, kompatibel auch ohne IF NOT EXISTS)
            // 1) Spalte
            $colExistsStmt = $this->db->prepare('SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'characters\' AND COLUMN_NAME = \'user_id\'');
            $colExistsStmt->execute();
            $colExists = (int)$colExistsStmt->fetch()['cnt'] > 0;
            if (!$colExists) {
                $this->db->exec('ALTER TABLE characters ADD COLUMN user_id INT NULL');
            }
            // 2) Index
            $idxExistsStmt = $this->db->prepare('SELECT COUNT(*) AS cnt FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'characters\' AND INDEX_NAME = \'idx_user\'');
            $idxExistsStmt->execute();
            $idxExists = (int)$idxExistsStmt->fetch()['cnt'] > 0;
            if (!$idxExists) {
                $this->db->exec('CREATE INDEX idx_user ON characters(user_id)');
            }
            // 3) Foreign Key
            $fkExistsStmt = $this->db->prepare('SELECT COUNT(*) AS cnt FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'characters\' AND CONSTRAINT_NAME = \'fk_char_user\' AND CONSTRAINT_TYPE = \'FOREIGN KEY\'');
            $fkExistsStmt->execute();
            $fkExists = (int)$fkExistsStmt->fetch()['cnt'] > 0;
            if (!$fkExists) {
                $this->db->exec('ALTER TABLE characters ADD CONSTRAINT fk_char_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL');
            }
            // Default-Admin sicherstellen
            $stmt = $this->db->query("SELECT COUNT(*) AS cnt FROM users");
            $cnt = (int)$stmt->fetch()['cnt'];
            if ($cnt === 0) {
                $username = 'admin';
                $password = 'admin123'; // Bitte nach dem ersten Login ändern!
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins = $this->db->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'admin')");
                $ins->execute([$username, $hash]);
            }
        } catch (Throwable $e) {
            // Lasse Fehler nach außen gehen, Handler antwortet mit JSON
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

        $stmt = $this->db->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Ungültige Zugangsdaten']);
            return;
        }

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

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


