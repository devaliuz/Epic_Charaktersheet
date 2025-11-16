/**
 * Character Management Module
 * Verwaltet Charakter-Erstellung, -Auswahl und -Wechsel
 */

// API Client wird global verfügbar gemacht (aus API.js)
// Wird später durch main.js initialisiert
export let apiClient = null;

// Initialisiere API Client wenn verfügbar
function initAPIClient() {
    if (typeof window !== 'undefined') {
        // Versuche window.apiClient zu verwenden (wird in API.js gesetzt)
        if (window.apiClient) {
            apiClient = window.apiClient;
            return;
        }
        // Versuche APIClient Klasse zu verwenden
        if (typeof APIClient !== 'undefined') {
            apiClient = new APIClient();
            window.apiClient = apiClient; // Auch global setzen
            return;
        }
    }
}

// Initialisiere sofort wenn möglich
initAPIClient();

// Initialisiere auch später (falls API.js noch nicht geladen)
if (typeof window !== 'undefined') {
    window.addEventListener('DOMContentLoaded', initAPIClient);
}

// Character Data Manager API wird ebenfalls global verfügbar gemacht
let CharacterDataManagerAPI = null;
if (typeof window !== 'undefined' && window.CharacterDataManagerAPI) {
    CharacterDataManagerAPI = window.CharacterDataManagerAPI;
}

// Aktuelle Character ID (aus localStorage oder Standard 1)
export let currentCharacterId = parseInt(localStorage.getItem('currentCharacterId')) || 1;
export let currentCharacterManager = null;

// Character Manager initialisieren
export function initCharacterManager(characterId) {
    currentCharacterId = characterId;
    localStorage.setItem('currentCharacterId', characterId.toString());
    
    if (typeof window !== 'undefined') {
        if (window.CharacterDataManagerAPI) {
            const CharacterDataManagerAPIClass = window.CharacterDataManagerAPI;
            currentCharacterManager = new CharacterDataManagerAPIClass(characterId);
            // Synchronisiere auch mit window.currentCharacterManager
            window.currentCharacterManager = currentCharacterManager;
            
            // Aktualisiere Session-Status
            if (window.sessionManager && window.sessionManager.updateSessionStatus) {
                setTimeout(() => window.sessionManager.updateSessionStatus(characterId), 300);
            }
        } else if (typeof CharacterDataManagerAPI !== 'undefined' && CharacterDataManagerAPI) {
            currentCharacterManager = new CharacterDataManagerAPI(characterId);
            // Synchronisiere auch mit window.currentCharacterManager
            window.currentCharacterManager = currentCharacterManager;
        } else {
            console.warn('CharacterDataManagerAPI nicht verfügbar!');
        }
    }
}

// Character Liste laden und im Select anzeigen
export async function loadCharacterList() {
    const selectElement = document.getElementById('characterSelect');
    if (!selectElement) return;
    
    selectElement.innerHTML = '<option value="">Lade Charaktere...</option>';
    
    try {
        // Initialisiere API Client wenn nicht vorhanden
        if (!apiClient) {
            initAPIClient();
            
            // Fallback: Verwende globale apiClient Instanz
            if (typeof window !== 'undefined' && window.apiClient) {
                apiClient = window.apiClient;
            } else if (typeof APIClient !== 'undefined') {
                apiClient = new APIClient();
                if (typeof window !== 'undefined') {
                    window.apiClient = apiClient;
                }
            } else {
                throw new Error('API Client nicht verfügbar');
            }
        }
        
        const characters = await apiClient.getAllCharacters();
        selectElement.innerHTML = ''; // Clear loading message
        
        if (!characters || characters.length === 0) {
            selectElement.innerHTML = '<option value="">Keine Charaktere gefunden</option>';
            return;
        }
        
        characters.forEach(char => {
            const option = document.createElement('option');
            option.value = char.id;
            option.innerText = `${char.name} (Level ${char.level || 1} ${char.class || 'Unbekannt'})`;
            selectElement.appendChild(option);
        });
        
        // Wähle den aktuell geladenen Charakter aus
        selectElement.value = currentCharacterId;
        
    } catch (error) {
        console.error('Fehler beim Laden der Charakterliste:', error);
        selectElement.innerHTML = '<option value="">Fehler beim Laden</option>';
    }
}

// Charakter wechseln
export async function switchCharacter(characterId) {
    if (!characterId) {
        const select = document.getElementById('characterSelect');
        characterId = select ? parseInt(select.value) : null;
    }
    
    if (!characterId) return;
    
    // Speichere aktuellen Character zuerst
    if (currentCharacterManager && typeof currentCharacterManager.save === 'function') {
        await currentCharacterManager.save().catch(() => {});
    } else if (window.saveCharacterData) {
        window.saveCharacterData();
    }
    
    // Initialisiere neuen Character Manager
    initCharacterManager(parseInt(characterId));
    
    // Lade neuen Character
    await loadCharacter(characterId);
}

// Character laden
export async function loadCharacter(characterId) {
    // Versuche zuerst neuesten Screenshot zu laden (wenn verfügbar)
    if (typeof window !== 'undefined' && window.loadLatestSnapshot) {
        try {
            const snapshotLoaded = await window.loadLatestSnapshot(characterId);
            if (snapshotLoaded) {
                console.log('✓ Character-Daten aus neuestem Screenshot geladen');
                currentCharacterManager = window.currentCharacterManager;
                if (window.updateAllStats) window.updateAllStats();
                return;
            }
        } catch (error) {
            console.warn('Fehler beim Laden des Screenshots, verwende normale Character-Daten:', error);
        }
    }
    
    // Normale Character-Daten laden
    if (typeof window !== 'undefined' && window.CharacterDataManagerAPI) {
        const apiManager = new window.CharacterDataManagerAPI(characterId);
        try {
            await apiManager.load();
            currentCharacterManager = apiManager;
            // Synchronisiere auch mit window.currentCharacterManager
            window.currentCharacterManager = apiManager;
            if (window.updateAllStats) window.updateAllStats();
        } catch (err) {
            console.error('❌ API-Laden fehlgeschlagen:', err);
            console.warn('Wechsle zu localStorage:', err);
            // Fallback zu localStorage
            if (window.loadCharacterData && window.loadCharacterData()) {
                // Daten geladen
            } else if (window.initDefaultCharacter) {
                window.initDefaultCharacter();
            }
        }
    } else if (typeof CharacterDataManagerAPI !== 'undefined') {
        const apiManager = new CharacterDataManagerAPI(characterId);
        try {
            await apiManager.load();
            currentCharacterManager = apiManager;
            // Synchronisiere auch mit window.currentCharacterManager
            if (typeof window !== 'undefined') {
                window.currentCharacterManager = apiManager;
            }
            if (typeof window !== 'undefined' && window.updateAllStats) window.updateAllStats();
        } catch (err) {
            console.error('❌ API-Laden fehlgeschlagen:', err);
            console.warn('Wechsle zu localStorage:', err);
            // Fallback zu localStorage
            if (typeof window !== 'undefined') {
                if (window.loadCharacterData && window.loadCharacterData()) {
                    // Daten geladen
                } else if (window.initDefaultCharacter) {
                    window.initDefaultCharacter();
                }
            }
        }
    } else {
        console.warn('CharacterDataManagerAPI nicht verfügbar, verwende localStorage');
        // Fallback zu localStorage
        if (typeof window !== 'undefined') {
            if (window.loadCharacterData && window.loadCharacterData()) {
                // Daten geladen
            } else if (window.initDefaultCharacter) {
                window.initDefaultCharacter();
            }
        }
    }
}

// Character Liste aktualisieren
export async function refreshCharacterList() {
    await loadCharacterList();
}

// Modal für neuen Character anzeigen
export function showCreateCharacterModal() {
    const modal = document.getElementById('createCharacterModal');
    if (modal) {
        modal.classList.add('active');
        const nameInput = document.getElementById('newCharName');
        if (nameInput) nameInput.focus();
    }
}

// Modal schließen
export function closeCreateCharacterModal() {
    const modal = document.getElementById('createCharacterModal');
    if (modal) {
        modal.classList.remove('active');
        // Felder zurücksetzen
        const nameEl = document.getElementById('newCharName');
        const classEl = document.getElementById('newCharClass');
        const raceEl = document.getElementById('newCharRace');
        const bgEl = document.getElementById('newCharBackground');
        const alignEl = document.getElementById('newCharAlignment');
        const levelEl = document.getElementById('newCharLevel');
        
        if (nameEl) nameEl.value = '';
        if (classEl) classEl.value = 'Barde';
        if (raceEl) raceEl.value = '';
        if (bgEl) bgEl.value = '';
        if (alignEl) alignEl.value = 'CN';
        if (levelEl) levelEl.value = '1';
    }
}

// Neuen Character erstellen
export async function createCharacter() {
    const nameEl = document.getElementById('newCharName');
    const classEl = document.getElementById('newCharClass');
    const raceEl = document.getElementById('newCharRace');
    const bgEl = document.getElementById('newCharBackground');
    const alignEl = document.getElementById('newCharAlignment');
    const levelEl = document.getElementById('newCharLevel');
    
    if (!nameEl || !classEl || !raceEl || !bgEl || !alignEl || !levelEl) {
        if (typeof window !== 'undefined' && window.customAlert) {
            await window.customAlert('Fehler: Formularfelder nicht gefunden!');
        }
        return;
    }
    
    const name = nameEl.value.trim();
    const className = classEl.value;
    const race = raceEl.value.trim();
    const background = bgEl.value.trim();
    const alignment = alignEl.value;
    const level = parseInt(levelEl.value) || 1;
    
            if (!name) {
        if (typeof window !== 'undefined' && window.customAlert) {
            await window.customAlert('Bitte gib einen Namen für den Charakter ein!');
        }
        return;
    }
    
    // Initialisiere API Client wenn nicht vorhanden
    if (!apiClient) {
        initAPIClient();
        if (!apiClient && typeof window !== 'undefined' && window.apiClient) {
            apiClient = window.apiClient;
        }
    }
    
    if (!apiClient) {
        throw new Error('API Client nicht verfügbar');
    }
    
    try {
        const result = await apiClient.createCharacter({
            name: name,
            class: className,
            race: race || 'Unbekannt',
            background: background || 'Unbekannt',
            alignment: alignment,
            level: level
        });
        
        if (result && result.id) {
            if (window.customAlert) {
                await window.customAlert(`Charakter "${name}" erfolgreich erstellt!`);
            }
            closeCreateCharacterModal();
            
            // Lade Character-Liste neu
            await loadCharacterList();
            
            // Wechsle zu neuem Character
            const select = document.getElementById('characterSelect');
            if (select) {
                select.value = result.id;
                await switchCharacter(result.id);
            }
        } else {
            throw new Error('Keine Character-ID erhalten');
        }
    } catch (error) {
        console.error('Fehler beim Erstellen des Charakters:', error);
        if (window.customAlert) {
            await window.customAlert(`Fehler beim Erstellen: ${error.message}`);
        }
    }
}

// Globale Funktionen für HTML-OnClick
window.switchCharacter = switchCharacter;
window.showCreateCharacterModal = showCreateCharacterModal;
window.closeCreateCharacterModal = closeCreateCharacterModal;
window.handleCreateCharacter = createCharacter;
window.refreshCharacterList = refreshCharacterList;

