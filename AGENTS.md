# AGENTS.md

## Cursor Cloud specific instructions

This is a PHP portfolio website with a CMS admin backend (German language). There are no package managers, no build steps, and no databases — all content is stored in `config.json`.

### Running the development server

```bash
php -S 0.0.0.0:8000
```

Run from the workspace root (`/workspace`). This serves both static files (`index.html`, `styles.css`, `script.js`) and PHP files (`admin.php`, `generate_html.php`, etc.).

### Linting

PHP has no separate lint tool to install. Use the built-in syntax checker:

```bash
for f in *.php; do php -l "$f"; done
```

### Key endpoints

- **Portfolio frontend**: `http://localhost:8000/index.html`
- **Admin panel**: `http://localhost:8000/admin.php` (password: `admin123`)
- **System test**: `http://localhost:8000/test_system.php`

### Gotchas

- The admin panel uses PHP sessions. The PHP built-in server handles this fine, but sessions require cookies — use a browser (not just curl) for full admin testing.
- Saving in the admin panel generates `*_vorschau.html` preview files. These are not committed and are deleted on logout.
- Rich-text editors (Quill.js, CKEditor 5) are loaded from CDNs — internet access is required for the admin panel to fully render.
- `send-mail.php` uses PHP's `mail()` function which requires a local MTA. This will silently fail without sendmail/postfix, but doesn't affect core functionality.
- `clone_website.php` requires the PHP cURL extension (`php-curl`).
- See `README_Backend.md` for detailed backend documentation.
