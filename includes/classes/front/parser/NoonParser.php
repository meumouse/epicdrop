<?php
/**
 * Noon data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class NoonParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $JSON_DATA_SELECTOR = '//script[@type="application/ld+json"]';
	private $DESCRIPTION_SELECTOR = '//section[contains(@class, "imqJMb")]/div';
	private $PRICE_SELECTOR = '//div[@class="priceNow"]';
	private $IMAGE_SELECTOR = '//div[contains(@class, "cAxeFA")]/img/@src';
	private $FEATURE_NAME = '//span[contains(@class, "gohNUx")]';
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
		$jsons = $this->getValue($this->JSON_DATA_SELECTOR);
		if ($jsons) {
			foreach ($jsons as $json) {
				if ($json) {
					$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
					$this->jsonDataArray = array_merge($this->jsonDataArray, json_decode($json, true));
				}
			}
		}
		$json = $this->getJson($this->content, ',"props":', ',"navConfig"');
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
				$categories[] = $categary['item']['name'];
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
		$descript = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		$descript = array_shift($descript);

		if (isset($this->jsonDataArray['catalog']['product']['feature_bullets']) && $this->jsonDataArray['catalog']['product']['feature_bullets']) {
			foreach ($this->jsonDataArray['catalog']['product']['feature_bullets'] as $discript) {
				$description .= $discript;
			}
		}
		if (isset($this->jsonDataArray['catalog']['product']['long_description'])) {
			$description .= $this->jsonDataArray['catalog']['product']['long_description'];
		}
		if ($descript) {
			$description .= $descript;
		}
		return $description;
	}

	public function getPrice() {
		$price = 0;
		$price = $this->getValue($this->PRICE_SELECTOR);
		$price = array_shift($price);
		if ($price) {
			$price = preg_replace('/[^0-9.]/', '', $price);
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
		if (isset($this->jsonDataArray['image'][0])) {
			return $this->jsonDataArray['image'][0];
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}
		$img = $this->getValue($this->IMAGE_SELECTOR);

		if (isset($this->jsonDataArray['catalog']['product']['groups']) && $this->jsonDataArray['catalog']['product']['groups']) {
			foreach ($this->jsonDataArray['catalog']['product']['groups'] as $imgsSku) {
				foreach ($imgsSku['options'] as $key => $imgsClr) {
					$productName = $imgsClr['url'];
					$skuId = $imgsClr['sku'];
					$city = explode('/', $this->url);
					$city = $city[3];
					$imgData = 'https://www.noon.com/' . $city . '/' . $productName . '/' . $skuId . '/p/';
					$htmlData = $this->getContent($imgData);

					if ($htmlData) {
						$json = $this->getJson($htmlData, ',"props":', ',"navConfig"');
						$jsonData = json_decode($json, true);
						if (isset($jsonData['catalog']['product']['image_keys']) && $jsonData['catalog']['product']['image_keys']) {
							foreach ($jsonData['catalog']['product']['image_keys'] as $imges) {
								$images[$key][] = 'https://z.nooncdn.com/products/tr:n-t_800/' . $imges . '.jpg';
							}
						}
					}
				}
			}
		} elseif (isset($this->jsonDataArray['catalog']['product']['image_keys']) && $this->jsonDataArray['catalog']['product']['image_keys']) {
			foreach ($this->jsonDataArray['catalog']['product']['image_keys'] as $imges) {
				$images[0][] = 'https://z.nooncdn.com/products/tr:n-t_800/' . $imges . '.jpg';
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
		if (isset($this->jsonDataArray['catalog']['product']['specifications']) && $this->jsonDataArray['catalog']['product']['specifications']) {
			foreach ($this->jsonDataArray['catalog']['product']['specifications'] as $features) {
				if (isset($features['value'])) {
					$attributes[] = array(
						'name' => $features['name'],
						'value' => $features['value']
					);
				}
			}
		}
		$featureName = $this->getValue($this->FEATURE_NAME);
		$featureName = array_shift($featureName);
		if ($attributes) {
			$featureGroups[] = array(
				'name' => $featureName,
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
		if (isset($this->jsonDataArray['catalog']['product']['groups']) && $this->jsonDataArray['catalog']['product']['groups']) {
			foreach ($this->jsonDataArray['catalog']['product']['groups'] as $attrs) {
				$colorAttr = array();
				foreach ($attrs['options'] as $colors) {
					if (isset($colors['name'])) {
						$colorAttr[] = $colors['name'];
					}
				}
			}
			if ($colorAttr) {
				$attrGroups[] = array(
					'name' => $attrs['name'],
					'is_color' => 1,
					'values' => $colorAttr
				);
			}
		}
		if (isset($this->jsonDataArray['catalog']['product']['variants']) && $this->jsonDataArray['catalog']['product']['variants']) {
			$sizeAttr = array();
			foreach ($this->jsonDataArray['catalog']['product']['variants'] as $sizes) {
				if ($sizes['variant']) {
					$sizeAttr[] = $sizes['variant'];
				}
			}
			if ($sizeAttr) {
				$attrGroups[] = array(
					'name' => 'Size',
					'is_color' => 0,
					'values' => $sizeAttr
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

		if (isset($this->jsonDataArray['catalog']['product']['groups']) && $this->jsonDataArray['catalog']['product']['groups']) {
			foreach ($this->jsonDataArray['catalog']['product']['groups'] as $attrs) {
				foreach ($attrs['options'] as $keys => $colors) {
					if (isset($this->jsonDataArray['catalog']['product']['variants']) && $this->jsonDataArray['catalog']['product']['variants']) {
						foreach ($this->jsonDataArray['catalog']['product']['variants'] as $sizes) {
							if (isset($sizes['variant']) && $sizes['variant']) {
								$combinations[] = array(
									'sku' => isset($colors['sku']) ? $colors['sku'] : $sku,
									'upc' => 0,
									'price' => isset($sizes['offers'][0]['price']) ? $sizes['offers'][0]['price'] : $price,
									'weight' => 0,
									'image_index' => $keys,
									'attributes' =>  array(
										array(
											'name' => $attrs['name'],
											'value' => $colors['name']
										),
										array(
											'name' => 'Size',
											'value' => $sizes['variant']
										)
									)
								);
							} else {
								$combinations[] = array(
									'sku' => isset($colors['sku']) ? $colors['sku'] : $sku,
									'upc' => 0,
									'price' => isset($sizes['offers'][0]['price']) ? $sizes['offers'][0]['price'] : $price,
									'weight' => 0,
									'image_index' => $keys,
									'attributes' =>  array(
										array(
											'name' => $attrs['name'],
											'value' => $colors['name']
										)
									)
								);
							}
						}
					}
				}
			}
		} elseif (isset($this->jsonDataArray['catalog']['product']['variants']) && $this->jsonDataArray['catalog']['product']['variants']) {
			foreach ($this->jsonDataArray['catalog']['product']['variants'] as $sizes) {
				if (isset($sizes['variant']) && $sizes['variant']) {
					$combinations[] = array(
						'sku' => isset($colors['sku']) ? $colors['sku'] : $sku,
						'upc' => 0,
						'price' => isset($sizes['offers'][0]['price']) ? $sizes['offers'][0]['price'] : $price,
						'weight' => 0,
						'image_index' => 0,
						'attributes' =>  array(
							array(
								'name' => 'Size',
								'value' => $sizes['variant']
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

	public function getCustomerReviews( $maxReviews = 0, &$reviews = array(), $page = 1) {
		if (!$reviews) {
			$sku = $this->getSKU();
			$this->reviewLink = 'https://www.noon.com/_svc/reviews/fetch/v1/product-reviews/list';
		}
		$reviewLink = $this->reviewLink;
		$postData = array('sku' => $sku, 'lang' => 'xx', 'ratings' => array(1, 2, 3, 4, 5), 'provideBreakdown' => true, 'page' => $page);
		$header = array('Content-Type: application/json');
		if ($reviewLink) {
			$reviewJson = $this->getContent($reviewLink, $postData, $header);

			if ($reviewJson) {
				$reviewArrayObject = json_decode($reviewJson, true);

				if (isset($reviewArrayObject['list']) && $reviewArrayObject['list']) {
					$isMaxReached = false;
					foreach ($reviewArrayObject['list'] as $reviewObject) {
						if ($reviewObject['displayName']) {
							$reviews[] = array(
								'author' => $reviewObject['displayName'],
								'title' => '',
								'content' => isset($reviewObject['comment']) ? $reviewObject['comment'] : '',
								'rating' =>$reviewObject['rating'],
								'timestamp' => gmdate('Y-m-d H:i:s', strtotime($reviewObject['updatedAt']))
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
		return $reviews;
	}
}
