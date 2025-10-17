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
ddev exec bin/tailwindcss -i public/css/input.css -o public/css/output.css --watch
# Für Production Build:
ddev exec bin/tailwindcss -i public/css/input.css -o public/css/output.css --minify
```

**Composer:**
```bash
ddev composer install
ddev composer require <package>
ddev composer update
```

## Architecture

### Controller Structure
- Alle Routes sind in `src/Controller/DefaultController.php` definiert mit PHP 8 Attributes
- Controller lädt JSON-Konfigurationsdateien aus `config/` für Content (z.B. `faq.json`, `references.json`, `advintage-landing-page.json`)
- Serializer deserialisiert JSON in Entity-Objekte (in `src/Entity/`)

### Template Organization
- Base-Template: `templates/base.html.twig`
- Seiten-Templates: `templates/default/*.html.twig`
- Wiederverwendbare Komponenten: `templates/_*.html.twig` (z.B. `_model.html.twig`)
- Custom Form-Layout: `templates/form_layout.html.twig`

### Styling with Tailwind
- Input CSS: `public/css/input.css`
- Output CSS: `public/css/output.css`
- Config: `tailwind.config.js` mit custom Brand-Colors (Orange/Gray Theme)
- Templates müssen im `content` Array der Tailwind-Config definiert sein

### Forms & Anti-Spam
- Contact Form verwendet `ContactRequestType` mit Omines Anti-Spam Bundle
- Mail-Versand über Symfony Mailer mit `TemplatedEmail`
- Sender/Recipient-Adressen in `.env` konfiguriert

### Static Assets
- Bilder: `public/images/`
- Landing Page Bilder: `public/images/advintage-landing-page/`
- Referenz-Bilder: `public/images/references/{id}/`

## Important Notes

- JSON-Dateien in `config/` dienen als Content-Datenbank (keine echte DB im Einsatz)
- Entities in `src/Entity/` sind reine DTOs ohne Doctrine-Annotations
- Alle Routen nutzen deutsche URLs (z.B. `/kontakt`, `/referenzen`, `/impressum`)
- Environment-Variablen für Contact Form müssen in `.env.local` überschrieben werden für Production
