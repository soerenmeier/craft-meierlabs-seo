<?php

namespace soerenmeier\meierlabsseo\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\models\Section;
use craft\models\Site;
use soerenmeier\meierlabsseo\MeierLabsSeo;
use yii\caching\TagDependency;

/**
 * Builds the sitemaps and robots.txt of a frontend origin.
 *
 * The crelte frontend proxies `/sitemap.xml`, `/sitemap-*.xml` and
 * `/robots.txt` to the actions of this plugin and passes its origin, so
 * every site whose base url starts with that origin is included.
 */
class Sitemap extends Component
{
	public const CACHE_TAG = 'meierlabs-seo';
	private const XML_HEAD = '<?xml version="1.0" encoding="UTF-8"?>';

	/** sites of the frontend origin, all sites if none matches */
	public function sitesForOrigin(?string $origin): array
	{
		$sites = array_filter(
			Craft::$app->getSites()->getAllSites(),
			fn(Site $site) => $site->hasUrls && $site->getBaseUrl()
		);

		if ($origin) {
			$origin = rtrim($origin, '/');
			$matching = array_filter(
				$sites,
				fn(Site $site) => $this->origin($site->getBaseUrl()) === $origin
			);
			if ($matching) {
				return array_values($matching);
			}
		}

		return array_values($sites);
	}

	public function index(string $origin): string
	{
		return $this->cached(['index', $origin], function () use ($origin) {
			$pageSize = MeierLabsSeo::$plugin->getSettings()->sitemapPageSize;
			$xml = self::XML_HEAD . "\n";
			$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

			foreach ($this->sitesForOrigin($origin) as $site) {
				foreach ($this->sections($site) as $section) {
					$query = $this->query($site, $section);
					$count = (clone $query)->count();
					$pages = (int)ceil($count / $pageSize);
					if (!$pages) {
						continue;
					}

					$lastMod = (clone $query)->orderBy(['dateUpdated' => SORT_DESC])->one()?->dateUpdated;
					for ($page = 1; $page <= $pages; $page++) {
						$loc = rtrim($origin, '/') . "/sitemap-{$site->handle}-{$section->handle}-$page.xml";
						$xml .= "\t<sitemap>\n\t\t<loc>" . $this->escape($loc) . "</loc>\n";
						if ($lastMod) {
							$xml .= "\t\t<lastmod>" . $lastMod->format(DATE_W3C) . "</lastmod>\n";
						}
						$xml .= "\t</sitemap>\n";
					}
				}
			}

			return $xml . "</sitemapindex>\n";
		});
	}

	/** null if the site or section has no sitemap */
	public function page(string $siteHandle, string $sectionHandle, int $page): ?string
	{
		$site = Craft::$app->getSites()->getSiteByHandle($siteHandle);
		$section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);
		if (!$site || !$section || $page < 1 || !in_array($section, $this->sections($site), true)) {
			return null;
		}

		return $this->cached(['page', $site->id, $section->id, $page], function () use ($site, $section, $page) {
			$seo = MeierLabsSeo::$plugin->seo;
			$pageSize = MeierLabsSeo::$plugin->getSettings()->sitemapPageSize;

			$entries = $this->query($site, $section)
				->offset(($page - 1) * $pageSize)
				->limit($pageSize)
				->all();
			if (!$entries && $page > 1) {
				return null;
			}

			$alternates = $this->alternates(array_map(fn($e) => $e->id, $entries));

			$xml = self::XML_HEAD . "\n";
			$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

			foreach ($entries as $entry) {
				if (!$seo->includeInSitemap($entry)) {
					continue;
				}

				$xml .= "\t<url>\n\t\t<loc>" . $this->escape($entry->getUrl()) . "</loc>\n";
				$xml .= "\t\t<lastmod>" . $entry->dateUpdated->format(DATE_W3C) . "</lastmod>\n";

				$alts = $alternates[$entry->id] ?? [];
				if (count($alts) > 2) {
					foreach ($alts as $hreflang => $url) {
						$xml .= "\t\t<xhtml:link rel=\"alternate\" hreflang=\"" . $this->escape($hreflang)
							. '" href="' . $this->escape($url) . "\"/>\n";
					}
				}
				$xml .= "\t</url>\n";
			}

			return $xml . "</urlset>\n";
		});
	}

	public function robots(string $origin): string
	{
		$sites = $this->sitesForOrigin($origin);
		$primary = current(array_filter($sites, fn(Site $site) => $site->primary)) ?: ($sites[0] ?? null);

		$lines = [];
		if (!MeierLabsSeo::$plugin->seo->indexingAllowed()) {
			$lines[] = '# indexing is only allowed in production';
			$lines[] = 'User-agent: *';
			$lines[] = 'Disallow: /';
			return implode("\n", $lines) . "\n";
		}

		$custom = $primary ? MeierLabsSeo::$plugin->seo->globals($primary)['robotsTxt'] : null;
		if ($custom) {
			$lines[] = trim($custom);
		} else {
			$lines[] = 'User-agent: *';
			$lines[] = 'Disallow:';
		}

		$lines[] = '';
		$lines[] = 'Sitemap: ' . rtrim($origin, '/') . '/sitemap.xml';

		return implode("\n", $lines) . "\n";
	}

	public function invalidate(): void
	{
		TagDependency::invalidate(Craft::$app->getCache(), self::CACHE_TAG);
	}

	/** sections of a site with urls and the sitemap enabled */
	private function sections(Site $site): array
	{
		$store = MeierLabsSeo::$plugin->store;
		$sections = [];

		foreach (Craft::$app->getEntries()->getAllSections() as $section) {
			$settings = $section->getSiteSettings()[$site->id] ?? null;
			if (!$settings || !$settings->hasUrls) {
				continue;
			}

			$data = $store->get(Store::SCOPE_SECTION, $section->uid, $site->id);
			if ($data['sitemap'] ?? true) {
				$sections[] = $section;
			}
		}

		return $sections;
	}

	private function query(Site $site, Section $section)
	{
		return Entry::find()
			->site($site)
			->section($section)
			->status('live')
			->uri(':notempty:')
			->orderBy(['elements.id' => SORT_ASC]);
	}

	/** urls of the given entries in all sites, by entry id and hreflang */
	private function alternates(array $ids): array
	{
		if (!$ids) {
			return [];
		}

		$alternates = [];
		$entries = Entry::find()
			->id($ids)
			->siteId('*')
			->unique(false)
			->status('live')
			->uri(':notempty:')
			->all();

		foreach ($entries as $entry) {
			$url = $entry->getUrl();
			if (!$url) {
				continue;
			}

			$site = $entry->getSite();
			$alternates[$entry->id][$site->language] = $url;
			if ($site->primary) {
				$alternates[$entry->id]['x-default'] = $url;
			}
		}

		return $alternates;
	}

	private function cached(array $key, callable $fn): ?string
	{
		return Craft::$app->getCache()->getOrSet(
			array_merge(['meierlabs-seo-sitemap'], $key),
			$fn,
			60 * 60 * 24,
			new TagDependency([
				'tags' => [
					self::CACHE_TAG,
					'element::' . Entry::class,
					'element::' . Entry::class . '::*',
				],
			])
		);
	}

	private function origin(string $url): string
	{
		$parts = parse_url($url);
		$origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
		if (isset($parts['port'])) {
			$origin .= ':' . $parts['port'];
		}
		return $origin;
	}

	private function escape(string $value): string
	{
		return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	}
}
