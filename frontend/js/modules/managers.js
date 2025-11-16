/**
 * Manager-Klassen für Charakter-Datenverwaltung
 * Verwaltet Equipment, Inventory, Spell Slots und Character Data
 */

// Character Data Manager - zentrale Speicher- und Ladeverwaltung
export class CharacterDataManager {
    constructor() {
        this.storageKey = 'barItonCharacterData';
        this.data = null;
    }

    // Speichere alle Charakterdaten
    save() {
        const spellSlotManager = new SpellSlotManager();
        const equipmentManager = new EquipmentManager();
        const inventoryManager = new InventoryManager();

        this.data = {
            // HP & Ressourcen
            currentHP: this.getElementText('currentHP'),
            maxHP: this.getElementText('maxHP'),
            tempHP: this.getElementText('tempHP'),
            currentBI: this.getElementText('currentBI'),
            currentHD: this.getElementText('hdTracker', '1'),
            maxHD: this.getElementText('maxHD', '1'),
            
            // XP & Level
            currentXP: this.getElementText('currentXP'),
            charLevel: this.getElementText('charLevel'),
            
            // Ausrüstung
            armorSlot: equipmentManager.getSlotData('armorSlot'),
            mainHandSlot: equipmentManager.getSlotData('mainHandSlot'),
            offHandSlot: equipmentManager.getSlotData('offHandSlot'),
            
            // Inventar
            equipment: inventoryManager.getItems('equipmentList'),
            consumables: inventoryManager.getItems('consumablesList'),
            tools: inventoryManager.getItems('toolsList'),
            treasure: inventoryManager.getTreasureItems(),
            
            // Geld
            gp: this.getElementText('gpTracker'),
            sp: this.getElementText('spTracker'),
            cp: this.getElementText('cpTracker'),
            
            // Notizen
            adventureNotes: this.getElementValue('adventureNotes'),
            characterNotes: this.getElementValue('characterNotes'),
            performanceNotes: this.getElementValue('performanceNotes'),
            
            // Todesrettungswürfe
            deathSaves: this.getDeathSaves(),
            
            // Zauberplätze
            spellSlots: spellSlotManager.getSlots(),
            
            // Portrait
            isStageMode: (typeof window !== 'undefined' && window.isStageMode) || false
        };
        
        try {
            localStorage.setItem(this.storageKey, JSON.stringify(this.data));
            console.log('Daten gespeichert:', {
                slots: {
                    armor: !!this.data.armorSlot,
                    mainhand: !!this.data.mainHandSlot,
                    offhand: !!this.data.offHandSlot
                },
                inventory: {
                    equipment: this.data.equipment?.length || 0,
                    consumables: this.data.consumables?.length || 0,
                    tools: this.data.tools?.length || 0,
                    treasure: this.data.treasure?.length || 0
                },
                spellSlots: this.data.spellSlots?.length || 0
            });
            return true;
        } catch(e) {
            console.error('Fehler beim Speichern:', e);
            return false;
        }
    }

    // Lade alle Charakterdaten
    load() {
        try {
            const savedData = localStorage.getItem(this.storageKey);
            if (!savedData) return false;
            
            this.data = JSON.parse(savedData);
            
            // HP & Ressourcen
            this.setElementText('currentHP', this.data.currentHP);
            this.setElementText('maxHP', this.data.maxHP);
            this.setElementText('tempHP', this.data.tempHP);
            this.setElementText('currentBI', this.data.currentBI);
            this.setElementTextIfExists('hdTracker', this.data.currentHD);
            this.setElementTextIfExists('maxHD', this.data.maxHD);
            
            // XP & Level
            this.setElementText('currentXP', this.data.currentXP);
            if (this.data.charLevel) {
                this.loadLevel(this.data.charLevel);
            }
            
            // Ausrüstung
            const equipmentManager = new EquipmentManager();
            if (this.data.armorSlot) equipmentManager.setSlotData('armorSlot', this.data.armorSlot);
            if (this.data.mainHandSlot) equipmentManager.setSlotData('mainHandSlot', this.data.mainHandSlot);
            if (this.data.offHandSlot) equipmentManager.setSlotData('offHandSlot', this.data.offHandSlot);
            equipmentManager.updateWeaponsTable();
            if (window.calculateArmorClass) window.calculateArmorClass();
            
            // Inventar
            const inventoryManager = new InventoryManager();
            if (this.data.equipment) inventoryManager.restoreItems('equipmentList', this.data.equipment);
            if (this.data.consumables) inventoryManager.restoreItems('consumablesList', this.data.consumables);
            if (this.data.tools) inventoryManager.restoreItems('toolsList', this.data.tools);
            if (this.data.treasure) inventoryManager.restoreTreasureItems(this.data.treasure);
            
            // Geld
            this.setElementText('gpTracker', this.data.gp);
            this.setElementText('spTracker', this.data.sp);
            this.setElementText('cpTracker', this.data.cp);
            
            // Notizen
            this.setElementValue('adventureNotes', this.data.adventureNotes);
            this.setElementValue('characterNotes', this.data.characterNotes);
            this.setElementValue('performanceNotes', this.data.performanceNotes);
            
            // Todesrettungswürfe
            this.restoreDeathSaves(this.data.deathSaves);
            
            // Zauberplätze
            const spellSlotManager = new SpellSlotManager();
            if (this.data.spellSlots) {
                spellSlotManager.restoreSlots(this.data.spellSlots);
            }
            
            // Portrait
            if (this.data.isStageMode !== undefined && typeof window !== 'undefined' && window.portraits) {
                window.isStageMode = this.data.isStageMode;
                const img = document.getElementById('characterImage');
                if (img) {
                    img.src = window.isStageMode ? window.portraits.stage : window.portraits.civil;
                }
            }
            
            if (typeof window !== 'undefined') {
                if (window.calculateArmorClass) window.calculateArmorClass();
                if (window.updateXPBar) window.updateXPBar();
            }
            
            console.log('Daten geladen:', {
                slots: {
                    armor: !!this.data.armorSlot,
                    mainhand: !!this.data.mainHandSlot,
                    offhand: !!this.data.offHandSlot
                },
                inventory: {
                    equipment: this.data.equipment?.length || 0,
                    consumables: this.data.consumables?.length || 0,
                    tools: this.data.tools?.length || 0,
                    treasure: this.data.treasure?.length || 0
                },
                spellSlots: this.data.spellSlots?.length || 0
            });
            return true;
        } catch(e) {
            console.error('Fehler beim Laden:', e);
            return false;
        }
    }

    // Hilfsmethoden
    getElementText(id, defaultValue = '') {
        const el = document.getElementById(id);
        return el ? el.innerText : defaultValue;
    }

    setElementText(id, value) {
        const el = document.getElementById(id);
        if (el && value !== undefined && value !== null) {
            el.innerText = value;
        }
    }

    setElementTextIfExists(id, value) {
        const el = document.getElementById(id);
        if (el && value !== undefined && value !== null) {
            el.innerText = value;
        }
    }

    getElementValue(id) {
        const el = document.getElementById(id);
        return el ? el.value : '';
    }

    setElementValue(id, value) {
        const el = document.getElementById(id);
        if (el && value !== undefined && value !== null) {
            el.value = value;
        }
    }

    loadLevel(level) {
        const savedLevel = parseInt(level);
        this.setElementText('charLevel', savedLevel);
        const levelEl = document.getElementById('charLevel');
        if (levelEl) {
            levelEl.setAttribute('data-last-level', String(savedLevel));
        }
        
        const profBonus = Math.floor((savedLevel - 1) / 4) + 2;
        this.setElementText('profBonus', profBonus);
        
        this.setElementTextIfExists('maxHD', savedLevel);
        
        const conMod = 1;
        const baseHP = 8;
        const additionalHP = (savedLevel - 1) * (5 + conMod);
        const newMaxHP = baseHP + conMod + additionalHP;
        this.setElementText('maxHP', newMaxHP);
        const maxHP2 = document.getElementById('maxHP2');
        if (maxHP2) {
            maxHP2.innerText = newMaxHP;
        }
    }

    getDeathSaves() {
        const saves = { successes: [], failures: [] };
        for (let i = 1; i <= 3; i++) {
            const success = document.getElementById('death' + i + 's');
            const failure = document.getElementById('death' + i + 'f');
            if (success && success.checked) saves.successes.push(i);
            if (failure && failure.checked) saves.failures.push(i);
        }
        return saves;
    }

    restoreDeathSaves(saves) {
        if (!saves) return;
        saves.successes?.forEach(i => {
            const checkbox = document.getElementById('death' + i + 's');
            if (checkbox) checkbox.checked = true;
        });
        saves.failures?.forEach(i => {
            const checkbox = document.getElementById('death' + i + 'f');
            if (checkbox) checkbox.checked = true;
        });
    }
}

// Spell Slot Manager - Verwaltung der Zauberplätze
export class SpellSlotManager {
    getSlots() {
        const slots = [];
        const spellSlotElements = Array.from(document.querySelectorAll('.spell-slot'));
        spellSlotElements.forEach(slot => {
            slots.push(slot.classList.contains('used'));
        });
        return slots;
    }

    restoreSlots(slots) {
        const spellSlotElements = Array.from(document.querySelectorAll('.spell-slot'));
        if (!Array.isArray(slots) || slots.length === 0) {
            spellSlotElements.forEach(slot => {
                slot.classList.remove('used');
            });
            if (typeof window !== 'undefined' && window.updateSpellButtons) window.updateSpellButtons();
            return;
        }
        spellSlotElements.forEach((slot, index) => {
            if (index < slots.length && slots[index] === true) {
                slot.classList.add('used');
            } else {
                slot.classList.remove('used');
            }
        });
        if (typeof window !== 'undefined' && window.updateSpellButtons) window.updateSpellButtons();
    }

    toggleSlot(slot) {
        slot.classList.toggle('used');
        if (typeof window !== 'undefined' && window.updateSpellButtons) window.updateSpellButtons();
        if (typeof window !== 'undefined' && window.characterDataManager) window.characterDataManager.save();
    }

    resetSlots() {
        document.querySelectorAll('.spell-slot').forEach(slot => {
            slot.classList.remove('used');
        });
        if (typeof window !== 'undefined' && window.updateSpellButtons) window.updateSpellButtons();
        if (typeof window !== 'undefined' && window.characterDataManager) window.characterDataManager.save();
    }
}

// Equipment Manager - Verwaltung der Ausrüstungs-Slots
export class EquipmentManager {
    getSlotData(slotId) {
        const slotItem = document.getElementById(slotId + 'Item');
        if (slotItem && slotItem.classList.contains('slot-item')) {
            const itemData = slotItem.getAttribute('data-item');
            if (itemData) {
                try {
                    return JSON.parse(itemData);
                } catch(e) {
                    return null;
                }
            }
        }
        return null;
    }

    setSlotData(slotId, itemData) {
        const slotItem = document.getElementById(slotId + 'Item');
        if (!slotItem) return;
        
        if (!itemData) {
            // Leerer Slot - zurücksetzen
            slotItem.className = 'slot-placeholder';
            slotItem.innerText = slotId === 'armorSlot' ? 'Keine Rüstung' : 'Leer';
            slotItem.removeAttribute('data-item');
            
            // Reaktiviere offhand Slot falls 2h-Waffe entfernt wurde
            if (slotId === 'mainHandSlot') {
                const offHandSlotContainer = document.getElementById('offHandSlot');
                if (offHandSlotContainer) {
                    offHandSlotContainer.style.opacity = '1';
                    offHandSlotContainer.style.pointerEvents = 'auto';
                    offHandSlotContainer.removeAttribute('data-disabled');
                }
            }
            return;
        }
        
        // Item in Slot setzen
        slotItem.className = 'slot-item';
        slotItem.innerText = itemData.name || 'Unbekannt';
        slotItem.setAttribute('data-item', JSON.stringify(itemData));
        
        // Aktualisiere 2h-Waffe Logik
        if (itemData.hands === '2h') {
            const offHandSlotContainer = document.getElementById('offHandSlot');
            const offHandSlotItem = document.getElementById('offHandSlotItem');
            if (offHandSlotContainer) {
                if (offHandSlotItem && offHandSlotItem.classList.contains('slot-item')) {
                    offHandSlotItem.className = 'slot-placeholder';
                    offHandSlotItem.innerText = 'Leer';
                    offHandSlotItem.removeAttribute('data-item');
                }
                offHandSlotContainer.style.opacity = '0.5';
                offHandSlotContainer.style.pointerEvents = 'none';
                offHandSlotContainer.setAttribute('data-disabled', 'true');
            }
        } else {
            const offHandSlotContainer = document.getElementById('offHandSlot');
            if (offHandSlotContainer && slotId === 'mainHandSlot') {
                offHandSlotContainer.style.opacity = '1';
                offHandSlotContainer.style.pointerEvents = 'auto';
                offHandSlotContainer.removeAttribute('data-disabled');
            }
        }
        
                if (slotId === 'armorSlot') {
            if (typeof window !== 'undefined' && window.calculateArmorClass) window.calculateArmorClass();
        }
        if (itemData.type === 'weapon') {
            this.updateWeaponsTable();
        }
    }

    updateWeaponsTable() {
        const mainHandSlot = document.getElementById('mainHandSlotItem');
        const offHandSlot = document.getElementById('offHandSlotItem');
        const mainHandRow = document.getElementById('mainHandWeapon');
        const offHandRow = document.getElementById('offHandWeapon');
        
        if (mainHandSlot && mainHandSlot.classList.contains('slot-item')) {
            try {
                const itemData = mainHandSlot.getAttribute('data-item');
                const item = JSON.parse(itemData);
                if (mainHandRow) {
                    mainHandRow.innerHTML = `
                        <td>${item.name} (Haupthand)</td>
                        <td>${item.toHit || '+4'}</td>
                        <td>${item.damage || '1d4+2'}</td>
                        <td>${item.range || '1,5m'}</td>
                    `;
                }
            } catch(e) {
                if (mainHandRow) {
                    mainHandRow.innerHTML = `<td>-</td><td>-</td><td>-</td><td>-</td>`;
                }
            }
        } else {
            if (mainHandRow) {
                mainHandRow.innerHTML = `<td>-</td><td>-</td><td>-</td><td>-</td>`;
            }
        }
        
        if (offHandSlot && offHandSlot.classList.contains('slot-item')) {
            try {
                const itemData = offHandSlot.getAttribute('data-item');
                const item = JSON.parse(itemData);
                const damage = item.offhandDamage || item.damage || '1d4';
                if (offHandRow) {
                    offHandRow.style.display = '';
                    offHandRow.innerHTML = `
                        <td>${item.name} (Nebenhand)</td>
                        <td>${item.toHit || '+4'}</td>
                        <td>${damage}</td>
                        <td>${item.range || '1,5m'}</td>
                    `;
                }
            } catch(e) {
                if (offHandRow) {
                    offHandRow.style.display = 'none';
                    offHandRow.innerHTML = `<td>-</td><td>-</td><td>-</td><td>-</td>`;
                }
            }
        } else {
            if (offHandRow) {
                offHandRow.style.display = 'none';
                offHandRow.innerHTML = `<td>-</td><td>-</td><td>-</td><td>-</td>`;
            }
        }
    }
}

// Inventory Manager - Verwaltung des Inventars
export class InventoryManager {
    getItems(listId) {
        const list = document.getElementById(listId);
        const items = [];
        if (!list) return items;
        list.querySelectorAll('.inventory-item').forEach(item => {
            const itemData = item.getAttribute('data-item');
            if (itemData) {
                try {
                    items.push(JSON.parse(itemData));
                } catch(e) {
                    // Ignoriere fehlerhafte Items
                }
            }
        });
        return items;
    }

    getTreasureItems() {
        const list = document.getElementById('treasureList');
        const items = [];
        if (!list) return items;
        list.querySelectorAll('.treasure-item').forEach(item => {
            const itemData = item.getAttribute('data-item');
            if (itemData) {
                try {
                    items.push(JSON.parse(itemData));
                } catch(e) {
                    // Ignoriere fehlerhafte Items
                }
            }
        });
        return items;
    }

    restoreItems(listId, items) {
        const list = document.getElementById(listId);
        if (!list) return;
        list.innerHTML = '';
        items.forEach(item => {
            if (listId === 'equipmentList') {
                const itemElement = (typeof window !== 'undefined' && window.createInventoryItemElement) ? window.createInventoryItemElement(item) : null;
                if (itemElement) {
                    list.appendChild(itemElement);
                }
            } else {
                const itemElement = document.createElement('div');
                itemElement.className = 'inventory-item';
                itemElement.setAttribute('data-item', JSON.stringify(item));
                const icon = listId === 'consumablesList' ? '🧪' : '📦';
                itemElement.innerHTML = `
                    <span>${icon} ${item.name}</span>
                    <div class="item-controls">
                        <button class="btn-remove" onclick="removeInventoryItem(this, '${listId === 'consumablesList' ? 'consumables' : 'tools'}')">×</button>
                    </div>
                `;
                list.appendChild(itemElement);
            }
        });
    }

    restoreTreasureItems(items) {
        const list = document.getElementById('treasureList');
        if (!list) return;
        list.innerHTML = '';
        items.forEach(item => {
            const itemElement = document.createElement('div');
            itemElement.className = 'treasure-item';
            itemElement.setAttribute('data-item', JSON.stringify(item));
            itemElement.setAttribute('draggable', 'false');
            itemElement.innerHTML = `
                <span>${item.name}</span>
                <span class="treasure-value">${item.value}</span>
                <button class="btn-remove" onclick="removeTreasureItem(this)">×</button>
            `;
            list.appendChild(itemElement);
        });
    }
}

