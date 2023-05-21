<?php
/**
 * Privalia data parser class
 *
 * @package: product-importer
 *
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class PrivaliaParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $JSON_DATA_SELECTOR2 = '//script[@type="application/json"]';
	private $CATEGORY_SELECTOR = '//ul[contains(@class, "dzzbxB")]/li/a|//ul[@id="breadcrumb_data"]/li/a';
	private $SKU_SELECTOR = '//input[@name="product_pk"]/@value';
	private $ATTR_NAME_SELECTOR = '//div[contains(@class, "cgFCZJ")]/div|//fieldset[@class="form_fieldset product_size "]/label/span[1]';
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

		//Set json data array
		$this->setJsonData();
	}
	
	public function getContent( $url, $postData = array(), $additionalHeaders = array()) {
		$curl = curl_init($url);
		$multpleUserAgent = array(
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.74 Safari/537.36',
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.5005.63 Safari/537.36 Edg/102.0.1245.30',
			'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.5005.63 Safari/537.36 Edg/102.0.1245.33',
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.0.0 Safari/537.36 [ip:93.66.66.228]',
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.0.0 Safari/537.36 [ip:151.47.23.12]',
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:101.0) Gecko/20100101 Firefox/101.0 [ip:93.40.186.242]'
		);
		
		$userAgent = rand(0, 5);
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
		$sku = $this->getSKU();
		$host = parse_url($this->url, PHP_URL_HOST);
		$dataUrl = 'https://' . $host . '/api/catalog/productsheet/v1/product/' . $sku;
		
		if ($dataUrl) {
			$json1 = $this->getContent($dataUrl);
			$this->jsonDataArray = json_decode($json1, true);
		}
		$json = $this->getValue( $this->JSON_DATA_SELECTOR2);
		
		if ($json) {
			foreach ($json as $jsonc) {
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
	
	public function shortData() {
		if (isset($this->jsonDataArray['props']['initialState']['ProductInformation']['data']['result'])) {
			return $this->jsonDataArray['props']['initialState']['ProductInformation']['data']['result'];
		}
	}
	
	public function getTitle() {
		$data = $this->shortData();
		$sku = $this->getSKU();
		if (isset($data['name'])) {
			return $data['name'];
		} elseif (isset($this->jsonDataArray['data']['product'][$sku]['name'])) {
			return $this->jsonDataArray['data']['product'][$sku]['name'];
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
		$data = $this->shortData();
		if (isset($data['description'])) {
			$shortDescription = $data['description'];
		}
		return $shortDescription;
	}

	public function getDescription() {
		$description = '';
		$sku = $this->getSKU();
		$data = $this->shortData();
		$host = parse_url($this->url, PHP_URL_HOST);
		if (isset($data['htmlDatasheet'])) {
			$htmlDescription = $this->getContent('https://' . $host . $data['htmlDatasheet']);
			$description =  $htmlDescription;
		} elseif (isset($this->jsonDataArray['data']['product'][$sku]['description'])) {
			return $this->jsonDataArray['data']['product'][$sku]['description'];
		}
		return $description;
	}

	public function getPrice() {
		$price = 0;
		$sku = $this->getSKU();
		$data = $this->shortData();
		if (isset($data['pricing']['price']['value'])) {
			return $data['pricing']['price']['value'];
		} elseif (isset($data['pricing']['retailPrice']['value'])) {
			return $data['pricing']['retailPrice']['value'];
		} elseif (isset($this->jsonDataArray['data']['product'][$sku]['pvsRaw'])) {
			return $this->jsonDataArray['data']['product'][$sku]['pvsRaw'];
		}
	}

	public function getSKU() {
		
		$sku = $this->getValue($this->SKU_SELECTOR);
		$sku = array_shift($sku);
		$urlArr = explode('product', $this->url);
		$halfUrl = array_pop($urlArr);
		$halfArr = explode('/', $halfUrl);
		$data = $this->shortData();
		
		if ($sku) {
			return $sku;
		} elseif (isset($data['id'])) {
			return $data['id'];
		} elseif ($halfArr[1]) {
			return $halfArr[1];
		}
	}

	public function getBrand() {
		$data = $this->shortData();
		$sku = $this->getSKU();
		if (isset($data['brand'])) {
			return $data['brand'];
		} elseif (isset($this->jsonDataArray['data']['product'][$sku]['_brand'][0])) {
			$brand = $this->jsonDataArray['data']['product'][$sku]['_brand'][0];
			return $this->jsonDataArray['data']['brand'][$brand]['name'];
		}
	}

	public function getCoverImage() {
		$data = $this->shortData();
		if (isset($data['medias'][0]['url'])) {
			return $data['medias'][0]['url'];
		}
	}

	public function getImages() {
		static $images = array();
		$sku = $this->getSKU();

		if ($images) {
			return $images;
		}
		$host = parse_url($this->url, PHP_URL_HOST);
		
		$data = $this->shortData();
		if (isset($data['medias']) && $data['medias']) {
			foreach ($data['medias'] as $image) {
				$images[0][] = $image['url'];
			}
		} elseif (isset($this->jsonDataArray['data']['product'][$sku]['_image']) && $this->jsonDataArray['data']['product'][$sku]['_image']) {
			foreach ($this->jsonDataArray['data']['product'][$sku]['_image'] as $image) {
				if (stripos($image, 'zoom') !== false) {
					$images[0][] = 'https://' . $host . $this->jsonDataArray['data']['image'][$image]['path'];
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
		
		$sku = $this->getSKU();
		$attrName = $this->getValue($this->ATTR_NAME_SELECTOR);
		$attrName = array_shift($attrName);
		
		$data = $this->shortData();
		$attribute = array();
		if (isset($data['selection']['options']) && $data['selection']['options']) {
			foreach ($data['selection']['options'] as $attr) {
				$attribute[] = $attr['name'];
			}
		} elseif (isset($this->jsonDataArray['data']['product'][$sku]['_size']) && $this->jsonDataArray['data']['product'][$sku]['_size']) {
			foreach ($this->jsonDataArray['data']['product'][$sku]['_size'] as $attrs) {
				$attribute[] = $this->jsonDataArray['data']['size'][$attrs]['name'];
			}
		}
		
		if ($attribute) {
			
			$attrGroups[] = array(
				'name' => $attrName,
				'is_color' => 0,
				'values' => $attribute
			);
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
		$attrName = $this->getValue($this->ATTR_NAME_SELECTOR);
		$attrName = array_shift($attrName);
		
		$data = $this->shortData();
		$attribute = array();
		if (isset($data['selection']['options']) && $data['selection']['options']) {
			foreach ($data['selection']['options'] as $attr) {
				
				if (isset($attr['name'])) {
					$combinations[] = array(
						'sku' => isset($attr['id']) ? $attr['id'] : $sku,
						'upc' => 0,
						'price' => isset($attr['pricing']['price']['value']) ? $attr['pricing']['price']['value'] : $price,
						'weight' => 0,
						'image_index' => 0,
						'attributes' => array(
							array(
								'name' => $attrName,
								'value' => $attr['name']
							)
						)
					);
				}
				
			}
		} elseif (isset($this->jsonDataArray['data']['product'][$sku]['_size']) && $this->jsonDataArray['data']['product'][$sku]['_size']) {
			foreach ($this->jsonDataArray['data']['product'][$sku]['_size'] as $attrs) {
				if (isset($this->jsonDataArray['data']['size'][$attrs]['name'])) {
					$combinations[] = array(
						'sku' => $sku,
						'upc' => 0,
						'price' => $price,
						'weight' => 0,
						'image_index' => 0,
						'attributes' => array(
							array(
								'name' => $attrName,
								'value' => $this->jsonDataArray['data']['size'][$attrs]['name']
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
		
		return $reviews;
	}

}
