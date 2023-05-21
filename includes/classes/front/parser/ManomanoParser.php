<?php
/**
 * Manomano data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class ManomanoParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $LANG_SELECTOR = '//meta[@name="platform"]/@content';
	private $ATTRIBUTE_LABEL_SELECTOR = '//div[contains(@class, "ModalModelPicker_pickerHeader")]';
	private $FEATURE_SELECTOR = '//ul[contains(@class, "attributes_list")]/li/div';
	private $PRICE_SELECTOR = '//div[@data-testid="main-price-container"]';
	private $DESCRIPTION_SELECTOR = '//div[@data-testid="grid-element-description"]/div[contains(@class, "block_container")]';
	private $REVIEW_SELECTOR = '//div[@data-testid="Reviews"]/div/div';
	private $CATEGORY_SELECTOR = '//ul[contains(@class, "root_")]/li/a';
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
	public function getContent( $url) {
		return @file_get_contents($url);
	}

	private function setJsonData() {
		$urlParse = explode('-', parse_url($this->url, PHP_URL_PATH));
		$id = array_pop($urlParse);

		$apiHost = $this->getJson($this->content, '"MS_API_URL":"', '","MS_CATALOG');

		$link = $apiHost . '/web/api/v1/product-page/product/PRODUCTID/';
		$lang = current($this->getValue($this->LANG_SELECTOR));
		$link .= $lang;

		$product = array();

		$this->jsonDataArray['id'] = $id;
		$this->jsonDataArray['lang'] = $lang;
		$this->jsonDataArray['apihost'] = $apiHost;
		$this->jsonDataArray['products'] = array();

		$json = $this->getContent(str_replace('PRODUCTID', $id, $link));
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$product = json_decode($json, true);
		}

		if ($product) {
			$this->jsonDataArray = array_merge($this->jsonDataArray, $product);
		} else {
			$json = $this->getValue('//script[@data-testid="structuredDataSEOProductElm"]');
			if ($json) {
				$json = iconv('UTF-8', 'UTF-8//IGNORE', array_shift($json));
				$this->jsonDataArray = array_merge($this->jsonDataArray, json_decode($json, true));
			}
		}

		if (isset($product['content']['product']['sku']['variantsLegacy']['data'])
			&& $product['content']['product']['sku']['variantsLegacy']['data']
		) {
			$this->jsonDataArray['variants'] = array();

			foreach ($product['content']['product']['sku']['variantsLegacy']['data'] as $variant) {
				$this->jsonDataArray['variants'][$variant['modelId']] = $variant['description'];

				$productLink = str_replace(
					array('PRODUCTID', 'MODELID'),
					array($id, $variant['modelId']),
					$link . '?model_id=MODELID'
				);

				$json = $this->getContent($productLink);
				if ($json) {
					$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
					$data = json_decode($json, true);

					if ($data) {
						$this->jsonDataArray['products'][$variant['modelId']] = $data;
					}
				}
			}
		}
	}

	private function getValue( $selector, $html = false) {
		if (empty($selector)) {
			return array();
		}
		$itmes = $this->xpath->query($selector);
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
		if (isset($this->jsonDataArray['content']['product']['sku']['title'])) {
			return $this->jsonDataArray['content']['product']['sku']['title'];
		}

		if (isset($this->jsonDataArray['name'])) {
			return $this->jsonDataArray['name'];
		}
		return '';
	}

	public function getCategories() {
		$categories = array();
		$categories = $this->getValue($this->CATEGORY_SELECTOR);

		return array_unique($categories);
	}

	public function getShortDescription() {
		if (isset($this->jsonDataArray['description'])) {
			return $this->jsonDataArray['description'];
		}
		return '';
	}

	public function getDescription() {
		if (isset($this->jsonDataArray['content']['product']['sku']['description']['text'])) {
			return $this->jsonDataArray['content']['product']['sku']['description']['text'];
		}

		$descript = $this->getValue($this->DESCRIPTION_SELECTOR, true);

		if ($descript) {
			return array_shift($descript);
		}
		return '';
	}

	public function getPrice( $jsonDataArray = array()) {
		if (!$jsonDataArray) {
			$jsonDataArray = $this->jsonDataArray;
		}

		if (isset($jsonDataArray['content']['product']['offer']['price']['currentPrice']['amountWithVat'])) {
			return $jsonDataArray['content']['product']['offer']['price']['currentPrice']['amountWithVat'];
		}

		if (isset($jsonDataArray['offers']['price'])) {
			return $jsonDataArray['offers']['price'];
		}

		$price = $this->getValue($this->PRICE_SELECTOR);

		return preg_replace('/\s+/', '.', preg_replace('/[^0-9]/', ' ', array_shift($price)));
	}

	public function getSKU( $jsonDataArray = null) {
		if (!$jsonDataArray) {
			$jsonDataArray = $this->jsonDataArray;
		}

		if (isset($jsonDataArray['content']['product']['sku']['id'])) {
			return $jsonDataArray['content']['product']['sku']['id'];
		}

		if (isset($jsonDataArray['sku'])) {
			return $jsonDataArray['sku'];
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['content']['product']['masterProduct']['brand']['name'])) {
			return $this->jsonDataArray['content']['product']['masterProduct']['brand']['name'];
		}

		if (isset($this->jsonDataArray['brand'])) {
			return $this->jsonDataArray['brand'];
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}

		if (isset($this->jsonDataArray['products'])
			&& $this->jsonDataArray['products']
		) {
			foreach ($this->jsonDataArray['products'] as $key => $product) {
				if (isset($product['content']['product']['sku']['media'])) {
					foreach ($product['content']['product']['sku']['media'] as $media) {
						if ('image' == $media['type']) {
							$images[$key][] = $media['largeUrl'];
						}
					}
				}
			}
		} elseif (isset($this->jsonDataArray['image']) && $this->jsonDataArray['image']) {
			foreach ($this->jsonDataArray['image'] as $img) {
				$images[0][] = $img;
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
		$attributes = array();

		$feature = $this->getValue($this->FEATURE_SELECTOR);

		for ($i = 0; $i<count($feature); $i = $i+2) {
			$attributes[] = array(
				'name' => $feature[$i],
				'value' => $feature[$i+1]
			);
		}

		if ($attributes) {
			$featureGroups[] = array(
				'name' => 'General',
				'attributes' => $attributes
			);
		}

		return $featureGroups;
	}

	public function getWeight( $jsonDataArray = array()) {
		$weight = array();

		if (!$jsonDataArray) {
			$jsonDataArray = $this->jsonDataArray;
		}

		if (isset($jsonDataArray['content']['deliveryOffers']['weight'])) {
			$weight = array(
				'value' => $jsonDataArray['content']['deliveryOffers']['weight'],
				'unit' => 'kg'
			);
		}

		return $weight;
	}

	public function getAttributes() {
		static $attrGroups = array();

		if ($attrGroups) {
			return $attrGroups;
		}

		if (isset($this->jsonDataArray['variants'])
			&& $this->jsonDataArray['variants']
			&& count($this->jsonDataArray['variants']) > 1
		) {
			$attrLabel = $this->getValue($this->ATTRIBUTE_LABEL_SELECTOR);

			$attrGroups[] = array(
				'name' => trim(preg_replace('/[0-9]/', '', current($attrLabel))),
				'is_color' => 1,
				'values' => $this->jsonDataArray['variants']
			);
		}

		return $attrGroups;
	}

	public function getCombinations() {
		static $combinations = array();
		if ($combinations) {
			return $combinations;
		}

		if (isset($this->jsonDataArray['products'])
			&& $this->jsonDataArray['products']
			&& count($this->jsonDataArray['products']) > 1
		) {
			$attrGroup = current($this->getAttributes());

			foreach ($this->jsonDataArray['products'] as $key => $product) {
				$combinations[] = array(
					'sku' => $key,
					'upc' => 0,
					'price' => $this->getPrice($product),
					'weight' => $this->getWeight($product),
					'image_index' => $key,
					'attributes' => array(
						array(
							'name' => $attrGroup['name'],
							'value' => $this->jsonDataArray['variants'][$key]
						)
					)
				);
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

	public function getCustomerReviews( $maxReviews = 0) {
		$reviews = array();
		$maxReviews = $maxReviews ? $maxReviews : 500;

		if (!$reviews) {
			$this->reviewLink = $this->jsonDataArray['apihost'];
			$this->reviewLink .= '/api/v2/article-ratings?platform=' . $this->jsonDataArray['lang'] . '&article_ids=';
			$this->reviewLink .= $this->jsonDataArray['id'] . '&limit=' . $maxReviews;
		}

		$reviewLink = $this->reviewLink;

		if ($reviewLink) {
			$json = $this->getContent($reviewLink);
			if ($json) {
				$jsons = iconv('UTF-8', 'UTF-8//IGNORE', $json);

				$reviewData = json_decode($jsons, true);

				if (isset($reviewData['content'])) {
					foreach ($reviewData['content'] as $commentInfo) {
						$reviews[] = array(
							'author' => $commentInfo['customer_name'],
							'title' => isset($commentInfo['title']) ? $commentInfo['title'] : '',
							'content' => $commentInfo['message'],
							'rating' => (int) $commentInfo['rating'],
							'timestamp' => gmdate('Y-m-d H:i:s', strtotime($commentInfo['created_at']))
						);
					}
				}
			}
		}
		return $reviews;
	}
}
