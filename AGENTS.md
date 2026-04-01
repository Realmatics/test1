## Cursor Cloud specific instructions

### Repository Structure

This repository contains three PHP web applications (each on a separate branch) and MCP deployment tools:

| Branch | Application | Description |
|--------|-------------|-------------|
| `portfolio-mit-backend` | Portfolio CMS | PHP admin panel + static HTML generator |
| `copy-website` | Website Cloner | Portfolio CMS + website cloning tool |
| `ki-anonym-browser-google` | Anonymous AI Chat | Gemini API proxy with data anonymization |
| `cursor/development-environment-setup-e081` | MCP Servers | SFTP/FTP deployment tools (Node.js) |

### Running the PHP Applications

All three PHP apps are plain PHP (no frameworks, no Composer) and can be served with the built-in PHP server:

```bash
# Check out the branch you want to work on, then:
php -S localhost:8000
```

- **No build step** required for any PHP app.
- **No database** — all persistence is file-based (`config.json`).
- PHP extensions required: `curl`, `json`, `mbstring`, `session`, `xml` (installed via `php php-cli php-curl php-mbstring php-xml`).

### Portfolio Admin Panel

- Default login password: `admin123` (hardcoded in `admin.php`, line 6).
- Workflow: edit in admin → "Speichern und Vorschau generieren" → "Vorschau freigeben" to publish.
- Generated preview files use `*_vorschau.html` suffix; released files overwrite originals.

### AI Chat (ki-anonym-browser-google)

- Requires a valid Google Gemini API key in `api.php` (currently hardcoded, may be expired/leaked).
- The frontend works without a valid API key — it displays error messages from the API.
- PHP `curl` extension is required for the API proxy.

### MCP Servers (on development-environment-setup-e081 branch)

- `mcp-server-sftp/`: run `npm install` then use via Cursor MCP config
- `mcp-server-ftp/`: run `npm install && npm run build` (TypeScript)
- See the existing AGENTS.md on that branch for detailed MCP setup instructions.

### Linting / Testing

- No automated test framework or linter is configured in this repository.
- Manual testing: start PHP server, open in browser, interact with the UI.
- `test_system.php` (on portfolio branches) runs basic system checks for PHP config, file permissions, and HTML generation.
