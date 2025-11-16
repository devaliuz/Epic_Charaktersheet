/**
 * API Client für Backend-Kommunikation
 * Ersetzt localStorage durch REST API Calls
 */

class APIClient {
    constructor(baseURL = '/backend/api') {
        this.baseURL = baseURL;
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;
        const config = {
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        };

        if (config.body && typeof config.body === 'object') {
            config.body = JSON.stringify(config.body);
        }

        try {
            const response = await fetch(url, config);
            
            // Prüfe ob Response JSON ist
            const contentType = response.headers.get('content-type');
            let data;
            
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                data = { error: await response.text() };
            }

            if (!response.ok) {
                throw new Error(data.error || data.message || `HTTP ${response.status}`);
            }

            return data;
        } catch (error) {
            console.error('API Fehler:', error);
            throw error;
        }
    }

    // Character API
    async getCharacter(id) {
        return this.request(`/characters.php?id=${id}`);
    }

    async getAllCharacters() {
        return this.request('/characters.php');
    }

    async createCharacter(data) {
        return this.request('/characters.php', {
            method: 'POST',
            body: data
        });
    }

    async updateCharacter(id, data) {
        return this.request(`/characters.php?id=${id}`, {
            method: 'PUT',
            body: data
        });
    }

    async deleteCharacter(id) {
        return this.request(`/characters.php?id=${id}`, {
            method: 'DELETE'
        });
    }
}

// Globale API-Instanz
const apiClient = new APIClient();

// Mache apiClient und CharacterDataManagerAPI global verfügbar
if (typeof window !== 'undefined') {
    window.apiClient = apiClient;
    window.APIClient = APIClient;
    // CharacterDataManagerAPI wird nach der Klassendefinition gesetzt
}

// Character Data Manager - angepasst für API mit localStorage Fallback
class CharacterDataManagerAPI {
    constructor(characterId = 1) {
        this.characterId = characterId;
        this.api = apiClient;
        this.useLocalStorage = false; // Fallback Flag
    }
    
    async save() {
        // Versuche zuerst API
        if (!this.useLocalStorage) {
            try {
                const data = this.collectData();
                console.log('Speichere Daten für Character ID:', this.characterId, {
                    name: data.name,
                    level: data.level,
                    equipment: Object.keys(data.equipment || {}).filter(k => data.equipment[k] !== null).length,
                    inventory: {
                        equipment: data.inventory?.equipment?.length || 0,
                        consumables: data.inventory?.consumables?.length || 0,
                        tools: data.inventory?.tools?.length || 0,
                        treasure: data.inventory?.treasure?.length || 0
                    },
                    spellSlots: data.spellSlots?.length || 0,
                    currentBI: data.stats?.current_bi,
                    currentHP: data.stats?.current_hp
                });
                const result = await this.api.updateCharacter(this.characterId, data);
                console.log('✓ Daten via API gespeichert:', result);
                return true;
            } catch (error) {
                console.error('❌ API Speichern fehlgeschlagen:', error);
                console.warn('Wechsle zu localStorage:', error);
                this.useLocalStorage = true; // Fallback aktivieren
            }
        }
        
        // Fallback zu localStorage
        if (typeof characterDataManager !== 'undefined') {
            characterDataManager.save();
            console.log('Daten via localStorage gespeichert (Fallback)');
            return true;
        }
        
        console.error('❌ Kein Speicher-Mechanismus verfügbar!');
        return false;
    }

    async load() {
        // Versuche zuerst neuesten Screenshot zu laden
        if (!this.useLocalStorage && window.sessionManager) {
            try {
                const snapshotLoaded = await window.loadLatestSnapshot(this.characterId);
                if (snapshotLoaded) {
                    console.log('✓ Character-Daten aus neuestem Screenshot geladen');
                    return true;
                }
            } catch (error) {
                console.warn('Fehler beim Laden des Screenshots, verwende normale Character-Daten:', error);
            }
        }
        
        // Versuche zuerst API
        if (!this.useLocalStorage) {
            try {
                console.log('Lade Character ID:', this.characterId);
                const character = await this.api.getCharacter(this.characterId);
                console.log('Character-Daten erhalten:', {
                    name: character.name,
                    level: character.level,
                    hasStats: !!character.stats,
                    hasEquipment: !!character.equipment,
                    hasInventory: !!character.inventory,
                    inventoryCount: Array.isArray(character.inventory) ? character.inventory.length : Object.keys(character.inventory || {}).length,
                    spellSlots: character.spellSlots?.length || 0
                });
                this.applyCharacterData(character);
                console.log('✓ Daten via API geladen und angewendet');
                return true;
            } catch (error) {
                console.error('❌ API Laden fehlgeschlagen:', error);
                console.warn('Wechsle zu localStorage:', error);
                this.useLocalStorage = true; // Fallback aktivieren
            }
        }
        
        // Fallback zu localStorage
        if (typeof characterDataManager !== 'undefined') {
            const result = characterDataManager.load();
            console.log('Daten via localStorage geladen (Fallback)');
            return result;
        }
        
        console.error('❌ Kein Lade-Mechanismus verfügbar!');
        return false;
    }

    collectData() {
        const spellSlotManager = new SpellSlotManager();
        const equipmentManager = new EquipmentManager();
        const inventoryManager = new InventoryManager();
        
        return {
            name: document.getElementById('characterName')?.innerText || 'Bar-iton',
            level: parseInt(document.getElementById('charLevel')?.innerText || '1'),
            
            stats: {
                str: parseInt(document.getElementById('statSTR')?.innerText || '8'),
                dex: parseInt(document.getElementById('statDEX')?.innerText || '15'),
                con: parseInt(document.getElementById('statCON')?.innerText || '12'),
                int: parseInt(document.getElementById('statINT')?.innerText || '13'),  // JavaScript verwendet 'int'
                wis: parseInt(document.getElementById('statWIS')?.innerText || '10'),
                cha: parseInt(document.getElementById('statCHA')?.innerText || '17'),
                current_hp: parseInt(document.getElementById('currentHP')?.innerText || '9'),
                max_hp: parseInt(document.getElementById('maxHP')?.innerText || '9'),
                temp_hp: parseInt(document.getElementById('tempHP')?.innerText || '0'),
                armor_class: parseInt(document.getElementById('armorClassDisplay')?.innerText || '15'),
                proficiency_bonus: parseInt(document.getElementById('profBonus')?.innerText || '2'),
                current_xp: parseInt(document.getElementById('currentXP')?.innerText || '0'),
                current_bi: parseInt(document.getElementById('currentBI')?.innerText || '2'),
                max_bi: 3,
                current_hd: document.getElementById('hdTracker') ? parseInt(document.getElementById('hdTracker').innerText) : 1,
                max_hd: document.getElementById('maxHD') ? parseInt(document.getElementById('maxHD').innerText) : 1
            },
            
            equipment: {
                armor: equipmentManager.getSlotData('armorSlot') || null,
                mainhand: equipmentManager.getSlotData('mainHandSlot') || null,
                offhand: equipmentManager.getSlotData('offHandSlot') || null
            },
            
            inventory: this.collectInventory(inventoryManager),
            
            spellSlots: spellSlotManager.getSlots(),
            
            money: {
                gold: parseInt(document.getElementById('gpTracker')?.innerText || '0'),
                silver: parseInt(document.getElementById('spTracker')?.innerText || '0'),
                copper: parseInt(document.getElementById('cpTracker')?.innerText || '0')
            },
            
            notes: {
                adventure: document.getElementById('adventureNotes').value,
                character: document.getElementById('characterNotes').value,
                performance: document.getElementById('performanceNotes').value
            },
            
            deathSaves: (typeof characterDataManager !== 'undefined' && characterDataManager.getDeathSaves) 
                ? characterDataManager.getDeathSaves() 
                : { successes: [], failures: [] },
            
            portrait_mode: isStageMode ? 'stage' : 'civil',
            
            // Skills - sammle alle Skill-Daten aus dem DOM
            skills: this.collectSkills()
        };
    }
    
    collectSkills() {
        // Sammle alle Skills aus dem DOM
        // Skills sind in Checkboxen mit data-skill Attribut gespeichert
        const skills = [];
        const skillCheckboxes = document.querySelectorAll('[data-skill]');
        
        skillCheckboxes.forEach(checkbox => {
            const skillName = checkbox.getAttribute('data-skill');
            const proficient = checkbox.checked;
            
            // Prüfe ob es auch Expertise gibt (normalerweise ein separates Checkbox oder Attribut)
            const expertiseCheckbox = document.querySelector(`[data-skill="${skillName}"][data-expertise]`);
            const expertise = expertiseCheckbox ? expertiseCheckbox.checked : false;
            
            // Bonus berechnen (wird normalerweise automatisch berechnet, aber wir speichern es auch)
            // Bonus = Stat-Modifier + (Proficient ? Proficiency Bonus : 0) + (Expertise ? Proficiency Bonus : 0)
            const statMod = this.getSkillStatModifier(skillName);
            const profBonus = parseInt(document.getElementById('profBonus')?.innerText || '2');
            let bonus = statMod;
            if (proficient) bonus += profBonus;
            if (expertise) bonus += profBonus;
            
            skills.push({
                skill_name: skillName,
                proficient: proficient ? 1 : 0,
                expertise: expertise ? 1 : 0,
                bonus: bonus
            });
        });
        
        return skills;
    }
    
    getSkillStatModifier(skillName) {
        // Mappe Skill-Namen zu Attributen
        const skillToStat = {
            'Akrobatik': 'dex',
            'Athletik': 'str',
            'Heimlichkeit': 'dex',
            'Täuschung': 'cha',
            'Geschichte': 'int',
            'Einschüchterung': 'cha',
            'Untersuchen': 'int',
            'Überzeugen': 'cha',
            'Medizin': 'wis',
            'Naturkunde': 'int',
            'Wahrnehmung': 'wis',
            'Religion': 'int',
            'Schleichen': 'dex',
            'Überleben': 'wis',
            'Auftreten': 'cha',
            'Fingerfertigkeit': 'dex',
            'Tierkunde': 'wis',
            'Handwerk': 'int'
        };
        
        const statName = skillToStat[skillName] || 'str';
        const statValue = parseInt(document.getElementById(`stat${statName.toUpperCase()}`)?.innerText || '8');
        return Math.floor((statValue - 10) / 2);
    }

    collectInventory(inventoryManager) {
        const equipmentManager = new EquipmentManager();
        
        // Hole ausgerüstete Items (um sie aus dem Inventar auszuschließen)
        const equippedArmor = equipmentManager.getSlotData('armorSlot');
        const equippedMainhand = equipmentManager.getSlotData('mainHandSlot');
        const equippedOffhand = equipmentManager.getSlotData('offHandSlot');
        
        // Erstelle Set von ausgerüsteten Item-IDs für schnellen Vergleich
        const equippedItemIds = new Set();
        if (equippedArmor && equippedArmor.id) equippedItemIds.add(String(equippedArmor.id));
        if (equippedArmor && equippedArmor.properties && equippedArmor.properties.id) equippedItemIds.add(equippedArmor.properties.id);
        if (equippedMainhand && equippedMainhand.id) equippedItemIds.add(String(equippedMainhand.id));
        if (equippedMainhand && equippedMainhand.properties && equippedMainhand.properties.id) equippedItemIds.add(equippedMainhand.properties.id);
        if (equippedOffhand && equippedOffhand.id) equippedItemIds.add(String(equippedOffhand.id));
        if (equippedOffhand && equippedOffhand.properties && equippedOffhand.properties.id) equippedItemIds.add(equippedOffhand.properties.id);
        
        // Prüfe auch nach Namen (falls keine ID vorhanden)
        const equippedNames = new Set();
        if (equippedArmor && equippedArmor.name) equippedNames.add(equippedArmor.name);
        if (equippedMainhand && equippedMainhand.name) equippedNames.add(equippedMainhand.name);
        if (equippedOffhand && equippedOffhand.name) equippedNames.add(equippedOffhand.name);
        
        // Funktion um zu prüfen ob Item ausgerüstet ist
        const isEquipped = (item) => {
            if (item.id && equippedItemIds.has(String(item.id))) return true;
            if (item.properties && item.properties.id && equippedItemIds.has(item.properties.id)) return true;
            if (item.name && equippedNames.has(item.name)) {
                // Zusätzliche Prüfung: Wenn Item-Name gleich, prüfe ob es das gleiche Item ist
                // (nur für Equipment-Items, da Verbrauchsgegenstände/Tools/Wertgegenstände nicht ausgerüstet werden können)
                if (item.type === 'weapon' || item.type === 'armor') {
                    // Für Waffen/Rüstung: Wenn Name gleich und es das gleiche Item ist, dann ist es ausgerüstet
                    if (equippedArmor && equippedArmor.name === item.name && item.type === 'armor') return true;
                    if (equippedMainhand && equippedMainhand.name === item.name && item.type === 'weapon') return true;
                    if (equippedOffhand && equippedOffhand.name === item.name && item.type === 'weapon') return true;
                }
            }
            return false;
        };
        
        const equipment = inventoryManager.getItems('equipmentList');
        const consumables = inventoryManager.getItems('consumablesList');
        const tools = inventoryManager.getItems('toolsList');
        const treasure = inventoryManager.getTreasureItems();
        
        // Items nach Kategorie gruppieren (Backend erwartet kategorisiert)
        // WICHTIG: Nur nicht-ausgerüstete Items ins Inventar aufnehmen
        const inventory = {
            equipment: [],
            consumables: [],
            tools: [],
            treasure: []
        };
        
        equipment.forEach(item => {
            // Überspringe ausgerüstete Items
            if (isEquipped(item)) return;
            
            if (!item.id) item.id = null;
            item.category = 'equipment';
            inventory.equipment.push(item);
        });
        
        // Verbrauchsgegenstände, Tools und Wertgegenstände werden immer ins Inventar aufgenommen
        // (sie können nicht ausgerüstet werden)
        consumables.forEach(item => {
            if (!item.id) item.id = null;
            item.category = 'consumables';
            // Stelle sicher, dass type korrekt ist (consumable, nicht consumables)
            if (!item.type || item.type === 'consumables') item.type = 'consumable';
            inventory.consumables.push(item);
        });
        
        tools.forEach(item => {
            if (!item.id) item.id = null;
            item.category = 'tools';
            // Stelle sicher, dass type korrekt ist (tool, nicht tools)
            if (!item.type || item.type === 'tools') item.type = 'tool';
            inventory.tools.push(item);
        });
        
        treasure.forEach(item => {
            if (!item.id) item.id = null;
            item.category = 'treasure';
            // Stelle sicher, dass type korrekt ist
            if (!item.type) item.type = 'treasure';
            inventory.treasure.push(item);
        });
        
        return inventory;
    }

    applyCharacterData(character) {
        // Apply character name and info
        if (character.name) {
            const nameEl = document.getElementById('characterName');
            if (nameEl) nameEl.innerText = character.name;
        }
        
        if (character.race || character.class || character.alignment) {
            const subtitleEl = document.getElementById('characterSubtitle');
            if (subtitleEl) {
                const parts = [];
                if (character.race) parts.push(character.race);
                if (character.class) parts.push(character.class);
                if (character.alignment) parts.push(character.alignment);
                subtitleEl.innerText = parts.join(' • ') || 'Unbekannt';
            }
        }
        
        // Apply stats
        if (character.stats) {
            const stats = character.stats;
            
            // Attribute setzen
            const statEls = {
                statSTR: stats.str || 8,
                statDEX: stats.dex || 15,
                statCON: stats.con || 12,
                statINT: stats.int || stats.int_stat || 13,
                statWIS: stats.wis || 10,
                statCHA: stats.cha || 17
            };
            
            Object.keys(statEls).forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.innerText = statEls[id];
                }
            });
            
            // Attribute-Modifikatoren aktualisieren
            if (typeof window.updateStatModifiers === 'function') {
                window.updateStatModifiers();
            }
            
            // HP & Ressourcen
            document.getElementById('currentHP').innerText = stats.current_hp || 9;
            document.getElementById('maxHP').innerText = stats.max_hp || 9;
            document.getElementById('tempHP').innerText = stats.temp_hp || 0;
            document.getElementById('currentBI').innerText = stats.current_bi || 2;
            document.getElementById('currentXP').innerText = stats.current_xp || 0;
            document.getElementById('armorClassDisplay').innerText = stats.armor_class || 15;
            document.getElementById('profBonus').innerText = stats.proficiency_bonus || 2;
            document.getElementById('charLevel').innerText = character.level || 1;
            
            if (document.getElementById('hdTracker')) {
                document.getElementById('hdTracker').innerText = stats.current_hd || 1;
            }
            if (document.getElementById('maxHD')) {
                document.getElementById('maxHD').innerText = stats.max_hd || 1;
            }
        }

        // WICHTIG: Equipment ZUERST laden, dann Inventory (um ausgerüstete Items auszuschließen)
        const equipmentManager = new EquipmentManager();
        const equippedItemIds = new Set();
        const equippedNames = new Set();
        
        // Lade Equipment ZUERST
        if (character.equipment) {
            // Equipment-Daten direkt setzen (vollständige Item-Objekte)
            if (character.equipment.armor) {
                equipmentManager.setSlotData('armorSlot', character.equipment.armor);
                if (character.equipment.armor.id) equippedItemIds.add(String(character.equipment.armor.id));
                if (character.equipment.armor.properties && character.equipment.armor.properties.id) equippedItemIds.add(character.equipment.armor.properties.id);
                if (character.equipment.armor.name) equippedNames.add(character.equipment.armor.name);
            }
            if (character.equipment.mainhand) {
                equipmentManager.setSlotData('mainHandSlot', character.equipment.mainhand);
                if (character.equipment.mainhand.id) equippedItemIds.add(String(character.equipment.mainhand.id));
                if (character.equipment.mainhand.properties && character.equipment.mainhand.properties.id) equippedItemIds.add(character.equipment.mainhand.properties.id);
                if (character.equipment.mainhand.name) equippedNames.add(character.equipment.mainhand.name);
            }
            if (character.equipment.offhand) {
                equipmentManager.setSlotData('offHandSlot', character.equipment.offhand);
                if (character.equipment.offhand.id) equippedItemIds.add(String(character.equipment.offhand.id));
                if (character.equipment.offhand.properties && character.equipment.offhand.properties.id) equippedItemIds.add(character.equipment.offhand.properties.id);
                if (character.equipment.offhand.name) equippedNames.add(character.equipment.offhand.name);
            }
            
            // Aktualisiere abhängige Werte
            if (typeof calculateArmorClass === 'function') calculateArmorClass();
            if (typeof updateWeaponsTable === 'function') updateWeaponsTable();
        }
        
        // Funktion um zu prüfen ob Item ausgerüstet ist
        const isEquipped = (item) => {
            if (item.id && equippedItemIds.has(String(item.id))) return true;
            if (item.properties && item.properties.id && equippedItemIds.has(item.properties.id)) return true;
            if (item.name && equippedNames.has(item.name)) {
                // Zusätzliche Prüfung: Wenn Item-Name gleich, prüfe ob es das gleiche Item ist
                if (item.type === 'weapon' || item.type === 'armor') {
                    if (character.equipment) {
                        if (character.equipment.armor && character.equipment.armor.name === item.name && item.type === 'armor') return true;
                        if (character.equipment.mainhand && character.equipment.mainhand.name === item.name && item.type === 'weapon') return true;
                        if (character.equipment.offhand && character.equipment.offhand.name === item.name && item.type === 'weapon') return true;
                    }
                }
            }
            return false;
        };
        
        // Apply inventory NACH Equipment (schließe ausgerüstete Items aus)
        if (character.inventory) {
            const inventoryManager = new InventoryManager();
            
            // Inventory nach Kategorien gruppieren
            // Inventory kann kategorisiert { equipment: [...] } oder flach [...] sein
            const byCategory = {
                equipment: [],
                consumables: [],
                tools: [],
                treasure: []
            };
            
            if (Array.isArray(character.inventory)) {
                // Flaches Array Format
                character.inventory.forEach(item => {
                    // Überspringe ausgerüstete Items
                    if (isEquipped(item)) return;
                    
                    const cat = item.category || 'equipment';
                    if (byCategory[cat]) {
                        byCategory[cat].push(item);
                    }
                });
            } else if (typeof character.inventory === 'object') {
                // Kategorisiertes Format: { equipment: [...], consumables: [...] }
                Object.keys(character.inventory).forEach(cat => {
                    if (byCategory[cat] && Array.isArray(character.inventory[cat])) {
                        // Filtere ausgerüstete Items heraus (nur für Equipment)
                        const items = cat === 'equipment' 
                            ? character.inventory[cat].filter(item => !isEquipped(item))
                            : character.inventory[cat];
                        byCategory[cat] = items;
                    }
                });
            }
            
            if (byCategory.equipment.length > 0) {
                inventoryManager.restoreItems('equipmentList', byCategory.equipment);
            }
            if (byCategory.consumables.length > 0) {
                inventoryManager.restoreItems('consumablesList', byCategory.consumables);
            }
            if (byCategory.tools.length > 0) {
                inventoryManager.restoreItems('toolsList', byCategory.tools);
            }
            if (byCategory.treasure.length > 0) {
                inventoryManager.restoreTreasureItems(byCategory.treasure);
            }
        }

        // Apply spell slots
        if (character.spellSlots && Array.isArray(character.spellSlots)) {
            const spellSlotManager = new SpellSlotManager();
            spellSlotManager.restoreSlots(character.spellSlots);
            if (typeof updateSpellButtons === 'function') {
                setTimeout(() => updateSpellButtons(), 100); // Warte kurz, damit UI aktualisiert ist
            }
        } else {
            // Wenn keine Spell Slots vorhanden, setze alle auf unbenutzt
            const spellSlotManager = new SpellSlotManager();
            spellSlotManager.restoreSlots([]);
            if (typeof updateSpellButtons === 'function') {
                setTimeout(() => updateSpellButtons(), 100);
            }
        }

        // Apply Bardic Inspiration (wird bereits in stats geladen, aber sicherstellen dass es richtig ist)
        if (character.stats && character.stats.current_bi !== undefined) {
            const biEl = document.getElementById('currentBI');
            if (biEl) {
                biEl.innerText = character.stats.current_bi;
            }
            if (typeof updateBardicInspirationCombat === 'function') {
                updateBardicInspirationCombat();
            }
        }

        // Apply money
        if (character.money) {
            document.getElementById('gpTracker').innerText = character.money.gold || 0;
            document.getElementById('spTracker').innerText = character.money.silver || 0;
            document.getElementById('cpTracker').innerText = character.money.copper || 0;
        }

        // Apply notes
        if (character.notes) {
            if (character.notes.adventure) {
                document.getElementById('adventureNotes').value = character.notes.adventure;
            }
            if (character.notes.character) {
                document.getElementById('characterNotes').value = character.notes.character;
            }
            if (character.notes.performance) {
                document.getElementById('performanceNotes').value = character.notes.performance;
            }
        }

        // Apply death saves
        if (character.deathSaves) {
            characterDataManager.restoreDeathSaves(character.deathSaves);
        }

        // Apply portrait mode
        if (character.portrait_mode && typeof window !== 'undefined' && window.portraits) {
            window.isStageMode = character.portrait_mode === 'stage';
            isStageMode = window.isStageMode;
            if (document.getElementById('characterImage')) {
                document.getElementById('characterImage').src = isStageMode ? window.portraits.stage : window.portraits.civil;
            }
        }

        // Apply skills
        if (character.skills && Array.isArray(character.skills)) {
            character.skills.forEach(skill => {
                const skillCheckbox = document.querySelector(`[data-skill="${skill.skill_name}"]`);
                if (skillCheckbox) {
                    skillCheckbox.checked = skill.proficient === 1 || skill.proficient === true;
                    
                    // Prüfe ob es auch Expertise gibt
                    const expertiseCheckbox = document.querySelector(`[data-skill="${skill.skill_name}"][data-expertise]`);
                    if (expertiseCheckbox) {
                        expertiseCheckbox.checked = skill.expertise === 1 || skill.expertise === true;
                    }
                }
            });
            
            // Aktualisiere Skill-Boni nach dem Laden
            if (typeof window.updateStatModifiers === 'function') {
                window.updateStatModifiers();
            }
        }

        // Update abhängige UI-Elemente
        if (typeof calculateArmorClass === 'function') calculateArmorClass();
        if (typeof updateWeaponsTable === 'function') updateWeaponsTable();
        if (typeof updateXPBar === 'function') updateXPBar();
        if (typeof updateSpellButtons === 'function') {
            setTimeout(() => updateSpellButtons(), 150); // Warte etwas länger für Spell Slots
        }
        if (typeof updateBardicInspirationCombat === 'function') updateBardicInspirationCombat();
    }
}

// Mache CharacterDataManagerAPI global verfügbar (NACH Klassendefinition)
if (typeof window !== 'undefined') {
    window.CharacterDataManagerAPI = CharacterDataManagerAPI;
}

// Session Management Functions
window.sessionManager = {
    activeSession: null,
    
    // Starte Session
    async startSession(characterId, sessionName = null) {
        try {
            const response = await fetch('/backend/api/sessions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ character_id: characterId, session_name: sessionName })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.activeSession = data.session_id;
                await this.updateSessionStatus(characterId);
                return data;
            } else {
                throw new Error(data.error || 'Fehler beim Starten der Session');
            }
        } catch (error) {
            console.error('Session-Start Fehler:', error);
            throw error;
        }
    },
    
    // Beende Session
    async endSession(sessionId, notes = null) {
        try {
            const response = await fetch('/backend/api/sessions.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ session_id: sessionId, notes: notes })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.activeSession = null;
                const characterId = window.currentCharacterId || 1;
                await this.updateSessionStatus(characterId);
                return data;
            } else {
                throw new Error(data.error || 'Fehler beim Beenden der Session');
            }
        } catch (error) {
            console.error('Session-Ende Fehler:', error);
            throw error;
        }
    },
    
    // Erstelle Screenshot
    async createSnapshot(characterId) {
        try {
            // WICHTIG: Sammle zuerst die aktuellen Character-Daten aus dem Frontend
            // damit Items und Notizen korrekt gespeichert werden
            let characterData = null;
            
            if (window.CharacterDataManagerAPI) {
                const manager = new window.CharacterDataManagerAPI(characterId);
                characterData = manager.collectData();
                
                // Prüfe ob Items und Notizen vorhanden sind
                const hasItems = characterData.inventory && (
                    Array.isArray(characterData.inventory) ? characterData.inventory.length > 0 :
                    Object.keys(characterData.inventory).some(key => characterData.inventory[key]?.length > 0)
                );
                const hasNotes = characterData.notes && (
                    (characterData.notes.adventure && characterData.notes.adventure.trim()) ||
                    (characterData.notes.character && characterData.notes.character.trim()) ||
                    (characterData.notes.performance && characterData.notes.performance.trim())
                );
                
                console.log('Sammle aktuelle Character-Daten für Screenshot:', {
                    hasEquipment: !!characterData.equipment,
                    hasInventory: hasItems,
                    inventoryCount: characterData.inventory ? 
                        (Array.isArray(characterData.inventory) ? characterData.inventory.length : 
                         Object.keys(characterData.inventory).reduce((sum, key) => sum + (characterData.inventory[key]?.length || 0), 0)) : 0,
                    hasNotes: hasNotes,
                    notes: {
                        adventure: characterData.notes?.adventure?.substring(0, 50) || 'leer',
                        character: characterData.notes?.character?.substring(0, 50) || 'leer',
                        performance: characterData.notes?.performance?.substring(0, 50) || 'leer'
                    }
                });
            }
            
            const response = await fetch('/backend/api/sessions.php?action=snapshot', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ 
                    character_id: characterId,
                    character_data: characterData  // Sende aktuelle Frontend-Daten mit
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                console.log('✓ Screenshot erstellt:', data.snapshot_id);
                return data;
            } else {
                throw new Error(data.error || 'Fehler beim Erstellen des Screenshots');
            }
        } catch (error) {
            console.error('Screenshot-Fehler:', error);
            throw error;
        }
    },
    
    // Lade neuesten Screenshot für einen Character
    async getLatestSnapshot(characterId) {
        try {
            const response = await fetch(`/backend/api/sessions.php?character_id=${characterId}&latest_snapshot=true`, { credentials: 'include' });
            
            if (!response.ok) {
                if (response.status === 404) {
                    // Kein Screenshot vorhanden - das ist OK
                    return null;
                }
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.error) {
                // Kein Screenshot vorhanden
                return null;
            }
            
            return data;
        } catch (error) {
            console.error('Fehler beim Laden des neuesten Screenshots:', error);
            return null;
        }
    },
    
    // Wende einen Screenshot an (stelle Character-Daten wieder her)
    async applySnapshot(snapshot) {
        if (!snapshot || !snapshot.character_data) {
            console.warn('Screenshot hat keine Character-Daten');
            return false;
        }
        
        try {
            const characterData = snapshot.character_data;
            
            // Verwende CharacterDataManagerAPI um die Daten anzuwenden
            if (window.CharacterDataManagerAPI) {
                const manager = new window.CharacterDataManagerAPI(characterData.id || 1);
                manager.applyCharacterData(characterData);
                console.log('✓ Screenshot angewendet:', {
                    snapshot_id: snapshot.id,
                    created_at: snapshot.created_at,
                    type: snapshot.snapshot_type
                });
                return true;
            } else {
                console.error('CharacterDataManagerAPI nicht verfügbar');
                return false;
            }
        } catch (error) {
            console.error('Fehler beim Anwenden des Screenshots:', error);
            return false;
        }
    },
    
    // Aktualisiere Session-Status in der UI
    async updateSessionStatus(characterId) {
        try {
            const response = await fetch(`/backend/api/sessions.php?character_id=${characterId}&active=true`, { credentials: 'include' });
            const activeSession = await response.json();
            
            const statusEl = document.getElementById('sessionStatus');
            const startBtn = document.getElementById('startSessionBtn');
            const endBtn = document.getElementById('endSessionBtn');
            
            if (activeSession && activeSession.id) {
                this.activeSession = activeSession.id;
                const startDate = new Date(activeSession.started_at);
                const duration = Math.floor((Date.now() - startDate.getTime()) / 1000 / 60); // Minuten
                
                if (statusEl) {
                    statusEl.textContent = `🟢 Session läuft seit ${duration} Min. (${startDate.toLocaleTimeString('de-DE')})`;
                    statusEl.style.color = '#88ff88';
                }
                if (startBtn) startBtn.style.display = 'none';
                if (endBtn) endBtn.style.display = 'inline-block';
            } else {
                this.activeSession = null;
                if (statusEl) {
                    statusEl.textContent = 'Keine aktive Session';
                    statusEl.style.color = '#aaa';
                }
                if (startBtn) startBtn.style.display = 'inline-block';
                if (endBtn) endBtn.style.display = 'none';
            }
        } catch (error) {
            console.error('Fehler beim Aktualisieren des Session-Status:', error);
        }
    }
};

// Globale Session-Funktionen
window.startSession = async function() {
    const characterId = window.currentCharacterId || 1;
    const sessionName = await window.customPrompt('Session-Name (optional):', 'Session ' + new Date().toLocaleString('de-DE'));
    
    try {
        await window.sessionManager.startSession(characterId, sessionName);
        await window.customAlert('Session gestartet! Alle Änderungen werden jetzt getrackt.');
    } catch (error) {
        await window.customAlert('Fehler: ' + error.message);
    }
};

window.endSession = async function() {
    if (!window.sessionManager.activeSession) {
        await window.customAlert('Keine aktive Session');
        return;
    }
    
    const notes = await window.customPrompt('Session-Notizen (optional):', '');
    const confirmed = await window.customConfirm('Session wirklich beenden? Es wird ein Screenshot erstellt.');
    
    if (confirmed) {
        try {
            await window.sessionManager.endSession(window.sessionManager.activeSession, notes);
            await window.customAlert('Session beendet! Screenshot wurde erstellt.');
        } catch (error) {
            await window.customAlert('Fehler: ' + error.message);
        }
    }
};

window.createSnapshot = async function() {
    const characterId = window.currentCharacterId || 1;
    const confirmed = await window.customConfirm('Screenshot des aktuellen Charakterzustands erstellen?');
    
    if (confirmed) {
        try {
            await window.sessionManager.createSnapshot(characterId);
            await window.customAlert('Screenshot erstellt!');
        } catch (error) {
            await window.customAlert('Fehler: ' + error.message);
        }
    }
};

// Erstelle sofortigen Screenshot beim Start (für aktuellen Stand)
window.createInitialSnapshot = async function() {
    const characterId = window.currentCharacterId || 1;
    try {
        await window.sessionManager.createSnapshot(characterId);
        console.log('✓ Initialer Screenshot erstellt');
    } catch (error) {
        console.error('Fehler beim Erstellen des initialen Screenshots:', error);
    }
};

// Lade und wende neuesten Screenshot an
window.loadLatestSnapshot = async function(characterId) {
    if (!characterId) {
        characterId = window.currentCharacterId || 1;
    }
    
    try {
        console.log('Lade neuesten Screenshot für Character ID:', characterId);
        const snapshot = await window.sessionManager.getLatestSnapshot(characterId);
        
        if (snapshot) {
            console.log('Neuester Screenshot gefunden:', {
                id: snapshot.id,
                created_at: snapshot.created_at,
                type: snapshot.snapshot_type
            });
            
            // Wende Screenshot an
            const applied = await window.sessionManager.applySnapshot(snapshot);
            
            if (applied) {
                console.log('✓ Neuester Screenshot erfolgreich angewendet');
                return true;
            } else {
                console.warn('Screenshot konnte nicht angewendet werden');
                return false;
            }
        } else {
            console.log('Kein Screenshot vorhanden - verwende aktuelle Character-Daten');
            return false;
        }
    } catch (error) {
        console.error('Fehler beim Laden des neuesten Screenshots:', error);
        return false;
    }
};
