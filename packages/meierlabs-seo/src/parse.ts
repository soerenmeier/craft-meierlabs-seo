import type { SeoData } from './types.js';

/**
 * Returns the data of `seoJson` or `seo { ... }`. Both are objects, JSON
 * strings (`seoJson` before 0.1.0-alpha.2) are parsed.
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
