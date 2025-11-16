<?php
/**
 * Character Model
 * Verwaltet Charakter-Daten in der Datenbank
 */

class Character {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Lade Charakter mit allen zugehörigen Daten
     */
    public function getById($id) {
        // Basis-Charakterdaten
        $stmt = $this->db->prepare("SELECT * FROM characters WHERE id = ?");
        $stmt->execute([$id]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$character) {
            return null;
        }
        
        // Stats laden
        $character['stats'] = $this->getStats($id);
        
        // Equipment laden
        $character['equipment'] = $this->getEquipment($id);
        
        // Inventory laden
        $character['inventory'] = $this->getInventory($id);
        
        // Spell Slots laden
        $character['spellSlots'] = $this->getSpellSlots($id);
        
        // Money laden
        $character['money'] = $this->getMoney($id);
        
        // Notes laden
        $character['notes'] = $this->getNotes($id);
        
        // Death Saves laden
        $character['deathSaves'] = $this->getDeathSaves($id);
        
        // Skills laden
        $character['skills'] = $this->getSkills($id);
        
        return $character;
    }
    
    /**
     * Lade alle Charaktere (vereinfacht)
     */
    public function getAll() {
        $stmt = $this->db->prepare("SELECT id, name, level, alignment, portrait_mode FROM characters ORDER BY name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Erstelle neuen Charakter
     */
    public function create($data) {
        $this->db->beginTransaction();
        
        try {
            // Charakter erstellen
            $stmt = $this->db->prepare("
                INSERT INTO characters (name, level, alignment, portrait_mode, user_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['name'] ?? 'Neuer Charakter',
                $data['level'] ?? 1,
                $data['alignment'] ?? 'CN',
                $data['portrait_mode'] ?? 'civil',
                isset($data['user_id']) ? (int)$data['user_id'] : null
            ]);
            
            $characterId = $this->db->lastInsertId();
            
            // Stats initialisieren
            $this->initStats($characterId, $data['stats'] ?? []);
            
            // Equipment initialisieren (leer)
            $this->initEquipment($characterId);
            
            // Money initialisieren
            $this->initMoney($characterId, $data['money'] ?? []);
            
            // Spell Slots initialisieren
            $this->initSpellSlots($characterId, $data['level'] ?? 1);
            
            // Death Saves initialisieren
            $this->initDeathSaves($characterId);
            
            // Notes initialisieren
            if (isset($data['notes'])) {
                $this->saveNotes($characterId, $data['notes']);
            }
            
            // Items erstellen falls vorhanden
            if (isset($data['inventory'])) {
                $this->saveInventory($characterId, $data['inventory']);
            }
            
            $this->db->commit();
            return $characterId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Aktualisiere Charakter
     */
    public function update($id, $data) {
        $this->db->beginTransaction();
        
        try {
            // Basis-Daten aktualisieren
            if (isset($data['name']) || isset($data['level']) || isset($data['alignment']) || isset($data['portrait_mode']) || array_key_exists('user_id', $data)) {
                $updates = [];
                $params = [];
                
                if (isset($data['name'])) {
                    $updates[] = "name = ?";
                    $params[] = $data['name'];
                }
                if (isset($data['level'])) {
                    $updates[] = "level = ?";
                    $params[] = $data['level'];
                }
                // class/race sind im neuen Schema katalogbasiert -> hier nicht mehr direkt setzen
                if (isset($data['alignment'])) {
                    $updates[] = "alignment = ?";
                    $params[] = $data['alignment'];
                }
                if (isset($data['portrait_mode'])) {
                    $updates[] = "portrait_mode = ?";
                    $params[] = $data['portrait_mode'];
                }
                if (array_key_exists('user_id', $data)) {
                    $updates[] = "user_id = ?";
                    // Erlaube NULL zum Entkoppeln von einem Benutzer
                    $params[] = $data['user_id'] === null ? null : (int)$data['user_id'];
                }
                
                $params[] = $id;
                $stmt = $this->db->prepare("UPDATE characters SET " . implode(', ', $updates) . " WHERE id = ?");
                $stmt->execute($params);
            }
            
            // Stats aktualisieren
            if (isset($data['stats'])) {
                $this->updateStats($id, $data['stats']);
            }
            
            // Equipment aktualisieren
            if (isset($data['equipment'])) {
                $this->saveEquipment($id, $data['equipment']);
            }
            
            // Inventory aktualisieren
            if (isset($data['inventory'])) {
                $this->saveInventory($id, $data['inventory']);
            }
            
            // Spell Slots aktualisieren
            if (isset($data['spellSlots'])) {
                $this->saveSpellSlots($id, $data['spellSlots']);
            }
            
            // Money aktualisieren
            if (isset($data['money'])) {
                $this->updateMoney($id, $data['money']);
            }
            
            // Notes aktualisieren
            if (isset($data['notes'])) {
                $this->saveNotes($id, $data['notes']);
            }
            
            // Death Saves aktualisieren
            if (isset($data['deathSaves'])) {
                $this->updateDeathSaves($id, $data['deathSaves']);
            }
            
            // Skills aktualisieren
            if (isset($data['skills'])) {
                $this->saveSkills($id, $data['skills']);
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Lösche Charakter
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM characters WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
    
    // === Private Helper Methods ===
    
    private function getStats($characterId) {
        $stmt = $this->db->prepare("SELECT * FROM character_stats WHERE character_id = ?");
        $stmt->execute([$characterId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Umbenennen für JavaScript-Kompatibilität
        if ($stats) {
            $stats['int'] = $stats['int_stat'];  // JavaScript erwartet 'int'
            unset($stats['int_stat']);
        }
        
        return $stats ?: null;
    }
    
    private function updateStats($characterId, $stats) {
        // Prüfe ob Stats existieren
        $stmt = $this->db->prepare("SELECT id FROM character_stats WHERE character_id = ?");
        $stmt->execute([$characterId]);
        $exists = $stmt->fetch();
        
        if (!$exists) {
            // Stats existieren nicht, erstelle sie
            $this->initStats($characterId, $stats);
            return;
        }
        
        // Stats aktualisieren
        $stmt = $this->db->prepare("
            UPDATE character_stats SET
                str = ?, dex = ?, con = ?, int_stat = ?, wis = ?, cha = ?,
                current_hp = ?, max_hp = ?, temp_hp = ?,
                armor_class = ?, proficiency_bonus = ?,
                current_xp = ?, current_bi = ?, max_bi = ?,
                current_hd = ?, max_hd = ?
            WHERE character_id = ?
        ");
        
        $stmt->execute([
            $stats['str'] ?? 8,
            $stats['dex'] ?? 8,
            $stats['con'] ?? 8,
            $stats['int'] ?? $stats['int_stat'] ?? 8,  // JavaScript sendet 'int'
            $stats['wis'] ?? 8,
            $stats['cha'] ?? 8,
            $stats['current_hp'] ?? 0,
            $stats['max_hp'] ?? 0,
            $stats['temp_hp'] ?? 0,
            $stats['armor_class'] ?? 10,
            $stats['proficiency_bonus'] ?? 2,
            $stats['current_xp'] ?? 0,
            $stats['current_bi'] ?? 0,
            $stats['max_bi'] ?? 3,
            $stats['current_hd'] ?? 1,
            $stats['max_hd'] ?? 1,
            $characterId
        ]);
    }
    
    private function initStats($characterId, $stats) {
        $stmt = $this->db->prepare("
            INSERT INTO character_stats (
                character_id, str, dex, con, int_stat, wis, cha,
                current_hp, max_hp, temp_hp, armor_class, proficiency_bonus,
                current_xp, current_bi, max_bi, current_hd, max_hd
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $characterId,
            $stats['str'] ?? 8,
            $stats['dex'] ?? 8,
            $stats['con'] ?? 8,
            $stats['int'] ?? $stats['int_stat'] ?? 8,
            $stats['wis'] ?? 8,
            $stats['cha'] ?? 8,
            $stats['current_hp'] ?? 9,
            $stats['max_hp'] ?? 9,
            $stats['temp_hp'] ?? 0,
            $stats['armor_class'] ?? 15,
            $stats['proficiency_bonus'] ?? 2,
            $stats['current_xp'] ?? 0,
            $stats['current_bi'] ?? 2,
            $stats['max_bi'] ?? 3,
            $stats['current_hd'] ?? 1,
            $stats['max_hd'] ?? 1
        ]);
    }
    
    private function getEquipment($characterId) {
        $stmt = $this->db->prepare("
            SELECT es.slot_type, i.*
            FROM equipment_slots es
            LEFT JOIN items i ON es.item_id = i.id
            WHERE es.character_id = ?
        ");
        $stmt->execute([$characterId]);
        
        $equipment = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['id']) {  // Nur wenn Item vorhanden
                $equipment[$row['slot_type']] = $this->formatItem($row);
            } else {
                $equipment[$row['slot_type']] = null;
            }
        }
        
        return $equipment;
    }
    
    private function saveEquipment($characterId, $equipment) {
        foreach (['armor', 'mainhand', 'offhand'] as $slotType) {
            $itemData = $equipment[$slotType] ?? null;
            $itemId = null;
            
            if ($itemData) {
                // Wenn vollständiges Item-Objekt gesendet wurde
                if (is_array($itemData) && isset($itemData['name'])) {
                    // Item erstellen oder finden
                    $itemId = $this->findOrCreateItem($characterId, $itemData);
                } 
                // Wenn nur item_id gesendet wurde
                elseif (is_numeric($itemData)) {
                    $itemId = (int)$itemData;
                }
                
                // Prüfe ob Item existiert und gehört zu diesem Character
                if ($itemId) {
                    $stmt = $this->db->prepare("SELECT id FROM items WHERE id = ? AND character_id = ?");
                    $stmt->execute([$itemId, $characterId]);
                    if (!$stmt->fetch()) {
                        $itemId = null;  // Item existiert nicht für diesen Character
                    }
                }
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO equipment_slots (character_id, slot_type, item_id)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE item_id = ?
            ");
            $stmt->execute([$characterId, $slotType, $itemId, $itemId]);
        }
    }
    
    /**
     * Finde oder erstelle Item
     */
    private function findOrCreateItem($characterId, $itemData) {
        // Versuche zuerst vorhandenes Item zu finden
        if (isset($itemData['id']) && $itemData['id']) {
            $stmt = $this->db->prepare("SELECT id FROM items WHERE id = ? AND character_id = ?");
            $stmt->execute([$itemData['id'], $characterId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                // Item aktualisieren
                $this->updateItem($itemData['id'], $itemData);
                return (int)$itemData['id'];
            }
        }
        
        // Versuche Item nach Name zu finden (falls kein ID vorhanden)
        if (isset($itemData['name'])) {
            $stmt = $this->db->prepare("SELECT id FROM items WHERE name = ? AND character_id = ? AND type = ? LIMIT 1");
            $itemType = $itemData['type'] ?? 'equipment';
            $stmt->execute([$itemData['name'], $characterId, $itemType]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                // Item aktualisieren
                $this->updateItem($row['id'], $itemData);
                return (int)$row['id'];
            }
        }
        
        // Neues Item erstellen
        $category = $itemData['category'] ?? 'equipment';
        $this->createItem($characterId, $category, $itemData);
        return (int)$this->db->lastInsertId();
    }
    
    private function initEquipment($characterId) {
        $stmt = $this->db->prepare("
            INSERT INTO equipment_slots (character_id, slot_type, item_id)
            VALUES (?, 'armor', NULL), (?, 'mainhand', NULL), (?, 'offhand', NULL)
            ON DUPLICATE KEY UPDATE item_id = item_id
        ");
        $stmt->execute([$characterId, $characterId, $characterId]);
    }
    
    private function getInventory($characterId) {
        // Items die nicht ausgerüstet sind
        $stmt = $this->db->prepare("
            SELECT i.*
            FROM items i
            LEFT JOIN equipment_slots es ON i.id = es.item_id AND es.character_id = ?
            WHERE i.character_id = ? AND es.item_id IS NULL
            ORDER BY i.category, i.name
        ");
        $stmt->execute([$characterId, $characterId]);
        
        // Rückgabe als flaches Array für Frontend (wird in API.js kategorisiert)
        $inventory = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $inventory[] = $this->formatItem($row);
        }
        
        return $inventory;
    }
    
    private function saveInventory($characterId, $inventory) {
        // Sammle alle Item-IDs die gespeichert werden sollen
        $itemIdsToKeep = [];
        $createdItems = 0;
        $updatedItems = 0;
        $errors = [];
        
        // Inventory kann als kategorisiertes Array oder flaches Array kommen
        if (isset($inventory['equipment']) || isset($inventory['consumables'])) {
            // Kategorisiertes Format: { equipment: [...], consumables: [...] }
            foreach ($inventory as $category => $items) {
                if (is_array($items)) {
                    foreach ($items as $item) {
                        try {
                            $itemId = $item['id'] ?? null;
                            
                            // Stelle sicher, dass category gesetzt ist
                            if (!isset($item['category'])) {
                                $item['category'] = $category;
                            }
                            
                            if ($itemId) {
                                // Prüfe ob Item existiert und gehört zu diesem Character
                                $stmt = $this->db->prepare("SELECT id FROM items WHERE id = ? AND character_id = ?");
                                $stmt->execute([$itemId, $characterId]);
                                if ($stmt->fetch()) {
                                    // Bestehendes Item aktualisieren
                                    $this->updateItem($itemId, $item);
                                    $itemIdsToKeep[] = $itemId;
                                    $updatedItems++;
                                } else {
                                    // Neues Item erstellen (mit vorhandener ID - sollte nicht passieren, aber sicherheitshalber)
                                    $newItemId = $this->createItem($characterId, $category, $item);
                                    if ($newItemId) {
                                        $itemIdsToKeep[] = $newItemId;
                                        $createdItems++;
                                    }
                                }
                            } else {
                                // Neues Item erstellen
                                $newItemId = $this->createItem($characterId, $category, $item);
                                if ($newItemId) {
                                    $itemIdsToKeep[] = $newItemId;
                                    $createdItems++;
                                }
                            }
                        } catch (Exception $e) {
                            $errors[] = "Fehler bei Item '" . ($item['name'] ?? 'Unbekannt') . "': " . $e->getMessage();
                            error_log("FEHLER beim Speichern von Item: " . $e->getMessage() . " | Item: " . json_encode($item));
                        }
                    }
                }
            }
        } else {
            // Flaches Array Format: [...]
            foreach ($inventory as $item) {
                try {
                    $category = $item['category'] ?? 'equipment';
                    $itemId = $item['id'] ?? null;
                    
                    // Stelle sicher, dass category gesetzt ist
                    if (!isset($item['category'])) {
                        $item['category'] = $category;
                    }
                    
                    if ($itemId) {
                        // Prüfe ob Item existiert und gehört zu diesem Character
                        $stmt = $this->db->prepare("SELECT id FROM items WHERE id = ? AND character_id = ?");
                        $stmt->execute([$itemId, $characterId]);
                        if ($stmt->fetch()) {
                            // Bestehendes Item aktualisieren
                            $this->updateItem($itemId, $item);
                            $itemIdsToKeep[] = $itemId;
                            $updatedItems++;
                        } else {
                            // Neues Item erstellen
                            $newItemId = $this->createItem($characterId, $category, $item);
                            if ($newItemId) {
                                $itemIdsToKeep[] = $newItemId;
                                $createdItems++;
                            }
                        }
                    } else {
                        // Neues Item erstellen
                        $newItemId = $this->createItem($characterId, $category, $item);
                        if ($newItemId) {
                            $itemIdsToKeep[] = $newItemId;
                            $createdItems++;
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = "Fehler bei Item '" . ($item['name'] ?? 'Unbekannt') . "': " . $e->getMessage();
                    error_log("FEHLER beim Speichern von Item: " . $e->getMessage() . " | Item: " . json_encode($item));
                }
            }
        }
        
        // Log für Debugging
        error_log("saveInventory: Character ID=$characterId, Erstellt=$createdItems, Aktualisiert=$updatedItems, Zu behalten=" . count($itemIdsToKeep));
        if (count($errors) > 0) {
            error_log("FEHLER beim Speichern: " . implode("; ", $errors));
        }
        
        // Lösche nur Items die nicht ausgerüstet sind UND nicht im neuen Inventar sind
        // WICHTIG: MySQL erlaubt keine Subqueries in NOT IN in DELETE-Statements direkt
        // Daher hole zuerst die ausgerüsteten Item-IDs
        $stmt = $this->db->prepare("SELECT DISTINCT item_id FROM equipment_slots WHERE character_id = ? AND item_id IS NOT NULL");
        $stmt->execute([$characterId]);
        $equippedItemIds = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $equippedItemIds[] = (int)$row['item_id'];
        }
        
        // Kombiniere ausgerüstete Items und Items die behalten werden sollen
        $allItemsToKeep = array_unique(array_merge($equippedItemIds, $itemIdsToKeep));
        
        if (count($allItemsToKeep) > 0) {
            $placeholders = implode(',', array_fill(0, count($allItemsToKeep), '?'));
            $stmt = $this->db->prepare("
                DELETE FROM items 
                WHERE character_id = ? 
                AND id NOT IN ($placeholders)
            ");
            $params = array_merge([$characterId], $allItemsToKeep);
            $stmt->execute($params);
        } else {
            // Wenn keine Items zu behalten, lösche alle Items für diesen Character
            // (sollte eigentlich nicht passieren, aber sicherheitshalber)
            $stmt = $this->db->prepare("DELETE FROM items WHERE character_id = ?");
            $stmt->execute([$characterId]);
        }
    }
    
    private function createItem($characterId, $category, $itemData) {
        // Validiere dass name vorhanden ist
        if (empty($itemData['name'])) {
            error_log("FEHLER: Item ohne Name kann nicht erstellt werden. Category: $category, Data: " . json_encode($itemData));
            throw new Exception("Item-Name fehlt");
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO items (
                character_id, name, type, category,
                damage, to_hit, range_property, combat_type, hands, light, offhand_damage,
                ac, dex_bonus, max_dex_bonus,
                value, quantity, properties
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // Mappe category zu type falls type nicht gesetzt oder falsch
        $itemType = $itemData['type'] ?? 'equipment';
        // Korrigiere häufige Fehler: 'tools' -> 'tool', 'consumables' -> 'consumable'
        if ($itemType === 'tools') $itemType = 'tool';
        if ($itemType === 'consumables') $itemType = 'consumable';
        // Wenn type immer noch nicht passt, verwende category als Fallback
        if (!in_array($itemType, ['weapon', 'armor', 'consumable', 'tool', 'treasure', 'equipment'])) {
            if ($category === 'tools') $itemType = 'tool';
            elseif ($category === 'consumables') $itemType = 'consumable';
            elseif ($category === 'treasure') $itemType = 'treasure';
            else $itemType = 'equipment';
        }
        
        try {
            $stmt->execute([
                $characterId,
                $itemData['name'],
                $itemType,
                $category,
                $itemData['damage'] ?? null,
                $itemData['toHit'] ?? null,
                $itemData['range'] ?? null,
                $itemData['combatType'] ?? null,
                $itemData['hands'] ?? null,
                (isset($itemData['light']) && $itemData['light'] !== '' && $itemData['light'] !== null && $itemData['light'] !== false && $itemData['light'] !== 'false') ? 1 : 0,
                $itemData['offhandDamage'] ?? null,
                $itemData['ac'] ?? null,
                (isset($itemData['dexBonus']) && $itemData['dexBonus'] !== '' && $itemData['dexBonus'] !== null && $itemData['dexBonus'] !== false && $itemData['dexBonus'] !== 'false') ? 1 : 0,
                $itemData['maxDexBonus'] ?? null,
                $itemData['value'] ?? null,
                $itemData['quantity'] ?? 1,
                isset($itemData['properties']) ? json_encode($itemData['properties']) : null
            ]);
            
            $newId = (int)$this->db->lastInsertId();
            
            if ($newId <= 0) {
                error_log("FEHLER: Item erstellt aber keine ID erhalten. Name: " . $itemData['name'] . ", Category: $category");
                throw new Exception("Item konnte nicht erstellt werden - keine ID erhalten");
            }
            
            // Log für Debugging
            error_log("Item erstellt: ID=$newId, Name=" . $itemData['name'] . ", Type=$itemType, Category=$category");
            
            // Gib die neue Item-ID zurück
            return $newId;
        } catch (PDOException $e) {
            error_log("FEHLER beim Erstellen von Item: " . $e->getMessage() . " | Name: " . ($itemData['name'] ?? 'N/A') . " | Type: $itemType | Category: $category");
            throw new Exception("Fehler beim Erstellen des Items: " . $e->getMessage());
        }
    }
    
    private function updateItem($itemId, $itemData) {
        $updates = [];
        $params = [];
        
        if (isset($itemData['name'])) {
            $updates[] = "name = ?";
            $params[] = $itemData['name'];
        }
        if (isset($itemData['type'])) {
            $updates[] = "type = ?";
            $params[] = $itemData['type'];
        }
        if (isset($itemData['category'])) {
            $updates[] = "category = ?";
            $params[] = $itemData['category'];
        }
        if (isset($itemData['quantity'])) {
            $updates[] = "quantity = ?";
            $params[] = $itemData['quantity'];
        }
        if (isset($itemData['damage'])) {
            $updates[] = "damage = ?";
            $params[] = $itemData['damage'];
        }
        if (isset($itemData['toHit'])) {
            $updates[] = "to_hit = ?";
            $params[] = $itemData['toHit'];
        }
        if (isset($itemData['range'])) {
            $updates[] = "range_property = ?";
            $params[] = $itemData['range'];
        }
        if (isset($itemData['combatType'])) {
            $updates[] = "combat_type = ?";
            $params[] = $itemData['combatType'];
        }
        if (isset($itemData['hands'])) {
            $updates[] = "hands = ?";
            $params[] = $itemData['hands'];
        }
        if (isset($itemData['light'])) {
            $updates[] = "light = ?";
            // Konvertiere leere Strings, null, undefined, false zu 0, sonst zu 1
            $lightValue = $itemData['light'];
            $params[] = ($lightValue === '' || $lightValue === null || $lightValue === false || $lightValue === 'false' || $lightValue === 0 || $lightValue === '0') ? 0 : 1;
        }
        if (isset($itemData['offhandDamage'])) {
            $updates[] = "offhand_damage = ?";
            $params[] = $itemData['offhandDamage'];
        }
        if (isset($itemData['ac'])) {
            $updates[] = "ac = ?";
            $params[] = $itemData['ac'];
        }
        if (isset($itemData['dexBonus'])) {
            $updates[] = "dex_bonus = ?";
            // Konvertiere leere Strings, null, undefined, false zu 0, sonst zu 1
            $dexBonusValue = $itemData['dexBonus'];
            $params[] = ($dexBonusValue === '' || $dexBonusValue === null || $dexBonusValue === false || $dexBonusValue === 'false' || $dexBonusValue === 0 || $dexBonusValue === '0') ? 0 : 1;
        }
        if (isset($itemData['maxDexBonus'])) {
            $updates[] = "max_dex_bonus = ?";
            $params[] = $itemData['maxDexBonus'];
        }
        if (isset($itemData['value'])) {
            $updates[] = "value = ?";
            $params[] = $itemData['value'];
        }
        if (isset($itemData['properties'])) {
            $updates[] = "properties = ?";
            $params[] = json_encode($itemData['properties']);
        }
        
        if (count($updates) > 0) {
            $params[] = $itemId;
            $stmt = $this->db->prepare("UPDATE items SET " . implode(', ', $updates) . " WHERE id = ?");
            $stmt->execute($params);
        }
    }
    
    private function formatItem($row) {
        return [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'type' => $row['type'],
            'category' => $row['category'],
            'damage' => $row['damage'],
            'toHit' => $row['to_hit'],
            'range' => $row['range_property'],
            'combatType' => $row['combat_type'],
            'hands' => $row['hands'],
            'light' => (bool)$row['light'],
            'offhandDamage' => $row['offhand_damage'],
            'ac' => $row['ac'] ? (int)$row['ac'] : null,
            'dexBonus' => (bool)$row['dex_bonus'],
            'maxDexBonus' => $row['max_dex_bonus'] ? (int)$row['max_dex_bonus'] : null,
            'value' => $row['value'],
            'quantity' => (int)$row['quantity'],
            'properties' => $row['properties'] ? json_decode($row['properties'], true) : null
        ];
    }
    
    private function getSpellSlots($characterId) {
        $stmt = $this->db->prepare("
            SELECT slot_level, slot_number, used
            FROM spell_slots
            WHERE character_id = ?
            ORDER BY slot_level, slot_number
        ");
        $stmt->execute([$characterId]);
        
        $slots = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $level = (int)$row['slot_level'];
            if (!isset($slots[$level])) {
                $slots[$level] = [];
            }
            $slots[$level][] = (bool)$row['used'];
        }
        
        // Für JavaScript-Kompatibilität: Array von Booleans für Level 1
        return isset($slots[1]) ? $slots[1] : [];
    }
    
    private function saveSpellSlots($characterId, $slots) {
        // Erwartet Array von Booleans [false, false] für Level 1
        foreach ($slots as $index => $used) {
            $slotNumber = $index + 1;
            $stmt = $this->db->prepare("
                INSERT INTO spell_slots (character_id, slot_level, slot_number, used)
                VALUES (?, 1, ?, ?)
                ON DUPLICATE KEY UPDATE used = ?
            ");
            $stmt->execute([$characterId, $slotNumber, $used ? 1 : 0, $used ? 1 : 0]);
        }
    }
    
    private function initSpellSlots($characterId, $level) {
        // Level 1 = 2 Slots
        $numSlots = $level >= 1 ? 2 : 0;
        
        for ($i = 1; $i <= $numSlots; $i++) {
            $stmt = $this->db->prepare("
                INSERT INTO spell_slots (character_id, slot_level, slot_number, used)
                VALUES (?, 1, ?, 0)
                ON DUPLICATE KEY UPDATE used = 0
            ");
            $stmt->execute([$characterId, $i]);
        }
    }
    
    private function getMoney($characterId) {
        $stmt = $this->db->prepare("SELECT gold, silver, copper FROM money WHERE character_id = ?");
        $stmt->execute([$characterId]);
        $money = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $money ?: ['gold' => 0, 'silver' => 0, 'copper' => 0];
    }
    
    private function updateMoney($characterId, $money) {
        $stmt = $this->db->prepare("
            INSERT INTO money (character_id, gold, silver, copper)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE gold = ?, silver = ?, copper = ?
        ");
        $stmt->execute([
            $characterId,
            $money['gold'] ?? 0,
            $money['silver'] ?? 0,
            $money['copper'] ?? 0,
            $money['gold'] ?? 0,
            $money['silver'] ?? 0,
            $money['copper'] ?? 0
        ]);
    }
    
    private function initMoney($characterId, $money) {
        $this->updateMoney($characterId, $money ?: []);
    }
    
    private function getNotes($characterId) {
        $stmt = $this->db->prepare("SELECT type, content FROM notes WHERE character_id = ?");
        $stmt->execute([$characterId]);
        
        $notes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $notes[$row['type']] = $row['content'];
        }
        
        return $notes;
    }
    
    private function saveNotes($characterId, $notes) {
        foreach (['adventure', 'character', 'performance'] as $type) {
            $content = $notes[$type] ?? '';
            
            $stmt = $this->db->prepare("
                INSERT INTO notes (character_id, type, content)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE content = ?
            ");
            $stmt->execute([$characterId, $type, $content, $content]);
        }
    }
    
    private function getDeathSaves($characterId) {
        $stmt = $this->db->prepare("SELECT successes, failures FROM death_saves WHERE character_id = ?");
        $stmt->execute([$characterId]);
        $saves = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$saves) {
            return ['successes' => [], 'failures' => []];
        }
        
        // Konvertiere zu Arrays (1-3)
        return [
            'successes' => range(1, $saves['successes']),
            'failures' => range(1, $saves['failures'])
        ];
    }
    
    private function updateDeathSaves($characterId, $saves) {
        $successes = is_array($saves['successes']) ? count($saves['successes']) : ($saves['successes'] ?? 0);
        $failures = is_array($saves['failures']) ? count($saves['failures']) : ($saves['failures'] ?? 0);
        
        $stmt = $this->db->prepare("
            INSERT INTO death_saves (character_id, successes, failures)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE successes = ?, failures = ?
        ");
        $stmt->execute([$characterId, $successes, $failures, $successes, $failures]);
    }
    
    private function initDeathSaves($characterId) {
        $this->updateDeathSaves($characterId, ['successes' => [], 'failures' => []]);
    }
    
    private function getSkills($characterId) {
        $stmt = $this->db->prepare("SELECT * FROM skills WHERE character_id = ?");
        $stmt->execute([$characterId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function saveSkills($characterId, $skills) {
        // Lösche alle Skills für diesen Character
        $stmt = $this->db->prepare("DELETE FROM skills WHERE character_id = ?");
        $stmt->execute([$characterId]);
        
        // Füge neue Skills ein
        $stmt = $this->db->prepare("
            INSERT INTO skills (character_id, skill_name, proficient, expertise, bonus)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($skills as $skill) {
            $stmt->execute([
                $characterId,
                $skill['skill_name'],
                $skill['proficient'] ?? 0,
                $skill['expertise'] ?? 0,
                $skill['bonus'] ?? 0
            ]);
        }
    }
}

