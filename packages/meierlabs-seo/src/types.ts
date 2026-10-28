/** `seo { ... }` of an entry or the root `seo(siteId: ...)` query */
export type SeoData = {
	title?: string | null;
	description?: string | null;
	canonical?: string | null;
	robots?: string | null;
	siteName?: string | null;
	locale?: string | null;
	image?: SeoImage | null;
	socialTitle?: string | null;
	socialDescription?: string | null;
	socialImage?: SeoImage | null;
	alternates?: SeoAlternate[] | null;
	meta?: SeoMeta[] | null;
	links?: SeoLink[] | null;
	jsonLd?: string | null;
};

export type SeoImage = {
	url: string;
	width?: number | null;
	height?: number | null;
	alt?: string | null;
	mimeType?: string | null;
};

export type SeoAlternate = {
	hreflang: string;
	url: string;
};

export type SeoMeta = {
	name?: string | null;
	property?: string | null;
	content: string;
};

export type SeoLink = {
	rel: string;
	href: string;
	hreflang?: string | null;
};
