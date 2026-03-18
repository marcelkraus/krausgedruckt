# krausgedruckt-homepage
> Übergreifender Kontext > krausgedruckt > homepage

Kurzname: krausgedruckt

Dieses Projekt ist Teil meiner IT-Landschaft – einer Sammlung
privater und gewerblicher Projekte. Übergeordneter Kontext und
Struktur sind im „Übergreifenden Kontext" dokumentiert.

## Beschreibung

Website für krausgedruckt (https://www.krausgedruckt.de) – meine
Marke für 3D-Druck und Druckdienstleistungen. Die Seite zeigt
Referenzen und FAQ (über EasyAdmin-Backend) sowie Landing-Pages
(über JSON-Konfiguration).

## Technologie-Stack

- **Backend:** Symfony 8.0, PHP 8.4
- **Templates:** Twig
- **Styling:** Tailwind CSS mit eigenen Markenfarben
  (Orange/Grau-Schema)
- **Backend-Verwaltung:** EasyAdmin 4
- **Datei-Upload:** VichUploaderBundle (Referenzbilder)
- **Anti-Spam:** Omines AntiSpamBundle (Honeypot, Timer,
  URL-Count)
- **Mail:** Symfony Mailer mit TemplatedEmail
- **Datenbank:** Doctrine ORM, MariaDB 10.4
- **Entwicklung:** DDEV (Nginx-FPM, PHP 8.4)

## Entwicklungsumgebung

### DDEV starten

```bash
ddev start
```

Zugriff über: https://krausgedruckt.ddev.site

### Tailwind CSS kompilieren

```bash
# Entwicklung mit Watch-Modus (Binary liegt in bin/tailwindcss):
ddev exec bin/tailwindcss -i public/css/input.css -o public/css/output.css --watch

# Produktions-Build (minifiziert):
ddev exec bin/tailwindcss -i public/css/input.css -o public/css/output.css --minify
```

### Symfony-Befehle

```bash
ddev exec bin/console cache:clear
ddev exec bin/console debug:routes
```

### Composer

```bash
ddev composer install
ddev composer require <package>
ddev composer update
```

### Datenbank-Migrationen

```bash
# Migration erstellen nach Entity-Änderungen
ddev exec bin/console make:migration

# Ausstehende Migrationen ausführen
ddev exec bin/console doctrine:migrations:migrate
```

## Projektstruktur

```
krausgedruckt-homepage/
├── config/
│   ├── advintage-landing-page.json   ← Landing-Page-Inhalte
│   └── packages/                     ← Bundle-Konfigurationen
├── src/
│   ├── Controller/
│   │   ├── DefaultController.php     ← Alle Frontend-Routes
│   │   └── Admin/
│   │       ├── DashboardController.php
│   │       ├── ReferenceCrudController.php
│   │       └── FaqEntryCrudController.php
│   ├── Entity/
│   │   ├── ContactRequest.php        ← Kontaktformular-Entity
│   │   ├── Reference.php             ← Doctrine (UUID v7)
│   │   ├── Source.php                ← Embedded Entity
│   │   ├── FaqEntry.php              ← Doctrine (UUID v7)
│   │   └── PrintableModel.php        ← DTO (JSON-basiert)
│   └── Form/Type/
│       └── ContactRequestType.php
├── templates/
│   ├── base.html.twig
│   ├── form_layout.html.twig         ← Formular-Layout (Tailwind)
│   ├── default/*.html.twig           ← Seitentemplates
│   └── _*.html.twig                  ← Wiederverwendbare Partials
├── public/
│   ├── css/
│   │   ├── input.css                 ← Tailwind-Input
│   │   └── output.css                ← Kompiliertes CSS
│   └── images/
│       └── references/               ← Upload via VichUploader
├── migrations/
├── .ddev/config.yaml
├── composer.json
├── package.json
└── tailwind.config.js
```

## Architektur

### Datenfluss
- **Referenzen** und **FAQ** werden in der Datenbank gespeichert
  und über EasyAdmin verwaltet
  (Datenbank → Doctrine → Entity → Twig)
- **Landing-Pages** nutzen JSON-Dateien in `config/`
  (JSON → Symfony Serializer → DTO → Twig)
- `Reference` und `FaqEntry` sind vollständige
  Doctrine-Entities mit UUID v7 als Primärschlüssel
- `PrintableModel` ist ein reines DTO ohne Doctrine-Mapping

### EasyAdmin-Backend
- Zugang über `/admin`
- **ReferenceCrudController:** CRUD für Referenzen mit
  Bild-Upload (VichUploader → `/public/images/references/`)
- **FaqEntryCrudController:** CRUD für FAQ-Einträge mit
  Sortierung über `sortOrder`-Feld (Auf-/Ab-Buttons)

### Template-Organisation
- Basis-Template: `templates/base.html.twig`
- Seitentemplates: `templates/default/*.html.twig`
- Wiederverwendbare Partials: `templates/_*.html.twig`
- Formular-Layout: `templates/form_layout.html.twig`

## Routing

| Route | Beschreibung |
|-------|-------------|
| `GET /` | Startseite |
| `GET /referenzen` | Referenzen (DB-basiert) |
| `GET /haeufig-gestellte-fragen` | FAQ (DB-basiert) |
| `GET /advintage` | Landing-Page (JSON-basiert) |
| `GET /kontakt` | Kontaktformular |
| `POST /kontakt` | Formular-Verarbeitung |
| `GET /impressum` | Impressum |
| `GET /datenschutz` | Datenschutzerklärung |
| `GET /admin` | EasyAdmin-Backend |

## Kontaktformular

- Felder: Name, E-Mail, Nachricht, Rabattcode
- Rabattcode vorbefüllbar via Query-Parameter:
  `/kontakt?discount-code=CODE`
- Anti-Spam-Profil: Honeypot, Timer (3–3600 Sekunden),
  Markup-Filter, URL-Limit (maximal 2 URLs)
- E-Mail-Versand über Symfony Mailer

## Tailwind-Konfiguration

- Eigene Markenfarben (Orange/Grau-Schema)
- Plugins: `@tailwindcss/forms`, `@tailwindcss/typography`
- Tailwind muss nach Template-Änderungen neu kompiliert werden

## Umgebungsvariablen

| Variable | Beschreibung |
|----------|-------------|
| `APP_ENV` | Umgebung (`dev` / `prod`) |
| `APP_SECRET` | Symfony Secret |
| `MAILER_DSN` | Mail-Transport |
| `CONTACT_FORM_SENDER_ADDRESS` | Absender des Kontaktformulars |
| `CONTACT_FORM_RECIPIENT_ADDRESS` | Empfänger des Kontaktformulars |
