# krausgedruckt

## Overview

Business website for **krausgedruckt** — the 3D printing branch of Marcel
Kraus's freelance work (https://www.krausgedruckt.de). It presents the
services, the printed references and the FAQ, and carries a contact form and a
shop band.

German only. References, categories and FAQ entries live in MariaDB and are
maintained through an EasyAdmin backend; everything else is markup or JSON.

It is one of three sites in a brand family, together with krausgebaut (internet
and IT services) and marcelkraus (the personal hub and curriculum vitae).
Header and footer are the family bracket and are shared by construction — see
*The family bracket* below.

## Technology stack

- **Backend:** Symfony 8.1, PHP 8.4, Twig
- **Database:** MariaDB 10.11 with Doctrine ORM and migrations
- **Backend UI:** EasyAdmin 5 at `/admin`, HTTP Basic
- **Images:** VichUploader for the lifecycle, LiipImagine for the rendered
  versions, Imagick for normalisation
- **Styling:** Tailwind CSS 4 (standalone CLI) with the forms and typography
  plugins
- **Fonts:** self-hosted — Aller (display + body), JetBrains Mono (mono /
  technical labels)
- **Form:** hand-rolled (no `symfony/form` in the frontend) +
  symfony/validator + symfony/rate-limiter + CSRF
- **Mail:** symfony/mailer; ddev Mailpit in development
- **Logging:** symfony/monolog-bundle (rotating file in prod)
- **Tests:** PHPUnit 13 with browser-kit and css-selector
- **Development:** ddev (apache-fpm, Node 22, MariaDB 10.11)

The web server is Apache so that `public/.htaccess` is exercised locally; under
nginx it was ignored and only ever ran in production.

`symfony/form` is installed, but only as a transitive dependency of EasyAdmin,
which needs it for the backend. The frontend does not touch it.

## Development

```bash
ddev start                                   # https://krausgedruckt.ddev.site
ddev launch -m                               # Mailpit, captured mail
ddev exec npm run build                      # Tailwind, minified
ddev exec npm run dev                        # Tailwind, watch mode
ddev exec bin/console cache:clear
ddev exec bin/console make:migration         # after an entity change
ddev exec bin/console doctrine:migrations:migrate
bin/reinstall-db                             # full local reset, see below
```

`bin/reinstall-db` drops and recreates the database, runs the migrations,
sweeps the uploaded reference images from `landscape/` and `portrait/` and
loads the fixtures. It excludes `.gitignore` from the sweep — without that the
reset silently removes the placeholders and the two directories fall out of the
repository.

**Rebuild Tailwind after every change to a template or to `input.css`.**
`public/css/output.css` is committed and linked statically, so an un-rebuilt
stylesheet ships silently broken. `input.css` sets
`@import "tailwindcss" source(none)` and declares the templates explicitly:
without that, Tailwind scans the whole tree and a stray word in a Markdown file
turns into a CSS rule. There is **no** `tailwind.config.js`; theme values live
in `@theme`.

**Never assemble a utility class from a variable.** Tailwind reads the
templates as text, so `bg-{{ token }}` never reaches the build.

### Quality gates (before merging to main)

```bash
ddev exec php bin/console lint:twig templates
ddev exec php bin/console lint:yaml config
ddev exec php bin/console lint:container
ddev exec bash -c 'find src tests -name "*.php" -exec php -l {} \;'
ddev exec bin/phpunit
ddev exec npm run build
```

**The test database is built, not maintained by hand.** `ddev exec composer
test-db` creates it, runs the migrations and loads the fixtures. Two tests
depend on a visible reference existing; without that step they skip rather
than fail, which reads as green on a machine that never had the data. The
two skips that remain are correct — `robots.txt` and `sitemap.xml` carry no
heading, so the heading test steps over them.

## Layout

```
config/             advintage-landing-page.json and the Symfony configuration
migrations/         eight Doctrine migrations
src/Controller/     DefaultController – all frontend routes
src/Controller/Admin/  Dashboard and the three CRUD controllers
src/Entity/         Reference, Category, FaqEntry (ORM)
src/Dto/            ContactRequest – the contact form payload, no ORM binding
src/EventListener/  ReferenceImageListener, SecurityHeadersListener
src/Service/        ImageNormalizer, ReferenceImageNamer,
                    InstagramCaptionBuilder
templates/          base.html.twig, default/, admin/, partials/
public/             css/, fonts/, images/, media/cache/, favicon.*
```

Each partial is the single source for its pattern and is never written by hand
in a page: `_logo` (brand lockup), `_eyebrow` (mono label with square marker),
`_icons` (line-icon macro), `_card` (the card shell), `_reference_card` (shared
by the homepage teaser and the overview), `_model` (attribution of a printed
model), `_button` (the two button steps), `_button_class` (their classes as a
bare string), `_contact_form`, `_conversion_band` (the closing invitation) and
`_sibling_band` (the nod to krausgebaut).

Three of them carry rules rather than just markup:

- **`_button_class`** holds the contrast rule for the filled button and emits
  nothing but a class string. `_button` renders the link version; a `<button>`
  that Symfony renders — the contact form's submit — pulls the same string
  through `include()`. Neither copy can drift from the other, and the rule
  below hangs on exactly one place.
- **`_card`** is embedded, not included, because the picture and the running
  text differ per case while the shell does not. A card **with** an `href` is
  one click target: the heading link stretches over the whole article
  (`after:absolute after:inset-0`) and the hover shadow is rendered. A card
  **without** one — the team — stays inert, because a growing shadow is a
  promise of a click.
- **`_conversion_band`** has three shapes: plain band, `boxed` as an inset
  card, and — with an `image` — a two column block with a picture. Its
  `actions` block defaults to the enquiry button; a page whose closing action
  is something else (the app, with two store badges) embeds it and overrides
  the block, so ground, measurements and rhythm still come from one place.

The navigation is built once from the `nav_items` list in `base.html.twig`. An
item names either a `route` inside the site or an external `url` with
`external: true`, which gets the off-site icon and opens in a new tab; the shop
is the only one and sits last, after the pages that belong to the site itself.
A page that stands apart may narrow the list down by setting `nav_items` at its
own top level, which is what the adVintage landing page does.

## Routing

All frontend routes are defined in `src/Controller/DefaultController.php` with
PHP attributes and use German URLs.

| Path | Route name | Purpose |
| --- | --- | --- |
| `/` | `app_homepage` | Homepage |
| `/advintage` | `app_landing_page_advintage` | Landing page, JSON-backed |
| `/app` | `app_app` | App Store landing page for the 3D-Druck-Kostenrechner |
| `/bewerten` | `app_review` | Redirect to the Google review URL |
| `/datenschutz` | `app_data_privacy` | Privacy policy |
| `/haeufig-gestellte-fragen` | `app_faq` | FAQ, database-backed |
| `/impressum` | `app_imprint` | Imprint |
| `/kontakt` | `app_contact` | Contact form (GET, POST) |
| `/kontakt-per-email` | `app_contact_email` | Redirect to `mailto:` |
| `/kontakt-per-whats-app` | `app_contact_whats_app` | Redirect to WhatsApp |
| `/referenzen` | `app_references` | Reference list, database-backed |
| `/referenzen/{year}/{slug}` | `app_reference_detail` | Reference detail page |
| `/robots.txt` | `app_robots` | robots, with an absolute sitemap URL |
| `/sitemap.xml` | `app_sitemap` | Public pages plus visible references |
| `/admin` | `admin` | EasyAdmin dashboard |
| `/admin/logout` | `admin_logout` | Logout, intercepted by the firewall |

Database-backed routes: `/` loads the three most recent visible references for
the teaser (`HOMEPAGE_REFERENCE_LIMIT`); `/referenzen` uses
`ReferenceRepository::findAllOrdered()` (by `createdAt` descending);
`/referenzen/{year}/{slug}` resolves through
`ReferenceRepository::findByYearAndSlug()` and answers 404 for invisible or
unknown references; the FAQ uses `FaqEntryRepository::findAllOrdered()` (by
`sortOrder` ascending).

`/advintage` loads `config/advintage-landing-page.json` and deserialises to
`PrintableModel[]`. The path is anchored to `kernel.project_dir`: a relative
path resolves against the working directory, which holds for the web server and
breaks in the test runner.

## Data model

- **References, categories and FAQ entries** live in MariaDB and are managed
  through EasyAdmin. Flow: database → Doctrine → entity → Twig.
- **Landing pages** use JSON files in `config/`. Flow: JSON → Symfony
  Serializer → entity DTO → Twig.
- `Reference`, `Category` and `FaqEntry` are full Doctrine entities with UUID v7
  primary keys. `Source` is a Doctrine embeddable used inside `Reference`
  (column prefix `source_`). `PrintableModel`, `Image` and `ContactRequest` are
  pure DTOs without Doctrine mapping.

### Reference (`reference` table)

- UUID v7 primary key, lifecycle callbacks enabled
- Content: `title`, `slug` (unique), `summary`, `description`
- The slug is generated from the title while a reference is created — the field
  is disabled there — and can be edited freely afterwards. Changing it changes
  the address of the detail page. A pattern check keeps it URL-safe and
  `UniqueEntity` catches a collision before the database does
- Images: `imageLandscape` / `imageFileLandscape` and `imagePortrait` /
  `imageFilePortrait`
- Classification: `category` (ManyToOne, nullable), `material` and `printer`
  (enums, nullable)
- Visibility: `isVisible`, defaults to `false`
- Attribution: embedded `Source` (`title`, `url`, `author`, all optional)
- Google rating: `ratingUrl` (nullable); timestamps `createdAt` (midnight on
  construction) and `updatedAt`
- **Both images and the category are mandatory**, enforced at form validation
  only — the columns stay nullable so references predating the split can still
  be read. The images are checked through a callback that accepts either a
  freshly uploaded file or an already stored one, because VichUploader writes
  the file name while flushing, long after validation has run. A reference
  predating the second image cannot be saved until a portrait image is
  supplied; that is intended

### Category (`category` table)

UUID v7 primary key, `name` and `slug` (unique). Same slug handling as the
reference. The slug is currently read nowhere — no route and no template uses
it.

### FaqEntry (`faq_entry` table)

UUID v7 primary key, index on `sort_order`. Fields `question`, `answer`,
`isVisible`, `sortOrder`, plus timestamps. Custom ordering through `sortOrder`
with up/down buttons in the backend, which needs a `getSortButtons()` getter for
EasyAdmin field rendering.

### Enums

- `App\Enum\Material`: ASA, FLEX, PA12-CF, PC, PC-CF, PETG, PLA
- `App\Enum\Printer`: Prusa CORE One INDX, CORE One L, CORE One+, MINI+, MK4S,
  MK4S + MMU3; `isMultiColor()` is true for the INDX and the MMU3 variant
- Both carry a `getHashtags()` method feeding the Instagram caption. **Keep the
  hashtags next to the case, never in a second place.**

## Backend

EasyAdmin 5 at `/admin`.

- **DashboardController** renders `templates/admin/dashboard.html.twig` with
  deep links into the reference and FAQ index pages; the menu holds Dashboard,
  Referenzen, Kategorien and FAQ
- **ReferenceCrudController** — references, sorted by `createdAt` descending
- **CategoryCrudController** — categories, sorted by `name` ascending. A
  category that references point at cannot be deleted: the button is hidden
  through `displayIf()` and `deleteEntity()` refuses the request, so a
  hand-crafted call cannot run into a foreign key error either. Use
  `ReferenceRepository::countByCategory()` for that check — it counts through
  the criteria API, because a hand-written DQL comparison against the
  association silently returns zero for the UUID identifier
- **FaqEntryCrudController** — FAQ entries, sorted by `sortOrder` ascending,
  with custom up/down actions that swap the sort order of neighbours
- All CRUD and field labels are German

**EasyAdmin 5 mechanics** — mandatory and easy to get wrong:

- Pretty URLs are required. They are loaded through
  `config/routes/easyadmin.yaml` with `type: easyadmin.routes`; the dashboard
  declares its own path via
  `#[AdminDashboard(routePath: '/admin', routeName: 'admin')]`
- Custom CRUD actions **must** carry `#[AdminRoute]`. Without it they are
  silently ignored when the routes are built
- Menu entries use `MenuItem::linkTo(<CrudController>::class, …)` and point at
  the CRUD controller, not at the entity
- `entityId` is a route path segment, not a query parameter. Read the record
  through `$context->getEntity()->getInstance()`, not from the query string
- Custom actions get **no** CSRF protection from the bundle — only `delete`,
  `batchDelete` and the boolean toggle are covered. State-changing actions have
  to restrict themselves to POST and validate their own token, as
  `FaqEntryCrudController` does for sorting

### Authentication

Firewall `admin` covers `^/admin` and uses **HTTP Basic** with realm
`krausgedruckt:admin`. One in-memory user `krausgedruckt` with `ROLE_ADMIN`;
the password hash comes from `ADMIN_PASSWORD`. Access control requires
`ROLE_ADMIN` for `^/admin`.

## Images

Every reference carries two images. Both are capped at 1080 pixels wide,
matching what Instagram accepts.

| | Landscape | Portrait |
| --- | --- | --- |
| Ratio | 5:4 | 4:5 |
| Stored size | 1080 × 864 | 1080 × 1350 |
| Directory | `public/images/references/landscape/` | `public/images/references/portrait/` |
| VichUploader mapping | `reference_images_landscape` | `reference_images_portrait` |
| LiipImagine filter | `reference_landscape` | `reference_portrait` |

- `Assert\Image` on the upload properties enforces the ratio with a tolerance of
  roughly one percent, a minimum size matching the target, a maximum of 12 MB
  and 30 megapixels, and restricts the type to JPEG, PNG and WebP. Imagick never
  scales up, so anything smaller than the target is rejected rather than
  interpolated
- `App\EventListener\ReferenceImageListener` listens on VichUploader's
  `POST_UPLOAD` and `POST_REMOVE`. On upload it hands the stored file to
  `App\Service\ImageNormalizer`, which applies the EXIF rotation, crops to the
  target size, converts to sRGB and strips the remaining metadata. The uploaded
  original is replaced, there is no archive copy. Both events drop the rendered
  versions from the LiipImagine cache, because the file name stays the same when
  an image is replaced
- `App\Service\ReferenceImageNamer` builds the filename as
  `<title-slug>-<uuid-without-dashes>.<extension>` for both mappings
- VichUploader owns the file lifecycle and needs no help: `delete_on_update`
  removes the previous picture when a new one is uploaded, even when the title
  changed the file name in between, and `delete_on_remove` removes both files
  when the reference is deleted. Renaming without uploading leaves the file
  under its old name — a cosmetic mismatch, nothing is lost
- **Display follows the device:** portrait wherever elements stack (mobile),
  landscape wherever they sit side by side (desktop). This holds on the detail
  page and the public list alike; the admin list always shows the landscape
  image
- References created before the split have no portrait image, so
  `Reference::getImagePortraitPathWithFallback()` falls back to the landscape
  one. Cached thumbnails land in `public/media/cache`
- **Never call `Imagick::autoOrientImage()` or `Imagick::autoOrient()`.** The
  name depends on the ImageMagick major version — the sixth binding knows the
  former, the seventh the latter — so either one works in one environment and
  fatals in the other. The rotation always goes through
  `ImageNormalizer::applyOrientation()`, which maps the eight EXIF values onto
  `flipImage()`, `flopImage()` and `rotateImage()`
- Known limitation: `Assert\Image` measures the physical pixels and ignores the
  EXIF orientation. A photo stored sideways is therefore rejected with a ratio
  message rather than being rotated first

## Design system

The three sites are one brand family, and the family lives in the shared
skeleton: header dimensions, the three-column dark footer, the container
widths, the typographic roles and the token architecture. What differs is
deliberate — papaya instead of petrol, the nozzle instead of the gear, a warm
ground instead of a cool one, soft cards with a shadow instead of flat hairline
cards, and a product photo where the sibling uses typography.

- **Typography:** `font-display` = `font-sans` = Aller (wordmark, headlines and
  body, which ties the type to the logo); `font-mono` = JetBrains Mono for
  eyebrows, labels and technical data. Both self-hosted in `public/fonts`, no
  external requests.
- **Container:** `max-w-6xl mx-auto px-6 lg:px-8`; text and legal pages
  `max-w-3xl`.
- **Section rhythm:** warm → white → warm → dark → white. The single dark block
  (`Ablauf`) arrives late on purpose.
- **Corners:** `rounded-lg` for buttons and fields, `rounded-2xl` for cards and
  containers. Cards are free-standing: border, white ground, soft shadow.
- **Two-tone headings:** the statement comes first in `neutral-900` (or white on
  a dark ground), the flourish follows underneath, smaller and in the accent —
  `<span class="mt-3 block text-[0.8em] text-accent-on-light">`. This holds on
  every page. **The homepage hero is the only exception**, and it is the only
  one allowed: reversing the order or inventing a third form elsewhere is drift,
  not personality.

### Color tokens

Defined in `public/css/input.css` under `@theme`. **No hex values in
templates** — use the tokens. Everything outside them is Tailwind's
`neutral-*`, hairlines are `neutral-200`. All tokens bind to `var(--color-…)`
rather than to copied hex values, so the interface cannot drift from the
palette.

**One vocabulary, all three brands.** The role is in the name, so the
difference between the sites falls into the values and not into the naming. The
same five tokens exist on krausgebaut and marcelkraus under the same names.

| Token | Value | Role |
| --- | --- | --- |
| `accent` | `orange-600` | the brand: surfaces, borders, markers, the mark — **never type** |
| `accent-on-light` | `orange-700` | type on a light ground (5.22:1 on white) |
| `accent-on-dark` | `orange-600` | type on a dark ground (4.98:1 on `neutral-900`) |
| `accent-hover` | `orange-500` | hover of a filled surface |
| `accent-on-light-hover` | `orange-800` | hover of type on a light ground |
| `surface-warm` | `orange-50` | the warm section ground |
| `brand-marcelkraus` | `purple-700` | the sibling brand — 2.54:1 on `neutral-900`, so not for the dark footer |
| `brand-marcelkraus-on-dark` | `purple-400` | the marcelkraus marker dot in the footer (6.42:1) |
| `brand-krausgebaut` | `cyan-800` | the sibling brand and the filled button of its band |
| `brand-krausgebaut-hover` | `cyan-900` | hover of that button |
| `brand-krausgebaut-on-dark` | `cyan-600` | type and markers on a dark ground (4.95:1) |

The naming makes the rule checkable: **`text-accent` without a role suffix must
not appear anywhere.** No project in the family has one.

The split is measured, not cosmetic. Papaya is a *light* color: it misses AA
as type on white (3.60:1) and carries a dark ground as it is. Petrol and purple
have the mirror problem, which is why their tokens hold different values under
the same names.

**The rules that follow from that:**

- **On a light ground type carries `accent-on-light`, never `accent`.**
- **On a dark ground type carries `accent-on-dark`.**
- **The filled button is `accent` with a `neutral-900` label** (4.98:1) and
  **lightens** to `accent-hover` on hover (6.21:1). The hover of a filled
  surface always moves in whichever direction keeps its label readable; here the
  label is near-black, so darkening would drop it to 3.43:1 on `orange-700`.
  There is one button and no second step: an outline variant existed in the
  partial without a single caller and has been removed.
- **`neutral-400` is never type on a light ground** — 2.58:1 on white. Use
  `neutral-500` at the very least, `neutral-600` for anything that carries
  meaning, which includes form labels, help text and placeholders. On a dark
  ground `neutral-400` is fine (6.94:1) and `neutral-500` is the one that misses
  (3.78:1).
- **Both sibling brands are dark colors** and neither can be read on a dark
  ground: `brand-krausgebaut` measures 2.48:1 there, `brand-marcelkraus`
  2.54:1. Anything on a dark ground therefore uses the `-on-dark` step — the
  band that points at the sister brand for its type, and the footer for both
  marker dots. That is the same split the accent has, only in the other
  direction.

### The family bracket

**Header and footer are binding.** Position, measurements, grid and behavior
stay identical across all three sites; the content of the lists does not.

- **The mobile menu** closes on the burger, on Escape (focus returns to the
  burger), on a link, on a tap outside and when the viewport grows to desktop.
  The burger swaps to a cross while open — an `aria-label` alone leaves sighted
  users without a signal.
- **The footer is a dark ground**, so every marker dot there carries an
  `-on-dark` step.
- **The skip link hands the focus to `main`** (`tabindex="-1"`); without it
  Safari leaves the focus on the link and the next tab goes back into the
  header.

### Brand mark

`_logo.html.twig` is the nozzle and the wordmark as one lockup. The nozzle and
"kraus" carry the accent, "gedruckt" the dark neutral; `mono: true` renders
everything in `fill-current`. Its construction matches the sibling brand: same
height, same baseline, only mark and color differ.

**The wordmark must stay outlined.** A master that keeps it as `<text>` carries
a `font-family` and therefore a dependency the logo is not allowed to have — it
would fall back to a generic sans wherever Aller is absent, and the mark is the
one thing on the page that has to be exact. A curve export is the requirement,
not a compromise.

Master artwork is **not** kept in the repository; Marcel supplies it on demand,
and every shipped asset is derived from it.

### Favicons

Three files at the web root, all derived from the master tile:

| File | Role |
|------|------|
| `favicon.svg` | primary — scales to any size a browser asks for |
| `favicon.ico` | 16 + 32 px in one file; also answers the implicit `/favicon.ico` request browsers make without a `<link>` |
| `apple-touch-icon.png` | 180×180, iOS home screen |

The SVG **must** keep its `width`/`height` attributes. Without them it has no
intrinsic size, so the browser rasterises it into a default box and scales that
into the tab slot, which leaves a pale rim around the tile. Every generated file
is checked for a fully opaque, single-color border before it ships.

The tile is `#F54900`, taken from the master — and that is exactly what the
`accent` token resolves to. **Artwork and palette agree to the digit, and they
have to stay that way.** When the artwork changes, the icon files are re-derived
from the master rather than edited.

## Contact form

**Hand-rolled — no `symfony/form`.** It is the same mechanism both sibling
sites use, so the family answers a submission through one path instead of three.

- `App\Dto\ContactRequest` is a plain object with public properties and
  validation attributes. It is filled from the request in the controller and
  handed to `symfony/validator`
- **Constraints are PHP attributes, never doc-block annotations.** The class
  shipped once with `@Assert\Email` in a doc block, which Symfony 8 does not
  read: the form accepted anything and an invalid address ended as a 500 inside
  the mailer
- `templates/partials/_contact_form.html.twig` renders it and holds the field
  classes. There is no form theme
- Five fields: `name`, `email`, `phone`, `discountCode`, `message`. The two
  required ones open the form and the optional ones follow, because a private
  customer is the majority here and should not have to skip a field before
  starting. The sister sites order their forms differently on purpose — there a
  company is the normal case

**The defences, and they behave differently on purpose:**

| Signal | Answer |
| --- | --- |
| Honeypot `website` filled | silent drop — fake success, nothing sent |
| Timestamp missing, tampered or younger than 3 seconds | silent drop |
| Timestamp older than 2 hours | **422 with a message**: a person whose form sat open |
| CSRF token invalid | 422 with a message |
| More than 5 submissions per hour and address | 422 with a message |
| Mail transport fails | 422 with a message |

The timestamp is signed with `hash_hmac` against `kernel.secret` — that is what
makes its age trustworthy, because otherwise a bot would simply post a value
that looks old enough. A bot learns nothing from a silent drop; a person is
never dropped without being told.

`send()` is wrapped and a `TransportExceptionInterface` goes through the normal
form-error path. That matters on this host: the sendmail DSN has two documented
ways of being wrong, and Apache replaces the Symfony error page with its own —
without the catch the enquiry is lost behind a bare 500.

The rate limiter is configured in `config/packages/rate_limiter.yaml` and raised
out of the way in the test environment, because its state outlives a single test
run.

- A rejected submission answers **422**, not 200, and re-renders with the
  submitted values, the first violation per field, and the visitor's still valid
  timestamp so a quick fix-and-resend is not read as a bot
- Fields carry **real labels**, not placeholders — a placeholder disappears on
  the first keystroke and leaves the field unnamed. A required field is marked
  once, by the accent asterisk on its label, with the `Pflichtfelder *` legend
  above the button
- The field keeps the **browser focus ring** (`focus:outline-2`) on top of the
  accent border. Replacing the ring with a one pixel border change made the
  form the only place on the site where the keyboard focus was weaker than the
  default
- The error border stays red while the field has focus, so the marking does not
  disappear at the moment it is needed
- The submit button pulls its classes from `_button_class.html.twig` rather than
  writing them out, so the contrast rule lives in exactly one place
- Errors are `red-600`, not the accent: on this brand an orange error would be
  indistinguishable from an orange heading
- The form-wide message is a live region (`role="alert"`); the honeypot is
  hidden with `sr-only` and carries an instruction rather than `aria-hidden`,
  which would hide a focusable field from assistive technology
- Discount code can be pre-filled: `/kontakt?discount-code=CODE`
- A successful submission sets a `contact_success` flash and redirects back onto
  `/kontakt`, where the confirmation takes the form's place. The redirect is
  what matters: without it a reload would send the message a second time
- Mail goes out through `TemplatedEmail`; the reply-to address is the sender of
  the contact request

## Instagram preview

Read-only page reachable from the three-dot menu of a reference, rendered by
`ReferenceCrudController::instagramPreview()` into
`templates/admin/instagram-preview.html.twig`.

`App\Service\InstagramCaptionBuilder` assembles the caption from three
paragraphs: the `#ModellMontag` introduction with the title and the summary,
the source sentence (only when all three source fields are set) and the hashtag
block. Hashtags come from `InstagramCaptionBuilder::GLOBAL_HASHTAGS` plus the
printer and the material; missing fields contribute nothing, duplicates are
dropped and the block is always sorted alphabetically. Without a summary the
introduction ends after the model name.

The caption sits in a copyable block with a button next to it. The asynchronous
clipboard API only exists in a secure context, so the button falls back to a
hidden selection when the backend is opened over plain HTTP.

## SEO / meta

Centralised in `base.html.twig`: `lang`, canonical, description, Open Graph and
Twitter card, all overridable per page through the `title`, `meta_description`,
`meta_robots` and `meta_image` blocks. `meta_image` is captured into a variable
rather than printed where it is defined, because the card needs the path twice.

Every page carries exactly one visible `h1`. Legal pages and the contact
confirmation are `noindex,follow`. JSON-LD sits in the `structured_data` block:
`ProfessionalService` on the homepage, `FAQPage` on the FAQ. Reference detail
pages carry none — the benefit is limited to image search.

`/robots.txt` and `/sitemap.xml` are generated by `DefaultController`. The
sitemap lists the public pages and every visible reference; legal pages, the
confirmation and the adVintage landing page stay out.

The sharing image `public/images/sharing.jpg` (1200×630) is a **finished asset,
not a build product** — the same rule the sibling projects follow, and its
composition is deliberately the mirror of krausgebaut's. Should it ever be
redrawn: white ground, the eyebrow with its square marker at the top left, the
logo lockup below it, a two line claim in `neutral-600`, and the domain with the
location as a mono line at the foot. The nozzle is oversized, **solid** accent
and cropped off the right edge, where the sibling crops its gear. Type is sized
for a chat card around 320 pixels wide rather than for the canvas, which is why
the mono lines sit well above their on-site sizes and why the mark is opaque
instead of a tint. The mono type is `accent-on-light` and `neutral-600`, never
`accent` — the contrast rule holds on the card as it does on the site.

## Analytics

Self-hosted Matomo (**SiteId 8**), inlined in `base.html.twig` behind
`{% if app.environment == 'prod' %}` — development and test never track.
Cookieless via `disableCookies`, so no consent banner is required.

## Tests

PHPUnit 13 with browser-kit and css-selector, matching the sibling projects.

- `tests/Controller/RouteSmokeTest.php` calls every frontend route once and
  asserts that it answers and carries exactly one `h1`
- `tests/Controller/ContactFormTest.php` pins the contact form: an invalid
  submission is refused with 422, names the field and sends nothing; a valid one
  redirects and sends exactly one mail; the confirmation takes the form's place;
  the discount code arrives from the query string; a filled honeypot and a
  tampered signature are dropped silently while a stale form is asked to resend

```bash
ddev exec bin/phpunit
```

The test environment ignores `.env.local` by design and appends `_test` to the
database name. That database needs to exist once per machine:

```bash
ddev mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS db_test; GRANT ALL PRIVILEGES ON db_test.* TO 'db'@'%'; FLUSH PRIVILEGES;"
ddev exec bin/console doctrine:migrations:migrate --no-interaction --env=test
ddev exec bin/console doctrine:fixtures:load --no-interaction --env=test
```

## Fixtures and empty directories

`src/DataFixtures/` contains `CategoryFixtures`, `ReferenceFixtures` and
`FaqEntryFixtures`. Load them with
`ddev exec bin/console doctrine:fixtures:load`, or use `bin/reinstall-db` for a
full reset.

A directory that has to exist in the repository but carries no tracked content
is held by an **empty `.gitignore`**, never by a `.gitkeep`. This applies
project-wide, including directories a Symfony recipe scaffolds. Currently:
`public/images/references/landscape/` and `public/images/references/portrait/`.

## Static assets

- Images: `public/images/`
- Landing page images: `public/images/advintage-landing-page/`
- Shop photo: `public/images/etsy-shop.jpg` (1080 × 1080, shop band on the
  homepage)
- Reference images: `public/images/references/landscape/` and `portrait/`
  (uploaded through VichUploader)
- Logo: `templates/partials/_logo.html.twig` — a Twig partial, not an image file
- Sharing image: `public/images/sharing.jpg` (1200×630, Open Graph)

## Logging

`symfony/monolog-bundle`, configured identically to the sibling projects because
all three run on the same host. Production writes to a **rotating file** rather
than the recipe's `php://stderr`: on the Uberspace host the FastCGI error stream
is not readable from the account, so an error would leave no trace that can be
looked at over SSH. Only errors are kept, together with the request that led up
to them; deprecations go to their own file.

## Security headers

`App\EventListener\SecurityHeadersListener` sets `X-Content-Type-Options`,
`Referrer-Policy` and `X-Frame-Options` on every main response.

## Deployment

Rolled out with `bin/deploy`, which is the sibling's script plus one step:

```bash
ssh kraus 'cd ~/html/krausgedruckt && bin/deploy'
```

It fetches, resets hard to `origin/main`, installs the production dependencies,
**removes** `var/cache/prod`, rebuilds it and then runs the migrations. The
reset is hard, but `.env.local`, `vendor/` and `var/` are ignored and survive
it. `public/css/output.css` is committed, so the server needs no npm run.

The cache is removed rather than cleared, and that is not a detail:
`cache:clear` loads the existing compiled container before it replaces it, so a
release that drops a bundle dies on a class that no longer exists. It has
happened once here, when the anti-spam bundle went.

Migrations run **after** the rebuild so they meet the new container rather than
the old one.

**Two things do not travel with the repository.** A fresh server is not complete
after a clone:

1. **The database** — references, categories and FAQ entries live in MariaDB.
   The migrations build the schema; the content needs a dump.
2. **The uploaded reference images** — `public/images/references/landscape/` and
   `portrait/` are ignored, so only the two empty `.gitignore` placeholders and
   the fixture pictures are versioned. Without copying them across, every
   reference loses its picture.

`public/media/cache/` does **not** need to travel; LiipImagine rebuilds it on
demand. What it does need is a writable `public/media/`, writable upload
directories and a writable `var/`.

`.env.local` has to exist on the server and override `APP_ENV`, `APP_SECRET`,
`DATABASE_URL` and `MAILER_DSN`. The last one is the quiet one: it defaults to
`null://null`, so an unset `MAILER_DSN` swallows every contact request without
raising an error.

## Code conventions

- **Comments, identifiers and this documentation are English.** Visible site
  content is German, with correct German quotation marks „…“.
- **No hex color values in templates** — use the design tokens. Standalone
  asset files such as `favicon.svg` may carry hex.
- **Voice:** the business speaks as „wir“ and addresses the customer as „du“.
  In the FAQ the customer speaks too, and keeps „ich“ for itself while
  addressing the business as „ihr“ — the same word therefore moves in one entry
  and stays in the next.
- **Routing** uses German URLs throughout.

## Environment variables

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

## Open points

1. **The test database is hand-loaded state, not a test fixture.** There is no
   transaction isolation, no fixture loading in `setUp()` and no migration run
   in the test environment; `RouteSmokeTest::testReferenceDetailRenders` covers
   that with `markTestSkipped` when the table is empty, so on a fresh machine
   the test is silently green. `bin/reinstall-db` runs against the development
   database, not `db_test`.
2. **Most of `src/` is untested.** The services, the image listener, the
   repositories, the admin controllers and the entities have no tests; only
   `DefaultController` is touched, through the smoke test.
