# AGENTS.md

## Cursor Cloud specific instructions

This is a flat PHP portfolio website with an admin CMS backend. There is no package manager, no build system, and no lockfile.

### Running the dev server

```bash
cd /workspace && php -S 0.0.0.0:8080
```

The PHP built-in server serves all files from `/workspace`. Access the portfolio at `http://localhost:8080/index.html` and the admin panel at `http://localhost:8080/admin.php`.

### Key details

- **Admin login**: Default password is `admin123` (set in `admin.php` line 9).
- **Config storage**: All CMS data is stored in `config.json` (flat file, no database).
- **HTML generation**: Saving in admin generates `*_vorschau.html` preview files. "Vorschau freigeben" copies previews over the live HTML files.
- **PHP extensions required**: `curl` (for `clone_website.php`), `json`, `session`, `mbstring`.
- **No lint/test/build tooling**: This project has no linter, test framework, or build pipeline. Validation is done by running PHP syntax checks (`php -l *.php`) and manually testing in the browser.
- **Rich text editors**: The admin panel loads Quill.js and CKEditor 5 from CDNs, so internet access is needed for full admin functionality.

### PHP syntax check (closest to "lint")

```bash
for f in /workspace/*.php; do php -l "$f"; done
```
