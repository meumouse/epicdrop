<?php
/**
 * Snapdeal data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class SnapdealParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $attributes = array();
	private $JSON_DATA_SELECTOR = '//script[@type="application/ld+json"]';
	private $JSON_DATA_SELECTOR1 = '//div[@id="attributesJson"]';
	private $TITLE_SELECTOR = '//h1[@itemprop="name"]';
	private $CATEGORY_SELECTOR = '//div[@class="rd-breadcrumb__item"]/a';
	private $DESCRIPTION_SELECTOR = '//div[@itemprop="description"]';
	private $PRICE_SELECTOR = '//span[@itemprop="price"]';
	private $BRAND_SELECTOR = '//input[@id="brandName"]/@value';
	private $IMAGE_SELECTOR = '//ul[@id="bx-slider-left-image-panel"]/li/img/@bigsrc';
	private $FEATURE_SELECTOR = '//ul[contains(@class, "dtls-list")]/li/span[@class="h-content"]';
	private $ATTRIBUTE_SELECTOR = '//div[@class="shop-variation-wrapper"]';
	private $SELECTED_COLOR_SELECTOR = '//div[@class="hover-name"]';
	private $ATTRIBUTE_SELECTOR_price = '//div[@data-tstid="sizesDropdownRow"]/span/span';
	private $ATTRIBUTE_SELECTOR_NM = '//div[@id="sizesDropdownTrigger"]/span/span[@class="_ec791b"]';
	private $REVIEW_SELECTOR = '//div[@itemtype="http://schema.org/Review"]';
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

	public function getContent( $url) {
		$curl = curl_init();

		curl_setopt_array(
			$curl,
			array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_HTTPHEADER => array(
					'cache-control: no-cache',
					'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.74 Safari/537.36'
				),
			)
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
		$this->jsonDataArray['attributes'] = array();
		$jsons = $this->getValue($this->JSON_DATA_SELECTOR);
		$jsons1 = $this->getValue($this->JSON_DATA_SELECTOR1);
		if ($jsons1) {
			foreach ($jsons1 as $json) {
				if ($json) {
					$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
					$this->jsonDataArray['attributes'] = array_merge($this->jsonDataArray['attributes'], json_decode($json, true));
				}
			}
		}
		if ($jsons) {
			foreach ($jsons as $json) {
				if ($json) {
					$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
					$this->jsonDataArray = array_merge($this->jsonDataArray, json_decode($json, true));
				}
			}
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
		$title = $this->getValue($this->TITLE_SELECTOR);
		if ($title) {
			$title = array_shift($title);
			return $title;
		}
		return '';
	}

	public function getCategories() {
		$categories = array();
		if (isset($this->jsonDataArray['itemListElement']) && $this->jsonDataArray['itemListElement']) {
			foreach ($this->jsonDataArray['itemListElement'] as $categary) {
				$categories[] = $categary['item']['name'];
			}
		}
		return $categories;
	}

	public function getDescription() {
		$description = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		if ($description) {
			$description = array_shift($description);
			return $description;
		}
		return '';
	}

	public function getPrice() {
		$price = 0;
		$price = $this->getValue($this->PRICE_SELECTOR);
		if ($price) {
			$price = array_shift($price);
			$price = preg_replace('/[^0-9.]/', '', $price);
		}
		return $price;
	}

	public function getSKU() {
		$url = explode('?', $this->url);
		$url = array_shift($url);
		$sku = preg_replace('/[^0-9]/', '', $url);
		if ($sku) {
			return $sku;
		}
		return '';
	}

	public function getBrand() {
		$brand = $this->getValue($this->BRAND_SELECTOR);
		if ($brand) {
			$brand = array_shift($brand);
		}
		return $brand;
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}
		$imgs = $this->getValue($this->IMAGE_SELECTOR);

		if (isset($this->jsonDataArray['attributes']) && $this->jsonDataArray['attributes']) {
			foreach ($this->jsonDataArray['attributes'] as $key => $imgsLink) {
				$images[$key] = str_replace('imgs', 'https://n1.sdlcdn.com/imgs', $imgsLink['images']);
			}
		} elseif ($imgs) {
			$images[0] = $imgs;
		}

		if (!$images) {
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

		$features = $this->getValue($this->FEATURE_SELECTOR);
		if ($features) {
			foreach ($features as $featur) {
				$feature = explode(':', $featur);
				if (isset($feature[1])) {
					$attributes[] = array(
						'name' => $feature[0],
						'value' => $feature[1]
					);
				}
			}
			if ($attributes) {
				$featureGroups[] = array(
					'name' => 'Highlights',
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
		$this->getCombinations();

		$attrGroups = array_map(
			function ( $attr) {
				$attr['values'] = array_unique($attr['values']);
				return $attr;
			},
			$this->attributes
		);

		return $attrGroups;
	}

	public function getCombinations() {
		static $combinations = array();

		if ($combinations) {
			return $combinations;
		}
		$price = $this->getPrice();
		$sku = $this->getSKU();

		if (isset($this->jsonDataArray['attributes']) && $this->jsonDataArray['attributes']) {
			foreach ($this->jsonDataArray['attributes'] as $keys => $attrs) {
				if (!isset($this->attributes[$attrs['name']])) {
					$this->attributes[$attrs['name']] = array(
						'name' => $attrs['name'],
						'is_color' => ( stripos($attrs['name'], 'color') !== false ) ? 1 : 0,
						'values' => array()
					);
				}
				$this->attributes[$attrs['name']]['values'][] = $attrs['value'];

				if (isset($attrs['subAttributes']) && $attrs['subAttributes']) {
					foreach ($attrs['subAttributes'] as $attrVal) {
						$combinations[] = array(
							'sku' => isset($attrs['id']) ? $attrs['id'] : $sku,
							'upc' => '',
							'price' => isset($attrs['price']) ? $attrs['price'] : $price,
							'weight' => 0,
							'image_index' => $keys,
							'attributes' =>  array(
								array(
									'name' => $attrs['name'],
									'value' => $attrs['value']
								),
								array(
									'name' => $attrVal['name'],
									'value' => $attrVal['value']
								)
							)
						);
						if (!isset($this->attributes[$attrVal['name']])) {
							$this->attributes[$attrVal['name']] = array(
								'name' => $attrVal['name'],
								'is_color' => 0,
								'values' => array()
							);
						}
						$this->attributes[$attrVal['name']]['values'][] = $attrVal['value'];
					}
				} elseif (isset($attrs['value'])) {
					if (!isset($this->attributes[$attrs['name']])) {
						$this->attributes[$attrs['name']] = array(
							'name' => $attrs['name'],
							'is_color' => ( stripos($attrs['name'], 'color') !== false ) ? 1 : 0,
							'values' => array()
						);
					}
					$this->attributes[$attrs['name']]['values'][] = $attrs['value'];

					$combinations[] = array(
						'sku' => isset($attrs['id']) ? $attrs['id'] : $sku,
						'upc' => '',
						'price' => isset($attrs['price']) ? $attrs['price'] : $price,
						'weight' => 0,
						'image_index' => $keys,
						'attributes' =>  array(
							array(
								'name' => $attrs['name'],
								'value' => $attrs['value']
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

	public function getCustomerReviews( $maxReviews = 0, &$reviews = array(), $offset = 0) {
		if (!$reviews) {
			$sku = $this->getSKU();
			$this->reviewLink = 'https://www.snapdeal.com/acors/web/getSelfieList/v2?productId=' . $sku;
		}
		$reviewLink = $this->reviewLink;
		$reviewLink .= '&offset=' . $offset;

		if ($reviewLink) {
			$reviewJson = $this->getContent($reviewLink);
			if ($reviewJson) {
				$reviewArrayObject = json_decode($reviewJson, true);

				if (isset($reviewArrayObject['selfieList']) && $reviewArrayObject['selfieList']) {
					$isMaxReached = false;
					foreach ($reviewArrayObject['selfieList'] as $reviewObject) {
						if ($reviewObject['nickname']) {
							$reviews[] = array(
								'author' => $reviewObject['nickname'],
								'title' => isset($reviewObject['headLine']) ? $reviewObject['headLine'] : '',
								'content' => isset($reviewObject['comments']) ? $reviewObject['comments'] : '',
								'rating' =>$reviewObject['rating'],
								'timestamp' => gmdate('Y-m-d H:i:s', strtotime($reviewObject['createdAt']))
							);
							if (0 < $maxReviews && count($reviews) >= $maxReviews) {
								$isMaxReached = true;
								break;
							}
						}
					}
					if (false == $isMaxReached) {
						$this->getCustomerReviews($maxReviews, $reviews, $offset+6);
					}
				}
			}
		}
		return $reviews;
	}
}
