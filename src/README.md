# src

- `Core/<Reader>/` — one mail reader per shop or carrier, plain PHP, no WordPress; each implements the modeller's
  `OrderMailParser` contract. Must not depend on anything under `WordPress/`.
- `WordPress/` — the boundary: registers the readers with Taxmod Mail and their tabs on the modeller's Configuration page.

Concept: [module-schnitt.md](https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md).
