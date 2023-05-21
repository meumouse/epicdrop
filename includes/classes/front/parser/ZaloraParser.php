<?php
/**
 * Zalora data parser class
 *
 * @package: product-importer
 *
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class ZaloraParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $JSON_DATA_SELECTOR = '//script[@type="application/ld+json"]';
	private $VARIANT_SELECTOR = '//div[@id="product-detail-grouped"]/ul/li/a/@href';
	private $images = array();
	private $TITLE_SELECTOR = '//div[contains(@class, "product__title ")]';
	private $CATEGORY_SELECTOR = '//div[contains(@class, "b-breadcrumb")]/ul/li/a/span';
	private $DESCRIPTION_SELECTOR = '//div[@id="productDesc"]';
	private $PRICE_SELECTOR = '//span[@id="js-detail_price_with_specificSize"]|//span[@id="js-detail_price_without_selectedSize"]';
	private $SKU_SELECTOR = '//input[@name="configSku"]/@value';
	private $BRAND_SELECTOR = '//input[@name="configBrand"]/@value';
	private $COVER_IMAGE_SELECTOR = '//div[@id="productZoom"]/@data-zoom';
	private $IMAGE_SELECTOR = '//ul[contains(@class, "prd-moreImagesList")]/li/@data-image-big';
	private $FEATURE_SELECTOR = '//div[@class="pvd-attribute-detail-flex"]/table/tbody/tr';
	private $SIZE_SELECTOR = '//select[@id="productSizeSelect"]/option';
	private $SIZE_NAME_SELECTOR = '//div[contains(@class, "prdSizeTitle")]';
	private $COLOR_SELECTOR = '//div/ul/li[contains(@class, "active-list-item")]/a/@data-color';
	private $COLOR_NAME_SELECTOR = '//div[@class="beauty-title"]/div';
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
		
		$json = $this->getValue( $this->JSON_DATA_SELECTOR);
		if ($json) {
			foreach ($json as $jsonc) {
				$jsonc = stripslashes($jsonc);
				
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
		
		$dataAttribute = $this->xpath->query($this->VARIANT_SELECTOR);
		$this->jsonDataArray['products'] = array();
		
		if ($dataAttribute->length) {
			foreach ($dataAttribute as $dataVarlink) {
				$host = parse_url($this->url, PHP_URL_HOST);
				$skuvar =  preg_replace('/[^0-9]/', '', $dataVarlink->nodeValue);
				
				$dataHtml = $this->getContent('https://' . $host . $dataVarlink->nodeValue);
			
				if ($dataHtml) {
					
					$dom = $this->getDomObj($dataHtml);
					$xpath = new \DomXPath($dom);
					$color = $this->getValue($this->COLOR_SELECTOR, false, $xpath);
					$key = array_shift($color);
					$this->jsonDataArray['products'][$key]['color'] = $key;
					
					$imageArrs = $this->getValue($this->IMAGE_SELECTOR, false, $xpath);
					if ($imageArrs) {
						$this->images[$key] = array();
						foreach ($imageArrs as $imageArr) {
							$imageArr = explode('http', $imageArr);
							$this->images[$key][] = 'http' . end($imageArr);
						}
					}
					
					$priceVar = $this->getValue($this->PRICE_SELECTOR, false, $xpath);
					$combPrice = array_shift($priceVar);
					
					if ($combPrice) {
						$this->jsonDataArray['products'][$key]['price'] = preg_replace('/[^0-9.]/', '', $combPrice);
					}
					
					$this->jsonDataArray['products'][$key]['sku'] = $skuvar;
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
		$title = $this->getValue($this->TITLE_SELECTOR);
		$title = array_shift($title);
		if ($title) {
			return $title;
		} elseif (isset($this->jsonDataArray['name'])) {
			return $this->jsonDataArray['name'];
		}
	}

	public function getCategories() {
		$categories = array();
		$categories = $this->getValue($this->CATEGORY_SELECTOR);
		if ($categories) {
			return array_unique($categories);
		} elseif (isset($this->jsonDataArray['itemListElement']) && $this->jsonDataArray['itemListElement']) {
			foreach ($this->jsonDataArray['itemListElement'] as $categorey) {
				$categories[] = $categorey['name'];
			}
		}
		return $categories;
	}

	public function getShortDescription() {
		$shortDescription = '';
		return $shortDescription;
	}

	public function getDescription() {
		$description = '';
		$descript = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		$descript = array_shift($descript);
		if ($descript) {
			$description = str_replace('style="height:0;display:none"', '', $descript);
		} elseif (isset($this->jsonDataArray['description'])) {
			$description = $this->jsonDataArray['description'];
		}
		return $description;
	}

	public function getPrice() {
		$price = 0;
		$prices = $this->getValue($this->PRICE_SELECTOR);
		$pricec = array_shift($prices);
		if ($pricec) {
			$price = preg_replace('/[^0-9.]/', '', $pricec);
		} elseif (isset($this->jsonDataArray['offers']['price'])) {
			$price = $this->jsonDataArray['offers']['price'];
		}
		return $price;
	}

	public function getSKU() {
		$sku = $this->getValue($this->SKU_SELECTOR);
		$sku = array_shift($sku);
		if ($sku) {
			return $sku;
		} elseif (isset($this->jsonDataArray['sku'])) {
			return $this->jsonDataArray['sku'];
		}
		return '';
	}

	public function getBrand() {
		$brand = $this->getValue($this->BRAND_SELECTOR);
		$brand = array_shift($brand);
		if ($brand) {
			return $brand;
		} elseif (isset($this->jsonDataArray['brand']['name'])) {
			return $this->jsonDataArray['brand']['name'];
		}
		return '';
	}

	public function getCoverImage() {
		$cover = $this->getValue($this->COVER_IMAGE_SELECTOR);
		$cover = array_shift($cover);
		
		if ($cover) {
			return $cover;
		} elseif (isset($this->jsonDataArray['image'])) {
			return 'https://dynamic.zacdn.com/AzKwsBhn73dDERhnyra54Keribw=/fit-in/762x1100/filters:quality(95):fill(ffffff)/' . $this->jsonDataArray['image'] . '.jpg';
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
			
			$imageArrs = $this->getValue($this->IMAGE_SELECTOR);
			
			if ($imageArrs) {
			
				$images[0] = array();
				foreach ($imageArrs as $imageArr) {
					$imageArr = explode('http', $imageArr);
					$images[0][] = 'http' . end($imageArr);
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
				$name = $this->xpath->query('.//td', $speci)->item(0)->nodeValue;
				$value = $this->xpath->query('.//td', $speci)->item(1)->nodeValue;
				if ($value) {
					$attributes[] = array(
						'name' => trim($name),
						'value' => trim($value)
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
				
		$size = $this->getValue($this->SIZE_SELECTOR);
		$colorName = $this->getValue($this->COLOR_NAME_SELECTOR);
		$sizeName = $this->getValue($this->SIZE_NAME_SELECTOR);
		$color = array();
		
		if (isset($this->jsonDataArray['products']) && $this->jsonDataArray['products']) {
			
			foreach ($this->jsonDataArray['products'] as $attr) {
				$color[$attr['color']] = $attr['color'];
			}
			
			if ($color) {
				$attrGroups[] = array(
					'name' => array_shift($colorName),
					'is_color' => 1,
					'values' => $color
				);
			}
		}
		
		if ($size) {
			array_shift($size);
			if ($size) {
				$attrGroups[] = array(
					'name' => preg_replace('/\s+/', ' ', array_shift($sizeName)),
					'is_color' => 0,
					'values' => $size
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
		$weight = $this->getWeight();
		$sku = $this->getSKU();
		$attrs = $this->getAttributes();
		$colorAttrs = array();
		
		foreach ($attrs as $attr) {
			if ($attr['is_color']) {
				$colorAttrs = $attr['values'];
			}
		}
		$combs = $this->makeCombinations($attrs);
		
		if ($combs) {
			foreach ($combs as $attrVals) {
				$imageIndex = 0;
				if ($colorAttrs) {
					foreach ($colorAttrs as $colorName) {
						if (in_array($colorName, array_column($attrVals, 'value'))) {
							$imageIndex = $colorName;
						}
					}
				}
				$skuComb = '';
				$combPrice = 0;
				if (isset($this->jsonDataArray['products'][$imageIndex]['price'])) {
					$combPrice = $this->jsonDataArray['products'][$imageIndex]['price'];
				}
				if (isset($this->jsonDataArray['products'][$imageIndex]['sku'])) {
					$skuComb = $this->jsonDataArray['products'][$imageIndex]['sku'];
				}
				
				$combinations[] = array(
					'sku' => $skuComb ? $skuComb : $sku,
					'upc' => 0,
					'price' => $combPrice ? $combPrice : $price,
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

	public function getCustomerReviews( $maxReviews = 0, &$reviews = array(), $per_page = 1) {
		
		if (!$reviews) {
			$host = parse_url($this->url, PHP_URL_HOST);
			$id = $this->getSKU();
			$this->reviewLink = 'https://' . $host . '/productreviews/reviews/?sku=' . $id . '&token=';
		}					

		$reviewLink = $this->reviewLink;
		$reviewLink .= '&per_page=' . $per_page;
		
		if ($reviewLink) {
			$json = $this->getContent($reviewLink);

			if ($json) {
				$reviewData = json_decode($json, true);
				$isMaxReached = false;

				if (isset($reviewData['reviews']) && $reviewData['reviews']) {
					foreach ($reviewData['reviews'] as $review) {
						$text = ''; 
						if (isset($review['tags']) && $review['tags']) {
							foreach ($review['tags'] as $coment) {
								$text .= $coment['label'] . ', '; 
							}
						}
						
						$reviews[] = array(
							'author' => $review['customer_display_name'],
							'title' => '',
							'content' => $text,
							'rating' => (int) $review['rating'],
							'timestamp' => $review['submitted_at']
						);

						if (0 < $maxReviews && count($reviews) >= $maxReviews) {
							$isMaxReached = true;
							break;
						}
					}


					if (false == $isMaxReached) {
						$this->getCustomerReviews($maxReviews, $reviews, $per_page+1);
					}
				}
			}
		}
		return $reviews;
	}

}
