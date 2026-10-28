# meierlabs-seo

Crelte frontend for the [Meier Labs SEO](https://github.com/soerenmeier/craft-meierlabs-seo)
Craft CMS plugin: renders the head tags of an entry and serves sitemaps and
robots.txt.

```svelte
<script>
	import { Seo } from 'meierlabs-seo';
</script>

<Seo seo={entry?.seoJson} />
```

```js
// server.js
import { seoRoutes } from 'meierlabs-seo/server';

export async function routes(router) {
	seoRoutes(router);
}
```

Add `seoJson` to the entry query, see the plugin README.
