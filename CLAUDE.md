# krausgedruckt

## Overview

Business website for **krausgedruckt** – the 3D printing branch of Marcel Kraus's freelance work (https://www.krausgedruckt.de). It presents the services, the blog and the FAQ, and carries a contact form, the print-order assistant and a shop band.

German only. The blog and the FAQ come from kongtent; everything else is markup or JSON. There is no database and no backend.

**Voice:** the business speaks as „wir“ and addresses the customer as „du“. In the FAQ the customer speaks too, keeping „ich“ for itself and addressing the business as „ihr“ – the same word therefore moves in one entry and stays in the next. Routing uses German URLs throughout.

## Stack notes

**Forms are hand-written**, the contact form included; `symfony/form` is not installed. The Tailwind forms plugin resets the fields of the contact form.

**ddev runs Apache here**, so `public/.htaccess` is exercised locally; under nginx it is ignored and only ever runs in production.

## Development

```bash
ddev start                                   # https://krausgedruckt.ddev.site
ddev launch -m                               # Mailpit, captured mail
ddev exec npm run build                      # Tailwind, minified
ddev exec bin/phpunit                        # the tests
bin/signature-cards                          # the table cards as PDF, on the host
```

**ddev runs without a database container**, as the sibling sites do.

## Layout

```
config/                advintage-landing-page.json, signature-models.json
                       and the Symfony configuration
src/Controller/        DefaultController, BlogController, PrintOrderController,
                       ShortLinkController, SignatureCardController
src/Entity/            the DTOs of the landing page and SignatureModel
src/Dto/               ContactRequest, ModelPreview, PrintOrderRequest, UsageContext
src/EventListener/     SecurityHeadersListener
src/Service/           PlatformFetcher, ModelLookup, a reader per platform,
                       the host resolver they stand on, SignedTimestamp,
                       SignatureModelCatalog, ShortLinkResolver,
                       SignatureCardRenderer
src/Twig/              PlainTextExtension, SoleLinkExtension
templates/             base.html.twig, content/, partials/, print/,
                       bundles/KongtentBundle/blocks/
public/                css/, fonts/, images/, favicon.*
```

Partials: `_logo`, `_eyebrow`, `_icons`, `_card`, `_post_card` (shared by the homepage teaser and the overview of the blog), `_button`, `_button_class`, `_link_arrow`, `_contact_form`, `_field_class`, `_error_focus`, `_conversion_band`, `_sibling_band`, and the four of the assistant: `_print_order_steps`, `_print_order_model`, `_print_order_details`, `_print_order_contact`.

Five carry rules rather than just markup:

* **`_button_class`** holds the contrast rule for the filled button and emits nothing but a class string. `_button` renders the link version; the header and every `<button>` pull the same string through `include()`. Neither copy can drift, and the rule hangs on exactly one place.
* **`_card`** is embedded, not included, because the picture and the running text differ per case while the shell does not. A card **with** an `href` is one click target: the heading link stretches over the whole article (`after:absolute after:inset-0`) and the hover shadow is rendered. A card **without** one stays inert, because a growing shadow is a promise of a click.
* **`_conversion_band`** has three shapes: plain band, `boxed` as an inset card, and – with an `image` – a two column block with a picture. Its `actions` block defaults to the inquiry button; a page whose closing action is something else embeds it and overrides the block, so ground, measurements and rhythm still come from one place. **An `eyebrow` turns a band into a section**: the mono label is what says a topic begins here, so a band carrying an offer of its own takes one and a band closing a page does not. Its text matches the navigation item word for word.
* **`_field_class`** emits the class string of an input, a textarea and a select, with the error state as its one parameter. The focus ring, the hairline and the error state are one rule, so they hang on one place – the same reason `_button_class` exists. Both forms of the site pull it.
* **`_link_arrow`** carries three tones, and the tone names the ground: `accent` and `muted` are for a light one, `dark` for a dark section. `muted` is `neutral-600` and measures 2.5:1 on `neutral-950` – an arrow link on the dark block that reaches for it disappears.

The navigation is built once from the `nav_items` list in `base.html.twig`: Modell drucken · Blog · App · FAQ · Shop. **The homepage has no entry of its own** – the logo leads there from every page, and a bar that repeats what the mark already does spends its widest slot on the one destination nobody has to be told about. **The FAQ is labeled „FAQ“ in the navigation and written out everywhere else** – the label is the only place where the long form costs more room than it earns. An item names either a `route` inside the site or an external `url` with `external: true`, which gets the off-site icon and opens in a new tab; the shop is the only one and sits last. A page that stands apart may narrow the list down by setting `nav_items` at its own top level; the adVintage landing page is the only one that does. **The print-order assistant carries the list unchanged**, although it stands apart in everything below the header – a visitor who arrives there from a campaign reaches the rest of the site from the same bar as everyone else.

## Routing

Defined in `src/Controller/DefaultController.php`, `src/Controller/BlogController.php`, `src/Controller/PrintOrderController.php` and `src/Controller/ShortLinkController.php` with PHP attributes.

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
| 18 | `/modell-drucken` | `app_print_order` | The print-order assistant (GET, POST) |
| 19 | `/modell-bild` | `app_model_image` | Relays a platform's preview image, signed |
| 20 | `/s/{slug}` | `app_short_link` | 302 to the homepage with a Matomo campaign – the address behind the QR code of a signature card |
| 21 | `/shop` | `app_shop` | 302 to the Etsy shop, for print only |

`/_signature-cards` exists in `dev` and `test` only and is not in the table – see „Signature cards“.

`/advintage` loads `config/advintage-landing-page.json` and deserialises to `PrintableModel[]`. **The path is anchored to `kernel.project_dir`:** a relative path resolves against the working directory, which holds for the web server and breaks in the test runner.

## Printed short links

**The QR code of every signature card leads to `/s/{slug}`, and every short link leads to the homepage.** What differs is what the scan is counted as: the redirect adds `mtm_campaign` and `mtm_kwd`. The address stays short so the code stays coarse enough to scan from a table, and it answers 302 because what is already printed must be free to lead elsewhere.

**A signature model answers under `signature-<slug>`** and is counted under the campaign of the current event in `config/signature-models.json`, its slug the keyword. The table cards are reprinted for every event, so the campaign sits beside the models and moves with the next event. **The path takes anything, and the last short link in it counts**: the cards printed for the Gangelt Games Festival 2026 carry codes that chain the addresses of the cards before them, and they must keep working.

**`/shop` is for print only.** The site links Etsy directly, because Matomo counts a click on a foreign address as an outlink and never sees one on an address of the site that the server redirects. Neither route is in the sitemap, and neither is blocked in `robots.txt`: the Location header holds nothing that has to stay out of a corpus.

## Signature cards

**A signature model is an outstanding print shown at events**, and a laminated table card lies in front of it: A4 landscape, in color, printed borderless at home. The model stands behind the card, so the card carries no picture. The left side names the model, its designer, the full address of the model as written – it is the credit – and the figures. The warm right side opens with the line for its kind; below it, the same on every card, stand „Lass drucken!“, the code and the shop, so the code sits in the same place on each.

**Two kinds, and the line says which.** A model that may be ordered says why: the designer released it in person, or its license permits commercial use – then `license` names it. The card does not print it: a license such as CC BY asks for title, author, source and license, and the full address leads to a page that names the license, which the license accepts as attribution. Every other model is a reference: shown, never offered, because a print for money is a commercial use of the model even where only the service is billed. The call to action is the same on both, since it asks for the service and not for the model.

**Chrome prints the cards from a page of the site**, so tokens, fonts and the logo are the website's own rather than an imitation. `/_signature-cards` renders every model of `config/signature-models.json` as one page each through `SignatureCardRenderer`; `bin/signature-cards` runs on the host – ddev has no Chrome, the host no PHP – and writes `var/signature-cards/<campaign>.pdf`, after making sure the page answers – Chrome prints an error page as readily as the cards. `CHROME` overrides the path of the browser. **The route exists in `dev` and `test` only**: the QR code library, `chillerlan/php-qrcode`, is a dev dependency, touched only inside `render()` so production's container never needs it.

**The code holds the production address without `www`**, `https://krausgedruckt.de/s/signature-<slug>`, never the host of the request; the domain keeps the path when it redirects to `www`. Error correction M, the library's quiet zone, on a white card inside a filled button: accent ground, an arrow at its end, 94 mm wide. **Its label „Lass drucken!“ is `surface-warm`, not the site's `neutral-900`** – a deliberate exception on paper: it takes up the ground of the column and closes the block, and at `text-3xl` bold it is large text, where 3:1 is the bar; it measures 3.39:1, so it has little reserve if a home printer lightens the orange.

**The card is read from a table, not a screen.** The type follows one scale: the name at `text-6xl`; „Lass drucken!“, the designer and every figure at `text-3xl`, the line for the kind at `text-2xl`, the text at `text-lg`, and mono labels from `text-sm` rather than `text-xs`; and a shadow is left out – Chrome prints it as a gray box. The line for the kind has one size on every card, however long it runs. **The text is escaped by `SignatureCardRenderer`, not by the template**, because it keeps a word after its „3D-“, a short name in quotation marks and the printer's name on one line – it cuts the text first and escapes every piece, so no pattern sees an entity – and the template prints the result raw. The source address likewise arrives in pieces that break after a slash, never inside `://`. Print time, parts and colors share a row; the material takes the first third of the next and the printer the other two, because its name wraps in one. Both columns end in a foot of one fixed height behind a hairline – the logo on the left, the shop on the right – so the two hairlines meet across the card; the figures and the code hang directly on it, and a longer or shorter text never moves them. **The designer's line sits at half the name rather than the flourish's 0.8em**: it is a credit, not a second statement, and at the size of the name it would outweigh the call to action. The page carries one `h1` per card, because each card is a page of its own once printed.

## The blog

**The posts come from kongtent** through `krausgebaut/kongtent-bundle`; this repository holds none of them. `/` shows the three newest (`HOMEPAGE_POST_LIMIT`), `/blog` all of them, and the sitemap lists every one. Every card shows its date rather than its rubric. **What the bundle enforces is in `../../workbench/kongtent-bundle/README.md`; what every reading site decides – the key and the deploy check, the address, the privacy policy – is in `../../docs/KONGTENT.md`.** What stands here is what this site decided.

**The references are posts like any other**, under a rubric Marcel keeps in kongtent. The site knows no rubric by name and shows the one a post carries above its headline. Their addresses under `/referenzen` answer 301 onto the same year and slug under `/blog`; where no post stands there, the redirect ends in 404.

**The route is `/blog/{year}/{slug}`**, and the year is the year of the post's date, compared in `BlogController` – kongtent is asked for the slug alone, and without the comparison one post would answer under every year.

**The site sets five block types itself**, under `templates/bundles/KongtentBundle/blocks/`: picture and gallery with their caption line, quotation on the accent rail, the term list as rows with the name in the mono label voice and the value in the body face, and the paragraph. Heading, list and embed render in the bundle's markup inside `prose`. Everything the site sets carries `not-prose`, and a link in generated markup outside `prose` gets its style from `[data-generated] a` in `public/css/input.css`.

**A paragraph that is one external link and nothing else is set as `_link_arrow` with the external icon**, opening in a new tab – the shape of a customer review. `App\Twig\SoleLinkExtension` recognizes it in the converter's markup; a link within this site stays running text, whether it is a path or an address on the host of the request, with or without `www.`.

**The feed carries the full text** and asks kongtent once per post, because the list carries no blocks.

**The references and their categories have no place in this project.** No entity, table, backend screen or upload stands for them; their addresses redirect, and a dump of both tables with the uploaded pictures is kept outside the workspace.

## The FAQ

**The questions are one content in kongtent**, slug `haeufig-gestellte-fragen`, fixed in `DefaultController::faq()` and not listed – without it the page answers 404 – so the blog, the feed and the sitemap never see it, and `BlogController` refuses a content that is not listed as a post. Its head is the page's head: `headline` and `teaser` the title and its second line, `metaTitle` and `metaDescription` the document head.

**Every heading opens a question, whatever its level, and the blocks up to the next one are its answer.** **An answer is expected to be text.** Every block renders, but the picture and gallery templates state the width of the blog column in `sizes`; a picture in a two-column card would load a size larger than it needs. A heading without an answer and whatever stands before the first heading are dropped without a word; the editor keeps the content in shape. The `FAQPage` data carries the text of every question and answer through `plain_text`, tags stripped and entities decoded.

## Reading a model

**One reader per platform, behind `ModelReader`.** Today there is one: Printables' pages sit behind a bot check that refuses a server, so `PrintablesReader` asks the platform's own GraphQL interface at `api.printables.com`, which needs no key.

**Measure the payload, not the status code, before adding a platform.** A page rendered in the browser can answer a server with 200 and carry Open Graph tags that are the same generic ones on every model; Thingiverse does. A reader that trusts them gives every model the platform's own name as its title, which is worse than no preview, because the card is what the visitor confirms.

`ModelLookup` picks the reader and shortens what comes back. Everything above it sees only a `ModelPreview`. **Entities are decoded by the reader**, not centrally: an interface hands its text out encoded, a crawler decodes while reading an attribute, and doing it twice turns an ampersand a designer typed into the character behind it.

**A reader serves its whole platform, and says so when the address is not a model.** An address on Printables that points at a profile, a collection or a search fails as `NotAModelPage`, not as an unsupported address: the visitor made a mistake he can fix in ten seconds, and telling him we do not read his platform leaves him with nothing to correct. Whoever is stuck anyway reaches the contact form from the same page.

**What a platform sends is shown as it arrives.** A title, a summary or a license name is someone else's text: its dashes, quotation marks and spelling stay untouched, whatever the house typography says. The rule binds what this site writes, not what it quotes.

**The preview picture is the platform's rendition, never the original.** A cover on Printables runs into the megabytes – seven on a measured model – which the relay refuses and the card would show as a broken picture. The address is built by inserting `thumbs/inside/640x480/<format>/` before the file name, the format following the file's own extension; only a fixed set of sizes exists and an invented one is answered with 400. If the pattern ever changes, the relay refuses an answer that is not an image and the card falls back to its own empty state.

**Printables also names the license, and it goes into the inquiry mail, never onto the page.** The workshop needs to know what it is calculating with; a visitor would read it as a promise that nobody checked.

**`PlatformFetcher` carries the whole security requirement** for both readers – the host list, the resolved address checked against the reserved ranges and pinned into the request, and limits on time, size and media type. A redirect is checked again at every hop on the fetched path and refused outright on the posted one, since the other side may not repeat a body. What is requested is rebuilt from the checked parts, never the string a visitor typed.

**The interface of Printables is undocumented** and its introspection is off: the field names were found by asking and can change without notice. When they do, the assistant carries on without a preview – which is why the failure path is not a nicety.

## The print-order assistant

`/modell-drucken`, three steps on one page, the last two closed until a model has been read. It is a **special page by decision** – the step bar exists nowhere else on the site – **but the header is not part of that**: navigation, button and logo are the ones every other page carries, in the same position and the same grid.

**„3D-“ is dropped wherever the assistant is meant** – on the page, in the navigation and in the band: a visitor who has come this far knows which kind of model it is, and the prefix on every second noun reads like a catalog. The printer keeps it, „3D-Drucker“ being the thing the visitor does not have, and so does the offer section of the homepage, which speaks about 3D models as a product rather than about his.

**One name for the way, and it is „Modell drucken“** – the route, the navigation item, the eyebrow of the band, its button, the arrow link in the hero and the document title. Four entrances lead here, and a wording of its own at each would read as four offers. **One of them names what the visitor brings instead, and deliberately:** „Ich habe ein fertiges Modell gefunden“ stands inside the process block, where the arrow link beside it offers the other case („Ich habe eine eigene Idee“) and the pair has to sort the two visitors rather than name the page.

**No figure for the response time, and no holiday switch.** The page, the band on the homepage and the customer's mail say „innerhalb weniger Stunden“; a number is a promise that has to hold on the worst day. The wording stands written out at each of them rather than in a partial – with nothing left to switch, a partial would bundle five words and no decision. The one figure that stands – twenty-four hours after a released offer – is a production time the workshop controls, and it is written out rather than configured.

**It works without JavaScript.** One form with two submit buttons: „Weiter“ carries `lookup` and only reads the model, „Anfrage senden“ sends. A browser triggers the first submit button for Enter, so Enter in the address field reads rather than sends. Nothing is kept on the server, and the page can also be reached with `?url=`, so a campaign link carries a model into it.

A second form for the address would keep Enter from sending as well, but then the address exists twice, and whoever corrects the visible field without pressing „Weiter“ sends the other one.

**A failed lookup opens the steps anyway** and says what went wrong. The address alone is enough for the workshop, so the inquiry must never hang on a platform answering – that is the promise the whole page is built on.

**The workshop's mail carries the shape of the contact form's** (`templates/content/contact.txt.twig`): one sentence saying what came in, a label per line, the remarks under their own heading. **It writes an empty field out, the customer's copy leaves it away** – „nicht gelesen“, „von der Plattform nicht genannt“ and „keine Angabe“ are three different starting points for a calculation and a dash would flatten them into one, while the same rows on the receipt only tell the customer what he did not fill in. The receipt is a letter and not a form: it opens and closes like one, and the summary sits between two `+++` rules.

**Two mails go out:** the inquiry to the workshop, with the license, and an acknowledgement to the customer without it. The second is where the instruction behind the license question is written down, in the conditional – an indicative sentence would turn the inquiry into the contract it must not be. For the same reason the button says „Anfrage senden“ and the page says what an order needs: the offer, and its release.

The mechanism – honeypot, signed timestamp, CSRF token, rate limit – is the contact form's, through `SignedTimestamp`, which both share.

Two enhancements need JavaScript and nothing depends on them: the step bar following the scroll position, and the button saying it is working while the model is fetched.

## Data model

* **Landing pages** use JSON files in `config/`. Flow: JSON → Serializer → entity DTO → Twig.
* **Signature models** are `config/signature-models.json`, read by `SignatureModelCatalog`. Every figure is text, because an estimate often carries a „ca.“.
* `PrintableModel`, `Image`, `SignatureModel`, `ContactRequest`, `ModelPreview` and `PrintOrderRequest` are plain DTOs; `UsageContext` is the enum behind the purpose question and carries its own labels.

## Design

Tokens, contrast rules and the family bracket are in `../docs/BRAND_FAMILY.md`. What differs here is deliberate: papaya instead of petrol, the nozzle instead of the gear, a warm ground instead of a cool one, soft cards with a shadow instead of flat hairline cards, and a product photo where the sibling uses typography.

* **Section rhythm:** warm → white → warm → dark → white, with the bands set between. The single dark block (`Ablauf`) arrives late on purpose. **The print-order band comes before the shop band**, because the way that stays on this site is offered before the way off it, and because the shop's „Lieber sofort etwas Fertiges?“ answers the question the band before it asks. The two bands carry the tones that keep the alternation: the print-order band `plain`, the shop band `warm`.
* **`surface-warm`** (`orange-50`) is the warm section ground, a token this site has and its siblings do not.
* **Corners:** `rounded-2xl` for cards and containers. Cards are free-standing: border, white ground, soft shadow.
* **Two-tone headings:** the statement comes first in `neutral-900` (or white on a dark ground), the flourish follows underneath, smaller and in the accent – `<span class="mt-3 block text-[0.8em] text-accent-on-light">`. This holds on every page. **Two heroes are allowed to reverse it and no third:** the homepage and the print-order assistant, which is a special page by decision. Inventing a further form elsewhere is drift, not personality.
* **Every filled button ends in an icon:** the arrow inside the site, the off-site icon for an external link. `_button` sets it by default; a `<button>` that pulls `_button_class` writes it out. **The header is the exception:** its button carries the label alone, as on the sibling sites, and shrinks to the mail icon on a phone.
* **The filled button is `accent` with a `neutral-900` label** (4.98:1) and **lightens** to `accent-hover` on hover (6.21:1); darkening would drop the label to 3.43:1. There is one button and no second step.
* **Errors are `red-600`, not the accent** – on this brand an orange error would be indistinguishable from an orange heading.
* Mono sizes: `text-sm` in the mobile menu and on the button, `text-xs` everywhere else.

The logo is the nozzle and the wordmark as one lockup; the nozzle and „kraus“ carry the accent, „gedruckt“ the dark neutral. Its construction matches the sibling brand: same height, same baseline, only mark and color differ.

## Contact form

The mechanism is in `../../docs/WEB_STACK.md`. Specific here:

* **Five fields:** `name`, `email`, `phone`, `discountCode`, `message`. **The two required ones open the form and the optional ones follow**, because a private customer is the majority here and should not have to skip a field before starting. The sister sites order their forms differently on purpose – there a company is the normal case
* `templates/partials/_field_class.html.twig` holds the field classes, shared with the assistant; `_contact_form` holds the layout and the field order. There is no form theme
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

163 cases, two of them skipped – `robots.txt` and `sitemap.xml` carry no heading, so the heading test steps over them. Tests that read kongtent answer from the recordings in `tests/fixtures/kongtent/` and never reach the network.

| # | File | Covers |
| --- | --- | --- |
| 1 | `tests/Controller/RouteSmokeTest.php` | every frontend route answers and carries exactly one `h1` |
| 2 | `tests/Controller/ContactFormTest.php` | an invalid submission is refused with 422, names the field and sends nothing; a valid one redirects and sends exactly one mail; the confirmation takes the form's place; the discount code arrives from the query string; a filled honeypot and a tampered signature are dropped silently while a stale form is asked to resend |
| 3 | `tests/Controller/BlogTest.php` | the feed is well-formed XML and carries every block type with plain text escaped twice, a post answers only under its own year and a year alone is no page, the reference addresses answer 301, every picture carries its measurements, the five block templates are this site's own and a link in generated markup outside `prose` is styled, in a caption line too, the teaser keeps its link in the post and loses it on a card, a lone external link carries the arrow while one within this site – by path or by its own host – does not, and the sitemap lists the posts |
| 4 | `tests/Controller/FaqTest.php` | every heading with an answer is a question in its order while the rest is dropped, an answer holds all of its blocks, the head comes from the content, the structured data carries the text of every answer, and a content that is not listed is no post |
| 5 | `tests/Twig/SoleLinkExtensionTest.php` | what counts as a lone external link |
| 6 | `tests/EventListener/SecurityHeadersListenerTest.php` | every public path carries the hardening headers, and the transport header follows the scheme |
| 7 | `tests/Service/PlatformFetcherTest.php` | which addresses the fetcher refuses – a foreign host, a leading dot, a port of its own, credentials, plain http – and what it actually sends for one it accepts: the rebuilt address, the pinned IP, the user agent, the budget |
| 8 | `tests/Service/PrintablesReaderTest.php` | title, summary, image and license out of a recorded answer of the interface, and that the query asks by id rather than by slug |
| 9 | `tests/Service/ModelLookupTest.php` | the choice between the readers, and the shortening every answer passes through |
| 10 | `tests/Controller/PrintOrderTest.php` | which steps are open when, that a failed lookup opens them anyway, that a valid inquiry sends exactly two mails with the license in one of them, and that a honeypot or a tampered timestamp is dropped without sending |
| 11 | `tests/Controller/ModelImageTest.php` | the relay hands out a signed platform image with the sniffing guard, and refuses an unsigned address, a swapped one and one off the platforms |
| 12 | `tests/Controller/HomepageTest.php` | where the homepage leads: the assistant three times and nowhere else, the way out beside the first step, and the assistant in the navigation |
| 13 | `tests/Controller/FilledButtonTest.php` | every filled button below the header ends in an icon, the open assistant included |
| 14 | `tests/Controller/ShortLinkTest.php` | a signature model leads home under the event's campaign with 302, a chained address leads to its last model, an unknown slug or one missing its prefix is 404, `/shop` leads to Etsy, and every model's slug is reachable and unique – the model is taken from the catalog, which changes with every event |
| 15 | `tests/Controller/SignatureCardTest.php` | the route renders one card per model, the code leads to the short link on the production host and every card encodes its own address only, a model released in person and one under a license are offered each with their own reason, the license is not printed, and a reference is offered neither, an empty text and the name of the event are left out, a word such as „3D-Druck“ never breaks after its „3D-“ nor a short name in quotation marks or the printer's name inside the text, while the text stays escaped, whatever characters it or the printer's name carry, and the source address breaks after a slash only, and production can never reach the dev dependency – no constructor type from it, no route in `prod` |

Files 5 and 7 to 9 are **plain `TestCase`s without kernel**, because the logic behind them is pure – that is what makes it cheap enough to pin every case rather than a sample. **The fetcher's host resolver is an interface** so those tests never reach a name server; the stand-in is `tests/Double/FixedHostResolver`. **The suite cannot reach the network at all:** `when@test` hands `PlatformFetcher` an empty `MockHttpClient` and kongtent its recordings, so a test that forgets to set up an answer fails loudly instead of asking the real platform. A test that spans two requests calls `disableReboot()` – the browser boots a fresh kernel per request otherwise, and a service put into the container before the first one would not survive into the second.

## Static assets

* Images: `public/images/`
* Landing page images: `public/images/advintage-landing-page/`
* Shop photo: `public/images/etsy-shop.jpg` (1080 × 1080, shop band)
* Logo: `templates/partials/_logo.html.twig` – a Twig partial, not an image
* Sharing image: `public/images/sharing.jpg` (1200×630)

## Deployment

Server directory `~/www/html/krausgedruckt`, on the account `krswrk`, host `nix`. Mechanism, deploy keys and the mailer are in `../../docs/DEPLOYMENT.md`.

**The mail is on the same account.** The MX record points at `in-mx.uberspace.de`, and `krausgedruckt.de` is registered for mail on `krswrk`, so `mail@krausgedruckt.de` is a mailbox of that account and the contact form delivers locally. The sender is not this domain – see “The sender on `krswrk`” in `../../docs/DEPLOYMENT.md`.

**A fresh server needs a writable `var/` and `.env.local`**, nothing else beyond the clone.

## Environment variables

Defaults live in `.env`, overrides in `.env.local` (never committed).

| # | Variable | Purpose |
| --- | --- | --- |
| 1 | `APP_ENV` | Symfony environment, overridden to `prod` in deployments |
| 2 | `APP_SECRET` | Symfony application secret |
| 3 | `CONTACT_TO` | Recipient of contact form mail |
| 4 | `CONTACT_FROM` | Sender of contact form mail |
| 5 | `KONGTENT_KEY` | Key of the channel the blog reads; empty in `.env`, set in `.env.local` |
| 6 | `KONGTENT_URL` | Address of kongtent; `.env` carries production, for the server and development alike – reading kongtent under ddev is an override in `.env.local` |
| 7 | `MAILER_DSN` | Symfony Mailer transport |

Outbound targets are **not** environment variables. They live in `config/services.yaml` and are read with `getParameter()`: `app.app_store_url_mobile`, `app.contact_email_address`, `app.etsy_url`, `app.google_review_url`, `app.instagram_url`, `app.legal_email_address`, `app.whats_app_url`.

**The minimum order value lives beside them** – `app.minimum_order_value`, handed to Twig as a global in `config/packages/twig.yaml` and stated once, in the proof line of the assistant. Neither mail states it.

**The frequently asked questions are outside all of this.** They are a content in kongtent, so the minimum order value stands there as editorial text and no parameter reaches it; changing `config/services.yaml` leaves it behind, and it has to follow by hand.

The app is promoted for iOS only; there is no Mac badge and no variable for one.

## Open points

1. **`ModelReader` and the host resolver are untested in their edges**, and `PlainTextExtension` is only seen through the FAQ. Everything else in `src/` is covered by the table above.
