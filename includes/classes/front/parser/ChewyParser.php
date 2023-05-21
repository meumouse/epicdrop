<?php
/**
 * Chewy data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.2 */

class ChewyParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $attributes = array();
	private $images = array();
	private $JSON_DATA_SELECTOR = '//script[@type="application/ld+json"]';
	private $TITLE_SELECTOR = '//div[@data-testid="product-title"]/h1';
	private $SHORT_DESCRIPTION_SELECTOR = '//section[@id="KEY_BENEFITS-section"]';
	private $DESCRIPTION_SELECTOR = '//div[@class="kib-accordion-new _22O2NqSsuGtEjLIUaQH0SH"]';
	private $IMAGE_SELECTOR = '//ul[@class="kib-carousel__content"]/li/button/img/@srcset';
	private $COVER_IMAGE_SELECTOR = '//div[@data-testid="image-magnify"]/img/@src';
	private $PRICE_SELECTOR = '//div[@data-testid="advertised-price"]';
	private $FEATURE_SELECTOR = '//div[@data-testid="see-more"]/div/section[2]/div/table/tbody/tr';
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
		
		//print_r($this->getAttributes());
		//print_r($this->getCombinations());
		//print_r($this->jsonDataArray);
		//die;
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

	public function productKey() {
		
		$sku = $this->getSKU();
		if (isset($this->jsonDataArray['ROOT_QUERY']['product({"id":"' . $sku . '"})']['__ref'])) {
			return $this->jsonDataArray['ROOT_QUERY']['product({"id":"' . $sku . '"})']['__ref'];
		}
	}
	
	public function getTitle() {
		$title = $this->getValue($this->TITLE_SELECTOR);
		$title = array_shift($title);
		if ($title) {
			return $title;
		}
	}

	public function getCategories() {
		$categories = array();
		if (isset($this->jsonDataArray['itemListElement']) && $this->jsonDataArray['itemListElement']) {
			foreach ($this->jsonDataArray['itemListElement'] as $categorey) {
				$categories[] = $categorey['name'];
			}
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
		
		$parts = explode('/', parse_url($this->url, PHP_URL_PATH));
		if ($parts) {
			return end($parts);
		}
		return '';
	}
	
	public function getUPC() {
		$jsons = $this->getValue( $this->JSON_DATA_SELECTOR);
		preg_match('/"gtin12": "([0-9]+)"/i', $jsons[2], $match);
		
		if (isset($match[1])) {
			return $match[1];
		}
		return '';
	}

	public function getBrand() {
		$data = $this->productKey();
		if (isset($this->jsonDataArray[$data]['manufacturerName'])) {
			return $this->jsonDataArray[$data]['manufacturerName'];
		}
		return '';
	}

	public function getCoverImage() {
		$cover = $this->getValue($this->COVER_IMAGE_SELECTOR);
		$cover = array_shift($cover);
		if ($cover) {
			return $cover;
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}
		$this->getCombinations();
		$images = $this->images;
		$imges = $this->getValue($this->IMAGE_SELECTOR);
		if ($imges) {
			foreach ($imges as $img) {
				$img = explode(' ', $img);
				$image = array_shift($img);
				$images[0][] = str_replace('SS108', 'SL1500', $image);
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
		$features = $this->xpath->query($this->FEATURE_SELECTOR);
		
		if ($features->length) {
			
			foreach ($features as $speci) {
				$name = $this->xpath->query('.//th', $speci)->item(0)->nodeValue;
				$value = $this->xpath->query('.//td', $speci)->item(0)->nodeValue;
				$attributes[] = array(
					'name' => $name,
					'value' => $value
				);
			}
			
			if ($attributes) {
				$featureGroups[] = array(
					'name' => 'Specifications',
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
		
		$upc = $this->getUPC();
		$sku = $this->getSKU();
		$weight = $this->getWeight();
		
		$productKey = $this->productKey();
		
		if (isset($this->jsonDataArray[$productKey]['items']) && $this->jsonDataArray[$productKey]['items']) {
			
			foreach ($this->jsonDataArray[$productKey]['items'] as $combArr) {
				$dataItem = $combArr['__ref'];
				$combSku = $this->jsonDataArray[$dataItem]['entryID'];
				
				if (isset($this->jsonDataArray[$dataItem]['fullImage'])) {
					$img = $this->jsonDataArray[$dataItem]['fullImage'];
					$img = array_pop($img);
					$this->images[$combSku][] = str_replace('SX275', 'SL1500', $img);
				}
				$combPrice = 0;
				
				if (isset($this->jsonDataArray[$dataItem]['advertisedPrice'])) {
					$combPrice = str_replace('$', '', $this->jsonDataArray[$dataItem]['advertisedPrice']);
				}
				$attrVals = array();
				
				if (isset($this->jsonDataArray[$dataItem]['attributeValues({"usage":["DEFINING"]})']) && $this->jsonDataArray[$dataItem]['attributeValues({"usage":["DEFINING"]})']) {
					foreach ($this->jsonDataArray[$dataItem]['attributeValues({"usage":["DEFINING"]})'] as $attrs) {
						$attrVal = $attrs['__ref'];
						
						
						if (isset($this->jsonDataArray[$attrVal]['value'])) {
							$value = $this->jsonDataArray[$attrVal]['value'];
							$valueName = $this->jsonDataArray[$attrVal]['attribute']['__ref'];
							$name = $this->jsonDataArray[$valueName]['name'];
							
							$attrVals[] = array(
								'name' => $name,
								'value' => $value
							);

							$key = base64_encode($name);

							if (!isset($this->attributes[$key])) {
								$this->attributes[$key] = array(
									'name' => $name,
									'is_color' => stripos($name, 'Color') !== false ? 1 : 0,
									'values' => array()
								);
							}
							$this->attributes[$key]['values'][] = $value;
						}
					}
					
					$combinations[] = array(
						'sku' => $combSku ? $combSku : $sku,
						'upc' => $upc,
						'price' => $combPrice ? $combPrice : $price,
						'weight' => $weight,
						'image_index' => $combSku,
						'attributes' => $attrVals
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
		
	public function getCustomerReviews( $maxReviews = 0, &$reviews = array()) {
		$maxReviews = $maxReviews > 0 ? $maxReviews : 100;
		if (!$reviews) {
			$host = parse_url($this->url, PHP_URL_HOST);
			$id = $this->getSKU();
			$this->reviewLink = 'https://' . $host . '/api/pdp-graphql/graphql?operationName=Reviews&variables={"id":"' . $id . '","first":' . $maxReviews . ',"sort":"MOST_RELEVANT","after":"YXJyYXljb25uZWN0aW9uOjk="}&extensions={"persistedQuery":{"version":1,"sha256Hash":"f1ad95c550af020ebbc5c6da08fd478b1cf25b5e911cba4710d773b84e063730"}}';
		}
		
		$reviewLink = $this->reviewLink;

		if ($reviewLink) {
			$json = $this->getContent($reviewLink);

			if ($json) {
				$reviewData = json_decode($json, true);
				$isMaxReached = false;

				if (isset($reviewData['data']['product']['reviews']['edges']) 
					&& $reviewData['data']['product']['reviews']['edges']) {
						
					foreach ($reviewData['data']['product']['reviews']['edges'] as $revie) {
						$review = $revie['node'];
						
						if (isset($review['text']) && $review['text']) {
							$reviews[] = array(
								'author' => isset($review['submittedBy']) ? $review['submittedBy'] : '',
								'title' => isset($review['title']) ? $review['title'] : '',
								'content' => $review['text'],
								'rating' => $review['rating'],
								'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['submittedAt']))
							);
						}
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
		$data = $this->productKey();
		
		if (isset($this->jsonDataArray[$data]['reviews:{}']['edges']) && $this->jsonDataArray[$data]['reviews:{}']['edges']) {
			
			foreach ($this->jsonDataArray[$data]['reviews:{}']['edges'] as $revie) {
				$reviewData = $revie['node']['__ref'];
				$review = $this->jsonDataArray[$reviewData];
				
				if (isset($review['text']) && $review['text']) {
					$reviews[] = array(
						'author' => isset($review['submittedBy']) ? $review['submittedBy'] : '',
						'title' => isset($review['title']) ? $review['title'] : '',
						'content' => $review['text'],
						'rating' => $review['rating'],
						'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['submittedAt']))
					);
				}
			}
		}
		return $reviews;
	}
}
