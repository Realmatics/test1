# Realmatics

Entscheidungs-Tools für Kunden: **Decision Brief**, **Offer Score**, **Change Impact Check**.

## App (`web/`)

PHP-Anwendung für Shared-Hosting / FTP-Webspace.

```bash
cd web
cp config.sample.php config.php   # API-Key & Setup-Key setzen
php -S 127.0.0.1:8080 router.php
```

Deploy nach FTP:

```bash
php scripts/deploy-web.php
```

Details: `web/DEPLOY.md`.

## FTP / MCP

```bash
node scripts/ftp.mjs check
node scripts/ftp.mjs list /
```
