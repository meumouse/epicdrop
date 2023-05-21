<?php
/**
 * Catch data parser class
 *
 * @package: product-importer
 *
 */
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class CatchParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $JSON_DATA_SELECTOR = '//script[@type="application/json"]';
	private $TITLE_SELECTOR = '//h1[@itemprop="name"]';
	private $CATEGORY_SELECTOR = '//span[@itemprop="itemListElement"]/a/span';
	private $SHORT_DESCRIPTION_SELECTOR = '//div[@class="description-block"]/p[2]';
	private $DESCRIPTION_SELECTOR = '//div[@class="description-block"]';
	private $PRICE_SELECTOR = '//div[@class="price--price-parts"]';
	private $SKU_SELECTOR = '//meta[@itemprop="productID"]/@content';
	private $BRAND_SELECTOR = '//meta[@itemprop="brand"]/@content';
	private $COVER_IMAGE_SELECTOR = '//div[@class="main-image"]/div/img/@srcset';
	private $IMAGE_SELECTOR = '//ul[contains(@class, "js-product-thumbnails__wrapper swiper-wrapper")]/li/a/@data-large-img';
	private $FEATURE_SELECTOR = '//div[@class="description-block"]/ul[@id="sublist"]/li';
	private $ATTR_NAME_SELECTOR = '//div[contains(@class, "attribute-modal-header")]/label';
	private $ATTRIBUTE_SELECTOR = '//div[@class="product-attribute-list"]/ul/li/@data-size-label';
	private $REVIEW_SELECTOR = '//div[@itemtype="http://schema.org/Review"]';
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
		$jsons = $this->getValue( $this->JSON_DATA_SELECTOR);
		
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
		$json = $this->getJson($this->content, 'APOLLO_STATE__ = "', '"; window');
		$json = stripslashes($json);
		
		$jsonData = json_decode($json, true, JSON_UNESCAPED_SLASHES);
		if ($json) {
			$this->jsonDataArray = array_merge($this->jsonDataArray, $jsonData);
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
		$title = $this->getValue($this->TITLE_SELECTOR);
		$title = array_shift($title);
		if ($title) {
			return $title;
		}
	}

	public function getCategories() {
		$categories = $this->getValue($this->CATEGORY_SELECTOR);
		if ($categories) {
			return array_unique($categories);
		}
		return $categories;
	}

	public function getShortDescription() {
		$shortDescription = '';
		$descript = $this->getValue($this->SHORT_DESCRIPTION_SELECTOR, true);
		$descript = array_shift($descript);
		if ($descript) {
			$shortDescription = $descript;
		}
		return $shortDescription;
	}

	public function getDescription() {
		$description = '';
		$descript = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		$descript = array_shift($descript);
		if ($descript) {
			$description = str_replace('style="height:0;display:none"', '', $descript);
		}
		return $description;
	}

	public function getPrice() {
		$price = 0;
		$prices = str_replace(',', '.', $this->getValue($this->PRICE_SELECTOR));
		$price = array_shift($prices);
		if ($price) {
			return preg_replace('/[^0-9.]/', '', $price);
		}
	}

	public function getSKU() {
		$sku = $this->getValue($this->SKU_SELECTOR);
		$sku = array_shift($sku);
		if ($sku) {
			return $sku;
		}
		return '';
	}

	public function getBrand() {
		$brand = $this->getValue($this->BRAND_SELECTOR);
		$brand = array_shift($brand);
		if ($brand) {
			return $brand;
		}
		return '';
	}

	public function getCoverImage() {
		$cover = $this->getValue($this->COVER_IMAGE_SELECTOR);
		$cover = array_shift($cover);
		$cover = explode(' ', $cover);
		$cover = array_reverse($cover);
		if ($cover[1]) {
			return $cover[1];
		}
		return '';
	}
	
	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}
		
		$imges = $this->getValue($this->IMAGE_SELECTOR);
		if ($imges) {
			$images[0] = $imges;
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
		
		$attributes = array();

		if ($featureGroups) {
			return $featureGroups;
		}
		
		$features = $this->getValue($this->FEATURE_SELECTOR);
		
		if ($features) {
			
			foreach ($features as $speci) {
				$feature = explode(':', $speci);
				if (isset($feature[1])) {
					$attributes[] = array(
						'name' => $feature[0],
						'value' => $feature[1]
					);
				}
				
			}
			
			if ($attributes) {
				$featureGroups[] = array(
					'name' => 'Features',
					'attributes' => $attributes,
				);
			}
		}
		
		return $featureGroups;
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
					if (stripos($attr['name'], 'Weight') !== false) {
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
	
	public function getAttributes() {
		static $attrGroups = array();
		if ($attrGroups) {
			return $attrGroups;
		}
		$attrName = $this->getValue($this->ATTR_NAME_SELECTOR);
		$attrName = array_shift($attrName);
		$attribute = $this->getValue($this->ATTRIBUTE_SELECTOR);
		
		if ($attribute) {
			
			$attrGroups[] = array(
				'name' => $attrName,
				'is_color' => 0,
				'values' => $attribute
			);
			
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
					'price' => $price,
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
		
	public function getCustomerReviews( $maxReviews = 0, &$reviews = array(), $offset = 1) {
		
		if (!$reviews) {
			$host = parse_url($this->url, PHP_URL_HOST);
			$id = $this->getSKU();
			$this->reviewLink = 'https://' . $host . '/product/' . $id . '/review_list_ajax?limit=10';
		}

		$reviewLink = $this->reviewLink;
		$reviewLink .= '&offset=' . $offset;
		
		if ($reviewLink) {
			$reviewHtml = $this->getContent($reviewLink);
			if ($reviewHtml) {
				$dom = $this->getDomObj($reviewHtml);
				$xpath = new \DomXPath($dom);

				$reviewArrayObject = $xpath->query($this->REVIEW_SELECTOR);
				if ($reviewArrayObject->length) {
					$isMaxReached = false;
					
					foreach ($reviewArrayObject as $reviewObject) {
						$author = $xpath->query('.//p[@itemprop="author"]', $reviewObject);
						$stars1 = 0;
						
						if ($author->length) {
							$stars = $xpath->query('.//meta[@itemprop="ratingValue"]/@content', $reviewObject);
							
							if ($stars->length) {
								$stars1 = $stars->item(0)->nodeValue;
							}					
							
							$reviews[] = array(
								'author' => $author->item(0)->nodeValue,
								'title' => '',
								'content' => trim($xpath->query('.//p[@class="review-text"]|.//p[@itemprop="description"]', $reviewObject)->item(0)->nodeValue),
								'rating' => $stars1 ? $stars1 : 1,
								'images' => '',
								'videos' => '',
								'timestamp' => $xpath->query('.//meta[@itemprop="datePublished"]/@content', $reviewObject)->item(0)->nodeValue
							);
							
							if (0 < $maxReviews && count($reviews) >= $maxReviews) {
								$isMaxReached = true;
								break;
							}
						}
					}
					
					if (false == $isMaxReached) {
						$this->getCustomerReviews($maxReviews, $reviews, $offset+10);
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
				$author = $this->xpath->query('.//p[@itemprop="author"]', $reviewObject);
				$stars1 = 0;
				
				if ($author->length) {
					$stars = $this->xpath->query('.//meta[@itemprop="ratingValue"]/@content', $reviewObject);
					
					if ($stars->length) {
						$stars1 = $stars->item(0)->nodeValue;
					}					
					
					$reviews[] = array(
						'author' => $author->item(0)->nodeValue,
						'title' => '',
						'content' => trim($this->xpath->query('.//p[@class="review-text"]', $reviewObject)->item(0)->nodeValue),
						'rating' => $stars1 ? $stars1 : 1,
						'images' => '',
						'videos' => '',
						'timestamp' => $this->xpath->query('.//meta[@itemprop="datePublished"]/@content', $reviewObject)->item(0)->nodeValue
					);
				}
			}
		}
		return $reviews;
	}
}
