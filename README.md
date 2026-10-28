# Meier Labs SEO for Craft CMS

## Overview

SEO for headless Craft CMS sites built with [crelte](https://github.com/crelte/crelte).
Craft resolves everything (titles, meta tags, JSON-LD, sitemaps), the crelte
frontend only renders it with the `meierlabs-seo` npm package. No Twig needed.

## Features

- Global settings per site: site name, title pattern (`{title} | {siteName}`),
  description, image, robots, X handle, robots.txt
- Overrides per section and entry type; the description and image of an entry
  type can come from one of its fields (also inside content blocks, e.g.
  `pageIntro.pageIntroCke`)
- An SEO field for single entries: title, description, image, social title,
  description and image, robots and canonical URL, with the inherited values
  as placeholders, length counters and a search result preview
- Open Graph and X tags, canonical and hreflang links
- Structured data (JSON-LD): Organization (any schema.org type, address, geo,
  opening hours, profiles), WebSite with its creator, WebPage and breadcrumbs
- Sitemaps per site and section with hreflang alternates; entries with
  `noindex` or a canonical URL to another page are left out
- Outside `production` every page gets `noindex, nofollow` and robots.txt
  disallows everything

Every value is taken from the first place that has one: the SEO field of the
entry, the entry type, the section, the global settings. Sites other than the
primary site inherit the global settings of the primary site, except the
description and robots.txt. Structured data is the same for all sites.

## Installation

```bash
composer require soerenmeier/meierlabs-seo -w && php craft plugin/install meierlabs-seo
```

Then add an SEO field (field type "SEO") to the entry types where editors
should be able to override the SEO of single entries, and set up the global
settings under SEO in the control panel. Non-admins need the permission
"Edit the SEO settings".

## Crelte

```bash
npm install meierlabs-seo
```

Every entry has a `seo` field in GraphQL. Add it to the entry query:

```graphql
fragment Seo on MeierLabsSeo {
  title
  meta {
    name
    property
    content
  }
  links {
    rel
    href
    hreflang
  }
  jsonLd
}

query ($uri: [String], $siteId: [QueryArgument]) {
  entry(uri: $uri, siteId: $siteId) {
    seo {
      ...Seo
    }
  }
}
```

Render it in `App.svelte`:

```svelte
<script>
	import { Seo } from 'meierlabs-seo';

	let { route } = $props();
	let entry = $derived($route.entry);
</script>

<Seo seo={entry?.seo} />
```

And serve the sitemaps and robots.txt in `server.js`:

```js
import { seoRoutes } from 'meierlabs-seo/server';

export async function routes(router) {
	seoRoutes(router);
}
```

`/sitemap.xml` lists a sitemap per site and section
(`/sitemap-<site>-<section>-<page>.xml`) for all sites on the domain of the request.

For pages without an entry, the root query `seo(siteId: Int, site: String)`
returns the global SEO of a site.

The other fields of `seo` (`description`, `canonical`, `robots`, `image`,
`socialImage`, `alternates`, ...) are there if the frontend needs them
separately.

## Configuration

`config/meierlabs-seo.php`:

```php
return [
	// null: only in production
	'allowIndexing' => null,
	'sitemapPageSize' => 2000,
	// size of the social image (og:image), cropped
	'imageWidth' => 1200,
	'imageHeight' => 630,
];
```

## Moving from SEOmatic

Change the type of the SEOmatic field to "SEO" (the values are not taken
over, SEOmatic's per entry overrides are rarely used), uninstall SEOmatic,
then set up the global, section and entry type settings, e.g. the field
mappings that SEOmatic did with `extractTextFromField`. In crelte replace
`@dark-crelte/extras/seomatic` with `meierlabs-seo` as shown above.

## Roadmap

- More structured data (products, articles, events, FAQ)
- Search result and social preview next to the entry
- Redirects
