# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Dies ist eine Symfony 7.3 Website für krausgedruckt (3D-Druckdienstleistungen), gehostet mit DDEV. Die Seite verwendet Twig-Templates mit Tailwind CSS für das Styling.

## Development Environment

### DDEV Setup
- Projekt läuft in DDEV mit PHP 8.2, nginx-fpm und MariaDB 10.4
- URL: https://krausgedruckt.ddev.site
- Start: `ddev start`
- Stop: `ddev stop`
- SSH in Container: `ddev ssh`

### Common Commands

**Symfony Console:**
```bash
ddev exec bin/console <command>
# oder innerhalb des Containers:
bin/console <command>
```

**Cache leeren:**
```bash
ddev exec bin/console cache:clear
```

**Tailwind CSS kompilieren:**
```bash
# Development mit Watch-Mode (binary ist in bin/tailwindcss):
ddev exec bin/tailwindcss -i public/css/input.css -o public/css/output.css --watch

# Production Build (minified):
ddev exec bin/tailwindcss -i public/css/input.css -o public/css/output.css --minify
```

**Composer:**
```bash
ddev composer install
ddev composer require <package>
ddev composer update
```

## Architecture

### Data Flow & Content Management
- Projekt nutzt **keine relationale Datenbank** für Content
- JSON-Dateien in `config/` dienen als Content-Datenbank (z.B. `faq.json`, `references.json`, `advintage-landing-page.json`)
- **Flow:** JSON → Symfony Serializer → Entity DTOs → Twig Templates
- Entities in `src/Entity/` sind reine DTOs ohne Doctrine-Annotations (z.B. `PrintableModel`, `Reference`, `Question`)

### Controller Structure
- Alle Routes sind in `src/Controller/DefaultController.php` definiert mit PHP 8 Attributes
- Controller instanziiert Symfony Serializer im Konstruktor
- Jede Route lädt ihre Content-Daten via `$this->serializer->deserialize()` aus den JSON-Dateien
- Beispiel: `/advintage` Route lädt `config/advintage-landing-page.json` und deserialisiert zu `PrintableModel[]`

### Template Organization
- Base-Template: `templates/base.html.twig`
- Seiten-Templates: `templates/default/*.html.twig`
- Wiederverwendbare Komponenten: `templates/_*.html.twig` (z.B. `_model.html.twig`)
- Custom Form-Layout: `templates/form_layout.html.twig`

### Styling with Tailwind
- Input CSS: `public/css/input.css`
- Output CSS: `public/css/output.css` (wird via `bin/tailwindcss` binary generiert)
- Config: `tailwind.config.js` mit custom Brand-Colors (Orange/Gray Theme)
- Plugins: `@tailwindcss/forms` und `@tailwindcss/typography` sind aktiv
- Templates müssen im `content` Array der Tailwind-Config definiert sein
- **Wichtig:** Nach Template-Änderungen muss Tailwind neu kompiliert werden

### Forms & Anti-Spam
- Contact Form verwendet `ContactRequestType` mit Omines Anti-Spam Bundle
- Mail-Versand über Symfony Mailer mit `TemplatedEmail`
- Form hat 4 Felder: `name`, `email`, `message`, `discountCode`
- E-Mail-Template: `templates/default/contact.txt.twig`
- Discount-Code kann via Query-Parameter vorausgefüllt werden: `/kontakt?discount-code=CODE`
- Sender/Recipient-Adressen in `.env` konfiguriert:
  - `CONTACT_FORM_SENDER_ADDRESS`
  - `CONTACT_FORM_RECIPIENT_ADDRESS`
- `.env.local` überschreibt diese für lokale/production Umgebungen

### Static Assets
- Bilder: `public/images/`
- Landing Page Bilder: `public/images/advintage-landing-page/`
- Referenz-Bilder: `public/images/references/{id}/`

## Important Notes

- **Content-Updates:** Um Content zu ändern, bearbeite die JSON-Dateien in `config/` (keine Datenbank-Migrations nötig)
- **Routing:** Alle Routen nutzen deutsche URLs (z.B. `/kontakt`, `/referenzen`, `/impressum`)
- **Database:** MariaDB wird aktuell nicht aktiv genutzt (nur für potentielle zukünftige Features)
- **Environment:** `.env.local` sollte nie committed werden und enthält lokale Overrides
