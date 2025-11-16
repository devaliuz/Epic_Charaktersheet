<?php
/**
 * Items Korrektur-Endpoint
 * Erlaubt das Korrigieren von fehlerhaften Items in der Datenbank
 * Nur für Entwicklung/Debugging!
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
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

if (!isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Action erforderlich']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    
    $action = $input['action'];
    
    if ($action === 'find') {
        // Finde fehlerhafte Items
        $characterId = $input['character_id'] ?? 1;
        
        $stmt = $db->prepare("
            SELECT id, character_id, name, type, category, created_at 
            FROM items 
            WHERE character_id = ? 
            ORDER BY id
        ");
        $stmt->execute([$characterId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Valide type-Werte laut Schema
        $validTypes = ['weapon', 'armor', 'consumable', 'tool', 'treasure', 'equipment'];
        $validCategories = ['equipment', 'consumables', 'tools', 'treasure'];
        
        $brokenItems = [];
        
        foreach ($items as $item) {
            $issues = [];
            
            // Prüfe type
            if (!in_array($item['type'], $validTypes)) {
                $issues[] = "Ungültiger type: '{$item['type']}'";
            } else if ($item['type'] === 'tools' || $item['type'] === 'consumables') {
                $issues[] = "Falscher type: '{$item['type']}'";
            }
            
            // Prüfe category
            if (!$item['category'] || !in_array($item['category'], $validCategories)) {
                $issues[] = "Fehlende oder ungültige category";
            }
            
            // Prüfe ob type zu category passt
            if ($item['category'] === 'tools' && $item['type'] !== 'tool') {
                $issues[] = "Type '{$item['type']}' passt nicht zu category 'tools'";
            }
            if ($item['category'] === 'consumables' && $item['type'] !== 'consumable') {
                $issues[] = "Type '{$item['type']}' passt nicht zu category 'consumables'";
            }
            if ($item['category'] === 'treasure' && $item['type'] !== 'treasure') {
                $issues[] = "Type '{$item['type']}' passt nicht zu category 'treasure'";
            }
            
            if (count($issues) > 0) {
                $brokenItems[] = [
                    ...$item,
                    'issues' => $issues
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'total_items' => count($items),
            'broken_items' => $brokenItems,
            'count' => count($brokenItems)
        ]);
        
    } else if ($action === 'fix') {
        // Korrigiere Items
        if (!isset($input['items']) || !is_array($input['items'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Items Array erforderlich']);
            exit();
        }
        
        $fixed = [];
        $errors = [];
        
        foreach ($input['items'] as $item) {
            try {
                $itemId = $item['id'] ?? null;
                $characterId = $item['character_id'] ?? 1;
                
                if (!$itemId) {
                    $errors[] = ['item' => $item, 'error' => 'Item ID fehlt'];
                    continue;
                }
                
                // Bestimme korrekten type
                $correctType = $item['type'] ?? 'equipment';
                
                // Korrigiere bekannte Fehler
                if ($correctType === 'tools') $correctType = 'tool';
                else if ($correctType === 'consumables') $correctType = 'consumable';
                
                // Wenn category vorhanden, verwende diese als Referenz
                $category = $item['category'] ?? 'equipment';
                if ($category === 'tools' && $correctType !== 'tool') $correctType = 'tool';
                else if ($category === 'consumables' && $correctType !== 'consumable') $correctType = 'consumable';
                else if ($category === 'treasure' && $correctType !== 'treasure') $correctType = 'treasure';
                else if (!$category) {
                    // Wenn keine category, setze basierend auf type
                    if ($correctType === 'tools') $correctType = 'tool';
                    else if ($correctType === 'consumables') $correctType = 'consumable';
                    else if (!in_array($correctType, ['weapon', 'armor', 'consumable', 'tool', 'treasure', 'equipment'])) {
                        $correctType = 'equipment';
                    }
                }
                
                // Stelle sicher, dass category gesetzt ist
                $correctCategory = $category;
                if (!$correctCategory || !in_array($correctCategory, ['equipment', 'consumables', 'tools', 'treasure'])) {
                    // Bestimme category basierend auf type
                    if ($correctType === 'tool') $correctCategory = 'tools';
                    else if ($correctType === 'consumable') $correctCategory = 'consumables';
                    else if ($correctType === 'treasure') $correctCategory = 'treasure';
                    else $correctCategory = 'equipment';
                }
                
                // Update Item
                $stmt = $db->prepare("
                    UPDATE items 
                    SET type = ?, category = ?
                    WHERE id = ? AND character_id = ?
                ");
                $stmt->execute([$correctType, $correctCategory, $itemId, $characterId]);
                
                if ($stmt->rowCount() > 0) {
                    $fixed[] = [
                        'id' => $itemId,
                        'name' => $item['name'],
                        'old_type' => $item['type'],
                        'new_type' => $correctType,
                        'old_category' => $item['category'] ?? 'FEHLT',
                        'new_category' => $correctCategory
                    ];
                } else {
                    $errors[] = ['item' => $item, 'error' => 'Item nicht gefunden oder nicht aktualisiert'];
                }
                
            } catch (PDOException $e) {
                $errors[] = ['item' => $item, 'error' => $e->getMessage()];
            }
        }
        
        echo json_encode([
            'success' => true,
            'fixed' => $fixed,
            'errors' => $errors,
            'fixed_count' => count($fixed),
            'error_count' => count($errors)
        ]);
        
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Unbekannte Action']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Datenbank-Fehler',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server-Fehler',
        'message' => $e->getMessage()
    ]);
}


