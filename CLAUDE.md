# krausgedruckt

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
# Drops and recreates the database, runs migrations, deletes all uploaded
# reference images from landscape/ and portrait/ and loads the fixtures
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
- Content: `title`, `slug` (unique), `summary`, `description`
- The slug is generated from the title while a reference is created — the field is disabled there — and can be edited freely afterwards. Changing it changes the address of the detail page. A pattern check keeps it URL-safe and `UniqueEntity` catches a collision before the database does
- Images: `imageLandscape` / `imageFileLandscape` (5:4, stored at 1080 × 864) and `imagePortrait` / `imageFilePortrait` (4:5, stored at 1080 × 1350)
- Classification: `category` (ManyToOne to `Category`, nullable), `material` (`Material` enum, nullable), `printer` (`Printer` enum, nullable)
- Visibility: `isVisible` (defaults to `false`)
- **Mandatory:** both images and the category. Enforced at form validation only — the columns stay nullable so references predating the split can still be read. The images are checked through a callback that accepts either a freshly uploaded file or an already stored one, because VichUploader writes the file name while flushing, long after validation has run. References predating the second image cannot be saved until a portrait image is supplied — this is intended
- Attribution: embedded `Source` (`title`, `url`, `author` — all optional)
- Google rating: `ratingUrl` (nullable)
- Timestamps: `createdAt` (set to midnight on construction), `updatedAt`

**Category** (`category` table)
- UUID v7 as primary key
- Fields: `name`, `slug` (unique)
- Same slug handling as the reference: generated from the name while the category is created, the field is disabled there, editable afterwards, pattern checked and unique. The slug is currently not read anywhere — no route and no template uses it

**FaqEntry** (`faq_entry` table)
- UUID v7 as primary key, index on `sort_order`
- Fields: `question`, `answer`, `isVisible`, `sortOrder`
- Timestamps: `createdAt`, `updatedAt`
- Custom sorting via `sortOrder` field with up/down buttons in the admin interface
- Requires a `getSortButtons()` getter method for EasyAdmin field rendering

**Enums**
- `App\Enum\Material`: ASA, FLEX, PA12-CF, PC, PC-CF, PETG, PLA
- `App\Enum\Printer`: Prusa CORE One INDX, Prusa CORE One L, Prusa CORE One+, Prusa MINI+, Prusa MK4S, Prusa MK4S + MMU3; `isMultiColor()` is true for the INDX and the MMU3 variant
- Both enums carry a `getHashtags()` method feeding the Instagram caption. Keep the hashtags next to the case, never in a second place

### EasyAdmin Backend
- EasyAdmin 5 for backend management at `/admin`
- **DashboardController** (`src/Controller/Admin/DashboardController.php`): entry point, renders `templates/admin/dashboard.html.twig` with deep links into the Reference and FAQ index pages; menu contains Dashboard, Referenzen, Kategorien and FAQ
- **ReferenceCrudController**: CRUD for references, sorted by `createdAt` descending
- **CategoryCrudController**: CRUD for categories, sorted by `name` ascending. A category that references point to cannot be deleted: the button is hidden through `displayIf()` and `deleteEntity()` refuses the request, so a hand crafted call cannot run into a foreign key error either. Use `ReferenceRepository::countByCategory()` for that check — it counts through the criteria API, because a hand written DQL comparison against the association silently returns zero for the UUID identifier
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
Every reference carries two images. Both are capped at a width of 1080 pixels, matching what Instagram accepts.

| | Landscape | Portrait |
| --- | --- | --- |
| Ratio | 5:4 | 4:5 |
| Stored size | 1080 × 864 | 1080 × 1350 |
| Directory | `public/images/references/landscape/` | `public/images/references/portrait/` |
| VichUploader mapping | `reference_images_landscape` | `reference_images_portrait` |
| LiipImagine filter | `reference_landscape` | `reference_portrait` |

- `Assert\Image` on the upload properties enforces the ratio with a tolerance of roughly one percent, a minimum size matching the target, a maximum of 12 MB and 30 megapixels, and restricts the type to JPEG, PNG and WebP. Imagick never scales up, so anything smaller than the target is rejected rather than interpolated
- `App\EventListener\ReferenceImageListener` listens on VichUploader's `POST_UPLOAD` and `POST_REMOVE` events. On upload it hands the stored file to `App\Service\ImageNormalizer`, which applies the EXIF rotation, crops to the target size, converts to sRGB and strips the remaining metadata. The uploaded original is replaced, there is no archive copy. Both events drop the rendered versions from the LiipImagine cache, because the file name stays the same when an image is replaced
- Namer: `App\Service\ReferenceImageNamer` builds the filename as `<title-slug>-<uuid-without-dashes>.<extension>` for both mappings
- VichUploader owns the file lifecycle and needs no help: `delete_on_update` removes the previous picture when a new one is uploaded, even when the title changed the file name in between, and `delete_on_remove` removes both files when the reference is deleted. Renaming without uploading leaves the file under its old name — a cosmetic mismatch, nothing is lost
- **Display follows the device:** portrait wherever elements stack (mobile), landscape wherever they sit side by side (desktop). This applies to the detail page and the public reference list alike; the admin list always shows the landscape image
- References created before the split have no portrait image, so `Reference::getImagePortraitPathWithFallback()` falls back to the landscape one
- Cached thumbnails land in `public/media/cache`
- **Never call `Imagick::autoOrientImage()` or `Imagick::autoOrient()`.** The name depends on the ImageMagick major version — the sixth binding knows the former, the seventh the latter — so either one works in one environment and fatals in the other. The rotation always goes through `ImageNormalizer::applyOrientation()`, which maps the eight EXIF values onto `flipImage()`, `flopImage()` and `rotateImage()`
- Known limitation: `Assert\Image` measures the physical pixels and ignores the EXIF orientation. A photo stored sideways is therefore rejected with a ratio message rather than being rotated first

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

### Instagram Preview
- Read-only page reachable from the three dot menu of a reference, rendered by `ReferenceCrudController::instagramPreview()` into `templates/admin/instagram-preview.html.twig`
- `App\Service\InstagramCaptionBuilder` assembles the caption from three paragraphs: the `#ModellMontag` introduction with the title and the summary, the source sentence (only when all three source fields are set) and the hashtag block
- Hashtags come from `InstagramCaptionBuilder::GLOBAL_HASHTAGS` plus the printer and the material. Missing fields contribute nothing, duplicates are dropped and the block is always sorted alphabetically
- Without a summary the introduction ends after the model name
- The caption sits in a copyable block with a button next to it. The asynchronous clipboard API only exists in a secure context, so the button falls back to a hidden selection when the backend is opened over plain HTTP

### Fixtures
- `src/DataFixtures/` contains `CategoryFixtures`, `ReferenceFixtures` and `FaqEntryFixtures`
- Load them with `ddev exec bin/console doctrine:fixtures:load`, or use `bin/reinstall-db` for a full reset

### Static Assets
- Images: `public/images/`
- Landing page images: `public/images/advintage-landing-page/`
- Reference images: `public/images/references/landscape/` and `public/images/references/portrait/` (uploaded via VichUploader)
- Logo: `public/images/krausgedruckt-logo.svg`
- Sharing image: `public/images/sharing.png` (1200×630, Open Graph)

### Favicons

Three files at the web root, all derived from the master kachel:

| File | Role |
|------|------|
| `favicon.svg` | primary – scales to any size a browser asks for |
| `favicon.ico` | 16 + 32 px in one file; also answers the implicit `/favicon.ico` request browsers make without a `<link>` |
| `apple-touch-icon.png` | 180×180, iOS home screen |

The SVG **must** keep its `width`/`height` attributes. Without them it has no
intrinsic size, so the browser rasterises it into a default box and scales that
into the tab slot, which leaves a pale rim around the tile. Rasterisation is
picked per size by measurement: 16 px comes straight out of the browser's own
rasteriser, 32 px and 180 px are downsampled from a 1024 px raster, which
measured sharper. Every generated file is checked for a fully opaque,
single-colour border before it ships.

The tile is `#EA580C`, taken from the master. The icons this set replaced had
drifted to `#EB5923` – they had been rasterised away from the vector at some
point and nobody noticed. Master artwork is **not** kept in the repository;
Marcel supplies it on demand, and every shipped asset is derived from it.

## Important Notes

- **Reference, category and FAQ updates:** managed via the EasyAdmin interface at `/admin`
- **Other content updates:** To change landing pages, edit the JSON files in `config/`
- **Routing:** All frontend routes use German URLs (e.g., `/kontakt`, `/referenzen`, `/haeufig-gestellte-fragen`)
- **Database:** MariaDB stores references, categories and FAQ entries — use Doctrine migrations for schema changes
- **Admin access:** EasyAdmin at `/admin`, protected by HTTP Basic
- **Environment:** `.env.local` should never be committed and contains local overrides
