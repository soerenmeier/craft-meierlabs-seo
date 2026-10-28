<?php

namespace soerenmeier\meierlabsseo;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\events\DefineGqlTypeFieldsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\gql\TypeManager;
use craft\helpers\UrlHelper;
use craft\services\Fields;
use craft\services\Gql;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use GraphQL\Type\Definition\Type;
use soerenmeier\meierlabsseo\assetbundles\CpAsset;
use soerenmeier\meierlabsseo\fields\SeoField;
use soerenmeier\meierlabsseo\gql\SeoType;
use soerenmeier\meierlabsseo\models\Settings;
use soerenmeier\meierlabsseo\services\Seo;
use soerenmeier\meierlabsseo\services\Sitemap;
use soerenmeier\meierlabsseo\services\Store;
use yii\base\Event;

/**
 * @property-read Store $store
 * @property-read Seo $seo
 * @property-read Sitemap $sitemap
 * @method Settings getSettings()
 */
class MeierLabsSeo extends Plugin
{
	public const PERMISSION = 'meierlabs-seo:edit';

	public static $plugin;

	public string $schemaVersion = '1.0.0';
	public bool $hasCpSettings = true;
	public bool $hasCpSection = true;

	public function init(): void
	{
		parent::init();
		self::$plugin = $this;

		$this->setComponents([
			'store' => Store::class,
			'seo' => Seo::class,
			'sitemap' => Sitemap::class,
		]);

		Event::on(
			Fields::class,
			Fields::EVENT_REGISTER_FIELD_TYPES,
			function (RegisterComponentTypesEvent $event) {
				$event->types[] = SeoField::class;
			}
		);

		Craft::$app->onInit($this->attachEventHandlers(...));
	}

	private function attachEventHandlers(): void
	{
		Event::on(
			UrlManager::class,
			UrlManager::EVENT_REGISTER_CP_URL_RULES,
			function (RegisterUrlRulesEvent $event) {
				$event->rules += [
					'meierlabs-seo' => 'meierlabs-seo/cp/index',
					'meierlabs-seo/global' => 'meierlabs-seo/cp/global',
					'meierlabs-seo/structured-data' => 'meierlabs-seo/cp/structured-data',
					'meierlabs-seo/sections' => 'meierlabs-seo/cp/sections',
					'meierlabs-seo/sections/<handle:{handle}>' => 'meierlabs-seo/cp/edit-section',
					'meierlabs-seo/entry-types' => 'meierlabs-seo/cp/entry-types',
					'meierlabs-seo/entry-types/<handle:{handle}>' => 'meierlabs-seo/cp/edit-entry-type',
				];
			}
		);

		Event::on(
			UserPermissions::class,
			UserPermissions::EVENT_REGISTER_PERMISSIONS,
			function (RegisterUserPermissionsEvent $event) {
				$event->permissions[] = [
					'heading' => 'Meier Labs SEO',
					'permissions' => [
						self::PERMISSION => ['label' => 'Edit the SEO settings'],
					],
				];
			}
		);

		// every entry gets a `seo` field with the resolved SEO
		Event::on(
			TypeManager::class,
			TypeManager::EVENT_DEFINE_GQL_TYPE_FIELDS,
			function (DefineGqlTypeFieldsEvent $event) {
				// also on the entry types, so a leftover content field
				// called `seo` (e.g. from SEOmatic) doesn't hide it
				if ($event->typeName !== 'EntryInterface' && !str_ends_with($event->typeName, '_Entry')) {
					return;
				}

				$event->fields['seo'] = [
					'name' => 'seo',
					'type' => SeoType::getType(),
					'description' => 'The resolved SEO of the entry (Meier Labs SEO).',
					'resolve' => fn($source) => $source instanceof Entry
						? $this->seo->resolveEntry($source)
						: null,
				];
			}
		);

		// the SEO of pages without an entry
		Event::on(
			Gql::class,
			Gql::EVENT_REGISTER_GQL_QUERIES,
			function (RegisterGqlQueriesEvent $event) {
				$event->queries['seo'] = [
					'type' => SeoType::getType(),
					'args' => [
						'siteId' => Type::int(),
						'site' => Type::string(),
					],
					'description' => 'The global SEO of a site, for pages without an entry.',
					'resolve' => function ($root, array $args) {
						$sites = Craft::$app->getSites();
						$site = match (true) {
							isset($args['siteId']) => $sites->getSiteById($args['siteId']),
							isset($args['site']) => $sites->getSiteByHandle($args['site']),
							default => $sites->getPrimarySite(),
						};

						return $site ? $this->seo->resolveSite($site) : null;
					},
				];
			}
		);
	}

	public function getCpNavItem(): ?array
	{
		$user = Craft::$app->getUser();
		if (!$user->checkPermission(self::PERMISSION)) {
			return null;
		}

		$item = parent::getCpNavItem();
		$item['label'] = 'SEO';
		$item['subnav'] = [
			'global' => ['label' => 'Global', 'url' => 'meierlabs-seo/global'],
			'sections' => ['label' => 'Sections', 'url' => 'meierlabs-seo/sections'],
			'entry-types' => ['label' => 'Entry Types', 'url' => 'meierlabs-seo/entry-types'],
			'structured-data' => ['label' => 'Structured Data', 'url' => 'meierlabs-seo/structured-data'],
		];

		return $item;
	}

	public function getSettingsResponse(): mixed
	{
		return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('meierlabs-seo/global'));
	}

	/** after the settings changed */
	public function invalidateCaches(): void
	{
		$this->sitemap->invalidate();
		Craft::$app->getGql()->invalidateCaches();
	}

	public function registerCpAssets(): void
	{
		Craft::$app->getView()->registerAssetBundle(CpAsset::class);
	}

	protected function createSettingsModel(): ?Model
	{
		return new Settings();
	}
}
