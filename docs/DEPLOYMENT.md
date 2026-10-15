# Deployment and configuration

Server directory `~/www/html/krausgedruckt`, on the account `krswrk`, host `nix`. Mechanism, deploy keys and the mailer are in `../../../docs/DEPLOYMENT.md`.

**The mail is on the same account.** The MX record points at `in-mx.uberspace.de`, and `krausgedruckt.de` is registered for mail on `krswrk`, so `mail@krausgedruckt.de` is a mailbox of that account and the contact form delivers locally. The sender is not this domain—see “The sender on `krswrk`” in `../../../docs/DEPLOYMENT.md`.

**A fresh server needs a writable `var/` and `.env.local`**, nothing else beyond the clone.

## Configuration

**The minimum order value is the parameter `app.minimum_order_value`**, handed to Twig as a global in `config/packages/twig.yaml` and stated once, in the proof line of the assistant. Neither mail states it.

The app is promoted for iOS only; there is no Mac badge and no variable for one.
