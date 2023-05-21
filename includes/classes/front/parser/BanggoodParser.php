<?php
/**
 * Banggood data parser class
 *
 * @package: product-importer
 *
 */
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class BanggoodParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $JSON_DATA_SELECTOR = '//script[@type="application/ld+json"]';
	private $VARIANT_SELECTOR = '//div[@class="product-poa"]/div';
	private $images = array();
	private $CATEGORY_SELECTOR = '//li[@property="itemListElement"]/h3/a/span';
	private $DESCRIPTION_SELECTOR = '//div[@id="J-detail-inner-wrap"]';
	private $PRICE_SELECTOR = '//span[@class="main-price"]';
	private $COVER_IMAGE_SELECTOR = '//img[@id="landingImage"]/@data-src';
	private $IMAGE_SELECTOR = '//div[@class="image-small"]/div/div/ul/li/@data-large';
	private $FEATURE_SELECTOR = '//div[@id="specification"]|//div[@class="specification-box"]/ul/li';
	private $REVIEW_SELECTOR = '//ul[@class="rev-list"]/li';
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
		$json = $this->getJson( $this->content, "attributesPoa: '", "', groupID");
		
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray['variants'] = json_decode($json, true);
		}
		$json = $this->getValue( $this->JSON_DATA_SELECTOR);
		if ($json) {
			foreach ($json as $jsonc) {
				$jsonc = str_replace(';', '', $jsonc);
				$json = iconv('UTF-8', 'UTF-8//IGNORE', $jsonc);
				$data = json_decode($json, true);
				if ($data) {
					if (!$this->jsonDataArray) {
						$this->jsonDataArray = array();
					}
					$this->jsonDataArray = array_merge($this->jsonDataArray, $data);
				}
			}
		}
		$this->jsonDataArray['products'] = array();
		
		if (isset($this->jsonDataArray['variants']) && $this->jsonDataArray['variants']) {
			$urlArr = parse_url($this->url);
			$path = explode('/', $urlArr['path']);
			if ('www.banggood.com' == $urlArr['host']) {
				$lang = $path[1];
			} else {
				$lang = ' ';
			}
			parse_str($urlArr['query'], $params);
			$id = $params['ID'];
			
			foreach ($this->jsonDataArray['variants'] as $key => $dataIds) {
				if (is_array($dataIds)) {
					if (2==count($dataIds)) {
						$L0 = strlen($dataIds[0]);
						$L1 = strlen($dataIds[1]);
						$dynamicUrl = str_replace(array('/' . $lang, $id), array('', $L1 . $dataIds[1] . $L0 . $dataIds[0]), $this->url);
						$dataHtml = $this->getContent($dynamicUrl);
					} else if (1==count($dataIds)) {
						$L0 = strlen($dataIds[0]);
						$dynamicUrl = str_replace(array('/' . $lang, $id), array('', $L0 . $dataIds[0]), $this->url);
						
						$dataHtml = $this->getContent($dynamicUrl);
					} else {
						$L0 = strlen($dataIds[0]);
						$L1 = strlen($dataIds[1]);
						$L2 = strlen($dataIds[2]);
						$dynamicUrl = str_replace(array('/' . $lang, $id), array('', $L2 . $dataIds[2] . $L1 . $dataIds[1] . $L0 . $dataIds[0]), $this->url);
						$dataHtml = $this->getContent($dynamicUrl);
					}
				}
				
				if ($dataHtml) {
					if (isset($this->jsonDataArray['offers']) && $this->jsonDataArray['offers']) {
						foreach ($this->jsonDataArray['offers'] as $data) {
							if (isset($dataIds[1])) {
								if (stripos($data['sku'], $dataIds[0] . '-' . $dataIds[1])) {
									$skuvar = $data['sku'];
									$combPrice = $data['price'];
								}
							} else if (stripos($data['sku'], $dataIds[0])) {
								$skuvar = $data['sku'];
								$combPrice = $data['price'];
							}
						}
					}
					$dom = $this->getDomObj($dataHtml);
					$xpath = new \DomXPath($dom);
									
					if ($combPrice) {
						$this->jsonDataArray['products'][$key]['price'] = preg_replace('/[^0-9.]/', '', $combPrice);
					}
					if ($skuvar) {
						$this->jsonDataArray['products'][$key]['sku'] = $skuvar;
					}
					$attribute = $xpath->query($this->VARIANT_SELECTOR);

					if ($attribute->length) {
						foreach ($attribute as $attrObject) {
							
							$attrName = @$xpath->query('.//div[contains(@class, "block-title")]/@data-text', $attrObject)->item(0)->nodeValue;
							$isColor = @$xpath->query('.//a/img', $attrObject);
							
							if ($isColor->length && isset($dataIds[1])) {
								
								$dataValueId = 'data-value-id="' . $dataIds[1] . '"';
								
								
								$this->images[$dataValueId][] = str_replace('other_items', 'large', @$xpath->query('.//a[@' . $dataValueId . ']/img/@data-src', $attrObject)->item(0)->nodeValue);
							} else {
								$dataValueId = 'data-value-id="' . $dataIds[0] . '"';
								if ($isColor->length) {
									$this->images[$dataValueId][] = str_replace('other_items', 'large', @$xpath->query('.//a[@' . $dataValueId . ']/img/@data-src', $attrObject)->item(0)->nodeValue);
								}
							}
							$attrValue = $xpath->query('.//a[@' . $dataValueId . ']/@title', $attrObject);
							if ($attrValue->length) {
								$this->jsonDataArray['products'][$key]['attributes'][$dataValueId] = array(
									'name' =>  $attrName,
									'value' =>  trim($attrValue->item(0)->nodeValue)
								);
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
		if (isset($this->jsonDataArray['name'])) {
			return $this->jsonDataArray['name'];
		}
	}

	public function getCategories() {
		$categories = array();
		$categories = $this->getValue($this->CATEGORY_SELECTOR);
		if ($categories) {
			return array_unique($categories);
		}
		return $categories;
	}

	public function getShortDescription() {
		$shortDescription = '';
		if (isset($this->jsonDataArray['description'])) {
			$shortDescription = $this->jsonDataArray['description'];
		}
		return $shortDescription;
	}

	public function getDescription() {
		$description = '';
		$descript = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		$descript = array_shift($descript);
		if ($descript) {
			$description = $descript;
		}
		return $description;
	}

	public function getPrice() {
		$price = 0;
		$prices = $this->getValue($this->PRICE_SELECTOR);
		$pricec = array_shift($prices);
		if ($pricec) {
			$price = preg_replace('/[^0-9.]/', '', $pricec);
		}
		return $price;
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['sku'][0])) {
			return $this->jsonDataArray['sku'][0];
		}
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['brand']['name'])) {
			return $this->jsonDataArray['brand']['name'];
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
		$sku = $this->getSKU();

		if ($images) {
			return $images;
		}
		$image = $this->getValue($this->IMAGE_SELECTOR);

		$images = $this->images;
		if ($image) {
			$images[0] = $image;
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

		$featureTexts = $this->getValue($this->FEATURE_SELECTOR);

		$attributes = array();
		foreach ($featureTexts as $f) {
			
			list($name, $value) = explode(':', $f . ':');

			if (!empty($value)) {
				$attributes[] = array(
					'name' => trim($name),
					'value' => trim($value)
				);
			}
		}

		if ($attributes) {
			$featureGroups[] = array(
				'name' => 'General',
				'attributes' => $attributes,
			);
		}

		return $featureGroups;
	}
	
	public function getWeight() {
		static $weight = array();
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
							'is_color' => stripos($attrVals['name'], 'Color') !== false ? 1 : 0,
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
				$imgaeIndex = 0;
				
				
				if (isset($attrVals['attributes'])) {
					
					foreach (array_keys($attrVals['attributes']) as $key) {
						if (isset($this->images[$key])) {
							$imgaeIndex = $key;
							break;
						}
					}
					
					$combinations[] = array(
						'sku' => isset($attrVals['sku']) ? $attrVals['sku'] : $sku,
						'upc' => 0,
						'price' => isset($attrVals['price']) ? $attrVals['price'] : $price,
						'weight' => $weight,
						'image_index' => $imgaeIndex,
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
	
	public function getCustomerReviews() {
		$reviews = array();
		$mainUrl = explode('?', $this->url);
		$url = str_replace('-p-', '-reviews-p', $mainUrl[0]);
		
		$dataHtml = $this->getContent($url);
		$dom = $this->getDomObj($dataHtml);
		$xpath = new \DomXPath($dom);
		$reviewArrayObject = $xpath->query($this->REVIEW_SELECTOR);
		
		if ($reviewArrayObject->length) {
			$videos = array(); 
			
			foreach ($reviewArrayObject as $reviewObject) {
				$author = $xpath->query('.//div[@class="reviewer-name"]/a', $reviewObject);
				$stars1 = 0;
						$images = array(); 		
				if ($author->length) {
					$stars = $xpath->query('.//i[contains(@class, "rev-score-star")]/i/@style', $reviewObject)->item(0)->nodeValue;
					$images[] = @$xpath->query('.//div/ul/li/img/@data-src', $reviewObject)->item(0)->nodeValue;
					if ($stars) {
						$stars1 = round(preg_replace('/[^0-9.]/', '', $stars)/20);
					}					

					$reviews[] = array(
						'author' => $author->item(0)->nodeValue,
						'title' => '',
						'content' => trim($xpath->query('.//p[@class="rev-txt"]', $reviewObject)->item(0)->nodeValue),
						'rating' => $stars1 ? $stars1 : 1,
						'images' => array_filter($images),
						'videos' => array_filter($videos),
						'timestamp' => $xpath->query('.//span[@class="rev-time"]', $reviewObject)->item(0)->nodeValue
					);
				}
			}
		}
		return $reviews;
	}
}
