<?php

namespace soerenmeier\meierlabsseo\gql;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use soerenmeier\meierlabsseo\models\SeoResult;

/**
 * The GraphQL types of a resolved SEO (`entry { seo { ... } }`).
 */
class SeoType
{
	public static function getType(): ObjectType
	{
		return GqlEntityRegistry::getOrCreate('MeierLabsSeo', fn() => new ObjectType([
			'name' => 'MeierLabsSeo',
			'description' => 'The resolved SEO of a page.',
			'fields' => fn() => [
				'title' => Type::string(),
				'description' => Type::string(),
				'canonical' => Type::string(),
				'robots' => Type::string(),
				'siteName' => Type::string(),
				'locale' => [
					'type' => Type::string(),
					'resolve' => fn(SeoResult $seo) => $seo->getLocale(),
				],
				'image' => [
					'type' => self::imageType(),
					'description' => 'The image, cropped to the social image size.',
					'resolve' => fn(SeoResult $seo) => $seo->getImageData(),
				],
				'socialTitle' => Type::string(),
				'socialDescription' => Type::string(),
				'socialImage' => [
					'type' => self::imageType(),
					'resolve' => fn(SeoResult $seo) => $seo->getSocialImageData(),
				],
				'alternates' => [
					'type' => Type::listOf(self::alternateType()),
					'resolve' => fn(SeoResult $seo) => $seo->alternates,
				],
				'meta' => [
					'type' => Type::listOf(self::metaType()),
					'description' => 'All meta tags for the head.',
					'resolve' => fn(SeoResult $seo) => $seo->getMeta(),
				],
				'links' => [
					'type' => Type::listOf(self::linkType()),
					'description' => 'Canonical and alternate links for the head.',
					'resolve' => fn(SeoResult $seo) => $seo->getLinks(),
				],
				'jsonLd' => [
					'type' => Type::string(),
					'description' => 'The structured data as JSON-LD.',
					'resolve' => fn(SeoResult $seo) => $seo->getJsonLdString(),
				],
			],
		]));
	}

	/**
	 * A JSON value, returned as a nested object instead of a string, so
	 * neither Craft nor the frontend needs to encode and parse it.
	 */
	public static function jsonType(): CustomScalarType
	{
		return GqlEntityRegistry::getOrCreate('MeierLabsSeoJson', fn() => new CustomScalarType([
			'name' => 'MeierLabsSeoJson',
			'description' => 'A JSON value (Meier Labs SEO).',
			'serialize' => fn($value) => $value,
			'parseValue' => fn($value) => $value,
			'parseLiteral' => fn($node) => null,
		]));
	}

	private static function imageType(): ObjectType
	{
		return GqlEntityRegistry::getOrCreate('MeierLabsSeoImage', fn() => new ObjectType([
			'name' => 'MeierLabsSeoImage',
			'fields' => [
				'url' => Type::string(),
				'width' => Type::int(),
				'height' => Type::int(),
				'alt' => Type::string(),
				'mimeType' => Type::string(),
			],
		]));
	}

	private static function alternateType(): ObjectType
	{
		return GqlEntityRegistry::getOrCreate('MeierLabsSeoAlternate', fn() => new ObjectType([
			'name' => 'MeierLabsSeoAlternate',
			'fields' => [
				'hreflang' => Type::string(),
				'url' => Type::string(),
			],
		]));
	}

	private static function metaType(): ObjectType
	{
		return GqlEntityRegistry::getOrCreate('MeierLabsSeoMeta', fn() => new ObjectType([
			'name' => 'MeierLabsSeoMeta',
			'fields' => [
				'name' => Type::string(),
				'property' => Type::string(),
				'content' => Type::string(),
			],
		]));
	}

	private static function linkType(): ObjectType
	{
		return GqlEntityRegistry::getOrCreate('MeierLabsSeoLink', fn() => new ObjectType([
			'name' => 'MeierLabsSeoLink',
			'fields' => [
				'rel' => Type::string(),
				'href' => Type::string(),
				'hreflang' => Type::string(),
			],
		]));
	}
}
