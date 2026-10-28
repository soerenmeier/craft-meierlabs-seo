<?php

namespace soerenmeier\meierlabsseo\models;

use Craft;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\Image;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\models\Site;
use soerenmeier\meierlabsseo\MeierLabsSeo;

/**
 * The resolved SEO of a page, what the frontend puts into the head.
 */
class SeoResult
{
	public Site $site;
	public ?Entry $entry = null;
	public string $siteName = '';
	public ?string $url = null;
	public string $title = '';
	public ?string $description = null;
	public ?Asset $image = null;
	public string $socialTitle = '';
	public ?string $socialDescription = null;
	public ?Asset $socialImage = null;
	public ?string $canonical = null;
	public string $robots = 'index, follow';
	public ?string $twitterHandle = null;
	/** @var array{hreflang: string, url: string}[] */
	public array $alternates = [];
	/** @var array{name: string, url: string}[] */
	public array $breadcrumbs = [];
	public array $structuredData = [];

	private array $_images = [];

	public function getLocale(): string
	{
		return str_replace('-', '_', $this->site->language);
	}

	/** the image cropped to the social image size */
	public function getImageData(): ?array
	{
		return $this->imageData($this->image, true);
	}

	public function getSocialImageData(): ?array
	{
		return $this->imageData($this->socialImage, true);
	}

	/** @return array{name: ?string, property: ?string, content: string}[] */
	public function getMeta(): array
	{
		$meta = [];
		$add = function (?string $name, ?string $property, mixed $content) use (&$meta) {
			if ($content !== null && $content !== '') {
				$meta[] = ['name' => $name, 'property' => $property, 'content' => (string)$content];
			}
		};

		$add('description', null, $this->description);
		$add('robots', null, $this->robots);

		$add(null, 'og:type', 'website');
		$add(null, 'og:site_name', $this->siteName);
		$add(null, 'og:title', $this->socialTitle);
		$add(null, 'og:description', $this->socialDescription);
		$add(null, 'og:url', $this->canonical);
		$add(null, 'og:locale', $this->getLocale());
		foreach ($this->alternates as $alt) {
			if ($alt['hreflang'] !== 'x-default' && $alt['hreflang'] !== $this->site->language) {
				$add(null, 'og:locale:alternate', str_replace('-', '_', $alt['hreflang']));
			}
		}

		$image = $this->getSocialImageData();
		if ($image) {
			$add(null, 'og:image', $image['url']);
			$add(null, 'og:image:width', $image['width']);
			$add(null, 'og:image:height', $image['height']);
			$add(null, 'og:image:alt', $image['alt']);
		}

		$add('twitter:card', null, $image ? 'summary_large_image' : 'summary');
		if ($this->twitterHandle) {
			$add('twitter:site', null, '@' . ltrim($this->twitterHandle, '@'));
		}

		return $meta;
	}

	/** @return array{rel: string, href: string, hreflang: ?string}[] */
	public function getLinks(): array
	{
		$links = [];
		if ($this->canonical) {
			$links[] = ['rel' => 'canonical', 'href' => $this->canonical, 'hreflang' => null];
		}
		foreach ($this->alternates as $alt) {
			$links[] = ['rel' => 'alternate', 'href' => $alt['url'], 'hreflang' => $alt['hreflang']];
		}
		return $links;
	}

	public function getJsonLd(): array
	{
		$sd = $this->structuredData;
		$baseUrl = $this->site->getBaseUrl() ?? '';
		$graph = [];

		$org = null;
		if (!empty($sd['name'])) {
			$org = array_filter([
				'@type' => ($sd['type'] ?? null) ?: 'Organization',
				'@id' => $baseUrl . '#organization',
				'name' => $sd['name'],
				'alternateName' => $sd['alternateName'] ?? null,
				'legalName' => $sd['legalName'] ?? null,
				'description' => $sd['description'] ?? null,
				'url' => ($sd['url'] ?? null) ?: $baseUrl,
				'logo' => $this->imageObject(MeierLabsSeo::$plugin->seo->asset($sd['logoId'] ?? null)),
				'image' => $this->imageObject(MeierLabsSeo::$plugin->seo->asset($sd['imageId'] ?? null)),
				'email' => $sd['email'] ?? null,
				'telephone' => $sd['telephone'] ?? null,
				'address' => $this->address($sd),
				'geo' => !empty($sd['latitude']) && !empty($sd['longitude']) ? [
					'@type' => 'GeoCoordinates',
					'latitude' => (float)$sd['latitude'],
					'longitude' => (float)$sd['longitude'],
				] : null,
				'openingHoursSpecification' => $this->openingHours($sd),
				'priceRange' => $sd['priceRange'] ?? null,
				'sameAs' => array_values(array_filter(array_map(
					fn($row) => trim($row['url'] ?? ''),
					is_array($sd['sameAs'] ?? null) ? $sd['sameAs'] : []
				))) ?: null,
			]);
			$graph[] = $org;
		}

		$creator = null;
		if (!empty($sd['creatorName'])) {
			$creator = array_filter([
				'@type' => ($sd['creatorType'] ?? null) ?: 'Organization',
				'name' => $sd['creatorName'],
				'url' => $sd['creatorUrl'] ?? null,
			]);
		}

		$website = array_filter([
			'@type' => 'WebSite',
			'@id' => $baseUrl . '#website',
			'url' => $baseUrl,
			'name' => $this->siteName,
			'inLanguage' => $this->site->language,
			'publisher' => $org ? ['@id' => $org['@id']] : null,
			'creator' => $creator,
		]);
		$graph[] = $website;

		if ($this->entry && $this->url) {
			$image = $this->imageObject($this->image);
			$page = array_filter([
				'@type' => 'WebPage',
				'@id' => $this->url . '#webpage',
				'url' => $this->url,
				'name' => $this->title,
				'description' => $this->description,
				'inLanguage' => $this->site->language,
				'isPartOf' => ['@id' => $website['@id']],
				'primaryImageOfPage' => $image,
				'datePublished' => $this->entry->postDate?->format(DATE_ATOM),
				'dateModified' => $this->entry->dateUpdated?->format(DATE_ATOM),
				'breadcrumb' => $this->breadcrumbs ? ['@id' => $this->url . '#breadcrumb'] : null,
			]);
			$graph[] = $page;

			if ($this->breadcrumbs) {
				$items = [];
				foreach ($this->breadcrumbs as $i => $crumb) {
					$items[] = [
						'@type' => 'ListItem',
						'position' => $i + 1,
						'name' => $crumb['name'],
						'item' => $crumb['url'],
					];
				}
				$graph[] = [
					'@type' => 'BreadcrumbList',
					'@id' => $this->url . '#breadcrumb',
					'itemListElement' => $items,
				];
			}
		}

		return ['@context' => 'https://schema.org', '@graph' => $graph];
	}

	public function getJsonLdString(): string
	{
		return Json::encode($this->getJsonLd(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}

	private function openingHours(array $sd): ?array
	{
		$specs = [];
		foreach ((array)($sd['openingHours'] ?? []) as $row) {
			$specs[] = [
				'@type' => 'OpeningHoursSpecification',
				'dayOfWeek' => $row['day'],
				'opens' => $row['opens'],
				'closes' => $row['closes'],
			];
		}
		return $specs ?: null;
	}

	private function address(array $sd): ?array
	{
		$address = array_filter([
			'streetAddress' => $sd['street'] ?? null,
			'postalCode' => $sd['postalCode'] ?? null,
			'addressLocality' => $sd['locality'] ?? null,
			'addressRegion' => $sd['region'] ?? null,
			'addressCountry' => $sd['country'] ?? null,
		]);

		return $address ? ['@type' => 'PostalAddress'] + $address : null;
	}

	private function imageObject(?Asset $asset): ?array
	{
		$data = $this->imageData($asset, false);
		if (!$data) {
			return null;
		}

		return array_filter([
			'@type' => 'ImageObject',
			'url' => $data['url'],
			'width' => $data['width'],
			'height' => $data['height'],
			'caption' => $data['alt'],
		]);
	}

	private function imageData(?Asset $asset, bool $crop): ?array
	{
		if (!$asset) {
			return null;
		}

		$key = $asset->id . ':' . (int)$crop;
		if (array_key_exists($key, $this->_images)) {
			return $this->_images[$key];
		}

		$settings = MeierLabsSeo::$plugin->getSettings();
		$transform = null;
		if ($crop && Image::canManipulateAsImage($asset->getExtension())) {
			$transform = [
				'width' => $settings->imageWidth,
				'height' => $settings->imageHeight,
				'mode' => 'crop',
			];
		}

		try {
			$url = $asset->getUrl($transform, true);
		} catch (\Throwable $e) {
			Craft::warning("Could not transform asset {$asset->id}: {$e->getMessage()}", __METHOD__);
			$url = $asset->getUrl();
		}
		if (!$url) {
			return $this->_images[$key] = null;
		}
		if (!UrlHelper::isAbsoluteUrl($url)) {
			$url = UrlHelper::siteUrl($url);
		}

		return $this->_images[$key] = [
			'url' => $url,
			'width' => $asset->getWidth($transform),
			'height' => $asset->getHeight($transform),
			'alt' => $asset->alt ?: $asset->title,
			'mimeType' => $asset->getMimeType($transform),
		];
	}
}
