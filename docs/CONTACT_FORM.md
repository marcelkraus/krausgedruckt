# Contact form

## Contact form

The mechanism is in `../../../docs/WEB_STACK.md`. Specific here:

* **Five fields:** `name`, `email`, `phone`, `discountCode`, `message`. **The two required ones open the form and the optional ones follow**, because a private customer is the majority here and should not have to skip a field before starting. The sister sites order their forms differently on purpose—there a company is the normal case
* `templates/partials/_field_class.html.twig` holds the field classes, shared with the assistant; `_contact_form` holds the layout and the field order. There is no form theme
* The field keeps the **browser focus ring** (`focus:outline-2`) on top of the accent border; replacing the ring with a one pixel border change makes the form the only place on the site where keyboard focus is weaker than the default
* The form-wide message is a live region (`role="alert"`); the honeypot is `sr-only` **and** `aria-hidden`, and keeps `tabindex="-1"`. The tab order alone is not enough: a screen reader's reading mode walks the document, not the tab chain, so without `aria-hidden` the trap is read out to exactly the visitors who cannot see that it is one
* Discount code can be pre-filled: `/kontakt?discount-code=CODE`
* Mail goes out through `TemplatedEmail`
* The legal mailbox is `mail+legal@krausgedruckt.de`
