<?php

namespace soerenmeier\meierlabsseo\controllers;

use Craft;
use craft\web\Controller;
use soerenmeier\meierlabsseo\MeierLabsSeo;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Sitemaps and robots.txt, proxied by the crelte frontend
 * (`seoRoutes` in the `meierlabs-seo` npm package).
 */
class SitemapController extends Controller
{
	protected array|bool|int $allowAnonymous = true;

	public function actionIndex(?string $origin = null): Response
	{
		$origin = $this->origin($origin);
		return $this->send(MeierLabsSeo::$plugin->sitemap->index($origin), 'application/xml');
	}

	public function actionPage(string $site, string $section, int $page = 1): Response
	{
		$xml = MeierLabsSeo::$plugin->sitemap->page($site, $section, $page);
		if ($xml === null) {
			throw new NotFoundHttpException('Sitemap not found');
		}

		return $this->send($xml, 'application/xml');
	}

	public function actionRobots(?string $origin = null): Response
	{
		$origin = $this->origin($origin);
		return $this->send(MeierLabsSeo::$plugin->sitemap->robots($origin), 'text/plain');
	}

	private function origin(?string $origin): string
	{
		if ($origin && preg_match('#^https?://[^/?\#]+$#', rtrim($origin, '/'))) {
			return rtrim($origin, '/');
		}

		$url = Craft::$app->getSites()->getPrimarySite()->getBaseUrl() ?? '';
		$parts = parse_url($url);
		return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '')
			. (isset($parts['port']) ? ':' . $parts['port'] : '');
	}

	private function send(string $content, string $type): Response
	{
		$response = $this->response;
		$response->format = Response::FORMAT_RAW;
		$response->content = $content;
		$response->headers->set('Content-Type', "$type; charset=utf-8");
		$response->headers->set('Cache-Control', 'public, max-age=3600');
		$response->headers->remove('Pragma');
		$response->headers->remove('Expires');

		return $response;
	}
}
