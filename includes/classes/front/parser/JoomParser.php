<?php
/**
 * Joom data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class JoomParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $CATEGORY_SELECTOR = '//a[@class="link___2DAus"]/span';
	private $COLOR_SELECTOR = '//h2[@class="title___39_yt"]';
	private $SIZE_SELECTOR = '//h2[@class="title___1mko2"]';
	private $REVIEW_SELECTOR = '//div[@class="review___TxpAd"]';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content, $url) {
		$this->content = preg_replace('/\s+/', ' ', $content);
		$this->dom = $this->getDomObj($content);
		$this->url = $url;
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
		$json = $this->getJson($this->content, 'window.__data=', '; window.__endpoint');
		$json = str_replace('undefined,', '"",', $json);
		$json = str_replace('undefined', '[]', $json);
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = json_decode($json, true);
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
		if (isset($this->jsonDataArray['clientProduct']['data']['header']['name'])) {
			return $this->jsonDataArray['clientProduct']['data']['header']['name'];
		}
		return '';
	}

	public function getCategories() {
		$categories = $this->getValue($this->CATEGORY_SELECTOR);
		if ($categories) {
			return array_filter($categories);
		}
		return $categories;
	}

	public function getShortDescription() {
		return '';
	}

	public function getDescription() {
		if (isset($this->jsonDataArray['clientProduct']['data']['header']['description'])) {
			return $this->jsonDataArray['clientProduct']['data']['header']['description'];
		}
		return '';
	}

	public function getPrice() {
		$price = 0;
		if (isset($this->jsonDataArray['clientProduct']['data']['header']['prices']['min']['amount'])) {
			$price = $this->jsonDataArray['clientProduct']['data']['header']['prices']['min']['amount'];
		} elseif (isset($this->jsonDataArray['clientProduct']['data']['header']['prices']['max']['amount'])) {
			$price = $this->jsonDataArray['clientProduct']['data']['header']['prices']['max']['amount'];
		}
		return $price;
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['clientProduct']['data']['header']['id'])) {
			return $this->jsonDataArray['clientProduct']['data']['header']['id'];
		}
		return '';
	}

	public function getBrand() {
		return '';
	}

	public function getCoverImage() {
		if (isset($this->jsonDataArray['clientProduct']['data']['header']['mainImage']['images'])) {
			$cImage = array_pop($this->jsonDataArray['clientProduct']['data']['header']['mainImage']['images']);
			if (isset($cImage['url'])) {
				return $cImage['url'];
			}
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}

		if (isset($this->jsonDataArray['clientProduct']['data']['header']['variants'])
			&& $this->jsonDataArray['clientProduct']['data']['header']['variants']) {
			foreach ($this->jsonDataArray['clientProduct']['data']['header']['variants'] as $imgs) {
				if (isset($imgs['mainImage']['images'])) {
					$imags = array_pop($imgs['mainImage']['images']);
					if (isset($imags['url'])) {
						$images[$imgs['colorId']][] = $imags['url'];
					}
				}
			}
		}
		if (isset($this->jsonDataArray['clientProduct']['data']['header']['gallery'])
			&& $this->jsonDataArray['clientProduct']['data']['header']['gallery']) {
			foreach ($this->jsonDataArray['clientProduct']['data']['header']['gallery'] as $imgs) {
				if (isset($imgs['payload']['images'])) {
					$imags = array_pop($imgs['payload']['images']);
					if (isset($imags['url'])) {
						$images[0][] = $imags['url'];
					}
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
		if (isset($this->jsonDataArray['clientProduct']['data']['header']['attributes']['featured']['items'])
			&& $this->jsonDataArray['clientProduct']['data']['header']['attributes']['featured']['items']) {
			$attributes = array();
			foreach ($this->jsonDataArray['clientProduct']['data']['header']['attributes']['featured']['items'] as $specifics) {
				if (isset($specifics['content']['text']['value'])) {
					$attributes[] = array(
						'name' => $specifics['content']['text']['key'],
						'value' => $specifics['content']['text'] ['value']
					);
				}
				if ($attributes) {
					$featureGroups[] = array(
						'name' => $specifics['id'],
						'attributes' => $attributes
					);
				}
			}
		}
		return $featureGroups;
	}

	public function getAttributes() {
		static $attrGroups = array();
		if ($attrGroups) {
			return $attrGroups;
		}
		$sizNm = '';
		$clrNm = '';
		$colorNm = $this->getValue($this->COLOR_SELECTOR);
		$sizeNm = $this->getValue($this->SIZE_SELECTOR);
		if ($colorNm) {
			$clrNm = array_shift($colorNm);
		}
		if ($sizeNm) {
			$sizNm = array_shift($sizeNm);
		}

		if (isset($this->jsonDataArray['clientProduct']['data']['header']['colors'])
			&& $this->jsonDataArray['clientProduct']['data']['header']['colors']) {
			$attributes = array();
			foreach ($this->jsonDataArray['clientProduct']['data']['header']['colors'] as $attri) {
				$attrs = explode('_', $attri);
				$attrs = array_shift($attrs);
				$attributes[$attri] = $attrs;
			}
			if ($attributes) {
				$attrGroups[] = array(
					'name' => $clrNm,
					'is_color' => 1,
					'values' => $attributes
				);
			}
		}

		if (isset($this->jsonDataArray['clientProduct']['data']['header']['sizes'])
			&& $this->jsonDataArray['clientProduct']['data']['header']['sizes']) {
			$attributes = array();
			foreach ($this->jsonDataArray['clientProduct']['data']['header']['sizes'] as $attri) {
				$attributes[] = $attri['id'];
			}
			if ($attributes) {
				$attrGroups[] = array(
					'name' => $sizNm,
					'is_color' => 0,
					'values' => $attributes
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
		$sizNm = '';
		$clrNm = '';
		$colorNm = $this->getValue($this->COLOR_SELECTOR);
		$sizeNm = $this->getValue($this->SIZE_SELECTOR);
		if ($colorNm) {
			$clrNm = array_shift($colorNm);
		}
		if ($sizeNm) {
			$sizNm = array_shift($sizeNm);
		}
		$price = $this->getPrice();
		$sku = $this->getSKU();
		$attri = $this->getAttributes();

		if (isset($this->jsonDataArray['clientProduct']['data']['header']['variants'])
			&& $this->jsonDataArray['clientProduct']['data']['header']['variants']) {
			foreach ($this->jsonDataArray['clientProduct']['data']['header']['variants'] as $combi) {
				if (isset($combi['colors'][0]['name'], $combi['size'])) {
					$combinations[] = array(
						'sku' => isset($combi['id']) ? $combi['id'] : $sku,
						'upc' => isset($combi['productId']) ? $combi['productId'] : '',
						'price' => isset($combi['price']['amount']) ? $combi['price']['amount'] : $price,
						'weight' => 0,
						'image_index' => $combi['colorId'],
						'attributes' => array(
							array(
								'name' => $sizNm,
								'value' => $combi['size']
							),
							array(
								'name' => $clrNm,
								'value' => $combi['colors'][0]['name']
							)
						)
					);
				} elseif (isset($combi['colors'][0]['name'])) {
					$combinations[] = array(
						'sku' => isset($combi['id']) ? $combi['id'] : $sku,
						'upc' => isset($combi['productId']) ? $combi['productId'] : '',
						'price' => isset($combi['price']['amount']) ? $combi['price']['amount'] : $price,
						'weight' => 0,
						'image_index' => $combi['colorId'],
						'attributes' => array(
							array(
								'name' => $clrNm,
								'value' => $combi['colors'][0]['name']
							)
						)
					);
				} elseif (isset($combi['size'])) {
					$combinations[] = array(
						'sku' => isset($combi['id']) ? $combi['id'] : $sku,
						'upc' => isset($combi['productId']) ? $combi['productId'] : '',
						'price' => isset($combi['price']['amount']) ? $combi['price']['amount'] : $price,
						'weight' => 0,
						'image_index' => $combi['colorId'],
						'attributes' => array(
							array(
								'name' => $sizNm,
								'value' => $combi['size']
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
				$author = $this->xpath->query('.//div[@itemprop="author"]/span', $reviewObject);
				if ($author->length) {
					$stars = 0;
					$stars = $this->xpath->query('.//div[@class="stars___1FcSu"]/@style', $reviewObject)->item(0)->nodeValue;

					if ('padding-right: 1.66667%;' == $stars) {
						$rating = 5;
					}
					if ('padding-right: 21.6667%;' == $stars) {
						$rating = 4;
					}
					if ('padding-right: 41.6667%;' == $stars) {
						$rating = 3;
					}
					if ('padding-right: 61.6667%;' == $stars) {
						$rating = 2;
					}
					if ('padding-right: 81.6667%;' == $stars) {
						$rating = 1;
					}
					$reviews[] = array(
						'author' => trim($author->item(0)->nodeValue),
						'title' => '',
						'content' => @$this->xpath->query('.//p[@itemprop="description"]', $reviewObject)->item(0)->nodeValue,
						'rating' => $rating,
						'timestamp' => gmdate('Y-m-d H:i:s', strtotime(trim($this->xpath->query('.//div[@class="date___1UTUf"]', $reviewObject)->item(0)->nodeValue)))
					);
				}
			}
		}
		return $reviews;
	}
}
