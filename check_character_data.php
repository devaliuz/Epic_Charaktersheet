<?php
/**
 * Script zum Abrufen aller Character-Daten über die API
 */

$characterId = 1; // Bar-iton

echo "=== ALLE DATEN FÜR BAR-ITON (Character ID: $characterId) ===\n\n";

// Lade Character-Daten
$url = "http://localhost/backend/api/characters.php?id=$characterId";
$response = @file_get_contents($url);

if ($response === false) {
    echo "FEHLER: API nicht erreichbar. Stelle sicher, dass der Server läuft.\n";
    echo "Versuche: http://localhost/backend/api/characters.php?id=$characterId\n";
    exit(1);
}

$character = json_decode($response, true);

if (!$character) {
    echo "FEHLER: Keine Daten erhalten oder ungültiges JSON.\n";
    echo "Response: " . substr($response, 0, 200) . "\n";
    exit(1);
}

// Basis-Informationen
echo "📋 BASIS-INFORMATIONEN:\n";
echo "  ID: " . ($character['id'] ?? 'N/A') . "\n";
echo "  Name: " . ($character['name'] ?? 'N/A') . "\n";
echo "  Level: " . ($character['level'] ?? 'N/A') . "\n";
echo "  Klasse: " . ($character['class'] ?? 'N/A') . "\n";
echo "  Rasse: " . ($character['race'] ?? 'N/A') . "\n";
echo "  Hintergrund: " . ($character['background'] ?? 'N/A') . "\n";
echo "  Ausrichtung: " . ($character['alignment'] ?? 'N/A') . "\n";
echo "  Portrait-Modus: " . ($character['portrait_mode'] ?? 'N/A') . "\n";
echo "\n";

// Stats
if (isset($character['stats'])) {
    $stats = $character['stats'];
    echo "📊 STATS:\n";
    echo "  Attribute:\n";
    echo "    STR: " . ($stats['str'] ?? 0) . "\n";
    echo "    DEX: " . ($stats['dex'] ?? 0) . "\n";
    echo "    CON: " . ($stats['con'] ?? 0) . "\n";
    echo "    INT: " . ($stats['int'] ?? $stats['int_stat'] ?? 0) . "\n";
    echo "    WIS: " . ($stats['wis'] ?? 0) . "\n";
    echo "    CHA: " . ($stats['cha'] ?? 0) . "\n";
    echo "  HP: " . ($stats['current_hp'] ?? 0) . "/" . ($stats['max_hp'] ?? 0) . " (Temp: " . ($stats['temp_hp'] ?? 0) . ")\n";
    echo "  AC: " . ($stats['armor_class'] ?? 0) . "\n";
    echo "  Geübtheitsbonus: " . ($stats['proficiency_bonus'] ?? 0) . "\n";
    echo "  XP: " . ($stats['current_xp'] ?? 0) . "\n";
    echo "  Bardische Inspiration: " . ($stats['current_bi'] ?? 0) . "/" . ($stats['max_bi'] ?? 0) . "\n";
    echo "  Hit Dice: " . ($stats['current_hd'] ?? 0) . "/" . ($stats['max_hd'] ?? 0) . "\n";
    echo "\n";
}

// Equipment
if (isset($character['equipment'])) {
    $equip = $character['equipment'];
    echo "⚔️ AUSGERÜSTETE ITEMS:\n";
    if (isset($equip['armor']) && $equip['armor']) {
        echo "  Rüstung: " . ($equip['armor']['name'] ?? 'Unbekannt') . " (ID: " . ($equip['armor']['id'] ?? 'N/A') . ")\n";
    } else {
        echo "  Rüstung: Keine\n";
    }
    if (isset($equip['mainhand']) && $equip['mainhand']) {
        echo "  Haupthand: " . ($equip['mainhand']['name'] ?? 'Unbekannt') . " (ID: " . ($equip['mainhand']['id'] ?? 'N/A') . ")\n";
    } else {
        echo "  Haupthand: Keine\n";
    }
    if (isset($equip['offhand']) && $equip['offhand']) {
        echo "  Nebenhand: " . ($equip['offhand']['name'] ?? 'Unbekannt') . " (ID: " . ($equip['offhand']['id'] ?? 'N/A') . ")\n";
    } else {
        echo "  Nebenhand: Keine\n";
    }
    echo "\n";
}

// Inventory
if (isset($character['inventory'])) {
    $inv = $character['inventory'];
    $items = [];
    
    if (is_array($inv)) {
        $items = $inv;
    } else {
        $items = array_merge(
            $inv['equipment'] ?? [],
            $inv['consumables'] ?? [],
            $inv['tools'] ?? [],
            $inv['treasure'] ?? []
        );
    }
    
    echo "🎒 INVENTAR (" . count($items) . " Items):\n";
    if (count($items) > 0) {
        foreach ($items as $item) {
            $name = $item['name'] ?? 'Unbekannt';
            $type = $item['type'] ?? 'N/A';
            $category = $item['category'] ?? 'N/A';
            $id = $item['id'] ?? 'N/A';
            echo "  - $name ($type/$category, ID: $id)\n";
        }
    } else {
        echo "  Keine Items\n";
    }
    echo "\n";
}

// Money
if (isset($character['money'])) {
    $money = $character['money'];
    echo "💰 GELD:\n";
    echo "  Gold: " . ($money['gold'] ?? 0) . "\n";
    echo "  Silber: " . ($money['silver'] ?? 0) . "\n";
    echo "  Kupfer: " . ($money['copper'] ?? 0) . "\n";
    echo "\n";
}

// Spell Slots
if (isset($character['spellSlots'])) {
    $slots = $character['spellSlots'];
    $used = count(array_filter($slots, function($s) { return $s === true; }));
    echo "✨ ZAUBERPLÄTZE:\n";
    echo "  Gesamt: " . count($slots) . "\n";
    echo "  Benutzt: $used\n";
    echo "  Verfügbar: " . (count($slots) - $used) . "\n";
    echo "\n";
}

// Notes (Notizen) - DAS IST WICHTIG!
if (isset($character['notes'])) {
    $notes = $character['notes'];
    echo "📝 NOTIZEN:\n";
    
    if (isset($notes['adventure']) && !empty($notes['adventure'])) {
        echo "  Abenteuer-Notizen:\n";
        echo "    " . str_replace("\n", "\n    ", $notes['adventure']) . "\n";
    } else {
        echo "  Abenteuer-Notizen: Keine\n";
    }
    echo "\n";
    
    if (isset($notes['character']) && !empty($notes['character'])) {
        echo "  Charakter-Notizen:\n";
        echo "    " . str_replace("\n", "\n    ", $notes['character']) . "\n";
    } else {
        echo "  Charakter-Notizen: Keine\n";
    }
    echo "\n";
    
    if (isset($notes['performance']) && !empty($notes['performance'])) {
        echo "  Auftritts-Notizen:\n";
        echo "    " . str_replace("\n", "\n    ", $notes['performance']) . "\n";
    } else {
        echo "  Auftritts-Notizen: Keine\n";
    }
    echo "\n";
} else {
    echo "📝 NOTIZEN: Keine Notizen gefunden\n\n";
}

// Skills
if (isset($character['skills']) && is_array($character['skills'])) {
    echo "🎯 SKILLS:\n";
    foreach ($character['skills'] as $skill) {
        $name = $skill['skill_name'] ?? 'Unbekannt';
        $prof = ($skill['proficient'] ?? 0) ? 'Ja' : 'Nein';
        $exp = ($skill['expertise'] ?? 0) ? 'Ja' : 'Nein';
        $bonus = $skill['bonus'] ?? 0;
        echo "  $name: Proficient=$prof, Expertise=$exp, Bonus=$bonus\n";
    }
    echo "\n";
}

// Death Saves
if (isset($character['deathSaves'])) {
    $ds = $character['deathSaves'];
    echo "💀 TODESRETTUNGSWÜRFE:\n";
    $successes = is_array($ds['successes'] ?? []) ? count($ds['successes']) : ($ds['successes'] ?? 0);
    $failures = is_array($ds['failures'] ?? []) ? count($ds['failures']) : ($ds['failures'] ?? 0);
    echo "  Erfolge: $successes/3\n";
    echo "  Fehlschläge: $failures/3\n";
    echo "\n";
}

echo "=== ENDE DER DATEN ===\n";


