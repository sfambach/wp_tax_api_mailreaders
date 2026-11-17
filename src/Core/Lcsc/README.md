# src/Core/Lcsc

The LCSC mail reader (`LcscMailParser`): recognises LCSC's mails and reads order confirmations (order number) and shipping notifications (tracking number, shipping method, items) into an order proposal; `LcscPackingList` reads the items from the packing list PDF attached to the shipping mail. LCSC's mails name no prices.
Plain PHP 8.2, **no WordPress call**; it implements the modeller's contract `Taxmod\Core\Import\Mail\OrderMailParser`.
Concept: [`docs/pakete/import/module-schnitt.md`](https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md).
