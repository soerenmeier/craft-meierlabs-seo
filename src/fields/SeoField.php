<?php

namespace soerenmeier\meierlabsseo\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\Entry;
use craft\helpers\Json;
use craft\models\GqlSchema;
use soerenmeier\meierlabsseo\MeierLabsSeo;
use soerenmeier\meierlabsseo\models\SeoFieldData;
use yii\db\Schema;

/**
 * Overrides the SEO of a single entry. The resolved SEO is queried with the
 * `seo` field every entry has in GraphQL, so this field itself is not part of
 * the schema.
 */
class SeoField extends Field
{
	public static function displayName(): string
	{
		return 'SEO';
	}

	public static function icon(): string
	{
		return 'magnifying-glass';
	}

	public static function dbType(): string
	{
		return Schema::TYPE_JSON;
	}

	public static function phpType(): string
	{
		return SeoFieldData::class;
	}

	public function normalizeValue(mixed $value, ?ElementInterface $element): mixed
	{
		if ($value instanceof SeoFieldData) {
			return $value;
		}

		if (is_string($value)) {
			$value = Json::decodeIfJson($value);
		}

		$data = new SeoFieldData();
		if (is_array($value)) {
			$data->setAttributes($value, false);
		}

		return $data;
	}

	public function serializeValue(mixed $value, ?ElementInterface $element): mixed
	{
		if (!$value instanceof SeoFieldData || $value->isEmpty()) {
			return null;
		}

		return array_filter($value->toArray(), fn($v) => $v !== null);
	}

	public function isValueEmpty(mixed $value, ElementInterface $element): bool
	{
		return !$value instanceof SeoFieldData || $value->isEmpty();
	}

	public function getElementValidationRules(): array
	{
		return [
			[
				function (ElementInterface $element) {
					$value = $element->getFieldValue($this->handle);
					if ($value instanceof SeoFieldData && !$value->validate()) {
						foreach ($value->getFirstErrors() as $error) {
							$element->addError("field:$this->handle", $error);
						}
					}
				},
			],
		];
	}

	public function includeInGqlSchema(GqlSchema $schema): bool
	{
		return false;
	}

	protected function searchKeywords(mixed $value, ElementInterface $element): string
	{
		if (!$value instanceof SeoFieldData) {
			return '';
		}

		return implode(' ', array_filter([$value->title, $value->description]));
	}

	protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
	{
		$plugin = MeierLabsSeo::$plugin;
		$defaults = $element instanceof Entry
			? $plugin->seo->defaultsForEntry($element)
			: null;

		$view = Craft::$app->getView();
		$plugin->registerCpAssets();

		return $view->renderTemplate('meierlabs-seo/_field/input', [
			'field' => $this,
			'name' => $this->handle,
			'value' => $value,
			'element' => $element,
			'defaults' => $defaults,
			'robotsOptions' => $plugin->seo->robotsOptions(Craft::t('meierlabs-seo', 'Inherit')),
		]);
	}
}
