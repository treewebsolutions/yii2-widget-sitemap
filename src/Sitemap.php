<?php

namespace tws\widgets\sitemap;

use yii\base\InvalidConfigException;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use tws\helpers\Url;
use Yii;

/**
 * Class Sitemap
 *
 * @link https://www.sitemaps.org/protocol.html
 *
 * @author Tree Web Solutions <treewebsolutions.com@gmail.com>
 */
class Sitemap extends \yii\base\Widget
{
	const CHANGEFREQ_ALWAYS = 'always';
	const CHANGEFREQ_HOURLY = 'hourly';
	const CHANGEFREQ_DAILY = 'daily';
	const CHANGEFREQ_WEEKLY = 'weekly';
	const CHANGEFREQ_MONTHLY = 'monthly';
	const CHANGEFREQ_YEARLY = 'yearly';
	const CHANGEFREQ_NEVER = 'never';

	/**
	 * @var array The items.
	 */
	public $items = [];

	/**
	 * @var array The URLs array.
	 */
	public $urls = [];

	/**
	 * @var Dom The xml document.
	 */
	protected $dom;


	/**
	 * @inheritdoc
	 * @throws InvalidConfigException
	 */
	public function init()
	{
		parent::init();

		if (!is_array($this->items)) {
			throw new InvalidConfigException('The "items" property must be set to a non-empty array.');
		}
		if (!is_array($this->urls)) {
			throw new InvalidConfigException('The "urls" property must be set to a non-empty array.');
		}

		$this->dom = new Dom();
	}

	/**
	 * @inheritdoc
	 * @throws \Exception
	 */
	public function run()
	{
		$this->renderItems();
		$this->renderUrls();

		return $this->dom->saveXML();
	}

	/**
	 * Formats a value to a specific date format.
	 *
	 * @param string|int $value
	 * @param string $format
	 * @return string|null
	 */
	protected static function formatDate($value, $format = DATE_W3C)
	{
		try {
			return (new \DateTime($value))->format($format);
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * Creates url elements from an AR model.
	 *
	 * @param array $item
	 * @param \yii\db\ActiveRecord $model
	 * @return array
	 */
	protected function createItemUrlElements($item, $model)
	{
		$urlElements = [];
		$baseElements = [
			'changefreq' => $item['options']['changefreq'],
			'priority' => $item['options']['priority'],
			'lastmod' => $item['options']['lastmod'] ? self::formatDate($model->{$item['options']['lastmod']}) : null,
		];

		if (empty($item['i18n'])) {
			$route = [];
			if (is_callable($item['route'])) {
				$route = (array) call_user_func_array($item['route'], [$item, $model]);
				$route[0] = '/' . trim($route[0], '/');
			}
			$urlElements[] = array_merge([
				'loc' => Url::to($route, true),
			], $baseElements);
		} elseif (!empty($item['i18n']['relation'])) {
			$modelTranslations = ArrayHelper::index($model->{$item['i18n']['relation']}, 'language_id');
			$translationLanguages = ArrayHelper::getColumn($modelTranslations, 'language_id');
			$routeCallback = $item['i18n']['route'] ?: $item['route'];

			foreach ($modelTranslations as $modelTranslation) {
				$route = [];
				if (is_callable($routeCallback)) {
					$route = (array) call_user_func_array($routeCallback, [$item, $model, $modelTranslation]);
					$route[0] = '/' . trim($route[0], '/');
				}
				$route['language'] = ($modelTranslation['language_id'] == Yii::$app->language ? $modelTranslation['language_id'] : mb_substr($modelTranslation['language_id'], 0, 2));

				// Prepare the <url> elements
				$elements = array_merge([
					'loc' => Url::to($route, true),
				], $baseElements);

				// Prepare the <url> alternate elements
				if (!empty($translationLanguages)) {
					foreach ($translationLanguages as $language_id) {
						$route = [];
						if (is_callable($routeCallback)) {
							$route = (array) call_user_func_array($routeCallback, [$item, $model, $modelTranslations[$language_id]]);
							$route[0] = '/' . trim($route[0], '/');
						}
						$route['language'] = ($language_id == Yii::$app->language ? $language_id : mb_substr($language_id, 0, 2));

						// Push an alternate link element to the <url> elements array
						$elements[] = [
							'elementName' => 'xhtml:link',
							'rel' => 'alternate',
							'hreflang' => $language_id,
							'href' => Url::to($route, true),
						];
					}
				}

				$urlElements[] = $elements;
			}
		}

		return $urlElements;
	}

	/**
	 * Renders <url> tags for all items.
	 *
	 * @throws \Exception
	 */
	protected function renderItems()
	{
		foreach ($this->items as $item) {
			if (empty($item['dataProvider'])) {
				continue;
			}
			/** @var ActiveDataProvider $dataProvider */
			$dataProvider = $item['dataProvider'];
			/** @var ActiveQuery $query */
			$query = $dataProvider->query;

			if (!empty($item['i18n']['relation'])) {
				$query->with($item['i18n']['relation']);
			}

			foreach ($dataProvider->getModels() as $model) {
				$this->dom->addUrls($this->createItemUrlElements($item, $model));
			}
		}
	}

	/**
	 * Renders <url> tags for all urls.
	 *
	 * @throws \Exception
	 */
	protected function renderUrls()
	{
		foreach ($this->urls as $url) {
			$this->dom->addUrl([
				'loc' => is_callable($url['route']) ? call_user_func_array($url['route'], [$url]) : Url::to($url['route'], true),
				'changefreq' => $url['options']['changefreq'],
				'priority' => $url['options']['priority'],
				'lastmod' => self::formatDate($url['options']['lastmod']),
			]);
		}
	}
}