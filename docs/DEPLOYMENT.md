# Deployment and configuration

## Deployment

Server directory `~/www/html/krausgedruckt`, on the account `krswrk`, host `nix`. Mechanism, deploy keys and the mailer are in `../../../docs/DEPLOYMENT.md`.

**The mail is on the same account.** The MX record points at `in-mx.uberspace.de`, and `krausgedruckt.de` is registered for mail on `krswrk`, so `mail@krausgedruckt.de` is a mailbox of that account and the contact form delivers locally. The sender is not this domain—see “The sender on `krswrk`” in `../../../docs/DEPLOYMENT.md`.

**A fresh server needs a writable `var/` and `.env.local`**, nothing else beyond the clone.

## Configuration

**Outbound targets are parameters in `config/services.yaml`**, read with `getParameter()`, not environment variables.

**The minimum order value lives beside them**—`app.minimum_order_value`, handed to Twig as a global in `config/packages/twig.yaml` and stated once, in the proof line of the assistant. Neither mail states it.

**The frequently asked questions are outside all of this.** They are a content in kongtent, so the minimum order value stands there as editorial text and no parameter reaches it; changing `config/services.yaml` leaves it behind, and it has to follow by hand.

The app is promoted for iOS only; there is no Mac badge and no variable for one.
