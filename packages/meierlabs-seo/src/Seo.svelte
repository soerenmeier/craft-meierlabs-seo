<!--
	Renders the title, meta tags, links and JSON-LD of a resolved SEO into
	the head. Needs `title`, `meta`, `links` and `jsonLd` in the query, see
	`seoFragment`.
-->
<script lang="ts">
	import type { SeoData } from './types.js';

	let { seo }: { seo: SeoData | null | undefined } = $props();

	// `<` is escaped so the JSON can't close the script tag
	const jsonLd = $derived(
		seo?.jsonLd
			? '<script type="application/ld+json">' +
					seo.jsonLd.replace(/</g, '\\u003c') +
					'</' +
					'script>'
			: '',
	);
</script>

<svelte:head>
	{#if seo}
		{#if seo.title}
			<title>{seo.title}</title>
		{/if}
		{#each seo.meta ?? [] as meta, i (i)}
			{#if meta.property}
				<meta property={meta.property} content={meta.content} />
			{:else}
				<meta name={meta.name} content={meta.content} />
			{/if}
		{/each}
		{#each seo.links ?? [] as link, i (i)}
			<link rel={link.rel} href={link.href} hreflang={link.hreflang ?? undefined} />
		{/each}
		{@html jsonLd}
	{/if}
</svelte:head>
