# The print-order assistant

## The print-order assistant

`/modell-drucken`, three steps on one page, the last two closed until a model has been read. It is a **special page by decision**—the step bar exists nowhere else on the site—**but the header is not part of that**: navigation, button and logo are the ones every other page carries, in the same position and the same grid.

**“3D-” is dropped wherever the assistant is meant**—on the page, in the navigation and in the band: a visitor who has come this far knows which kind of model it is, and the prefix on every second noun reads like a catalog. The printer keeps it, “3D-Drucker” being the thing the visitor does not have, and so does the offer section of the homepage, which speaks about 3D models as a product rather than about his.

**One name for the way, and it is “Modell drucken”**—the route, the navigation item, the eyebrow of the band, its button, the arrow link in the hero and the document title. Four entrances lead here, and a wording of its own at each would read as four offers. **One of them names what the visitor brings instead, and deliberately:** “Ich habe ein fertiges Modell gefunden” stands inside the process block, where the arrow link beside it offers the other case (“Ich habe eine eigene Idee”) and the pair has to sort the two visitors rather than name the page.

**No figure for the response time, and no holiday switch.** The page, the band on the homepage and the customer's mail say “innerhalb weniger Stunden”; a number is a promise that has to hold on the worst day. The wording stands written out at each of them rather than in a partial—with nothing left to switch, a partial would bundle five words and no decision. The one figure that stands—twenty-four hours after a released offer—is a production time the workshop controls, and it is written out rather than configured.

**It works without JavaScript.** One form with two submit buttons: “Weiter” carries `lookup` and only reads the model, “Anfrage senden” sends. A browser triggers the first submit button for Enter, so Enter in the address field reads rather than sends. Nothing is kept on the server, and the page can also be reached with `?url=`, so a campaign link carries a model into it.

A second form for the address would keep Enter from sending as well, but then the address exists twice, and whoever corrects the visible field without pressing “Weiter” sends the other one.

**A failed lookup opens the steps anyway** and says what went wrong. The address alone is enough for the workshop, so the inquiry stands whether or not a platform answers—that is the promise the whole page is built on.

**The workshop's mail carries the shape of the contact form's** (`templates/content/contact.txt.twig`): one sentence saying what came in, a label per line, the remarks under their own heading. **It writes an empty field out, the customer's copy leaves it away**—“nicht gelesen,” “von der Plattform nicht genannt” and “keine Angabe” are three different starting points for a calculation and a dash would flatten them into one, while the same rows on the receipt only tell the customer what he did not fill in. The receipt is a letter and not a form: it opens and closes like one, and the summary sits between two `+++` rules.

**Two mails go out:** the inquiry to the workshop, with the license, and an acknowledgment to the customer without it. The second is where the instruction behind the license question is written down, in the conditional—an indicative sentence would turn the inquiry into the contract it must not be. For the same reason the button says “Anfrage senden” and the page says what an order needs: the offer, and its release.

The mechanism—honeypot, signed timestamp, CSRF token, rate limit—is the contact form's, through `SignedTimestamp`, which both share.

Two enhancements need JavaScript and nothing depends on them: the step bar following the scroll position, and the button saying it is working while the model is fetched.

## Reading a model

**One reader per platform, behind `ModelReader`.** Today there is one: Printables' pages sit behind a bot check that refuses a server, so `PrintablesReader` asks the platform's own GraphQL interface at `api.printables.com`, which needs no key.

**Measure the payload, not the status code, before adding a platform.** A page rendered in the browser can answer a server with 200 and carry Open Graph tags that are the same generic ones on every model; Thingiverse does. A reader that trusts them gives every model the platform's own name as its title, which is worse than no preview, because the card is what the visitor confirms.

`ModelLookup` picks the reader and shortens what comes back. Everything above it sees only a `ModelPreview`. **Entities are decoded by the reader**, not centrally: an interface hands its text out encoded, a crawler decodes while reading an attribute, and doing it twice turns an ampersand a designer typed into the character behind it.

**A reader serves its whole platform, and says so when the address is not a model.** An address on Printables that points at a profile, a collection or a search fails as `NotAModelPage`, not as an unsupported address: the visitor made a mistake he can fix in ten seconds, and telling him we do not read his platform leaves him with nothing to correct. Whoever is stuck anyway reaches the contact form from the same page.

**What a platform sends is shown as it arrives.** A title, a summary or a license name is someone else's text: its dashes, quotation marks and spelling stay untouched, whatever the house typography says. The rule binds what this site writes, not what it quotes.

**The preview picture is the platform's rendition, never the original.** A cover on Printables runs into the megabytes—seven on a measured model—which the relay refuses and the card would show as a broken picture. The address is built by inserting `thumbs/inside/640x480/<format>/` before the file name, the format following the file's own extension; only a fixed set of sizes exists and an invented one is answered with 400. If the pattern ever changes, the relay refuses an answer that is not an image and the card falls back to its own empty state.

**Printables also names the license, and it goes into the inquiry mail, never onto the page.** The workshop needs to know what it is calculating with; a visitor would read it as a promise that nobody checked.

**`PlatformFetcher` carries the whole security requirement** for both readers—the host list, the resolved address checked against the reserved ranges and pinned into the request, and limits on time, size and media type. A redirect is checked again at every hop on the fetched path and refused outright on the posted one, since the other side may not repeat a body. What is requested is rebuilt from the checked parts, never the string a visitor typed.

**The interface of Printables is undocumented** and its introspection is off: the field names were found by asking and can change without notice. When they do, the assistant carries on without a preview—which is why the failure path is not a nicety.

## Data model

* **Landing pages** use JSON files in `config/`. Flow: JSON → Serializer → entity DTO → Twig. `/advintage` loads `config/advintage-landing-page.json`; **the path is anchored to `kernel.project_dir`**, because a relative path resolves against the working directory, which holds for the web server and breaks in the test runner.
* `UsageContext` is the enum behind the purpose question and carries its own labels.
