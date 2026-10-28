<?php

namespace soerenmeier\meierlabsseo\models;

use Craft;
use craft\base\Model;
use craft\elements\Asset;

/**
 * The value of an SEO field: overrides for a single entry, empty values
 * fall back to the entry type, section and global settings.
 */
class SeoFieldData extends Model
{
	public ?string $title = null;
	public ?string $description = null;
	public ?int $imageId = null;
	public ?string $socialTitle = null;
	public ?string $socialDescription = null;
	public ?int $socialImageId = null;
	public ?string $robots = null;
	public ?string $canonicalUrl = null;

	public function setAttributes($values, $safeOnly = true): void
	{
		foreach (['imageId', 'socialImageId'] as $key) {
			if (isset($values[$key])) {
				$values[$key] = self::toId($values[$key]);
			}
		}

		foreach ($values as $key => $value) {
			if (is_string($value)) {
				$value = trim($value);
				$values[$key] = $value === '' ? null : $value;
			}
		}

		parent::setAttributes($values, $safeOnly);
	}

	public function defineRules(): array
	{
		return [
			[['title', 'description', 'socialTitle', 'socialDescription', 'robots'], 'string'],
			[['canonicalUrl'], 'url'],
			[['imageId', 'socialImageId'], 'integer'],
		];
	}

	public function getImage(): ?Asset
	{
		return $this->imageId ? Asset::find()->id($this->imageId)->one() : null;
	}

	public function getSocialImage(): ?Asset
	{
		return $this->socialImageId
			? Asset::find()->id($this->socialImageId)->one()
			: null;
	}

	public function isEmpty(): bool
	{
		return empty(array_filter($this->toArray(), fn($v) => $v !== null));
	}

	/** element select inputs post an array of ids */
	public static function toId(mixed $value): ?int
	{
		if (is_array($value)) {
			$value = reset($value);
		}
		return is_numeric($value) ? (int)$value : null;
	}
}
