import type { CrelteServerRequest, ServerRouter } from 'crelte/server';

/**
 * Serves `/sitemap.xml`, `/sitemap-<site>-<section>-<page>.xml` and
 * `/robots.txt` from the Meier Labs SEO plugin.
 *
 * ```js
 * // server.js
 * import { seoRoutes } from 'meierlabs-seo/server';
 *
 * export async function routes(router) {
 * 	seoRoutes(router);
 * }
 * ```
 */
export function seoRoutes(router: ServerRouter) {
	router.get('/sitemap.xml', csr =>
		proxy(csr, 'sitemap/index', { origin: origin(csr) }),
	);
	router.get('/robots.txt', csr =>
		proxy(csr, 'sitemap/robots', { origin: origin(csr) }),
	);
	router.get('/:file', csr => {
		const file = new URL(csr.req.url).pathname.slice(1);
		const m = /^sitemap-(\w+?)-(\w+)-(\d+)\.xml$/.exec(file);
		if (!m) return;

		return proxy(csr, 'sitemap/page', {
			site: m[1],
			section: m[2],
			page: m[3],
		});
	});
}

function origin(csr: CrelteServerRequest): string {
	return new URL(csr.req.url).origin;
}

async function proxy(
	csr: CrelteServerRequest,
	action: string,
	params: Record<string, string>,
): Promise<Response | undefined> {
	const url = csr.backendUrl('/actions/meierlabs-seo/' + action);
	for (const [key, value] of Object.entries(params)) {
		url.searchParams.set(key, value);
	}

	let resp: Response;
	try {
		resp = await fetch(url, { redirect: 'manual' });
	} catch (e) {
		console.error('meierlabs-seo: failed to fetch ' + url, e);
		return;
	}

	// let the frontend render its 404
	if (resp.status === 404) return;

	const headers = new Headers();
	for (const name of ['content-type', 'cache-control', 'last-modified']) {
		const value = resp.headers.get(name);
		if (value) headers.set(name, value);
	}

	return new Response(await resp.text(), { status: resp.status, headers });
}
