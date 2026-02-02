<?php

namespace tws\widgets\sitemap;

class Dom extends \DOMDocument
{
	/**
	 * @var \DOMNode
	 */
	protected $urlSet;

	/**
	 * @inheritdoc
	 */
	public function __construct()
	{
		parent::__construct();

		$this->formatOutput = true;
		$this->encoding = 'utf-8';

		// Create <urlset> element
		$urlset = $this->createElement('urlset');
		$urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
		$urlset->setAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');

		// Append <urlset> to the DOM element
		$this->urlSet = $this->appendChild($urlset);
	}

	/**
	 * Adds an url element.
	 *
	 * @param array $items
	 * @return $this
	 */
	public function addUrl($items = [])
	{
		// Create <url>
		$url = $this->createElement('url');

		foreach ($items as $key => $value) {
			if (is_array($value)) {
				// Create the child element
				$element = $this->createElement($value['elementName']);
				// Remove the elementName from the attributes list
				unset($value['elementName']);
				// Add element attributes
				foreach ($value as $attributeName => $attributeValue) {
					$element->setAttribute($attributeName, $attributeValue);
				}
			} else {
				if (empty($value)) {
					continue;
				}
				// Create the child element
				$element = $this->createElement($key);
				// Add a text node as a child element
				$element->appendChild($this->createTextNode($value));
			}
			// Append element to <url>
			$url->appendChild($element);
		}
		// Append <url> to the <urlset>
		$this->urlSet->appendChild($url);

		return $this;
	}

	/**
	 * Adds multiple url elements.
	 *
	 * @param array $urls
	 * @return $this
	 */
	public function addUrls($urls = [])
	{
		foreach ($urls as $url) {
			$this->addUrl($url);
		}

		return $this;
	}
}
