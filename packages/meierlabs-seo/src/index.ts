export { default as Seo } from './Seo.svelte';
export type * from './types.js';

/**
 * The fields `<Seo>` needs. Add `seo { ...Seo }` to the entry query and this
 * fragment to the same file:
 *
 * ```graphql
 * fragment Seo on MeierLabsSeo {
 *   title
 *   meta { name property content }
 *   links { rel href hreflang }
 *   jsonLd
 * }
 * ```
 */
export const seoFragment = `fragment Seo on MeierLabsSeo {
	title
	meta { name property content }
	links { rel href hreflang }
	jsonLd
}`;
