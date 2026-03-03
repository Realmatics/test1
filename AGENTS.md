## Cursor Cloud specific instructions

### FTP Webspace MCP Server

Dieses Repository enthält einen konfigurierten MCP Server für FTP-Webspace-Zugriff (`mcp-server-ftp`).

**Aufbau:**
- `mcp-server-ftp/` – Geklonter und gebauter MCP Server (von [alxspiker/mcp-server-ftp](https://github.com/alxspiker/mcp-server-ftp))
- `.cursor/mcp.json` – Cursor MCP-Konfiguration mit Platzhalter-Werten

**Verfügbare MCP Tools:**
| Tool | Beschreibung |
|------|-------------|
| `list-directory` | Verzeichnisinhalt auf dem FTP-Server auflisten |
| `download-file` | Datei vom FTP-Server herunterladen |
| `upload-file` | Datei auf den FTP-Server hochladen |
| `create-directory` | Verzeichnis auf dem FTP-Server erstellen |
| `delete-file` | Datei vom FTP-Server löschen |
| `delete-directory` | Verzeichnis vom FTP-Server löschen |

**FTP-Zugangsdaten konfigurieren:**
Die FTP-Zugangsdaten müssen in `.cursor/mcp.json` unter `env` eingetragen werden:
- `FTP_HOST` – Hostname des FTP-Servers
- `FTP_PORT` – Port (Standard: 21)
- `FTP_USER` – FTP-Benutzername
- `FTP_PASSWORD` – FTP-Passwort
- `FTP_SECURE` – FTPS verwenden (`true`/`false`)

Alternativ können diese als Cursor Cloud Secrets eingerichtet werden (`FTP_HOST`, `FTP_PORT`, `FTP_USER`, `FTP_PASSWORD`, `FTP_SECURE`), dann muss `.cursor/mcp.json` entsprechend auf die Umgebungsvariablen verweisen.

**MCP Server neu bauen (falls nötig):**
```bash
cd mcp-server-ftp && npm install && npm run build
```
