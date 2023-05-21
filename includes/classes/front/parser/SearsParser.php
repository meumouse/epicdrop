<?php
/**
 * Sears data parser class
 *
 * @package: product-importer
 *
 */
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class SearsParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $images = array();
	private $PRICE_SELECTOR = '//li[contains(@class, "product-price-big")]/h4/span|//h4[contains(@class, "redSalePrice ")]/span|//span[@class="price-wrapper"]';
	private $ZIPCODE_SELECTOR = '//div[@id="newzipcodePanel"]/strong';
	private $COVER_IMAGE_SELECTOR = '//div[@data-id="product-image-main"]/img/@src';
	private $IMAGE_SELECTOR = '//ul[contains(@class, "prd-moreImagesList")]/li/@data-image-big';
	private $FEATURE_SELECTOR = '//div[@class="pvd-attribute-detail-flex"]/table/tbody/tr';
	private $SIZE_SELECTOR = '//select[@id="productSizeSelect"]/option';
	private $SIZE_NAME_SELECTOR = '//div[contains(@class, "prdSizeTitle")]';
	private $COLOR_SELECTOR = '//div/ul/li[contains(@class, "active-list-item")]/a/@data-color';
	private $COLOR_NAME_SELECTOR = '//div[@class="beauty-title"]/div';
	private $REVIEW_SELECTOR = '//div[@class="review-item"]';
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
			'authid: aA0NvvAIrVJY0vXTc99mQQ==',
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
		$api = 'https://' . $host . '/content/pdp/config/products/v1/products/' . $sku . '?site=sears';
		$json = $this->getContent($api);
		if ($json) {
			$this->jsonDataArray = json_decode($json, true);
		}

		$location = $this->getValue($this->ZIPCODE_SELECTOR);
		$zipcode = preg_replace('/[^0-9]/', '', array_shift($location));
		$this->jsonDataArray['products'] = array();
		if (isset($this->jsonDataArray['data']['attributes']['variants']) 
			&& $this->jsonDataArray['data']['attributes']['variants']) {
			foreach ($this->jsonDataArray['data']['attributes']['variants'] as $key => $variants) {
				if (isset($variants['offerId'])) {
					$offerId = $variants['offerId'];
				}
				$apiChild = 'https://' . $host . '/content/pdp/products/pricing/v2/get/price/display/json?offer=' . $offerId . '&priceMatch=Y&memberType=G&urgencyDeal=Y&site=SEARS&zipCode=' . $zipcode;
				$jsons = $this->getContent($apiChild);
			
				if ($jsons) {
					$jsonDataArray = json_decode($jsons, true);
				}
				
				if (isset($jsonDataArray['priceDisplay']['response'][0]['finalPrice']['numeric'])) {
					$this->jsonDataArray['products'][$key]['price'] = $jsonDataArray['priceDisplay']['response'][0]['finalPrice']['numeric'];
				} elseif (isset($jsonDataArray['priceDisplay']['response'][0]['oldPrice']['numeric'])) {
					$this->jsonDataArray['products'][$key]['price'] = $jsonDataArray['priceDisplay']['response'][0]['oldPrice']['numeric'];
				}

				$this->jsonDataArray['products'][$key]['sku'] = $offerId;
				
				if (isset($variants['attributes']) && $variants['attributes']) {
					foreach ($variants['attributes'] as $attrs) {
						$this->jsonDataArray['products'][$key]['attributes'][] = array(
							'name' => $attrs['name'],
							'value' => $attrs['value']
						);
					}
				}
				
				if (isset($variants['featuredImages']) && $variants['featuredImages']) {
					foreach ($variants['featuredImages'] as $imgs) {
						$this->images[$key][] = $imgs['src'];
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
		if (isset($this->jsonDataArray['data']['product']['name'])) {
			return $this->jsonDataArray['data']['product']['name'];
		}
	}

	public function getCategories() {
		$categories = array();
		if (isset($this->jsonDataArray['data']['productmapping']['primaryWebPath']) 
			&& $this->jsonDataArray['data']['productmapping']['primaryWebPath']) {
			foreach ($this->jsonDataArray['data']['productmapping']['primaryWebPath'] as $categorey) {
				$categories[] = $categorey['name'];
			}
		}
		return $categories;
	}

	public function getShortDescription() {
		$shortDescription = '';
		if (isset($this->jsonDataArray['data']['product']['seo']['desc'])) {
			$shortDescription = $this->jsonDataArray['data']['product']['seo']['desc'];
		}
		return $shortDescription;
	}

	public function getDescription() {
		$description = '';
		if (isset($this->jsonDataArray['data']['product']['desc']) && $this->jsonDataArray['data']['product']['desc']) {
			foreach ($this->jsonDataArray['data']['product']['desc'] as $desc) {
				$description .= $desc['val'];
			}
		}
		return $description;
	}

	public function getPrice() {

		$prices = $this->getValue($this->PRICE_SELECTOR);

		if ($prices) {
			return preg_replace('/[^0-9.]/', '', array_shift($prices));
		}
		
		return 0;
	}

	public function getSKU() {
		$urlArr = explode('?', $this->url);
		$url = array_shift($urlArr);
		$urlBreak = explode('/', $url);
		if ($urlBreak[4]) {
			return str_replace('p-', '', $urlBreak[4]);
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['data']['product']['brand']['name'])) {
			return $this->jsonDataArray['data']['product']['brand']['name'];
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
		if (isset($this->jsonDataArray['data']['product']['assets']['imgs']) && !$images) {
			foreach ($this->jsonDataArray['data']['product']['assets']['imgs'] as $imgLoop) {
				if (isset($imgLoop['vals'])) {
					foreach ($imgLoop['vals'] as $imge) {
						$images[0][] = $imge['src'];
					}
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
		
		if (isset($this->jsonDataArray['data']['product']['specs']) 
			&& $this->jsonDataArray['data']['product']['specs']) {
			foreach ($this->jsonDataArray['data']['product']['specs'] as $specs) {
				if (isset($specs['attrs'])) {
					foreach ($specs['attrs'] as $attrs) {
						$attributes[] = array(
							'name' => $attrs['name'],
							'value' => $attrs['val']
						);
					}
				}
				if ($attributes) {
					$featureGroups[] = array(
						'name' => $specs['grpName'],
						'attributes' => $attributes,
					);
				}
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
		
	private function parseTimeStamp( $text) {
		
		if (!empty($text)) {
			
			$replaceTexts = array(
				'january' => array(
					'janeiro',
					'1月',
					'janvier',
					'gennaio',
					'enero',
					'januari',
					'كانون الثاني',
					'يناير',
				),
				'february' => array(
					'fevereiro',
					'2月',
					'février',
					'februar',
					'febbraio',
					'febrero',
					'februari',
					'فبراير',
				),
				'march' => array(
					'Março',
					'3月',
					'mars',
					'marzo',
					'märz',					
					'marcha',
					'maart',
					'مارس',
				),
				'april' => array(
					'abril',
					'4月',
					'avril',
					'aprile',
					'أبريل',
				),
				'may' => array(
					'maio',
					'5月',
					'mai',
					'maggio',
					'mayo',
					'mei',
					'مايو',
				),
				'june' => array(
					'junho',
					'6月',
					'juin',					
					'junio',
					'juni',
					'giugno',
					'يونيو',
				),
				'july' => array(
					'julho',
					'7月',
					'juillet',
					'luglio',
					'julio',
					'juli',
					'تموز',
				),
				'august' => array(
					'agosto',
					'8月',
					'août',
					'augustus',
					'أغسطس',
				),
				'september' => array(
					'setembro',
					'9月',
					'septembre',
					'settembre',
					'septiembre',
					'سبتمبر',
				),
				'october' => array(
					'outubro',
					'10月',
					'octobre',
					'oktober',
					'ottobre',
					'octubre',
					'اكتوبر',
				),
				'november' => array(
					'novembro',
					'11月',
					'novembre',
					'noviembre',
					'نوفمبر',
				),
				'december' => array(
					'dezembro',
					'12月',
					'décembre',
					'dezember',
					'dicembre',
					'diciembre',
					'ديسمبر',
				),
			);
			
			$text = strtolower($text);
			
			foreach ($replaceTexts as $replaceWith => $find) {
				$textReplaced = str_replace($find, $replaceWith, $text);
				
				$splitTexts = explode(' ', $textReplaced);
				
				$splitTexts = array_map('trim', $splitTexts);
				
				$newText = '';
				
				if (array_intersect($splitTexts, array_keys($replaceTexts))) {
					
					foreach ($splitTexts as $txt) {
						if (preg_match('/^[0-9,]+$/i', $txt) || in_array(trim($txt), array_keys($replaceTexts))) {
							
							$newText .= $txt . ' ';
						}
					}
				}
				if (!empty($newText)) {
					return gmdate('Y-m-d H:i:s', strtotime($newText));
				}
			}
		}
		return gmdate('Y-m-d H:i:s');
	}
	
	public function getCustomerReviews( $maxReviews = 0, &$reviews = array()) {
		$maxReviews = $maxReviews ? $maxReviews : 500;
		if (!$reviews) {
			$host = parse_url($this->url, PHP_URL_HOST);
			$id = $this->getSKU();
			$this->reviewLink = 'https://' . $host . '/content/pdp/ratings/single/search/Sears/' . $id . '&targetType=product&sort=overall_rating&sortOrder=descending&limit=' . $maxReviews;
		}

		$reviewLink = $this->reviewLink;
		
		if ($reviewLink) {
			$json = $this->getContent($reviewLink);
			if ($json) {
				$reviewData = json_decode($json, true);
				$isMaxReached = false;

				if (isset($reviewData['data']['reviews']) && $reviewData['data']['reviews']) {
					foreach ($reviewData['data']['reviews'] as $review) {
						$videos = array(); 
						$images = array(); 
						if (isset($review['images'][0]['images']) && $review['images'][0]['images']) {
							foreach ($review['images'][0]['images'] as $img) {
								$images[] = $img['url']; 
							}
						}
						
						$reviews[] = array(
							'author' => $review['author']['screenName'],
							'title' => $review['summary'],
							'images' => $images,
							'videos' => $videos,
							'content' => $review['content'],
							'rating' => (int) $review['attribute_rating'][0]['value'],
							'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['publishedDate']))
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

		$reviewArrayObject = $this->xpath->query($this->REVIEW_SELECTOR);
		
		if ($reviewArrayObject->length) {
			$videos = array(); 
			$images = array(); 
			foreach ($reviewArrayObject as $reviewObject) {
				$author = $this->xpath->query('.//span[@class="title-5"]', $reviewObject);
				$stars1 = 0;
				
				if ($author->length) {
					$stars = $this->xpath->query('.//div[@class="star-rating"]/meta[2]/@content', $reviewObject);
					
					if ($stars->length) {
						$stars1 = $stars->item(0)->nodeValue;
					}					

					$reviews[] = array(
						'author' => $author->item(0)->nodeValue,
						'title' => $this->xpath->query('.//h5[@class="title-5"]', $reviewObject)->item(0)->nodeValue,
						'content' => trim($this->xpath->query('.//div[@class="inlineExpand"]/p', $reviewObject)->item(0)->nodeValue) . @$this->xpath->query('.//div[@class="title-5"]', $reviewObject)->item(0)->nodeValue,
						'rating' => $stars1 ? $stars1 : 1,
						'images' => $images,
						'videos' => $videos,
						'timestamp' => $this->parseTimeStamp($this->xpath->query('.//div[contains(@class, "review-info")]/div', $reviewObject)->item(0)->nodeValue)
					);
				}
			}
		}
		return $reviews;
	}
}
