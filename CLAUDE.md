# krausgedruckt

## Overview

Business website for **krausgedruckt** – the 3D printing branch of Marcel Kraus's freelance work (https://www.krausgedruckt.de). It presents the services, the blog and the FAQ, and carries a contact form and a shop band.

German only. The blog and the FAQ come from kongtent; everything else is markup or JSON. The `faq_entry` table and its EasyAdmin backend still stand but are read by nothing.

**Voice:** the business speaks as „wir“ and addresses the customer as „du“. In the FAQ the customer speaks too, keeping „ich“ for itself and addressing the business as „ihr“ – the same word therefore moves in one entry and stays in the next. Routing uses German URLs throughout.

## Stack notes

`symfony/form` is installed, but only as a transitive dependency of EasyAdmin. **The frontend does not touch it.** The Tailwind forms plugin is loaded for the same reason – the backend needs it.

**ddev runs Apache here**, so `public/.htaccess` is exercised locally; under nginx it is ignored and only ever runs in production.

## Development

```bash
ddev start                                   # https://krausgedruckt.ddev.site
ddev launch -m                               # Mailpit, captured mail
ddev exec npm run build                      # Tailwind, minified
ddev exec bin/console make:migration         # after an entity change
ddev exec bin/console doctrine:migrations:migrate
bin/reinstall-db                             # full local reset
```

`bin/reinstall-db` drops and recreates the database, runs the migrations and loads the fixtures.

**The test database is built, not maintained by hand:**

```bash
ddev exec composer test-db
```

It creates the database, runs the migrations and loads the fixtures. The two skips are correct – `robots.txt` and `sitemap.xml` carry no heading, so the heading test steps over them.

## Layout

```
config/                advintage-landing-page.json and the Symfony configuration
migrations/            ten Doctrine migrations
src/Controller/        DefaultController, BlogController – the frontend routes
src/Controller/Admin/  Dashboard and the FAQ CRUD controller
src/Entity/            FaqEntry, and the DTOs of the landing page
src/Dto/               ContactRequest
src/EventListener/     AdminLoginThrottleListener, SecurityHeadersListener
src/Security/          EnvironmentUserProvider
src/Twig/              PlainTextExtension, SoleLinkExtension
templates/             base.html.twig, content/, admin/, partials/,
                       bundles/KongtentBundle/blocks/
public/                css/, fonts/, images/, favicon.*
```

Partials: `_logo`, `_eyebrow`, `_icons`, `_card`, `_post_card` (shared by the homepage teaser and the overview of the blog), `_button`, `_button_class`, `_link_arrow`, `_contact_form`, `_conversion_band`, `_sibling_band`.

Three carry rules rather than just markup:

* **`_button_class`** holds the contrast rule for the filled button and emits nothing but a class string. `_button` renders the link version; the contact form's submit pulls the same string through `include()`. Neither copy can drift, and the rule hangs on exactly one place.
* **`_card`** is embedded, not included, because the picture and the running text differ per case while the shell does not. A card **with** an `href` is one click target: the heading link stretches over the whole article (`after:absolute after:inset-0`) and the hover shadow is rendered. A card **without** one stays inert, because a growing shadow is a promise of a click.
* **`_conversion_band`** has three shapes: plain band, `boxed` as an inset card, and – with an `image` – a two column block with a picture. Its `actions` block defaults to the inquiry button; a page whose closing action is something else embeds it and overrides the block, so ground, measurements and rhythm still come from one place.

The navigation is built once from the `nav_items` list in `base.html.twig`. An item names either a `route` inside the site or an external `url` with `external: true`, which gets the off-site icon and opens in a new tab; the shop is the only one and sits last. A page that stands apart may narrow the list down by setting `nav_items` at its own top level, which is what the adVintage landing page does.

## Routing

Defined in `src/Controller/DefaultController.php` and `src/Controller/BlogController.php` with PHP attributes.

| # | Path | Route name | Purpose |
| --- | --- | --- | --- |
| 1 | `/` | `app_homepage` | Homepage |
| 2 | `/advintage` | `app_landing_page_advintage` | Landing page, JSON-backed |
| 3 | `/app` | `app_app` | App Store landing page for the 3D-Druck-Kostenrechner |
| 4 | `/bewerten` | `app_review` | Redirect to the Google review URL |
| 5 | `/datenschutz` | `app_data_privacy` | Privacy policy |
| 6 | `/haeufig-gestellte-fragen` | `app_faq` | FAQ, one content out of kongtent |
| 7 | `/impressum` | `app_imprint` | Imprint |
| 8 | `/kontakt` | `app_contact` | Contact form (GET, POST) |
| 9 | `/kontakt-per-email` | `app_contact_email` | Redirect to `mailto:` |
| 10 | `/kontakt-per-whats-app` | `app_contact_whats_app` | Redirect to WhatsApp |
| 11 | `/blog` | `app_blog` | Overview of every post, read from kongtent |
| 12 | `/blog/feed.xml` | `app_blog_feed` | Atom feed with the full text |
| 13 | `/blog/{year}/{slug}` | `app_blog_post` | One post |
| 14 | `/referenzen` | `app_references_redirect` | 301 to `/blog` |
| 15 | `/referenzen/{year}/{slug}` | `app_reference_redirect` | 301 to the post under the same year and slug |
| 16 | `/robots.txt` | `app_robots` | robots, absolute sitemap URL |
| 17 | `/sitemap.xml` | `app_sitemap` | Public pages plus every post |
| 18 | `/admin` | `admin` | EasyAdmin dashboard |
| 19 | `/admin/logout` | `admin_logout` | Logout, intercepted by the firewall |

`/advintage` loads `config/advintage-landing-page.json` and deserialises to `PrintableModel[]`. **The path is anchored to `kernel.project_dir`:** a relative path resolves against the working directory, which holds for the web server and breaks in the test runner.

## The blog

**The posts come from kongtent** through `krausgebaut/kongtent-bundle`; this repository holds none of them. `/` shows the three newest (`HOMEPAGE_POST_LIMIT`), `/blog` all of them, and the sitemap lists every one. Every card shows its date rather than its rubric. **What the bundle enforces is in `../../workbench/kongtent-bundle/README.md`; what every reading site decides – the key and the deploy check, the address, the privacy policy – is in `../../docs/KONGTENT.md`.** What stands here is what this site decided.

**The references are posts like any other**, under a rubric Marcel keeps in kongtent. The site knows no rubric by name and shows the one a post carries above its headline. Their old addresses answer 301 onto the same year and slug, so a reference moved with its slug and its date keeps its links; one that was not moved answers 404 there.

**The route is `/blog/{year}/{slug}`**, and the year is the year of the post's date, compared in `BlogController` – kongtent is asked for the slug alone, and without the comparison one post would answer under every year.

**The site sets five block types itself**, under `templates/bundles/KongtentBundle/blocks/`: picture and gallery with their caption line, quotation on the accent rail, the term list as rows with the name in the mono label voice and the value in the body face, and the paragraph. Heading, list and embed render in the bundle's markup inside `prose`. Everything the site sets carries `not-prose`, and a link in generated markup outside `prose` gets its style from `[data-generated] a` in `public/css/input.css`.

**A paragraph that is one external link and nothing else is set as `_link_arrow` with the external icon**, opening in a new tab – the shape a customer review had on the references. `App\Twig\SoleLinkExtension` recognizes it in the converter's markup; a link within this site stays running text, whether it is a path or an address on the host of the request, with or without `www.`.

**The feed carries the full text** and asks kongtent once per post, because the list carries no blocks.

**The references and their categories have no place in this project.** No entity, table, backend screen or upload stands for them; their addresses redirect, and a dump of both tables with the uploaded pictures is kept outside the workspace.

## The FAQ

**The questions are one content in kongtent**, slug `haeufig-gestellte-fragen`, fixed in `DefaultController::faq()` and not listed – without it the page answers 404 – so the blog, the feed and the sitemap never see it, and `BlogController` refuses a content that is not listed as a post. Its head is the page's head: `headline` and `teaser` the title and its second line, `metaTitle` and `metaDescription` the document head.

**Every heading opens a question, whatever its level, and the blocks up to the next one are its answer.** **An answer is expected to be text.** Every block renders, but the picture and gallery templates state the width of the blog column in `sizes`; a picture in a two-column card would load a size larger than it needs. A heading without an answer and whatever stands before the first heading are dropped without a word; the editor keeps the content in shape. The `FAQPage` data carries the text of every question and answer through `plain_text`, tags stripped and entities decoded.

## Data model

* **Landing pages** use JSON files in `config/`. Flow: JSON → Serializer → entity DTO → Twig.
* `FaqEntry` is a full Doctrine entity with a UUID v7 primary key. `PrintableModel`, `Image` and `ContactRequest` are pure DTOs without Doctrine mapping.

### FaqEntry (`faq_entry` table)

UUID v7 primary key, index on `sort_order`. Fields `question`, `answer`, `isVisible`, `sortOrder`, plus timestamps. Custom ordering through `sortOrder` with up/down buttons in the backend, which needs a `getSortButtons()` getter for EasyAdmin field rendering.

## Backend

EasyAdmin 5 at `/admin`.

* **DashboardController** renders `templates/admin/dashboard.html.twig` with a deep link into the FAQ index page; the menu holds Dashboard and FAQ
* **FaqEntryCrudController** – sorted by `sortOrder` ascending, with custom up/down actions that swap the sort order of neighbors
* All CRUD and field labels are German

**EasyAdmin 5 mechanics, mandatory and easy to get wrong:**

1. Pretty URLs are required. They are loaded through `config/routes/easyadmin.yaml` with `type: easyadmin.routes`; the dashboard declares its own path via `#[AdminDashboard(routePath: '/admin', routeName: 'admin')]`
2. Custom CRUD actions **must** carry `#[AdminRoute]`. Without it they are silently ignored when the routes are built
3. Menu entries use `MenuItem::linkTo(<CrudController>::class, …)` and point at the CRUD controller, not at the entity
4. `entityId` is a route path segment, not a query parameter. Read the record through `$context->getEntity()->getInstance()`, not from the query string
5. Custom actions get **no** CSRF protection from the bundle – only `delete`, `batchDelete` and the boolean toggle are covered. State-changing actions have to restrict themselves to POST and validate their own token, as `FaqEntryCrudController` does for sorting

### Authentication

Firewall `admin` covers `^/admin` and uses **HTTP Basic** with realm `krausgedruckt:admin`. Access control requires `ROLE_ADMIN` for `^/admin`.

**Both halves of the credential come from the environment**, through `App\Security\EnvironmentUserProvider`: `ADMIN_USERNAME` and the hash in `ADMIN_PASSWORD`. A memory provider cannot do this – it spells the user name out as a YAML key, and a key is one of the few places Symfony does not resolve `%env()%`. That would put the name in the repository next to the path it unlocks, which on a public repository is half a set of credentials given away. The committed `.env` carries placeholders only.

**Failed attempts are counted and then refused.** Symfony's `login_throttling` does not reach this firewall: it only covers authenticators it considers interactive, and HTTP Basic is not one – measured, not assumed. `App\EventListener\AdminLoginThrottleListener` spends the `admin_login` limiter instead: five failures per address per fifteen minutes, checked **before** the password is verified, so an exhausted budget refuses the correct password too. A successful login costs nothing, which matters because Basic sends the credential on every single request.

**The check reads `getRemainingTokens()`, not `isAccepted()`.** Asking the limiter for zero tokens is always granted, so the accepted flag stays true long after the budget is gone.

## Design

Tokens, contrast rules and the family bracket are in `../docs/BRAND_FAMILY.md`. What differs here is deliberate: papaya instead of petrol, the nozzle instead of the gear, a warm ground instead of a cool one, soft cards with a shadow instead of flat hairline cards, and a product photo where the sibling uses typography.

* **Section rhythm:** warm → white → warm → dark → white. The single dark block (`Ablauf`) arrives late on purpose.
* **`surface-warm`** (`orange-50`) is the warm section ground, a token this site has and its siblings do not.
* **Corners:** `rounded-2xl` for cards and containers. Cards are free-standing: border, white ground, soft shadow.
* **Two-tone headings:** the statement comes first in `neutral-900` (or white on a dark ground), the flourish follows underneath, smaller and in the accent – `<span class="mt-3 block text-[0.8em] text-accent-on-light">`. This holds on every page. **The homepage hero is the only exception**, and it is the only one allowed: reversing the order or inventing a third form elsewhere is drift, not personality.
* **The filled button is `accent` with a `neutral-900` label** (4.98:1) and **lightens** to `accent-hover` on hover (6.21:1); darkening would drop the label to 3.43:1. There is one button and no second step.
* **Errors are `red-600`, not the accent** – on this brand an orange error would be indistinguishable from an orange heading.
* Mono sizes: `text-sm` in the mobile menu and on the button, `text-xs` everywhere else.

The logo is the nozzle and the wordmark as one lockup; the nozzle and „kraus“ carry the accent, „gedruckt“ the dark neutral. Its construction matches the sibling brand: same height, same baseline, only mark and color differ.

## Contact form

The mechanism is in `../../docs/WEB_STACK.md`. Specific here:

* **Five fields:** `name`, `email`, `phone`, `discountCode`, `message`. **The two required ones open the form and the optional ones follow**, because a private customer is the majority here and should not have to skip a field before starting. The sister sites order their forms differently on purpose – there a company is the normal case
* `templates/partials/_contact_form.html.twig` holds the field classes. There is no form theme
* The field keeps the **browser focus ring** (`focus:outline-2`) on top of the accent border; replacing the ring with a one pixel border change makes the form the only place on the site where keyboard focus is weaker than the default
* The form-wide message is a live region (`role="alert"`); the honeypot is `sr-only` **and** `aria-hidden`, and keeps `tabindex="-1"`. The tab order alone is not enough: a screen reader's reading mode walks the document, not the tab chain, so without `aria-hidden` the trap is read out to exactly the visitors who cannot see that it is one
* Discount code can be pre-filled: `/kontakt?discount-code=CODE`
* Mail goes out through `TemplatedEmail`
* The legal mailbox is `mail+legal@krausgedruckt.de`

## SEO / meta

Centralised in `base.html.twig`: `lang`, canonical, description, Open Graph and Twitter card, all overridable per page through the `title`, `meta_description`, `meta_robots` and `meta_image` blocks. `meta_image` is captured into a variable rather than printed where it is defined, because the card needs the path twice.

Every page carries exactly one visible `h1`. Legal pages and the contact confirmation are `noindex,follow`. JSON-LD sits in the `structured_data` block: `ProfessionalService` on the homepage, `FAQPage` on the FAQ. **Posts carry none** – the benefit is limited to image search. The Atom feed of the blog is declared on every page in `base.html.twig`.

`/robots.txt` and `/sitemap.xml` are generated by `DefaultController`. The sitemap lists the public pages and every post of the blog; legal pages, the confirmation and the adVintage landing page stay out.

The robots.txt disallows exactly the two routes whose Location header holds the mail address – `/kontakt-per-email` and `/kontakt-per-whats-app`. A crawler that fetches one of them takes the address into its corpus, and those corpora are where address lists come from.

**`/bewerten` does not belong in that list**, although it is built the same way. Its header holds a public Google address, so there is nothing to keep out of a corpus, and blocking it works against the index rather than for it: the link stands in the footer of every page, so a crawler barred from fetching it never learns that the address is a redirect and can hold it bare. It is the rule the legal pages stand on – a path a crawler may not fetch is a path it learns nothing about.

Sharing image composition, the deliberate mirror of krausgebaut's: white ground, the eyebrow with its square marker at the top left, the logo lockup below it, a two line claim in `neutral-600`, and the domain with the location as a mono line at the foot. The nozzle is oversized, **solid** accent and cropped off the right edge. The mono type is `accent-on-light` and `neutral-600`, never `accent`.

## Tests

62 cases. Tests that read kongtent answer from the recordings in `tests/fixtures/kongtent/` and never reach the network.

| # | File | Covers |
| --- | --- | --- |
| 1 | `tests/Controller/RouteSmokeTest.php` | every frontend route answers and carries exactly one `h1` |
| 2 | `tests/Controller/ContactFormTest.php` | an invalid submission is refused with 422, names the field and sends nothing; a valid one redirects and sends exactly one mail; the confirmation takes the form's place; the discount code arrives from the query string; a filled honeypot and a tampered signature are dropped silently while a stale form is asked to resend |
| 3 | `tests/Controller/BackendThrottleTest.php` | after the budget is spent the **correct** password is refused too – the only question a status code can answer here, because Basic returns 401 either way |
| 4 | `tests/Controller/BlogTest.php` | the feed is well-formed XML and carries every block type with plain text escaped twice, a post answers only under its own year and a year alone is no page, the old reference addresses answer 301, every picture carries its measurements, the five block templates are this site's own and a link in generated markup outside `prose` is styled, a lone external link carries the arrow while one within this site – by path or by its own host – does not, and the sitemap lists the posts |
| 5 | `tests/Controller/FaqTest.php` | every heading with an answer is a question in its order while the rest is dropped, an answer holds all of its blocks, the head comes from the content, the structured data carries the text of every answer, and a content that is not listed is no post |
| 6 | `tests/Twig/SoleLinkExtensionTest.php` | what counts as a lone external link |
| 7 | `tests/EventListener/SecurityHeadersListenerTest.php` | every public path carries the hardening headers, and the transport header follows the scheme |

File 6 is a **plain `TestCase` without kernel and without database**, because the logic behind it is pure – that is what makes it cheap enough to pin every case rather than a sample.

## Fixtures

`src/DataFixtures/` contains `FaqEntryFixtures`. Load them with `ddev exec bin/console doctrine:fixtures:load`, or use `bin/reinstall-db`.

## Static assets

* Images: `public/images/`
* Landing page images: `public/images/advintage-landing-page/`
* Shop photo: `public/images/etsy-shop.jpg` (1080 × 1080, shop band)
* Logo: `templates/partials/_logo.html.twig` – a Twig partial, not an image
* Sharing image: `public/images/sharing.jpg` (1200×630)

## Deployment

Server directory `~/www/html/krausgedruckt`, on the account `krswrk`, host `nix`. Mechanism, deploy keys, the migration dump and the mailer are in `../../docs/DEPLOYMENT.md`. The database is `krswrk_krausgedruckt`; the account prefix is compulsory there, `CREATE DATABASE krausgedruckt` is refused.

**The mail is on the same account.** The MX record points at `in-mx.uberspace.de`, and `krausgedruckt.de` is registered for mail on `krswrk`, so `mail@krausgedruckt.de` is a mailbox of that account and the contact form delivers locally. The sender is not this domain – see “The sender on `krswrk`” in `../../docs/DEPLOYMENT.md`.

**The database does not travel with the repository.** A fresh server is not complete after a clone: the migrations build the schema. What the server needs besides is a writable `var/`.

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
| 8 | `KONGTENT_KEY` | Key of the channel the blog reads; empty in `.env`, set in `.env.local` |
| 9 | `KONGTENT_URL` | Address of kongtent; `.env` carries production, for the server and development alike – reading kongtent under ddev is an override in `.env.local` |
| 10 | `MAILER_DSN` | Symfony Mailer transport |

Outbound targets are **not** environment variables. They live in `config/services.yaml` and are read with `getParameter()`: `app.app_store_url_mobile`, `app.contact_email_address`, `app.etsy_url`, `app.google_review_url`, `app.instagram_url`, `app.legal_email_address`, `app.whats_app_url`.

The app is promoted for iOS only; there is no Mac badge and no variable for one.

## Open points

1. **The test database is hand-loaded state, not a test fixture.** There is no transaction isolation, no fixture loading in `setUp()` and no migration run in the test environment. `bin/reinstall-db` runs against the development database, not `db_test`.
2. **Most of `src/` is untested.** The repository and the admin controllers have no tests; `DefaultController` is touched through the smoke test and the blog through its own.
