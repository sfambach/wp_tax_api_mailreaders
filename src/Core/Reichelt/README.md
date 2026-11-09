# src/Core

The Reichelt mail reader (`ReicheltMailParser`): recognises Reichelt's mails and reads order confirmations and shipping notices into an order proposal.
Plain PHP 8.2, **no WordPress call**; it implements the modeller's contract `Taxmod\Core\Import\Mail\OrderMailParser`.
Concept: [`docs/pakete/import/module-schnitt.md`](https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md).
