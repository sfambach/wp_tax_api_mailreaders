# Taxmod Mail Readers

Mail readers for the [Taxonomy Modeller](https://github.com/sfambach/wp-taxonomy-modeler) (WordPress plugin), in one module.

**Status 0.1.0 (needs modeller 0.1.143 and [Taxmod Mail](https://github.com/sfambach/wp_tax_mail) 0.6.0 or later):
reads the mails of AliExpress, Amazon, Conrad, Reichelt, Hermes and PayPal from the mailbox of Taxmod Mail and hands
them to the modeller's inbox.** Each reader keeps its own tab under *Taxonomy Modeller → Configuration*, with the same
key as before.

These six readers were separate plugins (`wp_tax_api_aliexpress`, `_amazon`, `_conrad`, `_reichelt`, `_hermes`,
`_paypal`) until 0.1.0. They are joined because every active plugin costs start-up time on each uncached page view, and
these six read mails only and have no settings of their own. Connections with an API or settings of their own (eBay,
DHL, GLS, JLCPCB, Mouser, DPD) stay modules of their own
([concept](https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md)).

| Reader | Reads | Notes |
|---|---|---|
| AliExpress | order and shipping mails | a shipping mail carries no order date |
| Amazon | order mails | no tracking number, no shipping cost |
| Conrad | order mails and invoices | the invoice PDF goes to the modeller's encrypted document store |
| Reichelt | order mails | Reichelt has no API |
| Hermes | parcel notices | goes to the order with the same tracking number, never creates one |
| PayPal | payment receipts | a payment to a shop with a reader of its own is left to that reader |

## Setup

1. Install and set up Taxmod Mail (mailbox access).
2. Activate this module and deactivate the six old single modules (they register the same readers twice otherwise).

## Development

`composer install`, then `vendor/bin/phpunit` (core suite, no WordPress). The sample mails in `tests/Core/*/fixtures`
are synthetic.

## License

Copyright (C) 2026 Stefan Fambach (SteFam), https://www.fambach.net — [PolyForm Noncommercial 1.0.0](LICENSE.md).
Commercial use needs the owner's prior consent.

## Authors

- **Stefan Fambach** (SteFam) – idea and concept, review and testing – https://www.fambach.net
- **Claude** (Anthropic AI) – implementation, via Claude Code

## AI disclosure

This project is built with AI assistance. Stefan Fambach had the idea, wrote the concept and reviews and tests every change; the implementation (code and documentation) is written largely by Claude (Anthropic) via Claude Code.
