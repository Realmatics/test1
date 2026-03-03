# Portfolio Backend-System

## Übersicht

Dieses PHP-Backend-System ermöglicht es Ihnen, alle relevanten Inhalte Ihrer Portfolio-Website über eine benutzerfreundliche Weboberfläche zu bearbeiten. Nach dem Speichern der Änderungen werden automatisch neue HTML-Dateien mit dem Suffix "_new" generiert.

## Dateien

- `admin.php` - Hauptadministrationsoberfläche
- `generate_html.php` - HTML-Generator für die neuen Dateien
- `config.json` - Konfigurationsdatei (wird automatisch erstellt)

## Installation

1. Laden Sie alle PHP-Dateien in Ihr Webverzeichnis hoch
2. Stellen Sie sicher, dass PHP auf Ihrem Server aktiviert ist
3. Öffnen Sie `admin.php` in Ihrem Browser

## Erste Anmeldung

- **URL**: `http://ihre-domain.de/admin.php`
- **Standard-Passwort**: `admin123`

⚠️ **WICHTIG**: Ändern Sie das Passwort in der Datei `admin.php` (Zeile 6) vor der Produktionsnutzung!

## Funktionen

### Allgemeine Einstellungen
- Website-Titel
- Hero-Titel und Untertitel
- Über-mich-Text
- Fähigkeiten (kommagetrennt)
- Footer-Text
- Social Media Links (GitHub, LinkedIn, Twitter)

### Projekte verwalten
- Unbegrenzte Anzahl von Projekten
- Für jedes Projekt: Titel, Beschreibung, Bild-URL, Demo-Link, GitHub-Link
- Projekte hinzufügen/entfernen über die Benutzeroberfläche

### Impressum
- Vollständige Kontaktdaten
- Umsatzsteuer-ID
- Berufsbezeichnung
- Kammer-Informationen

### Datenschutzerklärung
- Kontaktdaten für Datenschutzanfragen

## Generierte Dateien

Nach dem Speichern werden folgende Dateien erstellt:
- `index_new.html` - Aktualisierte Hauptseite
- `impressum_new.html` - Aktualisiertes Impressum
- `datenschutz_new.html` - Aktualisierte Datenschutzerklärung

## Workflow

1. Melden Sie sich im Admin-Bereich an
2. Bearbeiten Sie die gewünschten Inhalte in den verschiedenen Tabs
3. Klicken Sie auf "Speichern und HTML generieren"
4. Die neuen HTML-Dateien werden automatisch erstellt
5. Prüfen Sie die generierten Dateien
6. Bei Zufriedenheit können Sie die "_new" Dateien über die originalen kopieren

## Sicherheitshinweise

1. **Passwort ändern**: Ändern Sie das Standard-Passwort in `admin.php`
2. **Zugriff beschränken**: Beschränken Sie den Zugriff auf `admin.php` über .htaccess
3. **Backup**: Erstellen Sie regelmäßig Backups Ihrer `config.json`
4. **SSL**: Verwenden Sie HTTPS für den Admin-Bereich

## Beispiel .htaccess für Admin-Schutz

```apache
<Files "admin.php">
    AuthType Basic
    AuthName "Admin Area"
    AuthUserFile /pfad/zu/.htpasswd
    Require valid-user
</Files>
```

## Fehlerbehebung

### Häufige Probleme

1. **"Konfiguration nicht geladen"**
   - Prüfen Sie die Schreibrechte im Verzeichnis
   - Stellen Sie sicher, dass PHP JSON-Funktionen unterstützt

2. **HTML-Dateien werden nicht generiert**
   - Prüfen Sie die Schreibrechte im Verzeichnis
   - Überprüfen Sie die PHP-Fehlerprotokolle

3. **Styling fehlt in generierten Dateien**
   - Stellen Sie sicher, dass `styles.css` und `script.js` im gleichen Verzeichnis sind
   - Prüfen Sie die Pfade in den generierten HTML-Dateien

## Anpassungen

### Neue Felder hinzufügen

1. Erweitern Sie die Standard-Konfiguration in `admin.php`
2. Fügen Sie Formularfelder in der Admin-Oberfläche hinzu
3. Erweitern Sie die Verarbeitung in der `save_config` Sektion
4. Passen Sie die HTML-Generator-Funktionen in `generate_html.php` an

### Styling anpassen

Das Admin-Interface verwendet inline CSS. Sie können das Styling direkt in `admin.php` anpassen oder eine externe CSS-Datei einbinden.

## Support

Bei Problemen oder Fragen:
1. Prüfen Sie die PHP-Fehlerprotokolle
2. Stellen Sie sicher, dass alle Dateiberechtigungen korrekt sind
3. Überprüfen Sie die Browser-Konsole auf JavaScript-Fehler

## Lizenz

Dieses Backend-System steht unter der MIT-Lizenz und kann frei verwendet und angepasst werden. 