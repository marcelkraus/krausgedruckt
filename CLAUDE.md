# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Symfony 8.0 website for krausgedruckt (3D printing services), hosted with DDEV. The site uses Twig templates with Tailwind CSS for styling and EasyAdmin for backend management.

## Development Environment

### DDEV Setup
- Project runs in DDEV with PHP 8.4, nginx-fpm, and MariaDB 10.4
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
# Development with watch mode (binary is in bin/tailwindcss):
ddev exec bin/tailwindcss -i public/css/input.css -o public/css/output.css --watch

# Production build (minified):
ddev exec bin/tailwindcss -i public/css/input.css -o public/css/output.css --minify
```

**Composer:**
```bash
ddev composer install
ddev composer require <package>
ddev composer update
```

**Database Migrations:**
```bash
# Create a new migration after entity changes
ddev exec bin/console make:migration

# Run pending migrations
ddev exec bin/console doctrine:migrations:migrate
```

## Architecture

### Data Flow & Content Management
- **References** are stored in the MariaDB database and managed via EasyAdmin
- **Other content** (FAQ, landing pages) uses JSON files in `config/` (e.g., `faq.json`, `advintage-landing-page.json`)
- **Flow for references:** Database → Doctrine → Entity → Twig Templates
- **Flow for JSON content:** JSON → Symfony Serializer → Entity DTOs → Twig Templates
- `Reference` entity is a full Doctrine entity with ORM mapping and UUID as primary key
- Other entities (e.g., `PrintableModel`, `Question`) are pure DTOs without Doctrine annotations

### Controller Structure
- **Frontend routes** are defined in `src/Controller/DefaultController.php` with PHP 8 attributes
- Controller instantiates Symfony Serializer in constructor
- Each route loads its content data via `$this->serializer->deserialize()` from JSON files
- Example: `/advintage` route loads `config/advintage-landing-page.json` and deserializes to `PrintableModel[]`
- **Admin controllers** are located in `src/Controller/Admin/` for EasyAdmin CRUD operations

### EasyAdmin Backend
- EasyAdmin 4 for backend management at `/admin`
- **DashboardController** (`src/Controller/Admin/DashboardController.php`): Entry point for admin interface
- **ReferenceCrudController** (`src/Controller/Admin/ReferenceCrudController.php`): CRUD for references
- **VichUploaderBundle** for image uploads in references
  - Mapping: `reference_images` → `/public/images/references/`
  - Upload field: `imageFile` (VichImageType) in ReferenceCrudController
- **Reference Entity** features:
  - UUID v7 as primary key
  - Fields: `title`, `description`, `image`, `imageFile`
  - Embedded `Source` entity for attribution (title, URL, author)
  - Timestamps: `createdAt`, `updatedAt`

### Template Organization
- Base template: `templates/base.html.twig`
- Page templates: `templates/default/*.html.twig`
- Reusable components: `templates/_*.html.twig` (e.g., `_model.html.twig`)
- Custom form layout: `templates/form_layout.html.twig`

### Styling with Tailwind
- Input CSS: `public/css/input.css`
- Output CSS: `public/css/output.css` (generated via `bin/tailwindcss` binary)
- Config: `tailwind.config.js` with custom brand colors (orange/gray theme)
- Plugins: `@tailwindcss/forms` and `@tailwindcss/typography` are active
- Templates must be defined in the `content` array of Tailwind config
- **Important:** Tailwind must be recompiled after template changes

### Forms & Anti-Spam
- Contact form uses `ContactRequestType` with Omines Anti-Spam Bundle
- Email sending via Symfony Mailer with `TemplatedEmail`
- Form has 4 fields: `name`, `email`, `message`, `discountCode`
- Email template: `templates/default/contact.txt.twig`
- Discount code can be pre-filled via query parameter: `/kontakt?discount-code=CODE`
- Sender/recipient addresses configured in `.env`:
  - `CONTACT_FORM_SENDER_ADDRESS`
  - `CONTACT_FORM_RECIPIENT_ADDRESS`
- `.env.local` overrides these for local/production environments

### Static Assets
- Images: `public/images/`
- Landing page images: `public/images/advintage-landing-page/`
- Reference images: `public/images/references/` (uploaded via VichUploader)

## Important Notes

- **Reference updates:** References are managed via the EasyAdmin interface at `/admin`
- **Other content updates:** To change FAQ, landing pages, etc., edit the JSON files in `config/`
- **Routing:** All frontend routes use German URLs (e.g., `/kontakt`, `/referenzen`, `/impressum`)
- **Database:** MariaDB stores references (Reference entity) - use Doctrine migrations for schema changes
- **Admin access:** EasyAdmin at `/admin` for backend management
- **Environment:** `.env.local` should never be committed and contains local overrides
