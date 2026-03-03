/**
 * Vektorisierungs- und Textverarbeitungs-Modul
 * Verarbeitet Text für die Gemini API
 */
class Vectorizer {
    constructor() {
        this.maxChunkSize = 10000; // Maximale Zeichen pro Chunk
        this.chunkOverlap = 200; // Überlappung zwischen Chunks
    }

    /**
     * Teilt Text in Chunks auf für bessere Verarbeitung
     * @param {string} text - Der zu chunkende Text
     * @param {number} chunkSize - Größe jedes Chunks
     * @param {number} overlap - Überlappung zwischen Chunks
     * @returns {Array<string>} - Array von Text-Chunks
     */
    chunkText(text, chunkSize = null, overlap = null) {
        const size = chunkSize || this.maxChunkSize;
        const overlapSize = overlap || this.chunkOverlap;
        
        if (!text || text.length <= size) {
            return [text];
        }

        const chunks = [];
        let start = 0;

        while (start < text.length) {
            let end = start + size;
            
            // Versuche, am Ende eines Satzes oder Absatzes zu schneiden
            if (end < text.length) {
                const lastPeriod = text.lastIndexOf('.', end);
                const lastNewline = text.lastIndexOf('\n', end);
                const cutPoint = Math.max(lastPeriod, lastNewline);
                
                if (cutPoint > start + size * 0.5) {
                    end = cutPoint + 1;
                }
            }

            chunks.push(text.substring(start, end));
            start = end - overlapSize;
        }

        return chunks;
    }

    /**
     * Erstellt eine Zusammenfassung des Textes für Kontext
     * @param {string} text - Der Text
     * @param {number} maxLength - Maximale Länge der Zusammenfassung
     * @returns {string} - Zusammenfassung
     */
    createSummary(text, maxLength = 500) {
        if (!text || text.length <= maxLength) {
            return text;
        }

        // Einfache Zusammenfassung: Erste und letzte Teile
        const firstPart = text.substring(0, maxLength / 2);
        const lastPart = text.substring(text.length - maxLength / 2);
        
        return firstPart + '\n\n[... Text gekürzt ...]\n\n' + lastPart;
    }

    /**
     * Extrahiert Schlüsselwörter aus dem Text (einfache Heuristik)
     * @param {string} text - Der Text
     * @param {number} maxKeywords - Maximale Anzahl von Keywords
     * @returns {Array<string>} - Array von Schlüsselwörtern
     */
    extractKeywords(text, maxKeywords = 10) {
        if (!text) return [];

        // Entferne häufige Stoppwörter (vereinfacht)
        const stopWords = new Set([
            'der', 'die', 'das', 'und', 'oder', 'aber', 'ist', 'sind', 'war', 'waren',
            'ein', 'eine', 'einer', 'einem', 'einen', 'the', 'a', 'an', 'and', 'or', 'but',
            'is', 'are', 'was', 'were', 'in', 'on', 'at', 'to', 'for', 'of', 'with'
        ]);

        // Teile Text in Wörter
        const words = text.toLowerCase()
            .replace(/[^\w\s]/g, ' ')
            .split(/\s+/)
            .filter(word => word.length > 3 && !stopWords.has(word));

        // Zähle Häufigkeit
        const wordCount = {};
        words.forEach(word => {
            wordCount[word] = (wordCount[word] || 0) + 1;
        });

        // Sortiere nach Häufigkeit
        const sorted = Object.entries(wordCount)
            .sort((a, b) => b[1] - a[1])
            .slice(0, maxKeywords)
            .map(entry => entry[0]);

        return sorted;
    }

    /**
     * Bereitet Text für die API vor
     * @param {string} text - Der Text
     * @param {Object} options - Optionen
     * @returns {Object} - Vorbereitete Daten
     */
    prepareTextForAPI(text, options = {}) {
        const {
            includeSummary = false,
            includeKeywords = false,
            chunkIfNeeded = true
        } = options;

        const result = {
            originalText: text,
            processedText: text
        };

        if (includeSummary) {
            result.summary = this.createSummary(text);
        }

        if (includeKeywords) {
            result.keywords = this.extractKeywords(text);
        }

        if (chunkIfNeeded && text.length > this.maxChunkSize) {
            result.chunks = this.chunkText(text);
            result.isChunked = true;
        }

        return result;
    }

    /**
     * Kombiniert Frage mit Dateikontext
     * @param {string} question - Die Frage
     * @param {string} fileContext - Kontext aus der Datei
     * @returns {string} - Kombinierter Text
     */
    combineQuestionWithContext(question, fileContext) {
        if (!fileContext) {
            return question;
        }

        // Prüfe, ob es CSV-Daten sind
        const isCSVData = fileContext.includes('=== CSV-DATENBANK ===') || 
                         fileContext.includes('Datensatz #');

        if (isCSVData) {
            // Für CSV-Daten: Vollständigen Kontext verwenden und spezielle Anweisungen
            return `Du hast Zugriff auf eine anonymisierte CSV-Datenbank. Die Daten wurden anonymisiert, aber die Struktur und Beziehungen bleiben erhalten.

WICHTIG - WIE DU MIT ANONYMISIERTEN DATEN ARBEITEST:

1. NAMEN-SUCHEN:
   - Wenn jemand nach einem Namen wie "Anna", "Max", etc. fragt, behandle dies als Suche nach ALLEN anonymisierten Namen-Werten
   - Du weißt NICHT, welcher [NAME_X] welchem Originalnamen entspricht (das ist der Sinn der Anonymisierung)
   - ABER: Wenn nach einem Namen gefragt wird, durchsuche ALLE Datensätze und gib ALLE relevanten Informationen zurück
   - Beispiel: "Wo arbeitet Anna?" → Zeige ALLE Datensätze mit anonymisierten Namen und deren Arbeitsorte
   - Beispiel: "Zeige mir alle Informationen über Max" → Zeige ALLE Datensätze mit anonymisierten Namen

2. ALLGEMEINE ABFRAGEN:
   - Du kannst SQL-ähnliche Abfragen beantworten: "Zeige alle Datensätze wo...", "Wie viele...", "Welche Werte...", etc.
   - Verwende die Datensatz-Nummern (#1, #2, etc.) für Referenzen
   - Die anonymisierten Werte sind konsistent: derselbe Originalwert hat immer denselben anonymisierten Wert

3. ANTWORT-FORMAT:
   - Wenn nach einem spezifischen Namen gefragt wird, aber du nicht weißt, welcher [NAME_X] das ist:
     * Erkläre kurz, dass die Originalnamen anonymisiert sind
     * Zeige dann ALLE relevanten Datensätze mit anonymisierten Namen
     * Formatiere die Antwort klar mit Datensatz-Nummern
   - Beispiel-Format: "Der Mitarbeiter mit [NAME_1] arbeitet in [ABTEILUNG] (Datensatz #1)"

=== DATENBANK-INHALT ===

${fileContext}

=== FRAGE ===

${question}

ANWEISUNG: Beantworte die Frage wie eine Datenbankabfrage. 
- Wenn nach einem Namen gefragt wird: Zeige ALLE Datensätze mit anonymisierten Namen und den relevanten Informationen
- Verwende die Datensatz-Nummern für Referenzen
- Erkläre nicht, dass du nicht weißt welcher [NAME_X] welchem Namen entspricht - das ist normal bei anonymisierten Daten
- Gib einfach alle relevanten Informationen zurück, die zur Frage passen`;
        } else {
            // Für andere Dateitypen: Standard-Kontext
            const summary = this.createSummary(fileContext, 2000);
            return `Kontext aus hochgeladener Datei:\n\n${summary}\n\n---\n\nFrage: ${question}\n\nBitte beantworte die Frage basierend auf dem bereitgestellten Kontext.`;
        }
    }
}

// Export für Verwendung in anderen Modulen
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Vectorizer;
}

