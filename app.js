/**
 * Hauptanwendungslogik für Gemini Chat
 */
class GeminiChat {
    constructor() {
        this.apiUrl = 'api.php';
        this.currentFile = null;
        this.currentFileData = null;
        this.currentFileMimeType = null;
        this.anonymizer = new Anonymizer();
        this.vectorizer = new Vectorizer();
        this.nameMapping = {}; // Mapping: Originalname -> Anonymisierter Wert
        
        this.loadNameMapping();
        this.initializeEventListeners();
        this.addWelcomeMessage();
    }

    /**
     * Lädt Name-Mapping aus localStorage
     */
    loadNameMapping() {
        try {
            const stored = localStorage.getItem('gemini_name_mapping');
            if (stored) {
                this.nameMapping = JSON.parse(stored);
                console.log('Name-Mapping geladen:', this.nameMapping);
            }
        } catch (e) {
            console.error('Fehler beim Laden des Name-Mappings:', e);
        }
    }

    /**
     * Speichert Name-Mapping in localStorage
     */
    saveNameMapping() {
        try {
            localStorage.setItem('gemini_name_mapping', JSON.stringify(this.nameMapping));
            console.log('Name-Mapping gespeichert:', this.nameMapping);
        } catch (e) {
            console.error('Fehler beim Speichern des Name-Mappings:', e);
        }
    }

    /**
     * Ersetzt Namen in der Frage durch anonymisierte Werte
     * @param {string} question - Die ursprüngliche Frage
     * @returns {string} - Frage mit anonymisierten Namen
     */
    replaceNamesInQuestion(question) {
        let modifiedQuestion = question;
        const replacements = [];

        // Durchsuche alle Namen im Mapping
        for (const [originalName, anonymizedValue] of Object.entries(this.nameMapping)) {
            // Erstelle Regex für exakte Wortübereinstimmung (case-insensitive)
            const regex = new RegExp(`\\b${originalName}\\b`, 'gi');
            if (regex.test(modifiedQuestion)) {
                modifiedQuestion = modifiedQuestion.replace(regex, anonymizedValue);
                replacements.push({ original: originalName, anonymized: anonymizedValue });
            }
        }

        if (replacements.length > 0) {
            console.log('Namen in Frage ersetzt:', replacements);
        }

        return modifiedQuestion;
    }

    /**
     * Ersetzt anonymisierte Werte in der Antwort durch Originalnamen
     * @param {string} response - Die Antwort der KI
     * @returns {string} - Antwort mit Originalnamen
     */
    replaceNamesInResponse(response) {
        let modifiedResponse = response;

        // Erstelle Reverse-Mapping (anonymisiert -> original)
        const reverseMapping = {};
        for (const [original, anonymized] of Object.entries(this.nameMapping)) {
            reverseMapping[anonymized] = original;
        }

        // Ersetze anonymisierte Werte durch Originalnamen
        for (const [anonymized, original] of Object.entries(reverseMapping)) {
            const regex = new RegExp(anonymized.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
            modifiedResponse = modifiedResponse.replace(regex, original);
        }

        return modifiedResponse;
    }

    initializeEventListeners() {
        const sendButton = document.getElementById('sendButton');
        const messageInput = document.getElementById('messageInput');
        const fileInput = document.getElementById('fileInput');
        const removeFileBtn = document.getElementById('removeFile');
        const toggleDataView = document.getElementById('toggleDataView');

        if (!sendButton || !messageInput) {
            console.error('Wichtige DOM-Elemente nicht gefunden!');
            return;
        }
        
        console.log('Event-Listener werden initialisiert...');
        
        // Toggle für Datenübertragungs-Ansicht
        if (toggleDataView) {
            toggleDataView.addEventListener('click', () => this.toggleDataView());
        }

        // Enter-Taste zum Senden (Shift+Enter für neue Zeile)
        messageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        sendButton.addEventListener('click', () => {
            console.log('Send-Button geklickt');
            this.sendMessage();
        });

        fileInput.addEventListener('change', (e) => this.handleFileUpload(e));

        removeFileBtn.addEventListener('click', () => this.removeFile());
    }

    addWelcomeMessage() {
        const welcomeMessage = `
Willkommen beim Gemini Chat! 🤖

Du kannst:
- Fragen stellen und Antworten erhalten
- Dateien hochladen (PDF, TXT, CSV, Bilder)
- Fragen zu hochgeladenen Dateien stellen

Die Dateien werden automatisch anonymisiert, bevor sie an die API gesendet werden.
        `;
        this.addMessage('assistant', welcomeMessage);
    }

    async handleFileUpload(event) {
        const file = event.target.files[0];
        if (!file) return;

        this.currentFile = file;
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        
        fileName.textContent = `📎 ${file.name} (${this.formatFileSize(file.size)})`;
        fileInfo.style.display = 'flex';

        // Lade und verarbeite Datei
        try {
            await this.processFile(file);
            this.addMessage('assistant', `✅ Datei "${file.name}" wurde erfolgreich hochgeladen und verarbeitet. Du kannst jetzt Fragen dazu stellen.`);
        } catch (error) {
            console.error('Fehler beim Verarbeiten der Datei:', error);
            this.addMessage('assistant', `❌ Fehler beim Verarbeiten der Datei: ${error.message}`);
            this.removeFile();
        }
    }

    async processFile(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            this.currentFileMimeType = file.type;

            if (file.type.startsWith('image/')) {
                // Für Bilder: Base64-Kodierung
                reader.onload = async (e) => {
                    const base64 = e.target.result.split(',')[1]; // Entferne data:image/...;base64,
                    this.currentFileData = base64;
                    resolve();
                };
                reader.readAsDataURL(file);
            } else {
                // Für Textdateien: Text-Extraktion
                reader.onload = async (e) => {
                    try {
                        const fileContent = e.target.result;
                        // Übergebe auch den Dateinamen für CSV-Erkennung
                        const result = await this.anonymizer.anonymizeFile(fileContent, file.type, file.name);
                        
                        if (result.isImage) {
                            this.currentFileData = result.data;
                        } else {
                            // Speichere Name-Mapping wenn vorhanden (für CSV-Dateien)
                            if (result.nameMapping) {
                                // Merge mit existierendem Mapping
                                this.nameMapping = { ...this.nameMapping, ...result.nameMapping };
                                this.saveNameMapping();
                                console.log('Name-Mapping aktualisiert:', this.nameMapping);
                            }
                            
                            // Für Textdateien: Speichere anonymisierten Text
                            this.currentFileData = btoa(unescape(encodeURIComponent(result.text)));
                        }
                        resolve();
                    } catch (error) {
                        reject(error);
                    }
                };
                
                if (file.type === 'application/pdf') {
                    // PDF wird als ArrayBuffer gelesen
                    reader.readAsArrayBuffer(file);
                } else {
                    // Textdateien und CSV werden als Text gelesen
                    reader.readAsText(file, 'UTF-8');
                }
            }

            reader.onerror = () => reject(new Error('Fehler beim Lesen der Datei'));
        });
    }

    removeFile() {
        this.currentFile = null;
        this.currentFileData = null;
        this.currentFileMimeType = null;
        document.getElementById('fileInput').value = '';
        document.getElementById('fileInfo').style.display = 'none';
    }

    async sendMessage() {
        const messageInput = document.getElementById('messageInput');
        const message = messageInput.value.trim();

        console.log('sendMessage aufgerufen, Nachricht:', message);
        
        if (!message) {
            console.log('Keine Nachricht eingegeben');
            return;
        }

        // Zeige Benutzernachricht
        this.addMessage('user', message);
        messageInput.value = '';

        // Zeige Ladeanzeige
        const loadingId = this.addMessage('assistant', '', true);

        try {
            // Ersetze Namen in der Frage durch anonymisierte Werte
            let processedMessage = this.replaceNamesInQuestion(message);
            
            // Bereite Nachricht vor
            let finalMessage = processedMessage;
            
            // Wenn eine Datei hochgeladen wurde, kombiniere Frage mit Kontext
            if (this.currentFileData && this.currentFileMimeType) {
                if (this.currentFileMimeType.startsWith('image/')) {
                    // Für Bilder: Frage bleibt gleich, Bild wird separat gesendet
                    finalMessage = processedMessage;
                } else {
                    // Für Textdateien: Dekodiere und kombiniere mit Frage
                    try {
                        const decodedText = decodeURIComponent(escape(atob(this.currentFileData)));
                        finalMessage = this.vectorizer.combineQuestionWithContext(processedMessage, decodedText);
                    } catch (e) {
                        console.error('Fehler beim Dekodieren:', e);
                        finalMessage = processedMessage;
                    }
                }
            }

            // Hole das ausgewählte Modell für die Anzeige
            const modelSelect = document.getElementById('modelSelect');
            const selectedModel = modelSelect ? modelSelect.value : 'gemma-3-1b';
            const selectedModelText = modelSelect ? modelSelect.options[modelSelect.selectedIndex].text : 'gemma-3-1b';
            
            // Speichere die übertragenen Daten (anonymisierte Version)
            this.saveTransmittedData(message, finalMessage, selectedModel, selectedModelText);
            
            // Sende Anfrage an API
            const response = await this.sendToAPI(finalMessage);
            
            // Ersetze anonymisierte Werte in der Antwort durch Originalnamen
            const finalResponse = this.replaceNamesInResponse(response);
            
            // Entferne Ladeanzeige und zeige Antwort
            this.updateMessage(loadingId, 'assistant', finalResponse);
        } catch (error) {
            console.error('Fehler beim Senden:', error);
            this.updateMessage(loadingId, 'assistant', `❌ Fehler: ${error.message}`);
        }
    }

    async sendToAPI(message) {
        // Hole das ausgewählte Modell
        const modelSelect = document.getElementById('modelSelect');
        const selectedModel = modelSelect ? modelSelect.value : 'gemma-3-1b';

        const payload = {
            message: message,
            model: selectedModel
        };

        // Füge Dateidaten hinzu, wenn vorhanden
        if (this.currentFileData && this.currentFileMimeType) {
            payload.fileData = this.currentFileData;
            payload.fileName = this.currentFile?.name || 'uploaded_file';
            payload.mimeType = this.currentFileMimeType;
        }

        const response = await fetch(this.apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        // Prüfe, ob die Antwort Text enthält
        const responseText = await response.text();
        let data;
        
        try {
            data = JSON.parse(responseText);
        } catch (e) {
            throw new Error('Ungültige JSON-Antwort vom Server: ' + responseText.substring(0, 200));
        }

        if (!response.ok || !data.success) {
            // Erstelle eine detaillierte Fehlermeldung
            let errorMessage = data.error || 'Unbekannter Fehler';
            
            if (data.message) {
                errorMessage += ': ' + data.message;
            }
            
            if (data.status) {
                errorMessage += ' (Status: ' + data.status + ')';
            }
            
            if (data.httpCode) {
                errorMessage += ' [HTTP ' + data.httpCode + ']';
            }
            
            // Zeige auch Details in der Konsole für Debugging
            if (data.details) {
                console.error('API Fehler Details:', data.details);
            }
            
            throw new Error(errorMessage);
        }

        return data.response;
    }

    addMessage(role, text, isLoading = false) {
        const messagesContainer = document.getElementById('chatMessages');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${role}`;
        
        const messageId = 'msg-' + Date.now() + '-' + Math.random();
        messageDiv.id = messageId;

        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';

        if (isLoading) {
            bubble.innerHTML = '<div class="loading"></div> Antwort wird generiert...';
        } else {
            bubble.textContent = text;
        }

        const time = document.createElement('div');
        time.className = 'message-time';
        time.textContent = new Date().toLocaleTimeString('de-DE');

        messageDiv.appendChild(bubble);
        messageDiv.appendChild(time);
        messagesContainer.appendChild(messageDiv);

        // Scroll nach unten
        messagesContainer.scrollTop = messagesContainer.scrollHeight;

        return messageId;
    }

    updateMessage(messageId, role, text) {
        const messageDiv = document.getElementById(messageId);
        if (!messageDiv) return;

        const bubble = messageDiv.querySelector('.message-bubble');
        if (bubble) {
            bubble.textContent = text;
        }
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    /**
     * Speichert die übertragenen anonymisierten Daten
     * @param {string} originalMessage - Die ursprüngliche Nachricht des Benutzers
     * @param {string} anonymizedMessage - Die anonymisierte Nachricht, die an die API gesendet wurde
     * @param {string} model - Das verwendete Modell
     * @param {string} modelText - Der vollständige Modell-Text
     */
    saveTransmittedData(originalMessage, anonymizedMessage, model, modelText) {
        const transmittedDataContainer = document.getElementById('transmittedData');
        if (!transmittedDataContainer) return;

        // Entferne "Noch keine Daten" Meldung
        const noDataMsg = transmittedDataContainer.querySelector('.no-data');
        if (noDataMsg) {
            noDataMsg.remove();
        }

        // Erstelle neuen Eintrag
        const entry = document.createElement('div');
        entry.className = 'transmitted-data-entry';
        
        const time = new Date().toLocaleString('de-DE');
        
        entry.innerHTML = `
            <div class="transmitted-data-entry-header">
                Übertragung #${transmittedDataContainer.children.length + 1} - ${time}
            </div>
            <div class="transmitted-data-entry-content">
<strong>Verwendetes Modell:</strong>
${modelText}

<strong>Originale Frage:</strong>
${originalMessage}

<strong>Anonymisierte Daten an KI:</strong>
${anonymizedMessage.substring(0, 5000)}${anonymizedMessage.length > 5000 ? '\n\n[... (gekürzt, vollständiger Text in der Konsole)]' : ''}
            </div>
            <div class="transmitted-data-entry-time">
                ${anonymizedMessage.length} Zeichen übertragen | Modell: ${model}
            </div>
        `;

        // Füge am Anfang hinzu (neueste zuerst)
        transmittedDataContainer.insertBefore(entry, transmittedDataContainer.firstChild);

        // Speichere auch in Konsole für vollständige Ansicht
        console.log('=== ÜBERTRAGENE DATEN ===');
        console.log('Original:', originalMessage);
        console.log('Anonymisiert:', anonymizedMessage);
        console.log('========================');
    }

    /**
     * Zeigt/Versteckt den Datenübertragungs-Bereich
     */
    toggleDataView() {
        const content = document.getElementById('dataTransmissionContent');
        const toggleBtn = document.getElementById('toggleDataView');
        
        if (!content || !toggleBtn) return;

        if (content.style.display === 'none') {
            content.style.display = 'flex';
            toggleBtn.textContent = '▲ Ausblenden';
        } else {
            content.style.display = 'none';
            toggleBtn.textContent = '▼ Einblenden';
        }
    }
}

// Initialisiere die Anwendung, wenn das DOM geladen ist
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM geladen, initialisiere GeminiChat...');
    try {
        new GeminiChat();
        console.log('GeminiChat erfolgreich initialisiert');
    } catch (error) {
        console.error('Fehler bei der Initialisierung:', error);
    }
});