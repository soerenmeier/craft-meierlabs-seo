<?php

namespace soerenmeier\meierlabsseo\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset as CraftCpAsset;

class CpAsset extends AssetBundle
{
	public function init(): void
	{
		parent::init();

		$this->sourcePath = '@soerenmeier/meierlabsseo/web';
		$this->depends = [CraftCpAsset::class];
		$this->js = ['seo.js'];
		$this->css = ['seo.css'];
	}
}
