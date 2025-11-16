/**
 * Main Entry Point für die Character Sheet Anwendung
 * Importiert und initialisiert alle Module
 */

// API.js muss zuerst geladen werden (global verfügbar)
// Dann importiere Manager-Klassen
import { CharacterDataManager, SpellSlotManager, EquipmentManager, InventoryManager } from './modules/managers.js';

// Importiere Character Management
import { 
    initCharacterManager, 
    loadCharacterList, 
    switchCharacter, 
    currentCharacterId, 
    currentCharacterManager,
    loadCharacter,
    refreshCharacterList,
    showCreateCharacterModal,
    closeCreateCharacterModal,
    createCharacter,
    apiClient
} from './modules/character-management.js';

// Globale Instanzen für Kompatibilität mit inline JavaScript
if (typeof window !== 'undefined') {
    window.CharacterDataManager = CharacterDataManager;
    window.SpellSlotManager = SpellSlotManager;
    window.EquipmentManager = EquipmentManager;
    window.InventoryManager = InventoryManager;
    
    // Character Management Funktionen global verfügbar machen
    window.initCharacterManager = initCharacterManager;
    window.loadCharacterList = loadCharacterList;
    window.switchCharacter = switchCharacter;
    window.loadCharacter = loadCharacter;
    window.refreshCharacterList = refreshCharacterList;
    window.showCreateCharacterModal = showCreateCharacterModal;
    window.closeCreateCharacterModal = closeCreateCharacterModal;
    window.handleCreateCharacter = createCharacter;
    
    // Globale Character Data Manager Instanz (für localStorage Fallback)
    if (!window.characterDataManager) {
        window.characterDataManager = new CharacterDataManager();
    }
    
    // API Client global verfügbar machen
    if (!window.apiClient && apiClient) {
        window.apiClient = apiClient;
    }
    
    // Character Manager global verfügbar machen
    window.currentCharacterId = currentCharacterId;
    window.currentCharacterManager = currentCharacterManager;
}

// Exportiere für andere Module
export { 
    CharacterDataManager, 
    SpellSlotManager, 
    EquipmentManager, 
    InventoryManager 
};
export { 
    initCharacterManager, 
    loadCharacterList, 
    switchCharacter, 
    currentCharacterId, 
    currentCharacterManager,
    loadCharacter,
    refreshCharacterList,
    showCreateCharacterModal,
    closeCreateCharacterModal,
    createCharacter,
    apiClient
};
