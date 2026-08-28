# krausgedruckt

Shared rules that apply here: `../docs/WEB_STACK.md`, `../docs/DEPLOYMENT.md`,
`../docs/BRAND_FAMILY.md`. This document carries only what is true of this
project alone.

## Overview

Business website for **krausgedruckt** – the 3D printing branch of Marcel
Kraus's freelance work (https://www.krausgedruckt.de). It presents the services,
the printed references and the FAQ, and carries a contact form and a shop band.

German only. References, categories and FAQ entries live in MariaDB and are
maintained through an EasyAdmin backend; everything else is markup or JSON.

**Voice:** the business speaks as „wir“ and addresses the customer as „du“. In
the FAQ the customer speaks too, keeping „ich“ for itself and addressing the
business as „ihr“ – the same word therefore moves in one entry and stays in the
next. Routing uses German URLs throughout.

## Stack notes

`symfony/form` is installed, but only as a transitive dependency of EasyAdmin.
**The frontend does not touch it.** The Tailwind forms plugin is loaded for the
same reason – the backend needs it.

**ddev runs Apache here**, so `public/.htaccess` is exercised locally; under
nginx it is ignored and only ever runs in production.

## Development

```bash
ddev start                                   # https://krausgedruckt.ddev.site
ddev launch -m                               # Mailpit, captured mail
ddev exec npm run build                      # Tailwind, minified
ddev exec bin/console make:migration         # after an entity change
ddev exec bin/console doctrine:migrations:migrate
bin/reinstall-db                             # full local reset
```

`bin/reinstall-db` drops and recreates the database, runs the migrations, sweeps
the uploaded reference images from `landscape/` and `portrait/` and loads the
fixtures. **It excludes `.gitignore` from the sweep** – without that the reset
removes the placeholders and the two directories fall out of the repository.

**The test database is built, not maintained by hand:**

```bash
ddev exec composer test-db
```

It creates the database, runs the migrations and loads the fixtures. Two tests
depend on a visible reference existing; without that step they skip rather than
fail, which reads as green on a machine that never had the data. The two
remaining skips are correct – `robots.txt` and `sitemap.xml` carry no heading,
so the heading test steps over them.

## Layout

```
config/                advintage-landing-page.json and the Symfony configuration
migrations/            nine Doctrine migrations
src/Controller/        DefaultController – all frontend routes
src/Controller/Admin/  Dashboard and the three CRUD controllers
src/Entity/            Reference, Category, FaqEntry
src/Dto/               ContactRequest
src/EventListener/     AdminLoginThrottleListener, ReferenceImageListener,
                       SecurityHeadersListener
src/Security/          EnvironmentUserProvider
src/Service/           ImageNormalizer, ReferenceImageNamer,
                       InstagramCaptionBuilder, HashtagSorter
templates/             base.html.twig, default/, admin/, partials/
public/                css/, fonts/, images/, media/cache/, favicon.*
```

Partials: `_logo`, `_eyebrow`, `_icons`, `_card`, `_reference_card` (shared by
the homepage teaser and the overview), `_model` (attribution of a printed
model), `_button`, `_button_class`, `_link_arrow`, `_contact_form`,
`_conversion_band`, `_sibling_band`.

Three carry rules rather than just markup:

* **`_button_class`** holds the contrast rule for the filled button and emits
  nothing but a class string. `_button` renders the link version; the contact
  form's submit pulls the same string through `include()`. Neither copy can
  drift, and the rule hangs on exactly one place.
* **`_card`** is embedded, not included, because the picture and the running
  text differ per case while the shell does not. A card **with** an `href` is
  one click target: the heading link stretches over the whole article
  (`after:absolute after:inset-0`) and the hover shadow is rendered. A card
  **without** one stays inert, because a growing shadow is a promise of a click.
* **`_conversion_band`** has three shapes: plain band, `boxed` as an inset card,
  and – with an `image` – a two column block with a picture. Its `actions` block
  defaults to the enquiry button; a page whose closing action is something else
  embeds it and overrides the block, so ground, measurements and rhythm still
  come from one place.

The navigation is built once from the `nav_items` list in `base.html.twig`. An
item names either a `route` inside the site or an external `url` with
`external: true`, which gets the off-site icon and opens in a new tab; the shop
is the only one and sits last. A page that stands apart may narrow the list down
by setting `nav_items` at its own top level, which is what the adVintage landing
page does.

## Routing

Defined in `src/Controller/DefaultController.php` with PHP attributes.

| # | Path | Route name | Purpose |
| --- | --- | --- | --- |
| 1 | `/` | `app_homepage` | Homepage |
| 2 | `/advintage` | `app_landing_page_advintage` | Landing page, JSON-backed |
| 3 | `/app` | `app_app` | App Store landing page for the 3D-Druck-Kostenrechner |
| 4 | `/bewerten` | `app_review` | Redirect to the Google review URL |
| 5 | `/datenschutz` | `app_data_privacy` | Privacy policy |
| 6 | `/haeufig-gestellte-fragen` | `app_faq` | FAQ, database-backed |
| 7 | `/impressum` | `app_imprint` | Imprint |
| 8 | `/kontakt` | `app_contact` | Contact form (GET, POST) |
| 9 | `/kontakt-per-email` | `app_contact_email` | Redirect to `mailto:` |
| 10 | `/kontakt-per-whats-app` | `app_contact_whats_app` | Redirect to WhatsApp |
| 11 | `/referenzen` | `app_references` | Reference list, database-backed |
| 12 | `/referenzen/{year}/{slug}` | `app_reference_detail` | Reference detail page |
| 13 | `/robots.txt` | `app_robots` | robots, absolute sitemap URL |
| 14 | `/sitemap.xml` | `app_sitemap` | Public pages plus visible references |
| 15 | `/admin` | `admin` | EasyAdmin dashboard |
| 16 | `/admin/logout` | `admin_logout` | Logout, intercepted by the firewall |

Database-backed: `/` loads the three most recent visible references for the
teaser (`HOMEPAGE_REFERENCE_LIMIT`); `/referenzen` uses
`ReferenceRepository::findAllOrdered()` (by `createdAt` descending);
`/referenzen/{year}/{slug}` resolves through
`ReferenceRepository::findByYearAndSlug()` and answers 404 for invisible or
unknown references; the FAQ uses `FaqEntryRepository::findAllOrdered()` (by
`sortOrder` ascending).

`/advintage` loads `config/advintage-landing-page.json` and deserialises to
`PrintableModel[]`. **The path is anchored to `kernel.project_dir`:** a relative
path resolves against the working directory, which holds for the web server and
breaks in the test runner.

## Data model

* **References, categories and FAQ entries** live in MariaDB and are managed
  through EasyAdmin. Flow: database → Doctrine → entity → Twig.
* **Landing pages** use JSON files in `config/`. Flow: JSON → Serializer →
  entity DTO → Twig.
* `Reference`, `Category` and `FaqEntry` are full Doctrine entities with UUID v7
  primary keys. `Source` is a Doctrine embeddable used inside `Reference`
  (column prefix `source_`). `PrintableModel`, `Image` and `ContactRequest` are
  pure DTOs without Doctrine mapping.

### Reference (`reference` table)

* UUID v7 primary key, lifecycle callbacks enabled
* Content: `title`, `slug` (unique), `summary`, `description`
* The slug is generated from the title while a reference is created – the field
  is disabled there – and can be edited freely afterwards. Changing it changes
  the address of the detail page. A pattern check keeps it URL-safe and
  `UniqueEntity` catches a collision before the database does
* Images: `imageLandscape` / `imageFileLandscape` and `imagePortrait` /
  `imageFilePortrait`
* Classification: `category` (ManyToOne, nullable), `material` and `printer`
  (enums, nullable)
* Free tags: `hashtags` (nullable), the reference's own Instagram tags. **The
  setter owns the notation** – lower case, one leading hash per tag, no
  duplicates, alphabetical, joined with `", "` – so the field accepts a sloppy
  input and stores the form the comment needs. What normalizing cannot fix, a
  hyphen or a space inside a tag, a `Regex` constraint reports; `Assert\Length`
  guards the 255 characters of the column, because a longer value would reach
  the database as an exception instead of a form error. `getHashtagList()` hands
  the parsed list to the caption builder
* Visibility: `isVisible`, defaults to `false`
* Attribution: embedded `Source` (`title`, `url`, `author`, all optional)
* Google rating: `ratingUrl` (nullable); timestamps `createdAt` (midnight on
  construction) and `updatedAt`
* **Both images and the category are mandatory**, enforced at form validation
  only – the columns stay nullable so references predating the split can still
  be read. The images are checked through a callback that accepts either a
  freshly uploaded file or an already stored one, because VichUploader writes
  the file name while flushing, long after validation has run. A reference
  without a portrait image cannot be saved until one is supplied; that is
  intended

### Category (`category` table)

UUID v7 primary key, `name` and `slug` (unique). Same slug handling as the
reference. **The slug is currently read nowhere** – no route and no template
uses it.

### FaqEntry (`faq_entry` table)

UUID v7 primary key, index on `sort_order`. Fields `question`, `answer`,
`isVisible`, `sortOrder`, plus timestamps. Custom ordering through `sortOrder`
with up/down buttons in the backend, which needs a `getSortButtons()` getter for
EasyAdmin field rendering.

### Enums

* `App\Enum\Material`: ASA, FLEX, PA12-CF, PC, PC-CF, PETG, PLA
* `App\Enum\Printer`: Prusa CORE One INDX, CORE One L, CORE One+, MINI+, MK4S,
  MK4S + MMU3; `isMultiColor()` is true for the INDX and the MMU3 variant
* Both carry a `getHashtags()` method feeding the Instagram comment. **Keep the
  hashtags next to the case, never in a second place.**

## Backend

EasyAdmin 5 at `/admin`.

* **DashboardController** renders `templates/admin/dashboard.html.twig` with
  deep links into the reference and FAQ index pages; the menu holds Dashboard,
  Referenzen, Kategorien and FAQ
* **ReferenceCrudController** – sorted by `createdAt` descending
* **CategoryCrudController** – sorted by `name` ascending. A category that
  references point at cannot be deleted: the button is hidden through
  `displayIf()` and `deleteEntity()` refuses the request, so a hand-crafted call
  cannot run into a foreign key error either. **Use
  `ReferenceRepository::countByCategory()` for that check** – it counts through
  the criteria API, because a hand-written DQL comparison against the
  association silently returns zero for the UUID identifier
* **FaqEntryCrudController** – sorted by `sortOrder` ascending, with custom
  up/down actions that swap the sort order of neighbors
* All CRUD and field labels are German

**EasyAdmin 5 mechanics, mandatory and easy to get wrong:**

1. Pretty URLs are required. They are loaded through
   `config/routes/easyadmin.yaml` with `type: easyadmin.routes`; the dashboard
   declares its own path via
   `#[AdminDashboard(routePath: '/admin', routeName: 'admin')]`
2. Custom CRUD actions **must** carry `#[AdminRoute]`. Without it they are
   silently ignored when the routes are built
3. Menu entries use `MenuItem::linkTo(<CrudController>::class, …)` and point at
   the CRUD controller, not at the entity
4. `entityId` is a route path segment, not a query parameter. Read the record
   through `$context->getEntity()->getInstance()`, not from the query string
5. Custom actions get **no** CSRF protection from the bundle – only `delete`,
   `batchDelete` and the boolean toggle are covered. State-changing actions have
   to restrict themselves to POST and validate their own token, as
   `FaqEntryCrudController` does for sorting

### Authentication

Firewall `admin` covers `^/admin` and uses **HTTP Basic** with realm
`krausgedruckt:admin`. Access control requires `ROLE_ADMIN` for `^/admin`.

**Both halves of the credential come from the environment**, through
`App\Security\EnvironmentUserProvider`: `ADMIN_USERNAME` and the hash in
`ADMIN_PASSWORD`. A memory provider cannot do this – it spells the user name out
as a YAML key, and a key is one of the few places Symfony does not resolve
`%env()%`. That would put the name in the repository next to the path it
unlocks, which on a public repository is half a set of credentials given away.
The committed `.env` carries placeholders only.

**Failed attempts are counted and then refused.** Symfony's `login_throttling`
does not reach this firewall: it only covers authenticators it considers
interactive, and HTTP Basic is not one – measured, not assumed.
`App\EventListener\AdminLoginThrottleListener` spends the `admin_login` limiter
instead: five failures per address per fifteen minutes, checked **before** the
password is verified, so an exhausted budget refuses the correct password too. A
successful login costs nothing, which matters because Basic sends the credential
on every single request.

**The check reads `getRemainingTokens()`, not `isAccepted()`.** Asking the
limiter for zero tokens is always granted, so the accepted flag stays true long
after the budget is gone.

## Images

Every reference carries two images, both capped at 1080 pixels wide, matching
what Instagram accepts.

| # | | Landscape | Portrait |
| --- | --- | --- | --- |
| 1 | Ratio | 5:4 | 4:5 |
| 2 | Stored size | 1080 × 864 | 1080 × 1350 |
| 3 | Directory | `public/images/references/landscape/` | `public/images/references/portrait/` |
| 4 | Vich mapping | `reference_images_landscape` | `reference_images_portrait` |
| 5 | Liip filter | `reference_landscape` | `reference_portrait` |

* `Assert\Image` on the upload properties enforces the ratio with a tolerance of
  roughly one percent, a minimum size matching the target, a maximum of 12 MB
  and 30 megapixels, and restricts the type to JPEG, PNG and WebP. **Imagick
  never scales up**, so anything smaller than the target is rejected rather than
  interpolated
* `App\EventListener\ReferenceImageListener` listens on VichUploader's
  `POST_UPLOAD` and `POST_REMOVE`. On upload it hands the stored file to
  `App\Service\ImageNormalizer`, which applies the EXIF rotation, crops to the
  target size, converts to sRGB and strips the remaining metadata. The uploaded
  original is replaced, there is no archive copy. Both events drop the rendered
  versions from the LiipImagine cache, because the file name stays the same when
  an image is replaced
* `App\Service\ReferenceImageNamer` builds the filename as
  `<title-slug>-<uuid-without-dashes>.<extension>` for both mappings
* VichUploader owns the file lifecycle and needs no help: `delete_on_update`
  removes the previous picture when a new one is uploaded, even when the title
  changed the file name in between, and `delete_on_remove` removes both files
  when the reference is deleted. Renaming without uploading leaves the file
  under its old name – cosmetic, nothing is lost
* **Display follows the device:** portrait wherever elements stack (mobile),
  landscape wherever they sit side by side (desktop). This holds on the detail
  page and the public list alike; the admin list always shows the landscape
  image
* References without a portrait image fall back through
  `Reference::getImagePortraitPathWithFallback()`. Cached thumbnails land in
  `public/media/cache`
* **Never call `Imagick::autoOrientImage()` or `Imagick::autoOrient()`.** The
  name depends on the ImageMagick major version – the sixth binding knows the
  former, the seventh the latter – so either one works in one environment and
  fatals in the other. The rotation always goes through
  `ImageNormalizer::applyOrientation()`, which maps the eight EXIF values onto
  `flipImage()`, `flopImage()` and `rotateImage()`
* Known limitation: `Assert\Image` measures the physical pixels and ignores the
  EXIF orientation. A photo stored sideways is rejected with a ratio message
  rather than being rotated first

## Design

Tokens, contrast rules and the family bracket are in
`../docs/BRAND_FAMILY.md`. What differs here is deliberate: papaya instead of
petrol, the nozzle instead of the gear, a warm ground instead of a cool one,
soft cards with a shadow instead of flat hairline cards, and a product photo
where the sibling uses typography.

* **Section rhythm:** warm → white → warm → dark → white. The single dark block
  (`Ablauf`) arrives late on purpose.
* **`surface-warm`** (`orange-50`) is the warm section ground, a token this site
  has and its siblings do not.
* **Corners:** `rounded-2xl` for cards and containers. Cards are free-standing:
  border, white ground, soft shadow.
* **Two-tone headings:** the statement comes first in `neutral-900` (or white on
  a dark ground), the flourish follows underneath, smaller and in the accent –
  `<span class="mt-3 block text-[0.8em] text-accent-on-light">`. This holds on
  every page. **The homepage hero is the only exception**, and it is the only
  one allowed: reversing the order or inventing a third form elsewhere is drift,
  not personality.
* **The filled button is `accent` with a `neutral-900` label** (4.98:1) and
  **lightens** to `accent-hover` on hover (6.21:1); darkening would drop the
  label to 3.43:1. There is one button and no second step.
* **Errors are `red-600`, not the accent** – on this brand an orange error would
  be indistinguishable from an orange heading.
* Mono sizes: `text-sm` in the mobile menu and on the button, `text-xs`
  everywhere else.

The logo is the nozzle and the wordmark as one lockup; the nozzle and „kraus“
carry the accent, „gedruckt“ the dark neutral. Its construction matches the
sibling brand: same height, same baseline, only mark and color differ.

## Contact form

The mechanism is in `../docs/WEB_STACK.md`. Specific here:

* **Five fields:** `name`, `email`, `phone`, `discountCode`, `message`. **The
  two required ones open the form and the optional ones follow**, because a
  private customer is the majority here and should not have to skip a field
  before starting. The sister sites order their forms differently on purpose –
  there a company is the normal case
* `templates/partials/_contact_form.html.twig` holds the field classes. There is
  no form theme
* The field keeps the **browser focus ring** (`focus:outline-2`) on top of the
  accent border; replacing the ring with a one pixel border change makes the
  form the only place on the site where keyboard focus is weaker than the
  default
* The form-wide message is a live region (`role="alert"`); the honeypot is
  `sr-only` **and** `aria-hidden`, and keeps `tabindex="-1"`. The tab order
  alone is not enough: a screen reader's reading mode walks the document, not
  the tab chain, so without `aria-hidden` the trap is read out to exactly the
  visitors who cannot see that it is one
* Discount code can be pre-filled: `/kontakt?discount-code=CODE`
* Mail goes out through `TemplatedEmail`
* The legal mailbox is `mail+legal@krausgedruckt.de`

## Instagram preview

Read-only page reachable from the three-dot menu of a reference, rendered by
`ReferenceCrudController::instagramPreview()` into
`templates/admin/instagram-preview.html.twig`.

**Instagram counts five hashtags per post, so the tags are split over two
texts** and `App\Service\InstagramCaptionBuilder` builds both.

`buildCaption()` assembles the post from three paragraphs: the `#ModellMontag`
introduction with the title and the summary, the source sentence (only when all
three source fields are set) and `POST_HASHTAGS`. Without a summary the
introduction ends after the model name. That constant holds four tags –
`#krausgedruckt #3ddruck #erftstadt #rheinerftkreis` – because the introduction
already spends the fifth on `#ModellMontag`. **Its order is the published one
and therefore not alphabetical**, the one place in this project where a constant
list is not sorted.

`buildHashtagComment()` builds the block that goes under the post as the first
comment: `COMMENT_HASHTAGS` plus the printer, the material and the reference's
own `hashtags`. Missing fields contribute nothing, duplicates are dropped, and
**everything the caption already carries is subtracted** – `POST_HASHTAGS` and
`INTRODUCTION_HASHTAG` alike, compared without regard to case, because Instagram
reads a hashtag that way. No tag therefore appears twice.

**Sorting runs through `App\Service\HashtagSorter`, never through `sort()`.** A
byte comparison puts every umlaut behind the whole alphabet, and the tags may
carry one; the sorter folds `ä ö ü ß` onto their base letters without a locale
and without the intl extension. The entity uses the same sorter for what it
stores, so the two orders cannot drift.

**A tag belongs in `POST_HASHTAGS` only when it never varies.** A hashtag cannot
carry a hyphen – Instagram ends the tag there, turning `#rhein-erft-kreis` into
`#rhein` – so the joined spelling is correct, not a shortening.

Both texts sit in a copyable block with a button next to it, paired through
`data-instagram-copy-trigger` and `data-instagram-copy-source` so the script
serves any number of them. The asynchronous clipboard API only exists in a
secure context, so the button falls back to a hidden selection when the backend
is opened over plain HTTP.

## SEO / meta

Centralised in `base.html.twig`: `lang`, canonical, description, Open Graph and
Twitter card, all overridable per page through the `title`, `meta_description`,
`meta_robots` and `meta_image` blocks. `meta_image` is captured into a variable
rather than printed where it is defined, because the card needs the path twice.

Every page carries exactly one visible `h1`. Legal pages and the contact
confirmation are `noindex,follow`. JSON-LD sits in the `structured_data` block:
`ProfessionalService` on the homepage, `FAQPage` on the FAQ. **Reference detail
pages carry none** – the benefit is limited to image search.

`/robots.txt` and `/sitemap.xml` are generated by `DefaultController`. The
sitemap lists the public pages and every visible reference; legal pages, the
confirmation and the adVintage landing page stay out.

Sharing image composition, the deliberate mirror of krausgebaut's: white ground,
the eyebrow with its square marker at the top left, the logo lockup below it, a
two line claim in `neutral-600`, and the domain with the location as a mono line
at the foot. The nozzle is oversized, **solid** accent and cropped off the right
edge. The mono type is `accent-on-light` and `neutral-600`, never `accent`.

## Tests

72 cases.

| # | File | Covers |
| --- | --- | --- |
| 1 | `tests/Controller/RouteSmokeTest.php` | every frontend route answers and carries exactly one `h1` |
| 2 | `tests/Controller/ContactFormTest.php` | an invalid submission is refused with 422, names the field and sends nothing; a valid one redirects and sends exactly one mail; the confirmation takes the form's place; the discount code arrives from the query string; a filled honeypot and a tampered signature are dropped silently while a stale form is asked to resend |
| 3 | `tests/Controller/BackendThrottleTest.php` | after the budget is spent the **correct** password is refused too – the only question a status code can answer here, because Basic returns 401 either way |
| 4 | `tests/Entity/ReferenceHashtagsTest.php` | the hashtag notation of `Reference` |
| 5 | `tests/Service/InstagramCaptionBuilderTest.php` | the two Instagram texts |

Files 4 and 5 are **plain `TestCase`s without kernel and without database**,
because the logic behind them is pure – that is what makes them cheap enough to
pin every case rather than a sample. They carry the invariant the feature stands
on: `testNoHashtagReachesInstagramTwice` compares the hashtags of the caption
against those of the comment, folded to lower case.

## Fixtures and empty directories

`src/DataFixtures/` contains `CategoryFixtures`, `ReferenceFixtures` and
`FaqEntryFixtures`. Load them with
`ddev exec bin/console doctrine:fixtures:load`, or use `bin/reinstall-db`.

Directories held by an empty `.gitignore`:
`public/images/references/landscape/` and `public/images/references/portrait/`.

## Static assets

* Images: `public/images/`
* Landing page images: `public/images/advintage-landing-page/`
* Shop photo: `public/images/etsy-shop.jpg` (1080 × 1080, shop band)
* Reference images: `public/images/references/landscape/` and `portrait/`
* Logo: `templates/partials/_logo.html.twig` – a Twig partial, not an image
* Sharing image: `public/images/sharing.jpg` (1200×630)

## Deployment

Server directory `~/html/krausgedruckt`. Mechanism, deploy keys, the migration
dump and the mailer are in `../docs/DEPLOYMENT.md`.

**Two things do not travel with the repository.** A fresh server is not complete
after a clone:

1. **The database** – the migrations build the schema, the content needs a dump.
2. **The uploaded reference images** – `landscape/` and `portrait/` are ignored,
   so only the two empty `.gitignore` placeholders and the fixture pictures are
   versioned. Without copying them across, every reference loses its picture.

`public/media/cache/` does **not** need to travel; LiipImagine rebuilds it on
demand. What it does need is a writable `public/media/`, writable upload
directories and a writable `var/`.

## Environment variables

Defaults live in `.env`, overrides in `.env.local` (never committed).

| # | Variable | Purpose |
| --- | --- | --- |
| 1 | `ADMIN_USERNAME` | Name of the single admin user – never committed with a real value |
| 2 | `ADMIN_PASSWORD` | Password hash of that user |
| 3 | `APP_ENV` | Symfony environment, overridden to `prod` in deployments |
| 4 | `APP_SECRET` | Symfony application secret |
| 5 | `CONTACT_TO` | Recipient of contact form mail |
| 6 | `CONTACT_FROM` | Sender of contact form mail |
| 7 | `DATABASE_URL` | Doctrine connection string |
| 8 | `MAILER_DSN` | Symfony Mailer transport |

Outbound targets are **not** environment variables. They live in
`config/services.yaml` and are read with `getParameter()`:
`app.app_store_url_mobile`, `app.contact_email_address`, `app.etsy_url`,
`app.google_review_url`, `app.instagram_url`, `app.legal_email_address`,
`app.whats_app_url`.

The app is promoted for iOS only; there is no Mac badge and no variable for one.

## Open points

1. **The test database is hand-loaded state, not a test fixture.** There is no
   transaction isolation, no fixture loading in `setUp()` and no migration run
   in the test environment; `RouteSmokeTest::testReferenceDetailRenders` covers
   that with `markTestSkipped` when the table is empty, so on a fresh machine
   the test is silently green. `bin/reinstall-db` runs against the development
   database, not `db_test`.
2. **Most of `src/` is untested.** The image listener, the repositories, the
   admin controllers and the image services have no tests; `DefaultController`
   is touched through the smoke test, and the only unit-tested ground is the
   Instagram caption with the hashtag notation of `Reference`.
