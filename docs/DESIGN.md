# Design

## Partials

Five carry rules rather than just markup:

* **`_button_class`** holds the contrast rule for the filled button and emits nothing but a class string. `_button` renders the link version; the header and every `<button>` pull the same string through `include()`. Neither copy can drift, and the rule hangs on exactly one place.
* **`_card`** is embedded, not included, because the picture and the running text differ per case while the shell does not. A card **with** an `href` is one click target: the heading link stretches over the whole article (`after:absolute after:inset-0`) and the hover shadow is rendered. A card **without** one stays inert, because a growing shadow is a promise of a click.
* **`_conversion_band`** has three shapes: plain band, `boxed` as an inset card, and—with an `image`—a two column block with a picture. Its `actions` block defaults to the inquiry button; a page whose closing action is something else embeds it and overrides the block, so ground, measurements and rhythm still come from one place. **An `eyebrow` turns a band into a section**: the mono label is what says a topic begins here, so a band carrying an offer of its own takes one and a band closing a page does not. Its text matches the navigation item word for word.
* **`_field_class`** emits the class string of an input, a textarea and a select, with the error state as its one parameter. The focus ring, the hairline and the error state are one rule, so they hang on one place—the same reason `_button_class` exists. Both forms of the site pull it.
* **`_link_arrow`** carries three tones, and the tone names the ground: `accent` and `muted` are for a light one, `dark` for a dark section. `muted` is `neutral-600` and measures 2.5:1 on `neutral-950`—an arrow link on the dark block that reaches for it disappears.

## Navigation

The navigation is built once from the `nav_items` list in `base.html.twig`: Modell drucken · Blog · App · FAQ · Shop. **The homepage has no entry of its own**—the logo leads there from every page, and a bar that repeats what the mark already does spends its widest slot on the one destination nobody has to be told about. **The FAQ is labeled “FAQ” in the navigation and written out everywhere else**—the label is the only place where the long form costs more room than it earns. An item names either a `route` inside the site or an external `url` with `external: true`, which gets the off-site icon and opens in a new tab; the shop is the only one and sits last. A page that stands apart may narrow the list down by setting `nav_items` at its own top level; the adVintage landing page is the only one that does. **The print-order assistant carries the list unchanged**, although it stands apart in everything below the header—a visitor who arrives there from a campaign reaches the rest of the site from the same bar as everyone else.

## Look

Tokens, contrast rules and the family bracket are in `../../../docs/BRAND_FAMILY.md`. What differs here is deliberate: papaya instead of petrol, the nozzle instead of the gear, a warm ground instead of a cool one, soft cards with a shadow instead of flat hairline cards, and a product photo where the sibling uses typography.

* **Section rhythm:** warm → white → warm → dark → white, with the bands set between. The single dark block (`Ablauf`) arrives late on purpose. **The print-order band comes before the shop band**, because the way that stays on this site is offered before the way off it, and because the shop's “Lieber sofort etwas Fertiges?” answers the question the band before it asks. The two bands carry the tones that keep the alternation: the print-order band `plain`, the shop band `warm`.
* **`surface-warm`** (`orange-50`) is the warm section ground, a token this site has and its siblings do not.
* **Corners:** `rounded-2xl` for cards and containers. Cards are free-standing: border, white ground, soft shadow.
* **Two-tone headings:** the statement comes first in `neutral-900` (or white on a dark ground), the flourish follows underneath, smaller and in the accent—`<span class="mt-3 block text-[0.8em] text-accent-on-light">`. This holds on every page. **Two heroes are allowed to reverse it and no third:** the homepage and the print-order assistant, which is a special page by decision. Inventing a further form elsewhere is drift, not personality.
* **Every filled button ends in an icon:** the arrow inside the site, the off-site icon for an external link. `_button` sets it by default; a `<button>` that pulls `_button_class` writes it out. **The header is the exception:** its button carries the label alone, as on krausgebaut, and shrinks to the mail icon on a phone.
* **The filled button is `accent` with a `neutral-900` label** (4.98:1) and **lightens** to `accent-hover` on hover (6.21:1); darkening would drop the label to 3.43:1. There is one button and no second step.
* **Errors are `red-600`, not the accent**—on this brand an orange error would be indistinguishable from an orange heading.
* Mono sizes: `text-sm` in the mobile menu and on the button, `text-xs` everywhere else.

The logo is the nozzle and the wordmark as one lockup; the nozzle and “kraus” carry the accent, “gedruckt” the dark neutral. Its construction matches the sibling brand: same height, same baseline, only mark and color differ.

## SEO / meta

Centralized in `base.html.twig`: `lang`, canonical, description, Open Graph and Twitter card, all overridable per page through the `title`, `meta_description`, `meta_robots` and `meta_image` blocks. `meta_image` is captured into a variable rather than printed where it is defined, because the card needs the path twice.

Every page carries exactly one visible `h1`. Legal pages and the contact confirmation are `noindex,follow`. JSON-LD sits in the `structured_data` block: `ProfessionalService` on the homepage, `FAQPage` on the FAQ. **Posts carry none**—the benefit is limited to image search. The Atom feed of the blog is declared on every page in `base.html.twig`.

`/robots.txt` and `/sitemap.xml` are generated by `DefaultController`. The sitemap lists the public pages and every post of the blog; legal pages, the confirmation and the adVintage landing page stay out. The short links under `/s/` and `/shop` are in neither: they exist for print, and their Location header holds nothing that has to stay out of a corpus.

Sharing image composition, the deliberate mirror of krausgebaut's: white ground, the eyebrow with its square marker at the top left, the logo lockup below it, a two line claim in `neutral-600`, and the domain with the location as a mono line at the foot. The nozzle is oversized, **solid** accent and cropped off the right edge. The mono type is `accent-on-light` and `neutral-600`, never `accent`.
