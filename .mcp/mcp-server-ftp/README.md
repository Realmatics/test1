# ftp-webspace MCP

Repariert den Cursor-MCP `ftp-webspace`, der unter

`/workspace/.mcp/mcp-server-ftp/build/index.js`

erwartet wird.

## Verhalten

- Korrigiert ungültige MCP-Env-Werte (z. B. `FTP_HOST=hj`) durch Lesen der Cloud-Agent-Secrets aus Parent-Prozessen.
- Listing über FTP-`STAT` (Control-Channel, zuverlässig hinter NAT).
- Upload/Download über `curl --ftp-pret` (Pure-FTPd) mit Retries.
- Delete/Rename/Mkdir über Control-Channel-Kommandos.

## Direkt ohne MCP

```bash
node scripts/ftp.mjs check
node scripts/ftp.mjs list /
node scripts/ftp.mjs download test34.php /tmp/test34.php
node scripts/ftp.mjs upload ./local.txt remote.txt
```

## Dependencies

```bash
cd .mcp/mcp-server-ftp && npm install --omit=dev
```

`build/index.js` installiert fehlende Dependencies beim Start automatisch.
