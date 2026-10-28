<!--
	Renders the title, meta tags, links and JSON-LD of a resolved SEO into
	the head. Pass `seoJson` of the entry (or `seo { title meta links jsonLd }`).
-->
<script lang="ts">
	import { parseSeo } from './parse.js';
	import type { SeoData } from './types.js';

	let { seo: raw }: { seo: SeoData | string | null | undefined } = $props();

	const seo = $derived(parseSeo(raw));

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
			<link
				rel={link.rel}
				href={link.href}
				hreflang={link.hreflang ?? undefined}
			/>
		{/each}
		{@html jsonLd}
	{/if}
</svelte:head>
