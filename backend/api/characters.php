<?php
/**
 * Character API Endpoint
 * RESTful API für Charakter-Verwaltung
 * Endpoints:
 * - GET /api/characters.php?id=1 - Charakter laden
 * - GET /api/characters.php - Alle Charaktere auflisten
 * - POST /api/characters.php - Neuen Charakter erstellen
 * - PUT /api/characters.php?id=1 - Charakter aktualisieren
 * - DELETE /api/characters.php?id=1 - Charakter löschen
 */

// Session früh starten
if (session_status() === PHP_SESSION_NONE) {
    if (function_exists('session_set_cookie_params')) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => false,
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
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Vary: Origin');

// Handle OPTIONS request (CORS Preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../models/Character.php';
require_once __DIR__ . '/logger.php';

class CharacterAPI {
    private $db;
    private $characterModel;
    private $currentUser;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->characterModel = new Character($this->db);
        $this->currentUser = [
            'id' => $_SESSION['user_id'] ?? null,
            'role' => $_SESSION['role'] ?? null
        ];
    }
    
    private function requireAuth() {
        if (!$this->currentUser['id']) {
            http_response_code(401);
            echo json_encode(['error' => 'Nicht angemeldet']);
            exit();
        }
    }

    private function isAdmin() {
        return ($this->currentUser['role'] ?? null) === 'admin';
    }

    private function ensureOwnershipOrAdmin($characterId) {
        if ($this->isAdmin()) return;
        $stmt = $this->db->prepare("SELECT user_id FROM characters WHERE id = ?");
        $stmt->execute([$characterId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Charakter nicht gefunden']);
            exit();
        }
        if ((int)$row['user_id'] !== (int)$this->currentUser['id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Keine Berechtigung für diesen Charakter']);
            exit();
        }
    }

    // GET /api/characters.php?id=1 oder GET /api/characters.php
    public function getCharacter($id = null) {
        $this->requireAuth();
        app_log('characters.get.begin', ['id' => $id]);
        if ($id) {
            $this->ensureOwnershipOrAdmin($id);
            $character = $this->characterModel->getById($id);
            if (!$character) {
                http_response_code(404);
                echo json_encode(['error' => 'Charakter nicht gefunden']);
                return;
            }
            app_log('characters.get.one', ['id' => $id]);
            echo json_encode($character);
        } else {
            // Alle Charaktere auflisten (Admin: alle, User: eigene)
            if ($this->isAdmin()) {
                $characters = $this->characterModel->getAll();
            } else {
                $stmt = $this->db->prepare("SELECT id, name, level, class, race FROM characters WHERE user_id = ? ORDER BY name");
                $stmt->execute([$this->currentUser['id']]);
                $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            app_log('characters.get.list', ['count' => is_array($characters) ? count($characters) : null]);
            echo json_encode($characters);
        }
    }
    
    // POST /api/characters.php
    public function createCharacter($data) {
        $this->requireAuth();
        try {
            // Setze Eigentümer auf aktuellen User
            $data['user_id'] = (int)$this->currentUser['id'];
            $characterId = $this->characterModel->create($data);
            echo json_encode([
                'success' => true,
                'id' => $characterId,
                'message' => 'Charakter erfolgreich erstellt'
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    // PUT /api/characters.php?id=1
    public function updateCharacter($id, $data) {
        $this->requireAuth();
        $this->ensureOwnershipOrAdmin($id);
        try {
            $result = $this->characterModel->update($id, $data);
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Charakter erfolgreich aktualisiert'
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Charakter nicht gefunden'
                ]);
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    // DELETE /api/characters.php?id=1
    public function deleteCharacter($id) {
        $this->requireAuth();
        $this->ensureOwnershipOrAdmin($id);
        try {
            $result = $this->characterModel->delete($id);
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Charakter erfolgreich gelöscht'
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Charakter nicht gefunden'
                ]);
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}

// API Handler
$method = $_SERVER['REQUEST_METHOD'];
$api = new CharacterAPI();

try {
    switch ($method) {
        case 'GET':
            $id = $_GET['id'] ?? null;
            $api->getCharacter($id);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                http_response_code(400);
                echo json_encode(['error' => 'Ungültige JSON-Daten']);
                break;
            }
            $api->createCharacter($data);
            break;
            
        case 'PUT':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID erforderlich']);
                break;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                http_response_code(400);
                echo json_encode(['error' => 'Ungültige JSON-Daten']);
                break;
            }
            $api->updateCharacter($id, $data);
            break;
            
        case 'DELETE':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID erforderlich']);
                break;
            }
            $api->deleteCharacter($id);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Methode nicht erlaubt']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server-Fehler',
        'message' => $e->getMessage()
    ]);
}
