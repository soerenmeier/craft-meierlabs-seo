<?php

namespace soerenmeier\meierlabsseo\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\elements\Asset;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\errors\InvalidFieldException;
use craft\fields\Assets as AssetsField;
use craft\fields\ContentBlock;
use craft\fields\PlainText;
use craft\models\EntryType;
use craft\models\Site;
use Illuminate\Support\Collection;
use soerenmeier\meierlabsseo\fields\SeoField;
use soerenmeier\meierlabsseo\MeierLabsSeo;
use soerenmeier\meierlabsseo\models\SeoFieldData;
use soerenmeier\meierlabsseo\models\SeoResult;

/**
 * Resolves the SEO of an entry or a site.
 *
 * Every value is taken from the first place that has one:
 * the entry's SEO field, the entry type, the section and the global
 * settings. The description and image of an entry type can also come from
 * one of its fields (field mapping).
 */
class Seo extends Component
{
	public const DEFAULT_TITLE_PATTERN = '{title} | {siteName}';
	public const DESCRIPTION_LENGTH = 160;

	public function robotsOptions(?string $inheritLabel = null): array
	{
		$options = [];
		if ($inheritLabel !== null) {
			$options[] = ['label' => $inheritLabel, 'value' => ''];
		}

		return array_merge($options, [
			['label' => Craft::t('meierlabs-seo', 'Index, follow links'), 'value' => 'index, follow'],
			['label' => Craft::t('meierlabs-seo', 'No index, follow links'), 'value' => 'noindex, follow'],
			['label' => Craft::t('meierlabs-seo', 'Index, don’t follow links'), 'value' => 'index, nofollow'],
			['label' => Craft::t('meierlabs-seo', 'No index, don’t follow links'), 'value' => 'noindex, nofollow'],
		]);
	}

	public function organizationTypes(): array
	{
		return [
			'Organization',
			'Corporation',
			'LocalBusiness',
			'ProfessionalService',
			'MedicalBusiness',
			'MedicalClinic',
			'Physician',
			'Dentist',
			'LegalService',
			'FinancialService',
			'RealEstateAgent',
			'Store',
			'Restaurant',
			'LodgingBusiness',
			'EducationalOrganization',
			'NGO',
			'GovernmentOrganization',
			'Person',
		];
	}

	/** types that get opening hours, geo coordinates and a price range */
	public function localBusinessTypes(): array
	{
		return [
			'LocalBusiness',
			'ProfessionalService',
			'MedicalBusiness',
			'MedicalClinic',
			'Physician',
			'Dentist',
			'LegalService',
			'FinancialService',
			'RealEstateAgent',
			'Store',
			'Restaurant',
			'LodgingBusiness',
		];
	}

	public function indexingAllowed(): bool
	{
		$allow = MeierLabsSeo::$plugin->getSettings()->allowIndexing;
		return $allow ?? Craft::$app->env === 'production';
	}

	/**
	 * The global settings of a site with their defaults filled in. Other
	 * sites inherit everything but the description and robots.txt from the
	 * primary site.
	 */
	public function globals(Site $site): array
	{
		$store = MeierLabsSeo::$plugin->store;
		$data = $store->get(Store::SCOPE_GLOBAL, null, $site->id);

		$primary = Craft::$app->getSites()->getPrimarySite();
		$inherited = $primary->id !== $site->id
			? $store->get(Store::SCOPE_GLOBAL, null, $primary->id)
			: [];
		$value = fn(string $key) => ($data[$key] ?? null) ?: (($inherited[$key] ?? null) ?: null);

		return [
			'siteName' => $value('siteName') ?? $this->defaultSiteName($site),
			'titlePattern' => $value('titlePattern') ?? self::DEFAULT_TITLE_PATTERN,
			'description' => ($data['description'] ?? null) ?: null,
			'imageId' => $value('imageId'),
			'robots' => $value('robots') ?? 'index, follow',
			'twitterHandle' => $value('twitterHandle'),
			'robotsTxt' => ($data['robotsTxt'] ?? null) ?: null,
		];
	}

	/** the site group name, sites are often named after their language */
	public function defaultSiteName(Site $site): string
	{
		$groupName = $site->getGroup()->getName();
		return Craft::t('site', $groupName ?: $site->getName());
	}

	public function structuredData(): array
	{
		return MeierLabsSeo::$plugin->store->get(Store::SCOPE_STRUCTURED_DATA);
	}

	/**
	 * The entry type and section overrides of an entry, the most specific
	 * first.
	 */
	public function overrides(Entry $entry): array
	{
		$store = MeierLabsSeo::$plugin->store;
		$overrides = [];

		$type = $entry->getType();
		$overrides[] = $store->get(Store::SCOPE_ENTRY_TYPE, $type->uid, $entry->siteId);

		$section = $entry->getSection();
		if ($section) {
			$overrides[] = $store->get(Store::SCOPE_SECTION, $section->uid, $entry->siteId);
		}

		return $overrides;
	}

	public function sectionSitemapEnabled(Entry $entry): bool
	{
		$section = $entry->getSection();
		if (!$section) {
			return false;
		}

		$data = MeierLabsSeo::$plugin->store->get(Store::SCOPE_SECTION, $section->uid, $entry->siteId);
		return (bool)($data['sitemap'] ?? true);
	}

	public function includeInSitemap(Entry $entry): bool
	{
		$field = $this->fieldData($entry);
		if ($field?->canonicalUrl && $field->canonicalUrl !== $entry->getUrl()) {
			return false;
		}

		$robots = $field?->robots
			?? $this->first($this->overrides($entry), 'robots')
			?? $this->globals($entry->getSite())['robots'];

		return !str_contains($robots, 'noindex');
	}

	/** the SEO field value of an entry, if its layout has an SEO field */
	public function fieldData(Entry $entry): ?SeoFieldData
	{
		$layout = $entry->getFieldLayout();
		if (!$layout) {
			return null;
		}

		foreach ($layout->getCustomFields() as $field) {
			if ($field instanceof SeoField) {
				$value = $entry->getFieldValue($field->handle);
				return $value instanceof SeoFieldData ? $value : null;
			}
		}

		return null;
	}

	/**
	 * What an entry gets without its own SEO field values, used as
	 * placeholders in the field.
	 */
	public function defaultsForEntry(Entry $entry): array
	{
		$site = $entry->getSite();
		$globals = $this->globals($site);
		$overrides = $this->overrides($entry);

		$pattern = $this->first($overrides, 'titlePattern') ?? $globals['titlePattern'];
		[$titlePrefix, $titleSuffix] = $this->titleAffixes($pattern, $entry, $globals);

		$image = $this->mappedImage($entry)
			?? $this->asset($this->first($overrides, 'imageId') ?? $globals['imageId']);

		return [
			'title' => (string)$entry->title,
			'titlePrefix' => $titlePrefix,
			'titleSuffix' => $titleSuffix,
			'description' => $this->mappedDescription($entry)
				?? $this->first($overrides, 'description')
				?? $globals['description'],
			'image' => $image,
			'robots' => $this->first($overrides, 'robots') ?? $globals['robots'],
			'canonicalUrl' => $entry->getUrl(),
			'url' => $entry->getUrl(),
			'siteName' => $globals['siteName'],
		];
	}

	public function resolveEntry(Entry $entry): SeoResult
	{
		$site = $entry->getSite();
		$globals = $this->globals($site);
		$overrides = $this->overrides($entry);
		$field = $this->fieldData($entry) ?? new SeoFieldData();

		$pattern = $this->first($overrides, 'titlePattern') ?? $globals['titlePattern'];

		$result = new SeoResult();
		$result->site = $site;
		$result->entry = $entry;
		$result->siteName = $globals['siteName'];
		$result->twitterHandle = $globals['twitterHandle'];
		$result->url = $entry->getUrl();
		$result->title = $this->renderTitle(
			$pattern,
			$field->title ?? (string)$entry->title,
			$entry,
			$globals
		);
		$result->description = $field->description
			?? $this->mappedDescription($entry)
			?? $this->first($overrides, 'description')
			?? $globals['description'];
		$result->image = $field->getImage()
			?? $this->mappedImage($entry)
			?? $this->asset($this->first($overrides, 'imageId') ?? $globals['imageId']);
		$result->socialTitle = $field->socialTitle ?? $result->title;
		$result->socialDescription = $field->socialDescription ?? $result->description;
		$result->socialImage = $field->getSocialImage() ?? $result->image;
		$result->canonical = $field->canonicalUrl ?? $entry->getUrl();
		$result->robots = $this->indexingAllowed()
			? ($field->robots ?? $this->first($overrides, 'robots') ?? $globals['robots'])
			: 'noindex, nofollow';
		$result->alternates = $this->alternates($entry);
		$result->breadcrumbs = $this->breadcrumbs($entry);
		$result->structuredData = $this->structuredData();

		return $result;
	}

	/** the SEO of a page without an entry, only the global settings */
	public function resolveSite(Site $site): SeoResult
	{
		$globals = $this->globals($site);

		$result = new SeoResult();
		$result->site = $site;
		$result->siteName = $globals['siteName'];
		$result->twitterHandle = $globals['twitterHandle'];
		$result->url = $site->getBaseUrl();
		$result->title = $globals['siteName'];
		$result->description = $globals['description'];
		$result->image = $this->asset($globals['imageId']);
		$result->socialTitle = $result->title;
		$result->socialDescription = $result->description;
		$result->socialImage = $result->image;
		$result->canonical = $site->getBaseUrl();
		$result->robots = $this->indexingAllowed() ? $globals['robots'] : 'noindex, nofollow';
		$result->structuredData = $this->structuredData();

		return $result;
	}

	/** fields of an entry type usable as description (text) or image */
	public function mappableFields(EntryType $type, string $kind): array
	{
		$options = [];
		$this->collectFields($type->getFieldLayout()->getCustomFields(), $kind, '', '', $options);
		return $options;
	}

	private function collectFields(array $fields, string $kind, string $path, string $label, array &$options): void
	{
		foreach ($fields as $field) {
			/** @var FieldInterface $field */
			$fieldPath = $path . $field->handle;
			$fieldLabel = $label . Craft::t('site', $field->name);

			if ($field instanceof ContentBlock) {
				$this->collectFields(
					$field->getFieldLayout()->getCustomFields(),
					$kind,
					$fieldPath . '.',
					$fieldLabel . ' → ',
					$options
				);
				continue;
			}

			$matches = $kind === 'image'
				? $field instanceof AssetsField
				: $field instanceof PlainText || $field::class === 'craft\ckeditor\Field';

			if ($matches) {
				$options[] = ['label' => $fieldLabel, 'value' => $fieldPath];
			}
		}
	}

	private function mappedDescription(Entry $entry): ?string
	{
		$path = $this->entryTypeData($entry)['descriptionField'] ?? null;
		if (!$path) {
			return null;
		}

		$text = $this->plainText($this->pathValue($entry, $path));
		return $text === '' ? null : $this->truncate($text, self::DESCRIPTION_LENGTH);
	}

	private function mappedImage(Entry $entry): ?Asset
	{
		$path = $this->entryTypeData($entry)['imageField'] ?? null;
		if (!$path) {
			return null;
		}

		$value = $this->pathValue($entry, $path);
		if ($value instanceof ElementQueryInterface) {
			return (clone $value)->kind('image')->one();
		}
		if ($value instanceof Collection) {
			return $value->first(fn($a) => $a instanceof Asset && $a->kind === Asset::KIND_IMAGE);
		}

		return null;
	}

	private function entryTypeData(Entry $entry): array
	{
		return MeierLabsSeo::$plugin->store->get(
			Store::SCOPE_ENTRY_TYPE,
			$entry->getType()->uid,
			$entry->siteId
		);
	}

	/** the value of a field path like `pageIntro.pageIntroCke` */
	private function pathValue(ElementInterface $element, string $path): mixed
	{
		$value = $element;
		foreach (explode('.', $path) as $handle) {
			if ($value instanceof ElementQueryInterface) {
				$value = $value->one();
			}
			if (!$value instanceof ElementInterface) {
				return null;
			}

			try {
				$value = $value->getFieldValue($handle);
			} catch (InvalidFieldException) {
				return null;
			}
		}

		return $value;
	}

	private function plainText(mixed $value): string
	{
		if ($value === null) {
			return '';
		}

		// CKEditor: only the text, not the nested entries
		if (is_object($value) && method_exists($value, 'getChunks')) {
			$parts = [];
			foreach ($value->getChunks() as $chunk) {
				if (method_exists($chunk, 'getHtml')) {
					$parts[] = $chunk->getHtml();
				}
			}
			$value = implode(' ', $parts);
		}

		$text = (string)$value;
		// keep words of block elements apart
		$text = preg_replace('/<\/(p|div|h[1-6]|li|br)>|<br\s*\/?>/i', '$0 ', $text);
		$text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

		return trim(preg_replace('/\s+/u', ' ', $text));
	}

	private function truncate(string $text, int $length): string
	{
		if (mb_strlen($text) <= $length) {
			return $text;
		}

		$cut = mb_substr($text, 0, $length - 1);
		$space = mb_strrpos($cut, ' ');
		if ($space !== false && $space > $length / 2) {
			$cut = mb_substr($cut, 0, $space);
		}

		return rtrim($cut, " ,.;:-–") . '…';
	}

	private function renderTitle(string $pattern, string $title, Entry $entry, array $globals): string
	{
		try {
			$rendered = Craft::$app->getView()->renderObjectTemplate($pattern, $entry, [
				'title' => $title,
				'siteName' => $globals['siteName'],
				'sectionName' => $entry->getSection()?->name,
			]);
		} catch (\Throwable $e) {
			Craft::warning("Invalid title pattern \"$pattern\": {$e->getMessage()}", __METHOD__);
			$rendered = $title . ' | ' . $globals['siteName'];
		}

		return trim(preg_replace('/\s+/u', ' ', $rendered));
	}

	/** what the pattern puts before and after the title */
	private function titleAffixes(string $pattern, Entry $entry, array $globals): array
	{
		$marker = '___MLSEO_TITLE___';
		$rendered = $this->renderTitle($pattern, $marker, $entry, $globals);
		$parts = explode($marker, $rendered, 2);

		return count($parts) === 2 ? $parts : ['', ''];
	}

	private function alternates(Entry $entry): array
	{
		$entries = Entry::find()
			->id($entry->id)
			->siteId('*')
			->unique(false)
			->status('live')
			->all();

		$alternates = [];
		foreach ($entries as $alt) {
			$url = $alt->getUrl();
			if (!$url) {
				continue;
			}

			$site = $alt->getSite();
			$alternates[] = ['hreflang' => $site->language, 'url' => $url];
			if ($site->primary) {
				$alternates[] = ['hreflang' => 'x-default', 'url' => $url];
			}
		}

		// only worth it if there is another language
		return count($alternates) > 2 ? $alternates : [];
	}

	private function breadcrumbs(Entry $entry): array
	{
		if ($entry->uri === Element::HOMEPAGE_URI) {
			return [];
		}

		$crumbs = [];
		$home = Entry::find()->uri(Element::HOMEPAGE_URI)->siteId($entry->siteId)->one();
		if ($home) {
			$crumbs[] = ['name' => (string)$home->title, 'url' => $home->getUrl()];
		}

		if ($entry->getSection()?->type === 'structure') {
			foreach ($entry->getAncestors()->all() as $ancestor) {
				if ($ancestor->getUrl() && $ancestor->uri !== Element::HOMEPAGE_URI) {
					$crumbs[] = ['name' => (string)$ancestor->title, 'url' => $ancestor->getUrl()];
				}
			}
		}

		$crumbs[] = ['name' => (string)$entry->title, 'url' => $entry->getUrl()];

		return $crumbs;
	}

	private function first(array $overrides, string $key): mixed
	{
		foreach ($overrides as $data) {
			$value = $data[$key] ?? null;
			if ($value !== null && $value !== '') {
				return $value;
			}
		}
		return null;
	}

	public function asset(mixed $id): ?Asset
	{
		$id = SeoFieldData::toId($id);
		return $id ? Asset::find()->id($id)->one() : null;
	}
}
