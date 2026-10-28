<?php

namespace soerenmeier\meierlabsseo\controllers;

use Craft;
use craft\helpers\Cp;
use craft\models\EntryType;
use craft\models\Section;
use craft\models\Site;
use craft\web\Controller;
use soerenmeier\meierlabsseo\MeierLabsSeo;
use soerenmeier\meierlabsseo\models\SeoFieldData;
use soerenmeier\meierlabsseo\services\Store;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * The SEO section of the control panel: global settings, structured data
 * and the section and entry type overrides.
 */
class CpController extends Controller
{
	public function beforeAction($action): bool
	{
		$this->requirePermission(MeierLabsSeo::PERMISSION);
		MeierLabsSeo::$plugin->registerCpAssets();
		return parent::beforeAction($action);
	}

	public function actionIndex(): Response
	{
		return $this->redirect('meierlabs-seo/global');
	}

	public function actionGlobal(): Response
	{
		$site = $this->site();
		$seo = MeierLabsSeo::$plugin->seo;
		$store = MeierLabsSeo::$plugin->store;
		$data = $store->get(Store::SCOPE_GLOBAL, null, $site->id);

		return $this->renderTemplate('meierlabs-seo/global', [
			'site' => $site,
			'data' => $data,
			'image' => $seo->asset($data['imageId'] ?? null),
			'defaults' => $seo->globals($site),
			'isPrimary' => $site->primary,
			'robotsOptions' => $site->primary
				? $seo->robotsOptions()
				: $seo->robotsOptions(Craft::t('meierlabs-seo', 'Inherit from primary site')),
			'indexingAllowed' => $seo->indexingAllowed(),
		]);
	}

	public function actionSaveGlobal(): ?Response
	{
		$this->requirePostRequest();
		$site = $this->postedSite();
		$data = $this->request->getBodyParam('settings', []);

		MeierLabsSeo::$plugin->store->save(Store::SCOPE_GLOBAL, null, $site->id, [
			'siteName' => $this->str($data, 'siteName'),
			'titlePattern' => $this->str($data, 'titlePattern'),
			'description' => $this->str($data, 'description'),
			'imageId' => SeoFieldData::toId($data['imageId'] ?? null),
			'robots' => $this->str($data, 'robots'),
			'twitterHandle' => $this->str($data, 'twitterHandle'),
			'robotsTxt' => $this->str($data, 'robotsTxt'),
		]);

		return $this->saved(Craft::t('meierlabs-seo', 'Global SEO saved.'));
	}

	public function actionStructuredData(): Response
	{
		$seo = MeierLabsSeo::$plugin->seo;
		$data = $seo->structuredData();

		return $this->renderTemplate('meierlabs-seo/structured-data', [
			'data' => $data,
			'logo' => $seo->asset($data['logoId'] ?? null),
			'image' => $seo->asset($data['imageId'] ?? null),
			'dayOptions' => array_map(
				fn($d) => ['label' => Craft::t('meierlabs-seo', $d), 'value' => $d],
				['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
			),
			'localTypes' => $seo->localBusinessTypes(),
			'typeOptions' => array_map(
				fn($t) => ['label' => $t, 'value' => $t],
				$seo->organizationTypes()
			),
		]);
	}

	public function actionSaveStructuredData(): ?Response
	{
		$this->requirePostRequest();
		$data = $this->request->getBodyParam('settings', []);

		$sameAs = [];
		foreach ((array)($data['sameAs'] ?? []) as $row) {
			$url = trim($row['url'] ?? '');
			if ($url !== '') {
				$sameAs[] = ['url' => $url];
			}
		}

		$openingHours = [];
		foreach ((array)($data['openingHours'] ?? []) as $row) {
			$row = array_map(fn($v) => trim((string)$v), $row);
			if (($row['day'] ?? '') !== '' && ($row['opens'] ?? '') !== '' && ($row['closes'] ?? '') !== '') {
				$openingHours[] = ['day' => $row['day'], 'opens' => $row['opens'], 'closes' => $row['closes']];
			}
		}

		$values = [
			'logoId' => SeoFieldData::toId($data['logoId'] ?? null),
			'imageId' => SeoFieldData::toId($data['imageId'] ?? null),
			'sameAs' => $sameAs,
			'openingHours' => $openingHours,
		];
		foreach (['type', 'name', 'alternateName', 'legalName', 'description', 'url', 'email', 'latitude', 'longitude', 'priceRange', 'telephone', 'street', 'postalCode', 'locality', 'region', 'country', 'creatorType', 'creatorName', 'creatorUrl'] as $key) {
			$values[$key] = $this->str($data, $key);
		}

		MeierLabsSeo::$plugin->store->save(Store::SCOPE_STRUCTURED_DATA, null, null, $values);

		return $this->saved(Craft::t('meierlabs-seo', 'Structured data saved.'));
	}

	public function actionSections(): Response
	{
		$site = $this->site();
		$store = MeierLabsSeo::$plugin->store;

		$rows = [];
		foreach ($this->sections($site) as $section) {
			$rows[] = [
				'section' => $section,
				'data' => $store->get(Store::SCOPE_SECTION, $section->uid, $site->id),
			];
		}

		return $this->renderTemplate('meierlabs-seo/sections/index', [
			'site' => $site,
			'rows' => $rows,
		]);
	}

	public function actionEditSection(string $handle): Response
	{
		$site = $this->site();
		$section = $this->section($handle);
		$seo = MeierLabsSeo::$plugin->seo;
		$data = MeierLabsSeo::$plugin->store->get(Store::SCOPE_SECTION, $section->uid, $site->id);

		return $this->renderTemplate('meierlabs-seo/sections/_edit', [
			'site' => $site,
			'section' => $section,
			'data' => $data,
			'image' => $seo->asset($data['imageId'] ?? null),
			'globals' => $seo->globals($site),
			'robotsOptions' => $seo->robotsOptions(Craft::t('meierlabs-seo', 'Inherit from global')),
		]);
	}

	public function actionSaveSection(): ?Response
	{
		$this->requirePostRequest();
		$site = $this->postedSite();
		$section = $this->section($this->request->getRequiredBodyParam('section'));
		$data = $this->request->getBodyParam('settings', []);

		MeierLabsSeo::$plugin->store->save(Store::SCOPE_SECTION, $section->uid, $site->id, [
			'sitemap' => (bool)($data['sitemap'] ?? true),
			'titlePattern' => $this->str($data, 'titlePattern'),
			'description' => $this->str($data, 'description'),
			'imageId' => SeoFieldData::toId($data['imageId'] ?? null),
			'robots' => $this->str($data, 'robots'),
		]);

		return $this->saved(Craft::t('meierlabs-seo', 'Section SEO saved.'));
	}

	public function actionEntryTypes(): Response
	{
		$site = $this->site();
		$store = MeierLabsSeo::$plugin->store;

		$rows = [];
		foreach ($this->entryTypes($site) as [$type, $sections]) {
			$rows[] = [
				'type' => $type,
				'sections' => $sections,
				'data' => $store->get(Store::SCOPE_ENTRY_TYPE, $type->uid, $site->id),
			];
		}

		return $this->renderTemplate('meierlabs-seo/entry-types/index', [
			'site' => $site,
			'rows' => $rows,
		]);
	}

	public function actionEditEntryType(string $handle): Response
	{
		$site = $this->site();
		$type = $this->entryType($handle);
		$seo = MeierLabsSeo::$plugin->seo;
		$data = MeierLabsSeo::$plugin->store->get(Store::SCOPE_ENTRY_TYPE, $type->uid, $site->id);

		return $this->renderTemplate('meierlabs-seo/entry-types/_edit', [
			'site' => $site,
			'type' => $type,
			'data' => $data,
			'image' => $seo->asset($data['imageId'] ?? null),
			'globals' => $seo->globals($site),
			'robotsOptions' => $seo->robotsOptions(Craft::t('meierlabs-seo', 'Inherit from section')),
			'textFields' => $seo->mappableFields($type, 'text'),
			'imageFields' => $seo->mappableFields($type, 'image'),
		]);
	}

	public function actionSaveEntryType(): ?Response
	{
		$this->requirePostRequest();
		$site = $this->postedSite();
		$type = $this->entryType($this->request->getRequiredBodyParam('entryType'));
		$data = $this->request->getBodyParam('settings', []);

		MeierLabsSeo::$plugin->store->save(Store::SCOPE_ENTRY_TYPE, $type->uid, $site->id, [
			'titlePattern' => $this->str($data, 'titlePattern'),
			'description' => $this->str($data, 'description'),
			'imageId' => SeoFieldData::toId($data['imageId'] ?? null),
			'robots' => $this->str($data, 'robots'),
			'descriptionField' => $this->str($data, 'descriptionField'),
			'imageField' => $this->str($data, 'imageField'),
		]);

		return $this->saved(Craft::t('meierlabs-seo', 'Entry type SEO saved.'));
	}

	/** sections with urls in the site */
	private function sections(Site $site): array
	{
		return array_values(array_filter(
			Craft::$app->getEntries()->getAllSections(),
			fn(Section $section) => ($section->getSiteSettings()[$site->id] ?? null)?->hasUrls
		));
	}

	/** entry types of sections with urls, with their sections */
	private function entryTypes(Site $site): array
	{
		$types = [];
		foreach ($this->sections($site) as $section) {
			foreach ($section->getEntryTypes() as $type) {
				$types[$type->id] ??= [$type, []];
				$types[$type->id][1][] = $section;
			}
		}

		uasort($types, fn($a, $b) => strcasecmp($a[0]->name, $b[0]->name));
		return array_values($types);
	}

	private function section(string $handle): Section
	{
		$section = Craft::$app->getEntries()->getSectionByHandle($handle);
		if (!$section) {
			throw new NotFoundHttpException('Section not found');
		}
		return $section;
	}

	private function entryType(string $handle): EntryType
	{
		$type = Craft::$app->getEntries()->getEntryTypeByHandle($handle);
		if (!$type) {
			throw new NotFoundHttpException('Entry type not found');
		}
		return $type;
	}

	private function site(): Site
	{
		return Cp::requestedSite() ?? Craft::$app->getSites()->getPrimarySite();
	}

	private function postedSite(): Site
	{
		$site = Craft::$app->getSites()->getSiteById(
			(int)$this->request->getRequiredBodyParam('siteId')
		);
		if (!$site) {
			throw new NotFoundHttpException('Site not found');
		}
		return $site;
	}

	private function saved(string $message): ?Response
	{
		MeierLabsSeo::$plugin->invalidateCaches();
		return $this->asSuccess($message);
	}

	private function str(array $data, string $key): ?string
	{
		$value = trim((string)($data[$key] ?? ''));
		return $value === '' ? null : $value;
	}
}
