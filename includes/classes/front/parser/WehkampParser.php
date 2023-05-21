<?php
/**
 * Wehkamp data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class WehkampParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $images = array();
	private $JSON_DATA_SELECTOR = '//script[@type="application/ld+json"]';
	private $SHORT_DESCRIPTION_SELECTOR = '//div[@class="condition-info"]/div[@class="current"]';
	private $VARIANT_SELECTOR = '//ul[@id="rollup-KLE"]/li/a/@href';
	private $IMAGE_SELECTOR = '//button[@aria-label="thumbnail"]/div/div/img/@data-src|//button[@aria-label="thumbnail"]/div/div/img/@src';
	private $PRICE_SELECTOR = '//div[contains(@class, "PricingInfo__ba-pricing___RpkAa")]/span/span';
	private $ATTRIBUTE_SELECTOR = '//label[@for="rollup-KLE"]';
	private $ATTRIBUTE_SELECTOR1 = '//button[contains(@class, "UI_SizeButton_container")]/span';
	private $ATTRIBUTE_SELECTOR2 = '//div[@class="RollupsContainer__container___KOoFB"]/button[2]/span[2]';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content, $url) {
		$this->dom = $this->getDomObj($content);
		$this->url = $url;
		$content = iconv('UTF-8', 'UTF-8//IGNORE', $content);
		$content = preg_replace('!/\*.*?\*/!s', '', $content);
		$this->content = preg_replace('/\s+/', ' ', $content);

		/* Create a new XPath object */
		$this->xpath = new \DomXPath($this->dom);

		// Set json data array
		$this->setJsonData();
	}

	public function getContent( $url, $postData = array(), $additionalHeaders = array()) {
		$curl = curl_init($url);
		$headers = array(
			'cache-control: no-cache',
			'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.74 Safari/537.36'
		);
		if ($additionalHeaders) {
			$headers = array_merge($headers, $additionalHeaders);
		}
		if ($postData) {
			$curlOpt = array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_POST => true,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_POSTFIELDS => json_encode($postData),
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_HTTPHEADER => $headers
			);
		} else {
			$curlOpt = array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_HTTPHEADER => $headers
			);
		}
		curl_setopt_array(
			$curl,
			$curlOpt
		);
		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);

		if ($err) {
			return false;
		} else {
			return $response;
		}
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
				$data = json_decode($json, true);
				if ($data) {
					if (!$this->jsonDataArray) {
						$this->jsonDataArray = array();
					}
					$this->jsonDataArray = array_merge($this->jsonDataArray, $data);
				}
			}
		}

		$json = $this->getJson($this->content, 'buyingArea":', ',"imagePlayer');

		if ($json) {
			$this->jsonDataArray = array_merge($this->jsonDataArray, json_decode($json, true));
		}

		$dataAttribute = $this->xpath->query($this->VARIANT_SELECTOR);
		$this->jsonDataArray['products'] = array();

		if ($dataAttribute->length) {
			foreach ($dataAttribute as $key => $dataVarlink) {
				$dataHtml = $this->getContent($dataVarlink->nodeValue);
				if ($dataHtml) {
					$this->jsonDataArray['products'][$key]['attributes'] = array();
					$dom = $this->getDomObj($dataHtml);
					$xpath = new \DomXPath($dom);

					$imageArr = $this->getValue($this->IMAGE_SELECTOR, false, $xpath);
					if ($imageArr) {
						$this->images[$key] = str_replace('w=200', 'w=2000', $imageArr);
					}

					$priceVar = $this->getValue($this->PRICE_SELECTOR, false, $xpath);
					$combPrice = array_shift($priceVar);
					if ($combPrice) {
						$this->jsonDataArray['products'][$key]['price'] = preg_replace('/[^0-9.]/', '', $combPrice);
					}

					$attribute = $this->getValue($this->ATTRIBUTE_SELECTOR, false, $xpath);
					$attribute = array_shift($attribute);

					if ($attribute) {
						$attribute = explode(':', $attribute);

						if ($attribute[1]) {
							$this->jsonDataArray['products'][$key]['attributes'] = array(
								'name' =>  $attribute[0],
								'value' =>  $attribute[1]
							);
						}
					}
				}
			}
		}
	}

	private function getValue( $selector, $html = false, $xpath = null) {
		if (empty($selector)) {
			return array();
		}
		if (null == $xpath) {
			$xpath = $this->xpath;
		}
		$itmes = $xpath->query($selector);
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
		if (isset($this->jsonDataArray['offers']['name'])) {
			return $this->jsonDataArray['offers']['name'];
		}
	}

	public function getCategories() {
		$categories = array();
		if (isset($this->jsonDataArray['itemListElement']) && $this->jsonDataArray['itemListElement']) {
			foreach ($this->jsonDataArray['itemListElement'] as $categorey) {
				$categories[] = $categorey['item']['name'];
			}
		}
		return $categories;
	}

	public function getShortDescription() {
		$shortDescription = '';
		return $shortDescription;
	}

	public function getDescription() {
		if (isset($this->jsonDataArray['description'])) {
			return $this->jsonDataArray['description'];
		}
		return '';
	}

	public function getPrice() {
		$price = 0;
		if (isset($this->jsonDataArray['offers']['price'])) {
			$price = $this->jsonDataArray['offers']['price'];
		}
		return $price;
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
		if (isset($this->jsonDataArray['image'][0]['url'])) {
			return $this->jsonDataArray['image'][0]['url'];
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}

		$images = $this->images;
		if (!$images) {
			$images[0] = str_replace('w=200', 'w=2000', $this->getValue($this->IMAGE_SELECTOR));
		}

		if (!$images) {
			if (isset($this->jsonDataArray['image']) && $this->jsonDataArray['image']) {
				foreach ($this->jsonDataArray['image'] as $imges) {
					$images[0] = str_replace('w=200', 'w=2000', $imges['url']);
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

	public function getWeight() {
		static $weight = array();

		if ($weight) {
			return $weight;
		}

		$features = $this->getFeatures();

		if ($features) {
			foreach ($features as $feature) {
				foreach ($feature['attributes'] as $attr) {
					if (stripos($attr['name'], 'weight') !== false) {
						$weight = array(
							'value' => (float) preg_replace('/[^0-9.]/', '', $attr['value']),
							'unit' => preg_replace('/[0-9.]/', '', $attr['value'])
						);
						break 2;
					}
				}
			}
		}

		return $weight;
	}

	public function getFeatures() {
		static $featureGroups = array();

		if ($featureGroups) {
			return $featureGroups;
		}

		if (isset($this->jsonDataArray['bullets'])
			&& $this->jsonDataArray['bullets']) {
			$attributes = array();
			foreach ($this->jsonDataArray['bullets'] as $speci) {
				$speci = explode(':', $speci);
				$attributes[] = array(
					'name' => $speci[0],
					'value' => $speci[1]
				);
			}
			if ($attributes) {
				$featureGroups[] = array(
					'name' => 'specificaties',
					'attributes' => $attributes,
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

		if (isset($this->jsonDataArray['products']) && $this->jsonDataArray['products']) {
			$attributes = array();
			foreach ($this->jsonDataArray['products'] as $key => $attrs) {
				if (isset($attrs['attributes']['value'])) {
					$attributes[$key] = $attrs['attributes']['value'];
				}
			}
			if ($attributes) {
				$attrGroups[] = array(
					'name' => $this->jsonDataArray['products'][0]['attributes']['name'],
					'is_color' => stripos($this->jsonDataArray['products'][0]['attributes']['name'], 'Kleur') !== false ? 1 : 0,
					'values' => $attributes
				);
			}
		}

		if (isset($this->jsonDataArray['rollups']) && $this->jsonDataArray['rollups']) {
			foreach ($this->jsonDataArray['rollups'] as $attrs) {
				$sizeAttrs = array();
				if ('Kleur' !== $attrs['label']) {
					foreach ($attrs['items'] as $sizes) {
						$sizeAttrs[] = $sizes['label'];
					}
				}
				if ($sizeAttrs) {
					$attrGroups[] = array(
						'name' => $attrs['label'],
						'is_color' => 0,
						'values' => $sizeAttrs
					);
				}
			}
		}
		return $attrGroups;
	}

	public function makeCombination( $data, &$all = array(), $group = array(), $val = null, $i = 0) {
		if (isset($val)) {
			array_push($group, $val);
		}
		if ($i >= count($data)) {
			array_push($all, $group);
		} else {
			foreach ($data[$i] as $v) {
				$this->makeCombination($data, $all, $group, $v, $i + 1);
			}
		}
		return $all;
	}

	public function makeCombinations( $attributes) {
		static $combinations = array();
		if ($combinations) {
			return $combinations;
		}
		$attributeValues = array_map(function ( $a1) {
			return array_map(function ( $a2) {
				return $a2;
			}, $a1);
		}, array_column($attributes, 'values'));

		$atrCombs = $this->makeCombination($attributeValues);
		foreach ($atrCombs as $i => $atrComb) {
			foreach ($atrComb as $attrName) {
				foreach ($attributes as $attribute) {
					if (in_array($attrName, $attribute['values'])) {
						$combinations[$i][] = array(
							'name' => $attribute['name'],
							'value' => $attrName
						);
						break 1;
					}
				}
			}
		}
		return $combinations;
	}

	public function getCombinations() {
		static $combinations = array();
		if ($combinations) {
			return $combinations;
		}

		$price = $this->getPrice();
		$sku = $this->getSKU();
		$weight = $this->getWeight();
		$attrs = $this->getAttributes();
		$colorAttrs = array();

		foreach ($attrs as $attr) {
			if ($attr['is_color']) {
				$colorAttrs = $attr['values'];
				break;
			}
		}
		
		$combs = $this->makeCombinations($attrs);
		
		if ($combs) {
			foreach ($combs as $attrVals) {
				$imageIndex = 0;
				if ($colorAttrs) {
					foreach ($colorAttrs as $key => $colorName) {
						if (in_array($colorName, array_column($attrVals, 'value'))) {
							$imageIndex = $key;
							break( 1 );
						}
					}
				}

				$combinations[] = array(
					'sku' => $sku,
					'upc' => 0,
					'price' => isset($this->jsonDataArray['products'][$imageIndex]['price']) ? $this->jsonDataArray['products'][$imageIndex]['price'] : $price,
					'weight' => $weight,
					'image_index' => $imageIndex,
					'attributes' => $attrVals
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

	public function getCustomerReviews( $maxReviews = 0, &$reviews = array()) {
		$maxReviews = $maxReviews > 0 ? $maxReviews : 100;
		if (!$reviews) {
			$host = parse_url($this->url, PHP_URL_HOST);
			$id = $this->getSKU();
			$this->reviewLink = 'https://www.wehkamp.nl/service/product-review/products/' . $id . '/reviews?from=0&size=' . $maxReviews . '&sort_by=date_submitted&sort_order=desc';
		}
		$reviewLink = $this->reviewLink;

		if ($reviewLink) {
			$json = $this->getContent($reviewLink);

			if ($json) {
				$reviewData = json_decode($json, true);
				$isMaxReached = false;

				if (isset($reviewData['reviews']) && $reviewData['reviews']) {
					foreach ($reviewData['reviews'] as $review) {
						$reviews[] = array(
							'author' => isset($review['nickname']) ? $review['nickname'] : '',
							'title' => isset($review['title']) ? $review['title'] : '',
							'content' => isset($review['text']) ? $review['text'] : '',
							'rating' => (int) $review['rating'],
							'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['date_submitted']))
						);
					}
				}
			}
		}
		if (!$reviews) {
			$reviews = $this->getCustomerReviews2();
		}
		return $reviews;
	}

	public function getCustomerReviews2() {
		$reviews = array();
		if (isset($this->jsonDataArray['review']) && $this->jsonDataArray['review']) {
			foreach ($this->jsonDataArray['review'] as $review) {
				if (isset($review['reviewBody']) && $review['reviewBody']) {
					$reviews[] = array(
						'author' => isset($review['author']['name']) ? $review['author']['name'] : '',
						'title' => isset($review['name']) ? $review['name'] : '',
						'content' => $review['reviewBody'],
						'rating' => $review['reviewRating']['ratingValue'],
						'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['datePublished']))
					);
				}
			}
		}
		return $reviews;
	}
}
