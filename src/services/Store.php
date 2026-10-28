<?php

namespace soerenmeier\meierlabsseo\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use soerenmeier\meierlabsseo\migrations\Install;

/**
 * Stores the SEO settings in the database instead of the project config:
 * descriptions and images are content and need to be editable on
 * production where admin changes are disabled.
 */
class Store extends Component
{
	public const SCOPE_GLOBAL = 'global';
	public const SCOPE_SECTION = 'section';
	public const SCOPE_ENTRY_TYPE = 'entryType';
	public const SCOPE_STRUCTURED_DATA = 'structuredData';

	private array $_cache = [];

	public function get(
		string $scope,
		?string $scopeUid = null,
		?int $siteId = null
	): array {
		$key = "$scope:$scopeUid:$siteId";
		if (array_key_exists($key, $this->_cache)) {
			return $this->_cache[$key];
		}

		$data = $this->_query($scope, $scopeUid, $siteId)->select(['data'])->scalar();
		$data = $data ? Json::decodeIfJson($data) : [];

		return $this->_cache[$key] = is_array($data) ? $data : [];
	}

	public function save(
		string $scope,
		?string $scopeUid,
		?int $siteId,
		array $data
	): void {
		$db = Craft::$app->getDb();
		$now = Db::prepareDateForDb(new \DateTime());
		$id = $this->_query($scope, $scopeUid, $siteId)->select(['id'])->scalar();

		if ($id) {
			$db->createCommand()
				->update(Install::TABLE, [
					'data' => $data,
					'dateUpdated' => $now,
				], ['id' => $id])
				->execute();
		} else {
			$db->createCommand()
				->insert(Install::TABLE, [
					'siteId' => $siteId,
					'scope' => $scope,
					'scopeUid' => $scopeUid,
					'data' => $data,
					'dateCreated' => $now,
					'dateUpdated' => $now,
					'uid' => StringHelper::UUID(),
				])
				->execute();
		}

		$this->_cache = [];
	}

	private function _query(string $scope, ?string $scopeUid, ?int $siteId): Query
	{
		return (new Query())
			->from(Install::TABLE)
			->where([
				'scope' => $scope,
				'scopeUid' => $scopeUid,
				'siteId' => $siteId,
			]);
	}
}
