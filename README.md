# test1

FTP-Webspace-Zugriff für Cursor Cloud Agents.

## MCP `ftp-webspace`

Der Server liegt unter `.mcp/mcp-server-ftp/` und wird von Cursor als

`node /workspace/.mcp/mcp-server-ftp/build/index.js`

gestartet. Beim ersten Start werden npm-Dependencies bei Bedarf installiert.

## Direktzugriff (ohne MCP)

```bash
node scripts/ftp.mjs check
node scripts/ftp.mjs list /
node scripts/ftp.mjs download <remote> [local]
node scripts/ftp.mjs upload <local> <remote>
```

Benötigt die Cloud-Agent-Secrets `FTP_HOST`, `FTP_USER`, `FTP_PASSWORD`, optional `FTP_PORT`, `FTP_SECURE`.
