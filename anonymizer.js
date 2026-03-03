/**
 * Anonymisierungs-Modul für Browser-basierte Textverarbeitung
 */
class Anonymizer {
    constructor() {
        this.patterns = {
            // E-Mail-Adressen
            email: /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g,
            
            // Telefonnummern (verschiedene Formate)
            phone: /(\+?\d{1,3}[-.\s]?)?\(?\d{1,4}\)?[-.\s]?\d{1,4}[-.\s]?\d{1,9}/g,
            
            // IP-Adressen
            ip: /\b(?:\d{1,3}\.){3}\d{1,3}\b/g,
            
            // URLs
            url: /https?:\/\/[^\s]+/g,
            
            // Kreditkartennummern (vereinfacht)
            creditCard: /\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/g,
            
            // Postleitzahlen (DE Format)
            postalCode: /\b\d{5}\b/g,
            
            // IBAN (vereinfacht)
            iban: /\b[A-Z]{2}\d{2}[A-Z0-9]{4,30}\b/g,
        };
        
        this.replacements = {
            email: '[E-MAIL_ANONYMISIERT]',
            phone: '[TELEFON_ANONYMISIERT]',
            ip: '[IP_ANONYMISIERT]',
            url: '[URL_ANONYMISIERT]',
            creditCard: '[KREDITKARTE_ANONYMISIERT]',
            postalCode: '[PLZ_ANONYMISIERT]',
            iban: '[IBAN_ANONYMISIERT]'
        };

        // Mapping-Tabellen für konsistente Anonymisierung
        this.valueMappings = {
            names: new Map(),      // Original -> Anonymisiert
            emails: new Map(),
            phones: new Map(),
            other: new Map()
        };
        this.counter = {
            names: 1,
            emails: 1,
            phones: 1,
            other: 1
        };
    }

    /**
     * Anonymisiert Text nach vordefinierten Mustern
     * @param {string} text - Der zu anonymisierende Text
     * @param {boolean} consistent - Ob konsistente Anonymisierung verwendet werden soll
     * @returns {string} - Anonymisierter Text
     */
    anonymize(text, consistent = false) {
        if (!text || typeof text !== 'string') {
            return text;
        }

        let anonymizedText = text;

        if (consistent) {
            // Konsistente Anonymisierung für strukturierte Daten
            anonymizedText = this.anonymizeConsistent(text);
        } else {
            // Standard-Anonymisierung
            for (const [key, pattern] of Object.entries(this.patterns)) {
                anonymizedText = anonymizedText.replace(pattern, this.replacements[key]);
            }
            anonymizedText = this.anonymizeNames(anonymizedText);
        }

        return anonymizedText;
    }

    /**
     * Konsistente Anonymisierung: gleiche Werte werden durch gleiche anonymisierte Werte ersetzt
     * @param {string} text - Der zu anonymisierende Text
     * @returns {string} - Anonymisierter Text
     */
    anonymizeConsistent(text) {
        let result = text;

        // E-Mails konsistent anonymisieren
        result = result.replace(this.patterns.email, (match) => {
            if (!this.valueMappings.emails.has(match)) {
                this.valueMappings.emails.set(match, `[EMAIL_${this.counter.emails++}]`);
            }
            return this.valueMappings.emails.get(match);
        });

        // Telefonnummern konsistent anonymisieren
        result = result.replace(this.patterns.phone, (match) => {
            if (!this.valueMappings.phones.has(match)) {
                this.valueMappings.phones.set(match, `[TELEFON_${this.counter.phones++}]`);
            }
            return this.valueMappings.phones.get(match);
        });

        // Andere Muster
        for (const [key, pattern] of Object.entries(this.patterns)) {
            if (key !== 'email' && key !== 'phone') {
                result = result.replace(pattern, this.replacements[key]);
            }
        }

        return result;
    }

    /**
     * Konsistente Namensanonymisierung für CSV-Daten
     * @param {string} name - Der Name
     * @returns {string} - Anonymisierter Name mit konsistenter ID
     */
    anonymizeNameConsistent(name) {
        if (!name || typeof name !== 'string' || name.trim().length === 0) {
            return name;
        }

        const trimmedName = name.trim();
        
        // Prüfe, ob es ein häufiger Name ist (kann erweitert werden)
        const isCommonName = /^[A-ZÄÖÜ][a-zäöüß]+$/.test(trimmedName);
        
        if (isCommonName && !this.valueMappings.names.has(trimmedName)) {
            this.valueMappings.names.set(trimmedName, `[NAME_${this.counter.names++}]`);
        }

        if (this.valueMappings.names.has(trimmedName)) {
            return this.valueMappings.names.get(trimmedName);
        }

        return trimmedName;
    }

    /**
     * Einfache Namensanonymisierung (Heuristik)
     * Erkennt häufige deutsche Vornamen und Nachnamen
     * @param {string} text - Der Text
     * @returns {string} - Text mit anonymisierten Namen
     */
    anonymizeNames(text) {
        // Liste häufiger deutscher Namen (vereinfacht)
        const commonNames = [
            'Max', 'Anna', 'Paul', 'Emma', 'Lukas', 'Sophia', 'Felix', 'Hannah',
            'Müller', 'Schmidt', 'Schneider', 'Fischer', 'Weber', 'Meyer', 'Wagner', 'Becker'
        ];

        let result = text;
        const namePattern = new RegExp(`\\b(${commonNames.join('|')})\\b`, 'gi');
        result = result.replace(namePattern, '[NAME_ANONYMISIERT]');

        return result;
    }

    /**
     * Anonymisiert Dateiinhalt basierend auf MIME-Type
     * @param {string|ArrayBuffer} fileContent - Dateiinhalt
     * @param {string} mimeType - MIME-Type der Datei
     * @param {string} fileName - Dateiname (optional, für CSV-Erkennung)
     * @returns {Promise<{text: string, anonymized: boolean}>}
     */
    async anonymizeFile(fileContent, mimeType, fileName = '') {
        try {
            let text = '';

            // CSV-Erkennung: durch MIME-Type oder Dateiendung
            const isCSV = mimeType === 'text/csv' || 
                         mimeType === 'application/csv' ||
                         fileName.toLowerCase().endsWith('.csv');

            if (isCSV) {
                // CSV-Dateien: Parsen und Anonymisieren
                const csvResult = await this.processCSV(fileContent);
                // processCSV gibt jetzt ein Objekt mit text und nameMapping zurück
                if (typeof csvResult === 'object' && csvResult.nameMapping) {
                    return {
                        text: csvResult.text,
                        anonymized: true,
                        isImage: false,
                        nameMapping: csvResult.nameMapping
                    };
                } else {
                    // Fallback falls processCSV nur Text zurückgibt
                    text = typeof csvResult === 'string' ? csvResult : csvResult.text;
                }
            } else if (mimeType.startsWith('text/')) {
                // Textdateien
                if (typeof fileContent === 'string') {
                    text = fileContent;
                } else {
                    const decoder = new TextDecoder('utf-8');
                    text = decoder.decode(fileContent);
                }
            } else if (mimeType === 'application/pdf') {
                // PDF-Dateien - benötigt pdf.js
                text = await this.extractTextFromPDF(fileContent);
            } else if (mimeType.startsWith('image/')) {
                // Bilder werden direkt an die API gesendet, keine Text-Extraktion
                return {
                    text: null,
                    anonymized: false,
                    isImage: true,
                    data: fileContent
                };
            } else {
                // Andere Dateitypen
                text = 'Dateiinhalt konnte nicht extrahiert werden.';
            }
            
            // Für nicht-CSV-Dateien: Standard-Anonymisierung
            const anonymizedText = this.anonymize(text);
            
            return {
                text: anonymizedText,
                anonymized: anonymizedText !== text,
                isImage: false
            };
        } catch (error) {
            console.error('Fehler bei der Anonymisierung:', error);
            throw error;
        }
    }

    /**
     * Verarbeitet CSV-Dateien: Parsen, Anonymisieren und Formatieren
     * @param {string|ArrayBuffer} csvContent - CSV-Inhalt
     * @returns {Promise<string>} - Formatierter und anonymisierter CSV-Text
     */
    async processCSV(csvContent) {
        try {
            // Mapping-Tabellen zurücksetzen für neue Datei
            this.valueMappings = {
                names: new Map(),
                emails: new Map(),
                phones: new Map(),
                other: new Map()
            };
            this.counter = {
                names: 1,
                emails: 1,
                phones: 1,
                other: 1
            };

            // CSV-Inhalt als Text lesen
            let csvText = '';
            if (typeof csvContent === 'string') {
                csvText = csvContent;
            } else {
                const decoder = new TextDecoder('utf-8');
                csvText = decoder.decode(csvContent);
            }

            // CSV parsen (einfache Implementierung)
            const lines = csvText.split(/\r?\n/).filter(line => line.trim());
            if (lines.length === 0) {
                return 'CSV-Datei ist leer.';
            }

            // Header extrahieren
            const headerLine = lines[0];
            const headers = this.parseCSVLine(headerLine);
            
            // Datenzeilen parsen und anonymisieren
            const processedRows = [];
            const originalRows = []; // Für Referenz
            
            for (let i = 1; i < lines.length; i++) {
                const row = this.parseCSVLine(lines[i]);
                originalRows.push([...row]);
                
                // Anonymisiere jede Zelle mit konsistenter Anonymisierung
                const anonymizedRow = row.map((cell, idx) => {
                    // Prüfe Spaltennamen für bessere Anonymisierung
                    const headerName = headers[idx] ? headers[idx].toLowerCase() : '';
                    const isNameColumn = headerName.includes('name') || 
                                       headerName.includes('vorname') || 
                                       headerName.includes('nachname');
                    
                    if (isNameColumn) {
                        return this.anonymizeNameConsistent(cell);
                    } else {
                        // Für andere Spalten: konsistente Anonymisierung
                        return this.anonymize(cell, true);
                    }
                });
                processedRows.push(anonymizedRow);
            }

            // Formatieren als strukturierte Datenbank-ähnliche Darstellung für die API
            const formattedText = this.formatCSVForAPIStructured(headers, processedRows, originalRows);
            
            // Erstelle Mapping-Tabelle für Browser-Cache (Original -> Anonymisiert)
            const nameMapping = {};
            this.valueMappings.names.forEach((anonymized, original) => {
                nameMapping[original.toLowerCase()] = anonymized;
            });
            
            return {
                text: formattedText,
                nameMapping: nameMapping  // Mapping für Browser-Cache
            };
        } catch (error) {
            console.error('Fehler beim Verarbeiten der CSV:', error);
            throw new Error('CSV konnte nicht verarbeitet werden: ' + error.message);
        }
    }

    /**
     * Parst eine CSV-Zeile (einfache Implementierung, unterstützt Anführungszeichen)
     * @param {string} line - CSV-Zeile
     * @returns {Array<string>} - Array von Zellenwerten
     */
    parseCSVLine(line) {
        const result = [];
        let current = '';
        let inQuotes = false;

        for (let i = 0; i < line.length; i++) {
            const char = line[i];
            const nextChar = line[i + 1];

            if (char === '"') {
                if (inQuotes && nextChar === '"') {
                    // Escaped quote
                    current += '"';
                    i++; // Skip next quote
                } else {
                    // Toggle quote state
                    inQuotes = !inQuotes;
                }
            } else if (char === ',' && !inQuotes) {
                // End of field
                result.push(current.trim());
                current = '';
            } else {
                current += char;
            }
        }
        
        // Add last field
        result.push(current.trim());
        
        return result;
    }

    /**
     * Formatiert CSV-Daten als strukturierte, durchsuchbare Darstellung für die API
     * @param {Array<string>} headers - Spaltenüberschriften
     * @param {Array<Array<string>>} anonymizedRows - Anonymisierte Datenzeilen
     * @param {Array<Array<string>>} originalRows - Original-Datenzeilen (für Referenz)
     * @returns {string} - Formatierter Text
     */
    formatCSVForAPIStructured(headers, anonymizedRows, originalRows) {
        let text = '=== CSV-DATENBANK ===\n\n';
        
        text += `Struktur:\n`;
        text += `- Anzahl Datensätze: ${anonymizedRows.length}\n`;
        text += `- Anzahl Spalten: ${headers.length}\n`;
        text += `- Spalten: ${headers.join(', ')}\n\n`;

        text += `=== DATENSÄTZE ===\n\n`;
        
        // Jede Zeile als strukturierter Datensatz
        anonymizedRows.forEach((row, index) => {
            text += `Datensatz #${index + 1}:\n`;
            
            headers.forEach((header, colIdx) => {
                const value = row[colIdx] || '';
                text += `  ${header}: ${value}\n`;
            });
            
            text += '\n';
        });

        // Mapping-Informationen für bessere Suche
        text += `=== ANONYMISIERUNGS-HINWEISE ===\n`;
        text += `Hinweis: Die Daten wurden anonymisiert, aber die Struktur bleibt erhalten.\n`;
        text += `Du kannst nach anonymisierten Werten suchen (z.B. [NAME_1], [NAME_2], etc.).\n`;
        text += `Jeder anonymisierte Wert entspricht einem eindeutigen Originalwert.\n\n`;

        // Erstelle eine Suchhilfe basierend auf den anonymisierten Namen
        const nameMappings = Array.from(this.valueMappings.names.entries());
        if (nameMappings.length > 0) {
            text += `Anonymisierte Namen in den Daten:\n`;
            nameMappings.forEach(([original, anonymized]) => {
                text += `  ${anonymized} (ursprünglich ein Name)\n`;
            });
            text += '\n';
        }

        // Zusammenfassung für Datenbankabfragen
        text += `=== FÜR ABFRAGEN ===\n`;
        text += `Du kannst SQL-ähnliche Abfragen stellen, z.B.:\n`;
        text += `- "Zeige alle Datensätze wo [Spaltenname] = [Wert]"\n`;
        text += `- "Wie viele Datensätze gibt es?"\n`;
        text += `- "Welche Werte gibt es in der Spalte [Spaltenname]?"\n`;
        text += `- "Zeige Datensatz #X"\n`;
        text += `- "Suche nach [NAME_X] in der Spalte [Spaltenname]"\n\n`;

        // Vollständige Tabelle als Referenz
        text += `=== VOLLSTÄNDIGE TABELLE ===\n\n`;
        
        const maxColWidth = 25;
        const formatCell = (cell) => {
            const str = String(cell || '');
            return str.length > maxColWidth ? str.substring(0, maxColWidth - 3) + '...' : str;
        };

        // Header
        text += headers.map(h => formatCell(h).padEnd(maxColWidth)).join(' | ') + '\n';
        text += '-'.repeat(headers.length * (maxColWidth + 3)) + '\n';
        
        // Alle Datenzeilen
        anonymizedRows.forEach((row, idx) => {
            const formattedRow = headers.map((_, colIdx) => 
                formatCell(row[colIdx] || '').padEnd(maxColWidth)
            );
            text += formattedRow.join(' | ') + `  (#${idx + 1})\n`;
        });

        return text;
    }

    /**
     * Extrahiert Text aus PDF (vereinfachte Version)
     * Für vollständige PDF-Unterstützung sollte pdf.js verwendet werden
     * @param {ArrayBuffer} pdfData - PDF-Daten
     * @returns {Promise<string>}
     */
    async extractTextFromPDF(pdfData) {
        // Hinweis: Für vollständige PDF-Unterstützung sollte pdf.js geladen werden
        // Diese ist eine vereinfachte Version
        try {
            // Versuche, Text aus PDF zu extrahieren
            // In einer echten Implementierung würde hier pdf.js verwendet
            return '[PDF-Inhalt wird verarbeitet. Für vollständige PDF-Unterstützung wird pdf.js benötigt.]';
        } catch (error) {
            console.error('PDF-Extraktion fehlgeschlagen:', error);
            return '[PDF-Inhalt konnte nicht extrahiert werden]';
        }
    }
}

// Export für Verwendung in anderen Modulen
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Anonymizer;
}