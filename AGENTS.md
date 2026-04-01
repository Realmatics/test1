# AGENTS.md

## Cursor Cloud specific instructions

### Repository Structure

This repo uses **separate git branches** for each product (not a monorepo with directories). The `main` branch contains only a placeholder README. All application code lives on feature branches:

| Branch | Product | Description |
|--------|---------|-------------|
| `portfolio-mit-backend` | Portfolio CMS | PHP admin panel + static site generator |
| `copy-website` | Website Cloner | PHP-based website cloning tool (extends portfolio) |
| `ki-anonym-browser-google` | AI Chat | Browser-based Google Gemini chat with anonymization |

### Tech Stack

- **Backend**: PHP 8.x (vanilla, no framework, no Composer)
- **Frontend**: Vanilla HTML/CSS/JS (no build step, no npm)
- **Data**: JSON files (`config.json`) — no database required
- **No package managers**: No `package.json`, `composer.json`, or `requirements.txt`

### Running the Applications

To serve any branch locally, extract its files and use PHP's built-in server:

```bash
# Extract branch files to a working directory
git archive <branch-name> | tar -x -C /path/to/dir

# Start PHP dev server
php -S 0.0.0.0:8000 -t /path/to/dir
```

Suggested port assignments:
- Portfolio CMS (`portfolio-mit-backend`): port 8000
- AI Chat (`ki-anonym-browser-google`): port 8001

### Linting

There are no dedicated linter configs. Use `php -l <file>` to syntax-check PHP files:

```bash
for f in *.php; do php -l "$f"; done
```

### Testing

- **Portfolio CMS**: `test_system.php` runs automated checks (config creation, HTML generation, file permissions). Access via `http://localhost:8000/test_system.php`.
- **Portfolio Admin**: Login with password `admin123` at `/admin.php`.
- **AI Chat**: The UI works client-side. API calls require a valid Google Gemini API key in `api.php`.

### Important Caveats

- The portfolio generates `*_vorschau.html` preview files; the working directory must be writable by the PHP process.
- The AI Chat `api.php` contains a hardcoded API key that is revoked/leaked. A valid `GEMINI_API_KEY` must be set for actual AI responses.
- `send-mail.php` uses PHP `mail()` which requires a configured MTA; not needed for core CMS testing.
