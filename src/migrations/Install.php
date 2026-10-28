<?php

namespace soerenmeier\meierlabsseo\migrations;

use craft\db\Migration;
use craft\db\Table;

class Install extends Migration
{
	public const TABLE = '{{%meierlabs_seo_settings}}';

	public function safeUp(): bool
	{
		// one row per scope (global, section, entryType, structuredData) and
		// site; structuredData is shared by all sites (siteId null)
		$this->createTable(self::TABLE, [
			'id' => $this->primaryKey(),
			'siteId' => $this->integer(),
			'scope' => $this->string(32)->notNull(),
			'scopeUid' => $this->uid()->null(),
			'data' => $this->json(),
			'dateCreated' => $this->dateTime()->notNull(),
			'dateUpdated' => $this->dateTime()->notNull(),
			'uid' => $this->uid(),
		]);

		$this->createIndex(null, self::TABLE, ['scope', 'scopeUid', 'siteId']);
		$this->addForeignKey(
			null,
			self::TABLE,
			['siteId'],
			Table::SITES,
			['id'],
			'CASCADE',
			null
		);

		return true;
	}

	public function safeDown(): bool
	{
		$this->dropTableIfExists(self::TABLE);
		return true;
	}
}
