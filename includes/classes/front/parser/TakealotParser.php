<?php
/**
 * Takealot data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class TakealotParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $REVIEW_SELECTOR = '//div[@class="reviews-pagination-module_reviews_2-vdx"]/div';
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

	private function setJsonData() {
		$productId = $this->getSKU();
		$json = $this->getContent('https://api.takealot.com/rest/v-1-10-0/product-details/' . $productId);
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = json_decode($json, true);
		}
		$json = $this->getContent('https://api.takealot.com/rest/v-1-10-0/product-details/' . $productId . '/frequently-bought-together?platform=desktop&allow_variant=true');
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
		if (isset($this->jsonDataArray['title'])) {
			return $this->jsonDataArray['title'];
		}
		return '';
	}

	public function getCategories() {
		$categories = array();
		if (isset($this->jsonDataArray['breadcrumbs']['items']) && $this->jsonDataArray['breadcrumbs']['items']) {
			foreach ($this->jsonDataArray['breadcrumbs']['items'] as $categary) {
				$categories[] = $categary['name'];
			}
		}
		return $categories;
	}

	public function getShortDescription() {
		if (isset($this->jsonDataArray['core']['subtitle'])) {
			return $this->jsonDataArray['core']['subtitle'];
		}
		return '';
	}

	public function getDescription() {
		if (isset($this->jsonDataArray['description']['html'])) {
			return $this->jsonDataArray['description']['html'];
		}
		return '';
	}

	public function getPrice( $priceVar = array()) {
		$price = 0;
		$jsonDataArray = array();
		if ($priceVar) {
			$sku = $this->getSKU();
			$url = 'https://api.takealot.com/rest/v-1-10-0/product-details/' . $sku . '?' . http_build_query($priceVar);
			$json = $this->getContent($url);
			if ($json) {
				$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
				$jsonDataArray = json_decode($json, true);
			}
			if (isset($jsonDataArray['buybox']['prices'][0])) {
				$price = $jsonDataArray['buybox']['prices'][0];
			} elseif (isset($jsonDataArray['buybox']['prices'][1])) {
				$price = $jsonDataArray['buybox']['prices'][1];
			}
		} elseif (isset($this->jsonDataArray['buybox']['prices'][0])) {
			$price = $this->jsonDataArray['buybox']['prices'][0];
		} elseif (isset($this->jsonDataArray['buybox']['prices'][1])) {
			$price = $this->jsonDataArray['buybox']['prices'][1];
		}
		return $price;
	}

	public function getSKU() {
		$urls = explode('/', $this->url);
		foreach ($urls as $url) {
			if (stripos($url, 'PLID') !== false) {
				return $url;
				break;
			}
		}
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['core']['brand'])) {
			return $this->jsonDataArray['core']['brand'];
		}
		return '';
	}

	public function getCoverImage() {
		if (isset($this->jsonDataArray['gallery']['images'])) {
			$cvr = array_shift($this->jsonDataArray['gallery']['images']);
			return str_replace('{size}', 'zoom', $cvr);
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}
		if (isset($this->jsonDataArray['variants']['selectors']) && $this->jsonDataArray['variants']['selectors']) {
			foreach ($this->jsonDataArray['variants']['selectors'] as $imgs) {
				if ('Colour' == $imgs['title']) {
					foreach ($imgs['options'] as $key => $imgses) {
						foreach ($imgses['image'] as $imgUrl) {
							$images[$key][] = str_replace('{size}', 'zoom', $imgUrl);
						}
					}
				}
			}
		}
		if (isset($this->jsonDataArray['gallery']['images'])) {
			$images[0] = str_replace('{size}', 'zoom', $this->jsonDataArray['gallery']['images']);
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

		if (isset($this->jsonDataArray['product_information']['items'])
			&& $this->jsonDataArray['product_information']['items']) {
			$attributes = array();
			foreach ($this->jsonDataArray['product_information']['items'] as $feature) {
				if (is_array($feature['value'])) {
					if (isset($feature['value'][0]['name'])) {
						$attributes[] = array(
							'name' => $feature['display_name'],
							'value' => $feature['value'][0]['name']
						);
					} elseif (isset($feature['value']['name'])) {
						$attributes[] = array(
							'name' => $feature['display_name'],
							'value' => $feature['value']['name']
						);
					}
				} elseif (isset($feature['value'])) {
					$attributes[] = array(
						'name' => $feature['display_name'],
						'value' => $feature['value']
					);
				}
			}
			if ($attributes) {
				$featureGroups[] = array(
					'name' => $this->jsonDataArray['product_information']['tab_title'],
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
		if (isset($this->jsonDataArray['variants']['selectors']) && $this->jsonDataArray['variants']['selectors']) {
			foreach ($this->jsonDataArray['variants']['selectors'] as $attrArray) {
				$attributes = array();
				if (isset($attrArray['options'])) {
					foreach ($attrArray['options'] as $attrs) {
						if (isset($attrs['value']['value'])) {
							$attributes[] = $attrs['value']['value'];
						} elseif (isset($attrs['value'])) {
							$attributes[] = $attrs['value'];
						}
					}
				}
				if ($attributes) {
					$attrGroups[$attrArray['selector_type']] = array(
						'name' => $attrArray['title'],
						'is_color' => ( stripos($attrArray['title'], 'Colour') !== false ) ? 1 : 0,
						'values' => $attributes
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
		$sku = $this->getSKU();
		$attrs = $this->getAttributes();
		$combs = $this->makeCombinations($attrs);
		if ($combs) {
			foreach ($combs as $combVals) {
				$imageIndex = 0;
				$priceAttributes = array();
				foreach ($attrs as $key => $attr) {
					$attrVals = array_intersect($attr['values'], array_column($combVals, 'value'));
					if ($attrVals) {
						$attrVal = current($attrVals);
						$priceAttributes[$key] = $attrVal;
						if ($attr['is_color']) {
							$imageIndex = array_search($attrVal, $attr['values']);
						}
					}
				}
				$combinations[] = array(
					'sku' => $sku,
					'upc' => 0,
					'price' => $this->getPrice($priceAttributes),
					'weight' => 0,
					'image_index' => $imageIndex,
					'attributes' => $combVals
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

	public function getCustomerReviews( $maxReviews = 0, &$reviews = array(), $page = 1) {
		if (!$reviews) {
			$sku = preg_replace('/[^0-9]/', '', $this->getSKU());
			$this->reviewLink = 'https://api.takealot.com/rest/v-1-10-0/product-reviews/plid/' . $sku . '?';
		}
		$reviewLink = $this->reviewLink;
		$reviewLink .= '&page=' . $page;
		if ($reviewLink) {
			$reviewJson = $this->getContent($reviewLink);
			if ($reviewJson) {
				$reviewArrayObject = json_decode($reviewJson, true);
				if (isset($reviewArrayObject['reviews']) && $reviewArrayObject['reviews']) {
					$isMaxReached = false;
					foreach ($reviewArrayObject['reviews'] as $reviewObject) {
						if ($reviewObject['customer_name']) {
							$reviews[] = array(
								'author' => $reviewObject['customer_name'],
								'title' => '',
								'content' => isset($reviewObject['text']['body']) ? $reviewObject['text']['body'] : '',
								'rating' =>$reviewObject['rating'],
								'timestamp' => gmdate('Y-m-d H:i:s', strtotime($reviewObject['date']))
							);
							if (0 < $maxReviews && count($reviews) >= $maxReviews) {
								$isMaxReached = true;
								break;
							}
						}
					}
					if (false == $isMaxReached) {
						$this->getCustomerReviews($maxReviews, $reviews, $page++);
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
		$reviewArrayObject = $this->xpath->query($this->REVIEW_SELECTOR);
		if ($reviewArrayObject->length) {
			foreach ($reviewArrayObject as $reviewObject) {
				$author = $this->xpath->query('.//span[@class="review-list-item-module_customer-name_1x86y"]', $reviewObject)->item(0)->nodeValue;
				$author = explode('-', $author);
				$author1 = array_shift($author);
				$date = array_pop($author);
				$stars = $this->xpath->query('.//i[contains(@class, "full")]', $reviewObject);
				if ($stars->length) {
					$rating = 0;
					if ($stars) {
						$rating = $stars->length;
					}
					$reviews[] = array(
						'author' => trim($author1),
						'title' => '',
						'content' => @$this->xpath->query('.//span[@class="review-list-item-module_review-body_1xhGF"]', $reviewObject)->item(0)->nodeValue,
						'rating' => $rating,
						'timestamp' => gmdate('Y-m-d H:i:s', strtotime($date))
					);
				}
			}
		}
		return $reviews;
	}
}
