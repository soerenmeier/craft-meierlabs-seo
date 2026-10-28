# Release Notes for Meier Labs SEO

## 0.1.0-alpha.1 - 2026-10-28

- add `seoJson` to entries and as root query: the whole resolved SEO as JSON, so projects query only `seoJson` and new versions of the npm package don't need query changes
- npm: `<Seo>` takes `seoJson` (or a `seo { ... }` object), `parseSeo` returns the single values; `seoFragment` is removed
- German translations of the control panel
- the structured data form shows opening hours, coordinates and price range only for local business types and hides legal name and logo for a person
- plugin and nav icons

## 0.1.0-alpha.0 - 2026-10-28

First alpha release of the plugin and the `meierlabs-seo` npm package.
