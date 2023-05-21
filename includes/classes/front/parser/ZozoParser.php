<?php
/**
 * Zozo data parser class
 *
 * @package: product-importer
 *
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class ZozoParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $images = array();
	private $SIZE_VARIANT_SELECTOR = '//ul[@class="p-goods-add-cart-list"]/li';
	private $CATEGORY_SELECTOR = '//ol[@class="c-breadcrumb-list"]/li';
	private $PRICE_SELECTOR = '//div[@class="p-goods-information__price"]';
	private $IMAGE_SELECTOR = '//ul/li/div/span/img/@data-main-image-src';
	private $FEATURE_SELECTOR = '//dl[@class="p-goods-information-spec-horizontal-list"]';
	private $ATTRIBUTE_SELECTOR = '//dl[contains(@class, "tm-sale-prop")]/dt[@class="tb-metatit"]';
	private $COLOR_NAME_SELECTOR = '//dl[contains(@class, "tm-img-prop")]/dt[@class="tb-metatit"]';
	private $REVIEW_SELECTOR = '//div[@class="rate-grid"]/table/tbody/tr';
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

		//Set json data array
		$this->setJsonData();
	}
	
	public function getContent( $url, $postData = array(), $additionalHeaders = array()) {
		$curl = curl_init($url);
		$multpleUserAgent = array(
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.74 Safari/537.36',
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.0.0 Safari/537.36 [ip:93.66.66.228]',
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.0.0 Safari/537.36 [ip:151.47.23.12]',
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:101.0) Gecko/20100101 Firefox/101.0 [ip:93.40.186.242]'
		);
		
		$userAgent = rand(0, 3);
		$headers = array(
			'cache-control: no-cache',
			'user-agent: ' . $multpleUserAgent[$userAgent]
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
		$json = $this->getJson( $this->content, 'application/ld+json">', '</script>');
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = json_decode($json, true);
		}
		
		$dataAttribute = $this->xpath->query($this->SIZE_VARIANT_SELECTOR);
		$this->jsonDataArray['products'] = array();
		
		if ($dataAttribute->length) {
			foreach ($dataAttribute as $dataVarlink) {
				
				$sku = $this->xpath->query('.//@id', $dataVarlink)->item(0)->nodeValue;
				$size = $this->xpath->query('.//@data-size', $dataVarlink)->item(0)->nodeValue;
				$skuvar =  preg_replace('/[^0-9]/', '', $sku);
				
				if ($this->jsonDataArray) {
					foreach ($this->jsonDataArray as $mainData) {
						if (isset($mainData['sku']) && $mainData['sku']) {
							if ($skuvar == $mainData['sku']) {
								
								if (isset($mainData['image'])) {
									$this->images[$skuvar][] = str_replace(array('c.', '_500'), array('o.', ''), $mainData['image']);
								}
								
								if (isset($mainData['offers']['price'])) {
									$this->jsonDataArray['products'][$skuvar]['price'] = 
									preg_replace('/[^0-9.]/', '', $mainData['offers']['price']);
								}
								
								$this->jsonDataArray['products'][$skuvar]['sku'] = $skuvar;
								
								if (isset($mainData['color'])) {
									$this->jsonDataArray['products'][$skuvar]['attributes'] = array(
										array(
											'name' => '色',
											'value' => $mainData['color']
										),
										array(
											'name' => 'サイズ',
											'value' => $size
										)
									);
								}
							}
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
		if (isset($this->jsonDataArray[0]['name'])) {
			return $this->jsonDataArray[0]['name'];
		}
	}

	public function getCategories() {
		$categories = array();
		$categories = $this->getValue($this->CATEGORY_SELECTOR);
		if ($categories) {
			$categories = array_unique($categories);
		}
		return $categories;
	}

	public function getShortDescription() {
		$shortDescription = '';
		return $shortDescription;
	}

	public function getDescription() {
		$description = '';
		if (isset($this->jsonDataArray[0]['description'])) {
			$description = $this->jsonDataArray[0]['description'];
		}
		return $description;
	}

	public function getPrice() {
		$price = 0;
		$prices = $this->getValue($this->PRICE_SELECTOR);
		$pricec = array_shift($prices);
		if ($pricec) {
			$price = str_replace(',', '.', preg_replace('/[^0-9,.]/', '', $pricec));
		}
		return $price;
	}

	public function getSKU() {
		$urlArr = explode('did=', $this->url);
		$skuArr = explode('&', array_pop($urlArr));
		$sku = $skuArr[0];
		if ($sku) {
			return $sku;
		} elseif (isset($this->jsonDataArray[0]['sku'])) {
			return $this->jsonDataArray[0]['sku'];
		}
	}

	public function getBrand() {
		if (isset($this->jsonDataArray[0]['brand']['name'])) {
			return $this->jsonDataArray[0]['brand']['name'];
		}
		return '';
	}

	public function getCoverImage() {
		if (isset($this->jsonDataArray[0]['image'])) {
			return str_replace(array('c.', '_500'), array('o.', ''), $this->jsonDataArray[0]['image']);
		}
		return '';
	}
	
	public function getImages() {
		static $images = array();
		$sku = $this->getSKU();

		if ($images) {
			return $images;
		}
		
		$images = $this->images;
		if (!$images) {
			
			$imageArray = $this->getValue($this->IMAGE_SELECTOR);
			
			if ($imageArray) {
				
				$images[0] = array();
				
				foreach ($imageArray as $image) {
					$images[0][] = str_replace(array('c.', '_500'), array('o.', ''), $image);
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
		$features = $this->xpath->query($this->FEATURE_SELECTOR);
				
		if ($features->length) {
			
			foreach ($features as $speci) {
				$name = $this->xpath->query('.//dt[@class="p-goods-information-spec-horizontal-list__term"]', $speci)->item(0)->nodeValue;
				$value = $this->xpath->query('.//dd[@class="p-goods-information-spec-horizontal-list__description"]', $speci)->item(0)->nodeValue;
				if ($value) {
					$attributes[] = array(
						'name' => trim($name),
						'value' => preg_replace('/\s+/', ' ', $value)
					);
				}
			}
			if ($attributes) {
				$featureGroups[] = array(
					'name' => '全般的',
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
		
		if (isset($this->jsonDataArray['products']) && $this->jsonDataArray['products']) {
			foreach ($this->jsonDataArray['products'] as $attrs) {
				if (isset($attrs['attributes']) && $attrs['attributes']) {
					foreach ($attrs['attributes'] as $attrVals) {
				
						$key = base64_encode($attrVals['name']);
				
						if (!isset($attrGroups[$key])) {
							$attrGroups[$key] = array(
							'name' => $attrVals['name'],
							'is_color' => stripos($attrVals['name'], '色') !== false ? 1 : 0,
							'values' => array()
							);
						}
					
						if (!in_array($attrVals['value'], $attrGroups[$key]['values'])) {
							$attrGroups[$key]['values'][] = $attrVals['value'];
						}
					}
				}
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
		$attrs = $this->getAttributes();
		$weight = $this->getWeight();
		
		if (isset($this->jsonDataArray['products']) && $this->jsonDataArray['products']) {
			foreach ($this->jsonDataArray['products'] as $keys => $attrVals) {
				if (isset($attrVals['attributes'])) {
					$combinations[] = array(
						'sku' => isset($attrVals['sku']) ? $attrVals['sku'] : $sku,
						'upc' => 0,
						'price' => isset($attrVals['price']) ? $attrVals['price'] : $price,
						'weight' => $weight,
						'image_index' => $keys,
						'attributes' => $attrVals['attributes']
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
	
}
