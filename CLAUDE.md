# krausgedruckt

## Project Overview

This is a Symfony 8.1 website for krausgedruckt (3D printing services), hosted with DDEV. The site uses Twig templates with Tailwind CSS 4 for styling and EasyAdmin for backend management. It is the sibling of krausgebaut and shares its design skeleton.

## Development Environment

### DDEV Setup
- Project runs in DDEV with PHP 8.4, apache-fpm, Node 22 and MariaDB 10.11
- The web server is Apache so that `public/.htaccess` is exercised locally; under nginx it was ignored and only ever ran in production
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
# Production build (minified):
ddev exec npm run build

# Watch mode:
ddev exec npm run dev

# Watch mode via the wrapper script (no arguments):
ddev exec bin/tailwindcss
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
| `/referenzen` | `app_references` | Reference list, database-backed |
| `/robots.txt` | `app_robots` | robots, with an absolute sitemap URL |
| `/sitemap.xml` | `app_sitemap` | Sitemap, public pages plus visible references |
| `/referenzen/{year}/{slug}` | `app_reference_detail` | Reference detail page |
| `/admin` | `admin` | EasyAdmin dashboard |
| `/admin/logout` | `admin_logout` | Logout, intercepted by the firewall |

### Controller Structure
- `DefaultController` instantiates a Symfony Serializer in its constructor for JSON-based content
- **Database-backed routes:**
  - `/` loads the three most recent visible references for the homepage teaser (`HOMEPAGE_REFERENCE_LIMIT`)
  - `/referenzen` loads visible references via `ReferenceRepository::findAllOrdered()` (sorted by `createdAt` descending)
  - `/referenzen/{year}/{slug}` resolves a single reference via `ReferenceRepository::findByYearAndSlug()`; invisible or unknown references return 404
  - `/haeufig-gestellte-fragen` loads visible FAQ entries via `FaqEntryRepository::findAllOrdered()` (sorted by `sortOrder` ascending)
- **JSON-backed routes:**
  - `/advintage` loads `config/advintage-landing-page.json` and deserializes to `PrintableModel[]`. The path is anchored to `kernel.project_dir`; a relative path resolves against the working directory, which holds for the web server and breaks in the test runner
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
- Base template: `templates/base.html.twig` — head, header, footer and the mobile menu
- Page templates: `templates/default/*.html.twig`
- Admin template: `templates/admin/dashboard.html.twig`
- Patterns: `templates/partials/_*.html.twig`
- Email template: `templates/default/contact.txt.twig`

Each partial is the single source for its pattern and is never written by
hand in a page: `_logo` (brand lockup), `_eyebrow` (mono label with square
marker), `_icons` (line-icon macro), `_card` (the card shell),
`_reference_card` (shared by the homepage teaser and the overview),
`_model` (attribution of a printed model), `_button` (the two button steps),
`_button_class` (their classes as a bare string), `_contact_form` (the
hand-rolled form), `_conversion_band` (the closing invitation) and
`_sibling_band` (the nod to krausgebaut).

Three of them carry rules rather than just markup:

- **`_button_class`** holds the contrast rule for the filled button and
  emits nothing but a class string. `_button` renders the link version;
  a `<button>` that Symfony renders — the contact form's submit — pulls the
  same string through `include()`. Neither copy can drift from the other, and
  the rule below hangs on exactly one place.
- **`_card`** is embedded, not included, because the picture and the running
  text differ per case while the shell does not. A card **with** an `href`
  is one click target: the heading link stretches over the whole article
  (`after:absolute after:inset-0`) and the hover shadow is rendered. A card
  **without** one — the team — stays inert, because a growing shadow is a
  promise of a click.
- **`_conversion_band`** has three shapes. Plain band, `boxed` as an inset
  card, and — with an `image` — a two column block with a picture. Its
  `actions` block defaults to the enquiry button; a page whose closing action
  is something else (the app, with two store badges) embeds it and overrides
  the block, so ground, measurements and rhythm still come from one place.

The navigation is built once from the `nav_items` list in
`base.html.twig`. An item names either a `route` inside the site or an
external `url` with `external: true`, which gets the off-site icon and opens
in a new tab; the shop is the only one and sits last, after the pages that
belong to the site itself. A page that stands apart may narrow the list down
by setting `nav_items` at its own top level, which is what the adVintage
landing page does.

### Design system

krausgedruckt and krausgebaut are one brand family, and the family lives in
the shared skeleton: header dimensions (`h-20`, `max-w-6xl`,
`px-6 lg:px-8`, fixed, hairline, backdrop blur), the three-column dark
footer, the container widths, the typographic roles and the token
architecture. What differs is deliberate — papaya instead of petrol, the
nozzle instead of the gear, a warm ground instead of a cool one, soft cards
with a shadow instead of flat hairline cards, and a product photo where the
sibling uses typography. Both wordmarks are built identically, so the logo
partials mirror each other.

- **Typography:** `font-display` = `font-sans` = Aller (wordmark, headlines
  and body, which ties the type to the logo); `font-mono` = JetBrains Mono
  for eyebrows, labels and technical data. Both are self-hosted in
  `public/fonts`, no external requests.
- **Container:** `max-w-6xl mx-auto px-6 lg:px-8`; text pages and legal
  pages `max-w-3xl`.
- **Section rhythm:** warm → white → warm → dark → white. The single dark
  block (`Ablauf`) arrives late on purpose.
- **Corners:** `rounded-lg` for buttons and fields, `rounded-2xl` for cards
  and containers. Cards are free-standing: border, white ground, soft
  shadow.
- **Two-tone headings:** the statement comes first in `neutral-900` (or white
  on a dark ground), the flourish follows underneath, smaller and in the
  accent — `<span class="mt-3 block text-[0.8em] text-accent-on-light">`. This
  holds on every page. **The homepage hero is the only exception**, and it is
  the only one allowed: reversing the order or inventing a third form
  elsewhere is drift, not personality.

#### Colour tokens

Defined in `public/css/input.css` under `@theme`. **No hex values in
templates** — use the tokens. Everything outside them is Tailwind's
`neutral-*`, hairlines are `neutral-200`.

**One vocabulary, both brands.** The role is in the name, so the difference
between krausgedruckt and krausgebaut falls into the values and not into the
naming. The same five tokens exist over there under the same names.

| Token | Value | Role |
| --- | --- | --- |
| `accent` | `orange-600` | the brand: surfaces, borders, markers, the mark — **never type** |
| `accent-on-light` | `orange-700` | type on a light ground |
| `accent-on-dark` | `orange-600` | type on a dark ground |
| `accent-hover` | `orange-500` | hover of a filled surface |
| `accent-on-light-hover` | `orange-800` | hover of type on a light ground |
| `surface-warm` | `orange-50` | the warm section ground |
| `brand-marcelkraus`, `brand-krausgebaut`, `brand-krausgebaut-hover`, `brand-krausgebaut-on-dark` | own hex / `cyan-600` | sibling markers and the krausgebaut band |

The earlier names mixed two axes — one suffix named a role, another named a
state — and the two projects used different words for the same thing. That
is how a missing token went unnoticed: there was no name for "the brand as
type on a dark ground", so nobody saw it was never defined.

The naming makes the rule checkable: **`text-accent` without a role suffix
must not appear anywhere.** Neither project has one.

The split is measured, not cosmetic. Papaya is a *light* colour: it misses
AA as type on white (3.56:1) and carries a dark ground as it is (5.56:1).
The sibling's petrol has the mirror problem, which is why its tokens hold
different values under the same names.

0. **`neutral-400` is never type on a light ground** — it measures 2.58:1
   on white. Use `neutral-500` at the very least, `neutral-600` for anything
   that carries meaning, which includes form labels, help text and
   placeholders. On a dark ground the value is fine and `neutral-500` is the
   one that misses (3.78:1 on `neutral-900`).
1. **On a light ground type carries `accent-on-light`, never `accent`.**
2. **On a dark ground type carries `accent-on-dark`** — there it measures
   5.52:1.
3. **The filled button is `accent` with a `neutral-900` label** (5.04:1) and
   **lightens** to `accent-hover` on hover. The hover of a filled surface
   always moves in whichever direction keeps its label readable; here the
   label is near-black, so darkening would drop it to 3.55:1 on `orange-700`.
   The outline button is the secondary step.

Sibling colours stay out of the accent scale and carry their own
`brand-<name>` token. `brand-krausgebaut` is too dark to be read on a dark
ground (2.74:1), so the band that points at the sister brand uses
`brand-krausgebaut-on-dark` for anything that is type — the same split the
accent has, only in the other direction.

### Styling with Tailwind
- Tailwind 4, CSS-first. Input `public/css/input.css`, output
  `public/css/output.css` (committed, linked statically)
- `@import "tailwindcss" source(none)` plus an explicit `@source` for the
  templates. Without `source(none)` Tailwind scans the whole tree and a
  stray word in a Markdown file turns into a CSS rule
- Plugins are loaded with `@plugin`: `@tailwindcss/forms` and
  `@tailwindcss/typography`
- There is **no** `tailwind.config.js`. Theme values live in `@theme`
- **Important:** Tailwind must be recompiled after every template change —
  the stylesheet is committed, so an un-rebuilt build ships silently broken

### Contact form and anti-spam

**The form is hand-rolled — no `symfony/form`.** It is the same mechanism the
sister project uses, so the family answers a submission through one path
instead of two. `symfony/form` is still installed, but only as a transitive
dependency of EasyAdmin, which needs it for the backend; the frontend does
not touch it.

- `App\Entity\ContactRequest` is a plain object with public properties and
  validation attributes. It is filled from the request in the controller and
  handed to `symfony/validator`
- **Constraints are PHP attributes, never doc-block annotations.** The class
  shipped once with `@Assert\Email` in a doc block, which Symfony 8 does not
  read: the form accepted anything and an invalid address ended as a 500
  inside the mailer
- `templates/partials/_contact_form.html.twig` renders it and holds the field
  classes. There is no form theme any more
- 5 fields: `name`, `email`, `phone`, `discountCode`, `message`. The two
  required ones open the form and the optional ones follow, because a private
  customer is the majority here and should not have to skip a field before
  starting. The sister site orders its own form differently on purpose —
  there a company is the normal case

**Three defences, and they behave differently on purpose:**

| Signal | Answer |
| --- | --- |
| Honeypot `website` filled | silent drop — fake success, nothing sent |
| Timestamp missing, tampered or younger than 3 seconds | silent drop |
| Timestamp older than 2 hours | **422 with a message**: this is a person whose form sat open |
| CSRF token invalid | 422 with a message |
| More than 5 submissions per hour and address | 422 with a message |

The timestamp is signed with `hash_hmac` against `kernel.secret` — that is
what makes its age trustworthy, because otherwise a bot would simply post a
value that looks old enough. A bot learns nothing from a silent drop; a
person is never dropped without being told.

The rate limiter is configured in `config/packages/rate_limiter.yaml` and
raised out of the way in the test environment, because its state outlives a
single test run.

- A rejected submission answers **422**, not 200, and re-renders with the
  submitted values, the first violation per field, and the visitor's still
  valid timestamp so a quick fix-and-resend is not read as a bot
- Fields carry **real labels**, not placeholders — a placeholder disappears on
  the first keystroke and leaves the field unnamed. A required field is marked
  once, by the accent asterisk on its label, with the `Pflichtfelder *` legend
  above the button
- The field keeps the **browser focus ring** (`focus:outline-2`) on top of the
  accent border. Replacing the ring with a one pixel border change made the
  form the only place on the site where the keyboard focus was weaker than the
  default
- The submit button pulls its classes from `_button_class.html.twig` rather
  than writing them out, so the contrast rule lives in exactly one place
- Errors are `red-600`, not the accent: on this brand an orange error would be
  indistinguishable from an orange heading
- Discount code can be pre-filled via query parameter:
  `/kontakt?discount-code=CODE`
- A successful submission sets a `contact_success` flash and redirects back
  onto `/kontakt`, where the confirmation takes the form's place. This is the
  sister site's behaviour one to one. The redirect is what matters: without it
  a reload would send the message a second time
- Email sending via Symfony Mailer with `TemplatedEmail`; the reply-to address
  is the sender of the contact request

### Logging

`symfony/monolog-bundle`, configured identically to the sister project because
both run on the same host. Production writes to a **rotating file** rather than
the recipe's `php://stderr`: on the Uberspace host the FastCGI error stream is
not readable from the account, so an error would leave no trace that can be
looked at over SSH. Only errors are kept, together with the request that led up
to them; deprecations go to their own file.

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

### Tests

PHPUnit 13 with browser-kit and css-selector, matching the sibling project.
`tests/Controller/RouteSmokeTest.php` calls every frontend route once and
asserts that it answers and carries exactly one `h1`.
`tests/Controller/ContactFormTest.php` pins the contact form: an invalid
submission is refused with 422, names the field and sends nothing; a valid one
redirects and sends exactly one mail; the confirmation takes the form's place;
the discount code arrives from the query string; a filled honeypot and a
tampered signature are dropped silently while a stale form is asked to resend.

```bash
ddev exec bin/phpunit
```

The test environment ignores `.env.local` by design and appends `_test` to
the database name. That database needs to exist once per machine:

```bash
ddev mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS db_test; GRANT ALL PRIVILEGES ON db_test.* TO 'db'@'%'; FLUSH PRIVILEGES;"
ddev exec bin/console doctrine:migrations:migrate --no-interaction --env=test
ddev exec bin/console doctrine:fixtures:load --no-interaction --env=test
```

### SEO and meta

Centralised in `base.html.twig`: `lang`, canonical, description, Open Graph
and Twitter card, all overridable per page through the `title`,
`meta_description`, `meta_robots` and `meta_image` blocks. `meta_image` is
captured into a variable rather than printed where it is defined, because
the card needs the path twice.

Every page carries exactly one visible `h1`. Legal pages and the contact
confirmation are `noindex,follow`. JSON-LD sits in the `structured_data`
block: `ProfessionalService` on the homepage, `FAQPage` on the FAQ. Reference
detail pages carry none — the benefit is limited to image search.

`/robots.txt` and `/sitemap.xml` are generated by `DefaultController`. The
sitemap lists the public pages and every visible reference; legal pages, the
confirmation and the adVintage landing page stay out.

`App\EventListener\SecurityHeadersListener` sets `X-Content-Type-Options`,
`Referrer-Policy` and `X-Frame-Options` on every main response.

### Analytics

Self-hosted Matomo (**SiteId 8**), inlined in `base.html.twig` behind
`{% if app.environment == 'prod' %}` — development and test never track.
Cookieless via `disableCookies`, so no consent banner is required.

### Keeping empty directories

A directory that has to exist in the repository but carries no tracked
content is held by an **empty `.gitignore`**, never by a `.gitkeep`. This
applies project-wide, including directories a Symfony recipe scaffolds.

Currently: `public/images/references/landscape/` and
`public/images/references/portrait/`. `translations/` carries content again
and needs no placeholder.

`bin/reinstall-db` sweeps the two image directories and excludes
`.gitignore` from the sweep — without that exclusion the reset silently
removes the placeholders and the directories fall out of the repository.

### Fixtures
- `src/DataFixtures/` contains `CategoryFixtures`, `ReferenceFixtures` and `FaqEntryFixtures`
- Load them with `ddev exec bin/console doctrine:fixtures:load`, or use `bin/reinstall-db` for a full reset

### Static Assets
- Images: `public/images/`
- Landing page images: `public/images/advintage-landing-page/`
- Shop photo: `public/images/etsy-shop.jpg` (1080 × 1080, shown in the shop band on the homepage)
- Reference images: `public/images/references/landscape/` and `public/images/references/portrait/` (uploaded via VichUploader)
- Logo: `templates/partials/_logo.html.twig` — a Twig partial, not an image file
- Sharing image: `public/images/sharing.jpg` (1200×630, Open Graph)

The sharing image is a **finished asset, not a build product** — the same
rule the sister project follows, and its composition is deliberately the
mirror of it. Should it ever be redrawn: white ground, the eyebrow with its
square marker at the top left, the logo lockup below it, a two line claim in
`neutral-600`, and the domain with the location as a mono line at the foot.
The nozzle is oversized, **solid** accent and cropped off the right edge,
where the sibling crops its gear. Type is sized for a chat card around
320 pixels wide rather than for the canvas, which is why the mono lines sit
well above their on-site sizes and why the mark is opaque instead of a tint.
The mono type is `accent-on-light` and `neutral-600`, never `accent` — the
contrast rule holds on the card as it does on the site.

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

## Deployment

Rolled out with `bin/deploy`, which is the sister project's script plus one
step:

```bash
ssh kraus 'cd ~/html/krausgedruckt && bin/deploy'
```

It fetches, resets hard to `origin/main`, installs the production
dependencies, **removes** `var/cache/prod`, rebuilds it and then runs the
migrations. The reset is hard, but `.env.local`, `vendor/` and `var/` are
ignored and survive it. `public/css/output.css` is committed, so the server
needs no npm run.

The cache is removed rather than cleared, and that is not a detail:
`cache:clear` loads the existing compiled container before it replaces it, so
a release that drops a bundle dies on a class that no longer exists. It has
happened once here, when the anti-spam bundle went.

Migrations run **after** the rebuild so they meet the new container rather
than the old one.

**Two things do not travel with the repository.** A fresh server is not
complete after a clone:

1. **The database** — references, categories and FAQ entries live in MariaDB.
   Eight migrations build the schema; the content needs a dump.
2. **The uploaded reference images** — `public/images/references/landscape/`
   and `portrait/` are ignored, so only the two empty `.gitignore`
   placeholders and the fixture pictures are versioned. Without copying them
   across, every reference loses its picture.

`public/media/cache/` does **not** need to travel; LiipImagine rebuilds it on
demand. What it does need is a writable `public/media/`, writable upload
directories and a writable `var/`.

`.env.local` has to exist on the server and override `APP_ENV`, `APP_SECRET`,
`DATABASE_URL` and `MAILER_DSN`. The last one is the quiet one: it defaults to
`null://null`, so an unset `MAILER_DSN` swallows every contact request without
raising an error.

## Important Notes

- **Reference, category and FAQ updates:** managed via the EasyAdmin interface at `/admin`
- **Other content updates:** To change landing pages, edit the JSON files in `config/`
- **Routing:** All frontend routes use German URLs (e.g., `/kontakt`, `/referenzen`, `/haeufig-gestellte-fragen`)
- **Voice:** the business speaks as „wir“ and addresses the customer as „du“. In the FAQ the customer speaks too, and keeps „ich“ for itself while addressing the business as „ihr“ — the same word therefore moves in one entry and stays in the next
- **Database:** MariaDB stores references, categories and FAQ entries — use Doctrine migrations for schema changes
- **Admin access:** EasyAdmin at `/admin`, protected by HTTP Basic
- **Environment:** `.env.local` should never be committed and contains local overrides
