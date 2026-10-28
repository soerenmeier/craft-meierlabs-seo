<?php

namespace soerenmeier\meierlabsseo\models;

use craft\base\Model;

/**
 * Plugin config, set in `config/meierlabs-seo.php`. The SEO content itself
 * (titles, descriptions, images, ...) is edited in the control panel.
 */
class Settings extends Model
{
	/**
	 * Whether search engines may index the site. `null` allows it only
	 * when `CRAFT_ENVIRONMENT` is `production`, everything else gets
	 * `noindex, nofollow` and a robots.txt that disallows everything.
	 */
	public ?bool $allowIndexing = null;

	/** Entries per sitemap file */
	public int $sitemapPageSize = 2000;

	/** Size of the social image (og:image), cropped */
	public int $imageWidth = 1200;
	public int $imageHeight = 630;

	public function defineRules(): array
	{
		return [
			[['allowIndexing'], 'boolean'],
			[['sitemapPageSize', 'imageWidth', 'imageHeight'], 'integer', 'min' => 1],
		];
	}
}
