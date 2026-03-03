## Cursor Cloud specific instructions

### SFTP Webspace MCP Server

Dieses Repository enthält einen maßgeschneiderten SFTP MCP Server für den Zugriff auf den Strato-Webspace.

**Aufbau:**
- `mcp-server-sftp/` – Eigener SFTP MCP Server (Node.js, ssh2-sftp-client basiert, Passwort-Auth)
- `mcp-server-ftp/` – FTP MCP Server (nur für reines FTP, nicht für Strato geeignet da SFTP-only)
- `.cursor/mcp.json` – Cursor MCP-Konfiguration (in `.gitignore`, nicht committet)
- `.cursor/mcp.json.example` – Vorlage ohne Zugangsdaten

**Wichtig:** Der Strato-Server erlaubt nur SFTP (Port 22), kein FTP (Port 21) und keinen Shell-Zugriff. Deshalb wird der `mcp-server-sftp` verwendet, nicht `mcp-server-ftp`.

**Ersteinrichtung der MCP-Konfiguration:**
```bash
cp .cursor/mcp.json.example .cursor/mcp.json
# Dann die Zugangsdaten in .cursor/mcp.json eintragen
```
Alternativ die Credentials als Cursor Cloud Secrets hinterlegen: `SFTP_HOST`, `SFTP_PORT`, `SFTP_USER`, `SFTP_PASSWORD`.

**Verfügbare MCP Tools:**
| Tool | Beschreibung |
|------|-------------|
| `list-directory` | Verzeichnisinhalt auf dem SFTP-Server auflisten |
| `download-file` | Datei vom SFTP-Server herunterladen (Textinhalt) |
| `upload-file` | Datei auf den SFTP-Server hochladen |
| `create-directory` | Verzeichnis auf dem SFTP-Server erstellen |
| `delete-file` | Datei vom SFTP-Server löschen |
| `delete-directory` | Verzeichnis vom SFTP-Server löschen |

**Umgebungsvariablen für den SFTP MCP Server:**
- `SFTP_HOST` – Hostname des SFTP-Servers
- `SFTP_PORT` – Port (Standard: 22)
- `SFTP_USER` – SFTP-Benutzername
- `SFTP_PASSWORD` – SFTP-Passwort

**MCP Server manuell testen:**
```bash
cd mcp-server-sftp
printf '...' | SFTP_HOST=... SFTP_USER=... SFTP_PASSWORD=... node index.js
```

**Abhängigkeiten neu installieren (falls nötig):**
```bash
cd mcp-server-sftp && npm install
```
