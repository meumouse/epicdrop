<?php
/**
 * Falabella data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class FalabellaParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $JSON_DATA_SELECTOR = '//script[@type="application/ld+json"]';
	private $REVIEW_SELECTOR = '//li[contains(@class, "bv-content-review")]';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content, $url) {
		$this->content = preg_replace('/\s+/', ' ', $content);
		$this->url = $url;
		$this->dom = $this->getDomObj($content);
		$this->xpath = new \DomXPath($this->dom);

		// Set json data array
		$this->setJsonData();
	}

	private function getDomObj( $content) {
		$dom = new \DomDocument('1.0', 'UTF-8');
		libxml_use_internal_errors(true);
		$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
		libxml_use_internal_errors(false);

		return $dom;
	}

	private function setJsonData() {
		$jsons = $this->getValue($this->JSON_DATA_SELECTOR);
		if ($jsons) {
			foreach ($jsons as $json) {
				$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
				$this->jsonDataArray = array_merge($this->jsonDataArray, json_decode($json, true));
			}
		}
		$json = $this->getJson($this->content, 'DATA__" type="application/json">', '</script>');
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = array_merge($this->jsonDataArray, json_decode($json, true));
		}
	}

	private function getValue( $selector, $html = false, $xpath = null) {
		if (empty($selector)) {
			return array();
		}
		if ($xpath) {
			$itmes = $xpath->query($selector);
		} else {
			$itmes = $this->xpath->query($selector);
		}
		$response = array();
		foreach ($itmes as $itme) {
			if ($html) {
				$element = $this->dom->saveHTML($itme);
			} else {
				$element = $itme->nodeValue;
			}
			$response[] = trim($element);
		}
		return $response;
	}

	public function getTitle() {
		if (isset($this->jsonDataArray['name'])) {
			return $this->jsonDataArray['name'];
		}
		return '';
	}

	public function getCategories() {
		$categories = array();
		if (isset($this->jsonDataArray['itemListElement']) && $this->jsonDataArray['itemListElement']) {
			foreach ($this->jsonDataArray['itemListElement'] as $categary) {
				$categories[] = $categary['name'];
			}
		}
		return $categories;
	}

	public function getShortDescription() {
		if (isset($this->jsonDataArray['description'])) {
			return $this->jsonDataArray['description'];
		}
		return '';
	}

	public function getDescription() {
		$description = '';

		if (isset($this->jsonDataArray['props']['pageProps']['openGraphData']['description'])) {
			$description .= $this->jsonDataArray['props']['pageProps']['openGraphData']['description'];
		}
		if (isset($this->jsonDataArray['props']['pageProps']['productData']['description'])) {
			$description .= $this->jsonDataArray['props']['pageProps']['productData']['description'];
		}
		if (isset($this->jsonDataArray['props']['pageProps']['productData']['longDescription'])) {
			$description .= $this->jsonDataArray['props']['pageProps']['productData']['longDescription'];
		}
		return $description;
	}

	public function getPrice() {
		$price = 0;
		if (isset($this->jsonDataArray['offers']['price'])) {
			$price = $this->jsonDataArray['offers']['price'];
		}
		return preg_replace('/[^0-9.]/', '', $price);
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['sku'])) {
			return $this->jsonDataArray['sku'];
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['brand']['name'])) {
			return $this->jsonDataArray['brand']['name'];
		}
		return '';
	}

	public function getCoverImage() {
		if (isset($this->jsonDataArray['props']['pageProps']['openGraphData']['images'][0]['url'])) {
			return $this->jsonDataArray['props']['pageProps']['openGraphData']['images'][0]['url'];
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}

		if (isset($this->jsonDataArray['props']['pageProps']['productData']['variants'])
			&& $this->jsonDataArray['props']['pageProps']['productData']['variants']) {
			foreach ($this->jsonDataArray['props']['pageProps']['productData']['variants'] as $key => $imgsLink) {
				foreach ($imgsLink['medias'] as $image) {
					$images[$key][] = $image['url'] . '?wid=1500&qlt=100';
				}
			}
		}

		if (!$images) {
			$cover = $this->getCoverImage();
			if ($cover) {
				$images[0][] = $cover;
			}
		}
		foreach ($images as &$imgs) {
			$imgs = array_unique($imgs, SORT_STRING);
		}
		return $images;
	}

	public function getFeatures() {
		static $featureGroups = array();

		if ($featureGroups) {
			return $featureGroups;
		}
		if (isset($this->jsonDataArray['props']['pageProps']['productData']['attributes']['specifications'])
			&& $this->jsonDataArray['props']['pageProps']['productData']['attributes']['specifications']) {
			foreach ($this->jsonDataArray['props']['pageProps']['productData']['attributes']['specifications'] as $features) {
				if (isset($features['name'])) {
					$attributes[] = array(
						'name' => $features['name'],
						'value' => preg_replace('/\s+/', ' ', $features['value'])
					);
				}
			}
			if ($attributes) {
				$featureGroups[] = array(
					'name' => 'Especificaciones',
					'attributes' => $attributes
				);
			}
		}
		return $featureGroups;
	}

	public function getAttributes() {
		static $attrGroups = array();
		if ($attrGroups) {
			return $attrGroups;
		}

		if (isset($this->jsonDataArray['props']['pageProps']['productData']['variants'])
			&& $this->jsonDataArray['props']['pageProps']['productData']['variants']) {
			$color = array();
			$size = array();
			foreach ($this->jsonDataArray['props']['pageProps']['productData']['variants'] as $attrs) {
				if (isset($attrs['attributes']['colorName'])) {
					$color[] = $attrs['attributes']['colorName'];
				}
				if (isset($attrs['attributes']['size'])) {
					$size[] = $attrs['attributes']['size'];
				}
			}
			if ($color) {
				$attrGroups[] = array(
					'name' => 'Color',
					'is_color' => 1,
					'values' => array_unique($color)
				);
			}
			if ($size) {
				$attrGroups[] = array(
					'name' => 'Selecciona Talla',
					'is_color' => 0,
					'values' => array_unique($size)
				);
			}
		}
		return $attrGroups;
	}

	public function getCombinations() {
		static $combinations = array();

		if ($combinations) {
			return $combinations;
		}

		$price = $this->getPrice();
		$sku = $this->getSKU();
		$weight = $this->getWeight();

		if (isset($this->jsonDataArray['props']['pageProps']['productData']['variants'])
			&& $this->jsonDataArray['props']['pageProps']['productData']['variants']) {
			foreach ($this->jsonDataArray['props']['pageProps']['productData']['variants'] as $keys => $attrs) {
				if (isset($attrs['attributes']['size'], $attrs['attributes']['colorName'])) {
					$combinations[] = array(
						'sku' => isset($attrs['id']) ? $attrs['id'] : $sku,
						'upc' => 0,
						'price' => isset($attrs['prices'][0]['price'][0]) ? $attrs['prices'][0]['price'][0] : $price,
						'weight' => 0,
						'image_index' => $keys,
						'attributes' =>  array(
							array(
								'name' => 'Color',
								'value' => $attrs['attributes']['colorName']
							),
							array(
								'name' => 'Selecciona Talla',
								'value' => $attrs['attributes']['size']
							)
						)
					);
				} elseif (isset($attrs['attributes']['colorName'])) {
					$combinations[] = array(
						'sku' => isset($attrs['id']) ? $attrs['id'] : $sku,
						'upc' => 0,
						'price' => isset($attrs['prices'][0]['price'][0]) ? $attrs['prices'][0]['price'][0] : $price,
						'weight' => 0,
						'image_index' => $keys,
						'attributes' =>  array(
							array(
								'name' => 'Color',
								'value' => $attrs['attributes']['colorName']
							)
						)
					);
				} elseif (isset($attrs['attributes']['size'])) {
					$combinations[] = array(
						'sku' => isset($attrs['id']) ? $attrs['id'] : $sku,
						'upc' => 0,
						'price' => isset($attrs['prices'][0]['price'][0]) ? $attrs['prices'][0]['price'][0] : $price,
						'weight' => 0,
						'image_index' => $keys,
						'attributes' =>  array(
							array(
								'name' => 'Selecciona Talla',
								'value' => $attrs['attributes']['size']
							)
						)
					);
				}
			}
		}
		return $combinations;
	}

	public function getMetaTitle() {
		$metatitle = $this->getValue($this->META_TITLE_SELECTOR);
		return array_shift($metatitle);
	}

	public function getMetaDecription() {
		$metadescription = $this->getValue($this->META_DESCRIPTION_SELECTOR);
		return array_shift($metadescription);
	}

	public function getMetaKeywords() {
		$metakeywords = $this->getValue($this->META_KEYWORDS_SELECTOR);
		return array_shift($metakeywords);
	}

	public function getCustomerReviews() {
		$reviews = array();

		$reviewArrayObject = $this->xpath->query($this->REVIEW_SELECTOR);
		if ($reviewArrayObject->length) {
			foreach ($reviewArrayObject as $reviewObject) {
				$content = $this->xpath->query('.//div[@class="bv-content-summary-body-text"]/p', $reviewObject);
				$stars = $this->xpath->query('.//meta[@itemprop="ratingValue"]/@content', $reviewObject)->item(0)->nodeValue;

				if ($content->length) {
					$reviews[] = array(
						'author' => $this->xpath->query('.//span[@class="bv-author"]', $reviewObject)->item(0)->nodeValue,
						'title' => $this->xpath->query('.//span[@itemprop="name"]', $reviewObject)->item(0)->nodeValue,
						'content' => trim($content->item(0)->nodeValue),
						'rating' => $stars,
						'timestamp' => $this->xpath->query('.//meta[@itemprop="datePublished"]/@content', $reviewObject)->item(0)->nodeValue
					);
				}
			}
		}
		return $reviews;
	}
}
