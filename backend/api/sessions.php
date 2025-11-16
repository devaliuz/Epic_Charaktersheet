<?php
/**
 * Sessions API
 * Verwaltet Spiel-Sessions und Snapshots
 */

// Session früh starten
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
// Dynamischer Origin für Cookies
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:8080';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS request (CORS Preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../models/Character.php';

class SessionsAPI {
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
    
    // GET /api/sessions.php?character_id=1 - Alle Sessions eines Charakters
    // GET /api/sessions.php?character_id=1&active=true - Aktive Session
    public function getSessions($characterId, $activeOnly = false) {
        $this->requireAuth();
        $this->ensureOwnershipOrAdmin($characterId);
        if ($activeOnly) {
            $stmt = $this->db->prepare("
                SELECT * FROM sessions 
                WHERE character_id = ? AND ended_at IS NULL 
                ORDER BY started_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$characterId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($session) {
                $session['snapshots'] = $this->getSessionSnapshots($session['id']);
            }
            
            echo json_encode($session ?: null);
        } else {
            $stmt = $this->db->prepare("
                SELECT s.*, 
                       (SELECT COUNT(*) FROM session_snapshots WHERE session_id = s.id) as snapshot_count
                FROM sessions s
                WHERE s.character_id = ?
                ORDER BY s.started_at DESC
            ");
            $stmt->execute([$characterId]);
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($sessions);
        }
    }
    
    // POST /api/sessions.php - Neue Session starten
    public function startSession($characterId, $sessionName = null) {
        $this->requireAuth();
        $this->ensureOwnershipOrAdmin($characterId);
        // Prüfe ob bereits aktive Session existiert
        $stmt = $this->db->prepare("SELECT id FROM sessions WHERE character_id = ? AND ended_at IS NULL");
        $stmt->execute([$characterId]);
        $activeSession = $stmt->fetch();
        
        if ($activeSession) {
            http_response_code(400);
            echo json_encode(['error' => 'Es läuft bereits eine aktive Session für diesen Charakter']);
            return;
        }
        
        $this->db->beginTransaction();
        
        try {
            // Erstelle neue Session
            $stmt = $this->db->prepare("
                INSERT INTO sessions (character_id, session_name, started_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$characterId, $sessionName ?: 'Session ' . date('Y-m-d H:i')]);
            $sessionId = $this->db->lastInsertId();
            
            // Erstelle Start-Snapshot
            $this->createSnapshot($characterId, $sessionId, 'session_start');
            
            $this->db->commit();
            
            echo json_encode([
                'success' => true,
                'session_id' => $sessionId,
                'message' => 'Session gestartet'
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Fehler beim Starten der Session: ' . $e->getMessage()]);
        }
    }
    
    // PUT /api/sessions.php?id=1 - Session beenden
    public function endSession($sessionId, $notes = null) {
        $this->requireAuth();
        $this->db->beginTransaction();
        
        try {
            // Hole Session-Info
            $stmt = $this->db->prepare("SELECT character_id FROM sessions WHERE id = ? AND ended_at IS NULL");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                http_response_code(404);
                echo json_encode(['error' => 'Session nicht gefunden oder bereits beendet']);
                return;
            }
            
            // Berechtigung prüfen
            $this->ensureOwnershipOrAdmin($session['character_id']);
            
            // Erstelle End-Snapshot (aus Datenbank, da Session beendet wird)
            $this->createSnapshot($session['character_id'], $sessionId, 'session_end');
            
            // Beende Session
            $stmt = $this->db->prepare("
                UPDATE sessions 
                SET ended_at = NOW(), notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$notes, $sessionId]);
            
            $this->db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Session beendet'
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Fehler beim Beenden der Session: ' . $e->getMessage()]);
        }
    }
    
    // POST /api/sessions.php?action=snapshot - Manueller Screenshot
    public function createManualSnapshot($characterId, $characterData = null) {
        $this->requireAuth();
        $this->ensureOwnershipOrAdmin($characterId);
        try {
            // Wenn Character-Daten übergeben wurden, verwende diese (aktueller Frontend-Zustand)
            // Sonst lade aus Datenbank (Fallback)
            if ($characterData) {
                $snapshotId = $this->createSnapshotFromData($characterId, null, 'manual', $characterData);
            } else {
                $snapshotId = $this->createSnapshot($characterId, null, 'manual');
            }
            
            echo json_encode([
                'success' => true,
                'snapshot_id' => $snapshotId,
                'message' => 'Screenshot erstellt'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Fehler beim Erstellen des Screenshots: ' . $e->getMessage()]);
        }
    }
    
    // GET /api/sessions.php?snapshot_id=1 - Hole Snapshot
    public function getSnapshot($snapshotId) {
        $this->requireAuth();
        $stmt = $this->db->prepare("SELECT * FROM session_snapshots WHERE id = ?");
        $stmt->execute([$snapshotId]);
        $snapshot = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$snapshot) {
            http_response_code(404);
            echo json_encode(['error' => 'Snapshot nicht gefunden']);
            return;
        }
        
        // Berechtigung prüfen
        $this->ensureOwnershipOrAdmin($snapshot['character_id']);
        
        // Dekodiere JSON
        $snapshot['character_data'] = json_decode($snapshot['character_data'], true);
        echo json_encode($snapshot);
    }
    
    // GET /api/sessions.php?character_id=1&latest_snapshot=true - Hole neuesten Screenshot
    public function getLatestSnapshot($characterId) {
        $this->requireAuth();
        $this->ensureOwnershipOrAdmin($characterId);
        $stmt = $this->db->prepare("
            SELECT * FROM session_snapshots 
            WHERE character_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$characterId]);
        $snapshot = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$snapshot) {
            http_response_code(404);
            echo json_encode(['error' => 'Kein Screenshot gefunden']);
            return;
        }
        
        // Dekodiere JSON
        $snapshot['character_data'] = json_decode($snapshot['character_data'], true);
        echo json_encode($snapshot);
    }
    
    // GET /api/sessions.php?session_id=1&snapshots=true - Hole alle Snapshots einer Session
    public function getSessionSnapshots($sessionId) {
        $stmt = $this->db->prepare("
            SELECT id, snapshot_type, created_at 
            FROM session_snapshots 
            WHERE session_id = ? 
            ORDER BY created_at ASC
        ");
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Private: Erstelle Snapshot aus Datenbank
    private function createSnapshot($characterId, $sessionId = null, $type = 'manual') {
        // Hole vollständigen Charakterzustand aus Datenbank
        $character = $this->characterModel->getById($characterId);
        
        if (!$character) {
            throw new Exception('Charakter nicht gefunden');
        }
        
        // Erstelle Snapshot
        $stmt = $this->db->prepare("
            INSERT INTO session_snapshots (session_id, character_id, snapshot_type, character_data)
            VALUES (?, ?, ?, ?)
        ");
        
        $characterJson = json_encode($character, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $stmt->execute([$sessionId, $characterId, $type, $characterJson]);
        
        return $this->db->lastInsertId();
    }
    
    // Private: Erstelle Snapshot aus übergebenen Daten (aktueller Frontend-Zustand)
    private function createSnapshotFromData($characterId, $sessionId = null, $type = 'manual', $characterData) {
        // Stelle sicher, dass character_id gesetzt ist
        $characterData['id'] = $characterId;
        
        // Erstelle Snapshot
        $stmt = $this->db->prepare("
            INSERT INTO session_snapshots (session_id, character_id, snapshot_type, character_data)
            VALUES (?, ?, ?, ?)
        ");
        
        $characterJson = json_encode($characterData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $stmt->execute([$sessionId, $characterId, $type, $characterJson]);
        
        return $this->db->lastInsertId();
    }
}

// API Handler
$api = new SessionsAPI();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['snapshot_id'])) {
                $api->getSnapshot($_GET['snapshot_id']);
            } elseif (isset($_GET['character_id']) && isset($_GET['latest_snapshot']) && $_GET['latest_snapshot'] === 'true') {
                $api->getLatestSnapshot($_GET['character_id']);
            } elseif (isset($_GET['character_id'])) {
                $activeOnly = isset($_GET['active']) && $_GET['active'] === 'true';
                $api->getSessions($_GET['character_id'], $activeOnly);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'character_id oder snapshot_id erforderlich']);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($action === 'snapshot') {
                $characterId = $data['character_id'] ?? $_GET['character_id'] ?? null;
                if (!$characterId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'character_id erforderlich']);
                    break;
                }
                // Wenn character_data übergeben wurde, verwende diese (aktueller Frontend-Zustand)
                $characterData = $data['character_data'] ?? null;
                $api->createManualSnapshot($characterId, $characterData);
            } else {
                // Session starten
                $characterId = $data['character_id'] ?? $_GET['character_id'] ?? null;
                $sessionName = $data['session_name'] ?? null;
                
                if (!$characterId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'character_id erforderlich']);
                    break;
                }
                
                $api->startSession($characterId, $sessionName);
            }
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            $sessionId = $data['session_id'] ?? $_GET['id'] ?? null;
            $notes = $data['notes'] ?? null;
            
            if (!$sessionId) {
                http_response_code(400);
                echo json_encode(['error' => 'session_id erforderlich']);
                break;
            }
            
            $api->endSession($sessionId, $notes);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Methode nicht erlaubt']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

