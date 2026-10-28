import type { SeoData } from './types.js';

/**
 * Parses `seoJson` (a JSON string) into its data, objects from
 * `seo { ... }` are returned as they are.
 */
export function parseSeo(
	seo: SeoData | string | null | undefined,
): SeoData | null {
	if (!seo) return null;
	if (typeof seo !== 'string') return seo;

	try {
		return JSON.parse(seo) as SeoData;
	} catch (e) {
		console.error('meierlabs-seo: invalid seoJson', e);
		return null;
	}
}
