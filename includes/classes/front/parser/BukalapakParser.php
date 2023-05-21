<?php
/**
 * Bukalapak data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class BukalapakParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $CATEGORY_SELECTOR = '//li[@class="c-bl-breadcrumb__item"]';
	private $DESCRIPTION_SELECTOR = '//div[contains(@class, "c-information__description")]';
	private $PRODUCT_ID_SELECTOR = '//div/@product-id';
	private $FEATURE_SELECTOR_NM = '//h3[@class="c-information__subtitle"]';
	private $FEATURE_SELECTOR = '//table[@class="c-information__table"]/tbody/tr';
	private $ATTRIBUTES_SELECTOR = '//div[@class="c-main-product__variant__item"]';
	private $IMAGE_SELECTOR = '//div[@class="c-product-gallery__thumbnail-img"]/picture/source/img/@src';
	private $REVIEW_LINK_SELECTOR = '//a[@class="see-all-catalog-review"]/@href';
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

	public function getContent( $url, $postVar = array()) {
		$curl = curl_init();
		$curlopts = array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_HTTPHEADER => array(
				'cache-control: no-cache',
				"Origin: $url",
				'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36'
			),
		);
		if ($postVar) {
			$curlopts[CURLOPT_POST] = true;
			$curlopts[CURLOPT_POSTFIELDS] =  http_build_query($postVar);
		} else {
			$curlopts[CURLOPT_CUSTOMREQUEST] = 'GET';
		}

		curl_setopt_array(
			$curl,
			$curlopts
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
		$json = $this->getJson($this->content, '<script type="application/ld+json">', '</script>', 4);
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = json_decode($json, true);
		}
		$token = $this->getJson($this->content, 'access_token":"', '","created');
		$productId = $this->getValue($this->PRODUCT_ID_SELECTOR);
		$productId = array_shift($productId);
		if ($token && $productId) {
			$urlData = 'https://api.bukalapak.com/products/' . $productId . '/skus?access_token=' . $token;
			if ($urlData) {
				$imgJson = $this->getContent($urlData);
				if ($imgJson) {
					$json = iconv('UTF-8', 'UTF-8//IGNORE', $imgJson);
					$this->jsonDataArray = array_merge($this->jsonDataArray, json_decode($json, true));
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
		$descript = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		
		if ($descript) {
			return array_shift($descript);
		}
		return '';
	}

	public function getPrice() {
		if (isset($this->jsonDataArray['offers']['lowPrice'])) {
			return $this->jsonDataArray['offers']['lowPrice']/1000;
		}
		return '';
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
		if (isset($this->jsonDataArray['image'])) {
			return $this->jsonDataArray['image'];
		}
		return '';
	}

	public function getImages() {
		static $images = array();
		if ($images) {
			return $images;
		}
		$pageImage = $this->getValue($this->IMAGE_SELECTOR);
		if (isset($this->jsonDataArray['data'])) {
			foreach ($this->jsonDataArray['data'] as $key => $imgs) {
				if (isset($imgs['images']['large_urls']) && $imgs['images']['large_urls']) {
					$images[$key] = $imgs['images']['large_urls'];
				}
			}
		} elseif ($pageImage) {
			$images[0] = str_replace('128', '1024', $pageImage);
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
		$attributes = array();

		$featureArrayObject = $this->xpath->query($this->FEATURE_SELECTOR);
		$featureNM = $this->getValue($this->FEATURE_SELECTOR_NM);
		if ($featureNM) {
			$featureNM = array_shift($featureNM);
		}

		if ($featureArrayObject->length) {
			foreach ($featureArrayObject as $features) {
				$name = $this->xpath->query('.//th', $features);
				$value = $this->xpath->query('.//td', $features);

				$attributes[] = array(
					'name' => $name->item(0)->nodeValue,
					'value' => $value->item(1)->nodeValue
				);
			}
		}
		if ($attributes) {
			$featureGroups[] = array(
				'name' => $featureNM,
				'attributes' => $attributes
			);
		}
		return $featureGroups;
	}

	public function getAttributes() {
		static $attrGroups = array();
		if ($attrGroups) {
			return $attrGroups;
		}

		$AttrsArrayObject = $this->xpath->query($this->ATTRIBUTES_SELECTOR);
		if ($AttrsArrayObject->length) {
			foreach ($AttrsArrayObject as $attrsObject) {
				$attrName = $this->xpath->query('.//div[@class="c-main-product__label"]', $attrsObject)->item(0)->nodeValue;
				$attrValue = $this->xpath->query('.//li[@class="multiselect__element"]/span/span', $attrsObject);
				$attrValues = array();
				if ($attrValue->length) {
					foreach ($attrValue as $attrVals) {
						$attrValues[] = $attrVals->nodeValue;
					}
				}

				$attrGroups[] = array(
					'name' => $attrName,
					'is_color' => ( stripos($attrName, 'warna') !== false ) ? 1 : 0,
					'values' => $attrValues
				);
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
		$attrs = $this->getAttributes();
		$combs = $this->makeCombinations($attrs);

		if ($combs) {
			foreach ($combs as $attrVals) {
				$imageIndex = 0;
				foreach ($attrs as $attribute) {
					foreach ($attribute['values'] as $key => $vals) {
						if (in_array($vals, array_column($attrVals, 'value'))) {
							if ($attribute['is_color']) {
								$imageIndex = $key;
							}

							break ( 1 );
						}
					}
				}
				$combinations[] = array(
					'sku' => $sku,
					'upc' => 0,
					'price' => $price,
					'weight' => 0,
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

	public function getCustomerReviews( $maxReviews = 0) {
		$reviews = array();
		$maxReviews = $maxReviews > 0 ? $maxReviews : 500;

		$token = $this->getJson($this->content, 'access_token":"', '","created');
		$productId = $this->getValue($this->PRODUCT_ID_SELECTOR);
		$productId = array_shift($productId);
		if ($token && $productId) {
			$urlData = 'https://api.bukalapak.com/product-reviews?product_id=' . $productId . '&aggregates[]=ratings&limit=' . $maxReviews . '&access_token=' . $token;
			if ($urlData) {
				$reviewJson = $this->getContent($urlData);

				if ($reviewJson) {
					$json = iconv('UTF-8', 'UTF-8//IGNORE', $reviewJson);
					$reviewsData = json_decode($json, true);

					if ($reviewsData) {
						if (isset($reviewsData['data']) && $reviewsData['data']) {
							foreach ($reviewsData['data'] as $review) {
								if (isset($review['review']['content'])) {
									$reviews[] = array(
										'author' => $review['sender']['name'],
										'title' => $review['review']['title'],
										'content' => $review['review']['content'],
										'rating' => $review['review']['rate'],
										'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['created_at']))
									);
								}
							}
						}
					}
				}
			}
		}
		if (!$reviews) {
			return $this->getCustomerReviews2();
		}
		return $reviews;
	}

	public function getCustomerReviews2() {
		$reviews = array();
		if (isset($this->jsonDataArray['review']) && $this->jsonDataArray['review']) {
			foreach ($this->jsonDataArray['review'] as $review) {
				if (isset($review['reviewBody'])) {
					$reviews[] = array(
						'author' => $review['author']['name'],
						'title' => '',
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
