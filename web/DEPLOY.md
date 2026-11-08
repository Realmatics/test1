# Realmatics — Go-Live auf dem Webspace

## Was die App kann

- Öffentliche Landingpage mit Preisen
- Registrierung / Login
- **Decision Brief** (Kern)
- **Offer Score** und **Change Impact Check** (ab Plan Team)
- Brief-Historie, Monatskontingente, Trial-Briefs
- Demo-Modus ohne API-Key; echter KI-Modus mit `openai_api_key`

## Lokal

```bash
cd web
cp config.sample.php config.php
php -S 127.0.0.1:8080 router.php
```

## FTP-Deploy

Der FTP-Account enthält die App-Dateien (`index.php`, `lib/`, `views/`, …)
sowie `install_bundle.php` als Fallback.

### Wichtig: Document Root

Der FTP-Login ist **nur dann** die Kunden-Website, wenn die Domain / Subdomain
im Hosting-Panel auf genau dieses Verzeichnis zeigt.

Aktuell liefert die Domain/IP für PHP-Dateien oft **404** — dann ist das
Document Root ein anderes Verzeichnis. Im 1blu/Kundenpanel:

1. Domain oder Subdomain auf dieses FTP-Verzeichnis legen  
   **oder** die Dateien in das echte `htdocs`/`httpdocs` kopieren
2. Danach im Browser öffnen:

```
https://DEINE-DOMAIN/install_bundle.php?key=rm-install-7f3a
```

Das schreibt/repariert alle App-Dateien und legt `config.php` an, falls fehlend.
Anschließend `install_bundle.php` löschen.

### Config

In `config.php`:

- `openai_api_key` — für echten KI-Modus
- `admin_setup_key` — zum Freischalten von Team/Pro unter `/upgrade`
- optional Stripe Payment Links

Aktuell auf dem Server (Demo): Setup-Key `rm-live-setup-change-me` — bitte ändern.

### Apache

`mod_rewrite` + `AllowOverride` für `.htaccess`. `data/` muss beschreibbar sein.
