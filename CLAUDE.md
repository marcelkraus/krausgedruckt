# krausgedruckt

Business website for **krausgedruckt**, Marcel Kraus's 3D printing (https://www.krausgedruckt.de): the services, the blog, the FAQ, a contact form, the print-order assistant and a shop band. German only, German URLs. The blog and the FAQ come from kongtent; everything else is markup or JSON. No database.

## Rules

* **Marcel speaks in the first person and addresses the customer as „du“.** In the FAQ the customer speaks too, so „ich“ and „du“ change sides between a question and its answer.
* **Forms are hand-written**, the contact form and the assistant alike, on the mechanism in `../../docs/WEB_STACK.md`; `symfony/form` is not installed.
* **Outbound targets and the minimum order value are parameters in `config/services.yaml`.** The FAQ in kongtent states the minimum order value as text and follows a change by hand.
* **A printed piece answers under `/s/<prefix>-<code>`** and leads to the homepage; the scheme is in `../../docs/WORKSPACE.md` › Short links, this site's codes in `ShortLinkController`. `/shop` exists for print only; the site links Etsy directly.
* **The test suite never reaches the network**: kongtent answers from `tests/fixtures/kongtent/`, `PlatformFetcher` from an empty `MockHttpClient`. Pure logic gets a plain `TestCase`; a test spanning two requests calls `disableReboot()`.
* **ddev runs Apache**, so `public/.htaccess` is exercised locally.

## Documents

| # | Document | Read before |
| --- | --- | --- |
| 1 | `docs/DESIGN.md` | touching a template, a partial, the navigation, the stylesheet, the logo, the sharing image or the head data |
| 2 | `docs/BLOG_AND_FAQ.md` | touching the blog, the FAQ, a block template override or the feed |
| 3 | `docs/PRINT_ORDER.md` | touching `/modell-drucken`, a model reader, its mails or a landing page |
| 4 | `docs/CONTACT_FORM.md` | touching the contact form |
| 5 | `docs/DEPLOYMENT.md` | touching the server, the mail or a parameter |

## Agent skills

Issues and specs live as local markdown files under `.scratch/` (`docs/agents/issue-tracker.md`). Triage uses the five canonical roles (`docs/agents/triage-labels.md`). Domain docs are single-context: one `CONTEXT.md` and `docs/adr/` at the repository root (`docs/agents/domain.md`).
