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
- Controller lädt JSON-Konfigurationsdateien aus `config/` für Content (z.B. `faq.json`, `advintage-landing-page.json`)
- Serializer deserialisiert JSON in Entity-Objekte (in `src/Entity/`)
- Admin-Backend via EasyAdmin in `src/Controller/Admin/` für Portfolio-Verwaltung

### Data Architecture (Hybrid Approach)
- **JSON-basiert** (für statische Inhalte):
  - FAQ (`config/faq.json` → `App\Entity\Question`)
  - Landing Pages (`config/advintage-landing-page.json` → `App\Entity\PrintableModel`)
  - Entities sind einfache DTOs ohne Doctrine-Annotations
- **Doctrine ORM** (für dynamische Inhalte):
  - Portfolio-Pieces (`App\Entity\PortfolioPiece`) mit MariaDB 10.4
  - Bilder werden über `getImages()` dynamisch aus Filesystem geladen (`public/images/portfolio-pieces/{id}/`)

### Template Organization
- Base-Template: `templates/base.html.twig`
- Seiten-Templates: `templates/default/*.html.twig`
- Custom Form-Layout: `templates/form_layout.html.twig`

### Styling with Tailwind
- Input CSS: `public/css/input.css`
- Output CSS: `public/css/output.css`
- Config: `tailwind.config.js` mit custom Brand-Colors (Orange/Gray Theme)
- **Wichtig**: Tailwind-Klassen müssen alphabetisch sortiert sein
- Custom Colors: `brand-primary` (#f97316), `brand-secondary` (#4b5563), `background-primary` (#ffedd5), `background-secondary` (#e5e7eb)

### Forms & Anti-Spam
- Contact Form verwendet `ContactRequestType` mit Omines Anti-Spam Bundle
- Mail-Versand über Symfony Mailer mit `TemplatedEmail`
- Sender/Recipient-Adressen via `$_SERVER['CONTACT_FORM_SENDER_ADDRESS']` und `$_SERVER['CONTACT_FORM_RECIPIENT_ADDRESS']`

### Static Assets
- Bilder: `public/images/`
- Landing Page Bilder: `public/images/advintage-landing-page/`
- Portfolio-Bilder: `public/images/portfolio-pieces/{id}/` (dynamisch geladen)

## Important Notes

- Alle Routen nutzen deutsche URLs (z.B. `/kontakt`, `/haeufig-gestellte-fragen`, `/impressum`)
- Keine Tests vorhanden (kein PHPUnit-Setup)
- Environment-Variablen für Contact Form müssen in `.env.local` überschrieben werden
