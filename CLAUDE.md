# krausgedruckt-homepage
> krauswerk > marcelkraus-hub > krausgedruckt-brand > krausgedruckt-homepage

Dieses Projekt ist Teil des krauswerks – meinem persönlichen Hub für
alle Projekte und Marken. Übergeordneter Kontext und Struktur sind im
krauswerk-Repository dokumentiert:
https://github.com/marcelkraus/krauswerk

## Project Overview

This is a Symfony 8.1 website for krausgedruckt (3D printing services), hosted with DDEV. The site uses Twig templates with Tailwind CSS for styling and EasyAdmin for backend management.

## Development Environment

### DDEV Setup
- Project runs in DDEV with PHP 8.4, nginx-fpm, and MariaDB 10.11
- URL: https://krausgedruckt.ddev.site
- Start: `ddev start`
- Stop: `ddev stop`
- SSH into container: `ddev ssh`

### Common Commands

**Symfony Console:**
```bash
ddev exec bin/console <command>
# or inside the container:
bin/console <command>
```

**Clear cache:**
```bash
ddev exec bin/console cache:clear
```

**Compile Tailwind CSS:**
```bash
# Watch mode (wrapper script, minified output, no arguments):
ddev exec bin/tailwindcss

# Production build (minified):
ddev exec npx tailwindcss -i public/css/input.css -o public/css/output.css --minify
```

**Composer:**
```bash
ddev composer install
ddev composer require <package>
ddev composer update
```

**npm:**
```bash
ddev exec npm install
ddev exec npm update
```

**Database Migrations:**
```bash
# Create a new migration after entity changes
ddev exec bin/console make:migration

# Run pending migrations
ddev exec bin/console doctrine:migrations:migrate
```

**Reset the local database:**
```bash
# Drops and recreates the database, runs migrations, deletes all JPG files
# in public/images/references/ and loads the fixtures
bin/reinstall-db
```

## Architecture

### Data Flow & Content Management
- **References**, **Categories** and **FAQ** are stored in the MariaDB database and managed via EasyAdmin
- **Other content** (landing pages) uses JSON files in `config/` (e.g., `advintage-landing-page.json`)
- **Flow for database content:** Database → Doctrine → Entity → Twig Templates
- **Flow for JSON content:** JSON → Symfony Serializer → Entity DTOs → Twig Templates
- `Reference`, `Category` and `FaqEntry` are full Doctrine entities with ORM mapping and UUID v7 as primary key
- `Source` is a Doctrine embeddable used inside `Reference` (column prefix `source_`)
- Other entities (`PrintableModel`, `Image`, `ContactRequest`) are pure DTOs without Doctrine annotations

### Routes
All frontend routes are defined in `src/Controller/DefaultController.php` with PHP 8 attributes and use German URLs.

| Path | Route name | Purpose |
| --- | --- | --- |
| `/` | `app_homepage` | Homepage |
| `/advintage` | `app_landing_page_advintage` | Landing page, JSON-backed |
| `/app` | `app_app` | App Store landing page for the "3D-Druck-Kostenrechner" |
| `/bewerten` | `app_review` | Redirect to the Google review URL |
| `/datenschutz` | `app_data_privacy` | Privacy policy |
| `/haeufig-gestellte-fragen` | `app_faq` | FAQ, database-backed |
| `/impressum` | `app_imprint` | Imprint |
| `/kontakt` | `app_contact` | Contact form (GET, POST) |
| `/kontakt-per-email` | `app_contact_email` | Redirect to `mailto:` |
| `/kontakt-per-whats-app` | `app_contact_whats_app` | Redirect to WhatsApp |
| `/kontakt/bestaetigung` | `app_contact_confirmation` | Confirmation after form submission |
| `/referenzen` | `app_references` | Reference list, database-backed |
| `/referenzen/{year}/{slug}` | `app_reference_detail` | Reference detail page |
| `/admin` | `admin` | EasyAdmin dashboard |
| `/admin/logout` | `admin_logout` | Logout, intercepted by the firewall |

### Controller Structure
- `DefaultController` instantiates a Symfony Serializer in its constructor for JSON-based content
- **Database-backed routes:**
  - `/referenzen` loads visible references via `ReferenceRepository::findAllOrdered()` (sorted by `createdAt` descending)
  - `/referenzen/{year}/{slug}` resolves a single reference via `ReferenceRepository::findByYearAndSlug()`; invisible or unknown references return 404
  - `/haeufig-gestellte-fragen` loads visible FAQ entries via `FaqEntryRepository::findAllOrdered()` (sorted by `sortOrder` ascending)
- **JSON-backed routes:**
  - `/advintage` loads `config/advintage-landing-page.json` and deserializes to `PrintableModel[]`
- **Admin controllers** are located in `src/Controller/Admin/` for EasyAdmin CRUD operations

### Entities

**Reference** (`reference` table)
- UUID v7 as primary key, lifecycle callbacks enabled
- Content: `title`, `slug` (unique), `summary`, `description`, `image`, `imageFile`
- Classification: `category` (ManyToOne to `Category`, nullable), `material` (`Material` enum, nullable), `printer` (`Printer` enum, nullable)
- Visibility: `isVisible` (defaults to `false`)
- Attribution: embedded `Source` (`title`, `url`, `author` — all optional)
- Google rating: `ratingUrl` (nullable)
- Timestamps: `createdAt` (set to midnight on construction), `updatedAt`

**Category** (`category` table)
- UUID v7 as primary key
- Fields: `name`, `slug` (unique)

**FaqEntry** (`faq_entry` table)
- UUID v7 as primary key, index on `sort_order`
- Fields: `question`, `answer`, `isVisible`, `sortOrder`
- Timestamps: `createdAt`, `updatedAt`
- Custom sorting via `sortOrder` field with up/down buttons in the admin interface
- Requires a `getSortButtons()` getter method for EasyAdmin field rendering

**Enums**
- `App\Enum\Material`: ASA, FLEX, PA12-CF, PC, PC-CF, PETG, PLA
- `App\Enum\Printer`: Prusa CORE One+, Prusa MINI+, Prusa MK4S, Prusa MK4S+MMU; `isMultiColor()` is true for the MMU variant

### EasyAdmin Backend
- EasyAdmin 5 for backend management at `/admin`
- **DashboardController** (`src/Controller/Admin/DashboardController.php`): entry point, renders `templates/admin/dashboard.html.twig` with deep links into the Reference and FAQ index pages; menu contains Dashboard, Referenzen, Kategorien and FAQ
- **ReferenceCrudController**: CRUD for references, sorted by `createdAt` descending
- **CategoryCrudController**: CRUD for categories, sorted by `name` ascending
- **FaqEntryCrudController**: CRUD for FAQ entries, sorted by `sortOrder` ascending, with custom up/down actions that swap the sort order of neighbouring entries
- All CRUD labels and field labels are German

**EasyAdmin 5 mechanics** — these are mandatory and easy to get wrong:
- Pretty URLs are required. They are loaded through `config/routes/easyadmin.yaml` with `type: easyadmin.routes`; the dashboard declares its own path via `#[AdminDashboard(routePath: '/admin', routeName: 'admin')]`
- Custom CRUD actions **must** carry `#[AdminRoute]`. Without it they are silently ignored when the routes are built
- Menu entries use `MenuItem::linkTo(<CrudController>::class, ...)` and point at the CRUD controller, not at the entity
- `entityId` is a route path segment, not a query parameter. Read the record through `$context->getEntity()->getInstance()` rather than from the query string
- Custom actions get no CSRF protection from the bundle — only `delete`, `batchDelete` and the boolean toggle are covered. State-changing actions have to restrict themselves to POST and validate their own token, as `FaqEntryCrudController` does for sorting

### Admin Authentication
- Firewall `admin` covers `^/admin` and uses **HTTP Basic** with realm `krausgedruckt:admin`
- Single in-memory user `krausgedruckt` with role `ROLE_ADMIN`
- The password hash comes from the `ADMIN_PASSWORD` environment variable
- Access control requires `ROLE_ADMIN` for `^/admin`

### Image Handling
- **VichUploaderBundle** handles image uploads for references
  - Mapping: `reference_images` → `/public/images/references/`, URI prefix `/images/references`
  - Upload field: `imageFile` (VichImageType) in `ReferenceCrudController`
  - Namer: `App\Service\ReferenceImageNamer` builds the filename as `<title-slug>-<uuid-without-dashes>.<extension>`
- `App\EventSubscriber\ReferenceImageSubscriber` listens on `postPersist` and renames temporary uploads (filenames containing `temp_`) once the entity has its UUID
- **LiipImagineBundle** provides the `reference_thumb` filter set (GD driver, 600×400 outbound thumbnail, quality 80); cached files land in `public/media/cache`

### Template Organization
- Base template: `templates/base.html.twig`
- Page templates: `templates/default/*.html.twig`
- Admin template: `templates/admin/dashboard.html.twig`
- Reusable components: `templates/_*.html.twig` (e.g., `_model.html.twig`)
- Custom form layout: `templates/form_layout.html.twig`
- Email template: `templates/default/contact.txt.twig`

### Styling with Tailwind
- Input CSS: `public/css/input.css`
- Output CSS: `public/css/output.css` (generated via the Tailwind CSS CLI)
- Config: `tailwind.config.js`
- The default palette is reduced to `transparent`, `current`, `black` and `white`; everything else is defined explicitly:
  - `background-primary` (orange-100), `background-secondary` (gray-200)
  - `brand-papaya` (`#EA580C`), `brand-primary-text` (`#171717`)
  - `muted` (gray-600), `placeholder` (gray-400)
- Plugins: `@tailwindcss/forms` and `@tailwindcss/typography` are active
- Templates must be defined in the `content` array of Tailwind config
- **Important:** Tailwind must be recompiled after template changes

### Forms & Anti-Spam
- Contact form uses `ContactRequestType` with the Omines Anti-Spam Bundle (profile `default`)
- The profile combines a honeypot field (`email_address`), a submission timer (3 seconds to 1 hour), banned markup detection and a URL limit (max. 2 URLs, max. 1 identical); anti-spam is disabled in the test environment
- Email sending via Symfony Mailer with `TemplatedEmail`; the reply-to address is the sender of the contact request
- Form has 4 fields: `name`, `email`, `message`, `discountCode`, plus a submit button
- Discount code can be pre-filled via query parameter: `/kontakt?discount-code=CODE`
- A successful submission redirects to `/kontakt/bestaetigung`

### Environment Variables
Defaults live in `.env`, overrides in `.env.local` (never committed).

| Variable | Purpose |
| --- | --- |
| `ADMIN_PASSWORD` | Password hash for the in-memory admin user |
| `APP_ENV` | Symfony environment, overridden to `prod` in deployments |
| `APP_SECRET` | Symfony application secret |
| `APP_STORE_URL_DESKTOP` | App Store link used on `/app` for desktop visitors |
| `APP_STORE_URL_MOBILE` | App Store link used on `/app` for mobile visitors |
| `CONTACT_FORM_RECIPIENT_ADDRESS` | Recipient of contact form emails |
| `CONTACT_FORM_SENDER_ADDRESS` | Sender of contact form emails |
| `DATABASE_URL` | Doctrine connection string |
| `GOOGLE_REVIEW_URL` | Redirect target of `/bewerten` |
| `MAILER_DSN` | Symfony Mailer transport |

### Fixtures
- `src/DataFixtures/` contains `CategoryFixtures`, `ReferenceFixtures` and `FaqEntryFixtures`
- Load them with `ddev exec bin/console doctrine:fixtures:load`, or use `bin/reinstall-db` for a full reset

### Static Assets
- Images: `public/images/`
- Landing page images: `public/images/advintage-landing-page/`
- Reference images: `public/images/references/` (uploaded via VichUploader)
- Logo and favicons: `public/images/krausgedruckt-logo.svg`, `krausgedruckt-logo-sharing.png`, `favicon-*.png`, `apple-touch-icon.png`

## Important Notes

- **Reference, category and FAQ updates:** managed via the EasyAdmin interface at `/admin`
- **Other content updates:** To change landing pages, edit the JSON files in `config/`
- **Routing:** All frontend routes use German URLs (e.g., `/kontakt`, `/referenzen`, `/haeufig-gestellte-fragen`)
- **Database:** MariaDB stores references, categories and FAQ entries — use Doctrine migrations for schema changes
- **Admin access:** EasyAdmin at `/admin`, protected by HTTP Basic
- **Environment:** `.env.local` should never be committed and contains local overrides
