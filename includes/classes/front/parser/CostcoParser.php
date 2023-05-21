<?php
/**
 * Costco data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class CostcoParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $images = array();
	private $JSON_DATA_SELECTOR1 = '//script[@type="application/ld+json"]';
	private $CATEGORY_SELECTOR = '//div[@class="breadcrumb-section"]/ol/li/a|//li[@itemprop="itemListElement"]/a';
	private $TITLE_SELECTOR = '//div[@class="sc-pc-legacy-title-full-desktop"]/h1|//h1[@itemprop="name"]';
	private $SHORT_DESCRIPTION_SELECTOR = '//div[@id="product-details"]/div[contains(@class, "features-container")]';
	private $DESCRIPTION_SELECTOR = '//div[@id="product_details"]';
	private $DESCRIPTION_SELECTOR2 = '//div[@class="product-info-description"]';
	private $PRICE_SELECTOR = '//meta[@property="product:price:amount"]/@content';
	private $COVER_IMAGE_SELECTOR = '//meta[@property="og:image"]/@content|//img[@id="productImage"]/@src';
	private $VARIANT_SELECTOR = '//a[@class="color-variant"]/@href';
	private $IMAGE_SELECTOR = '//picture/source/@srcset';
	private $IMAGE_SELECTOR1 = '//div[@class="slick-list"]/div/a/img/@src';
	private $BRAND_SELECTOR = '//div[@itemprop="brand"]';
	private $FEATURE_SELECTOR = '//div[contains(@class, "product-classification-wrapper")]/*/div';
	private $FEATURE_SELECTOR2 = '//div[contains(@class, "product-info-specs")]/div';
	private $ATTR_NAME_SELECTOR = '//label[@for="productOption00"]/strong|//label[@for="productOption01"]/strong';
	private $ATTR_VALUE_SELECTOR = '//div[@id="swatches-productOption00"]/div|//div[@id="swatches-productOption01"]/div';
	private $SIZE_SELECTOR = '//div[@class="variant-options"]/select/option';
	private $COLOR_SELECTOR = '//div[@class="variant-selector"]/div';
	private $REVIEW_SELECTOR = '//ol/li[@itemprop="review"]';
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
		$json1 = $this->getValue($this->JSON_DATA_SELECTOR1);
		if ($json1) {
			foreach ($json1 as $jsonc) {
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
				$url = explode('/', $dataVarlink->nodeValue);
				$urlSku = explode('-', array_pop($url));
				$skuvar = array_shift($urlSku);
				$dataHtml = $this->getContent('https://' . $host . $dataVarlink->nodeValue);
			
				if ($dataHtml) {
					
					$dom = $this->getDomObj($dataHtml);
					$xpath = new \DomXPath($dom);
					
					$color = $this->getValue($this->COLOR_SELECTOR, false, $xpath);
					$colorAttr = explode(':', array_shift($color));
					$colors = array_pop($colorAttr);
					if (isset($colorAttr[0])) {
						$this->jsonDataArray['products'][$colors]['colorName'] = $colorAttr[0];
					}
					
					$this->jsonDataArray['products'][$colors]['color'] = $colors;
					$imageArr = $this->getValue($this->IMAGE_SELECTOR, false, $xpath);
					if ($imageArr) {
						foreach ($imageArr as $imges) {
							$this->images[$colors][] = 'https://' . $host . $imges;
						}
						
					}
					
					$priceVar = $this->getValue($this->PRICE_SELECTOR, false, $xpath);
					$combPrice = array_shift($priceVar);
					
					if ($combPrice) {
						$this->jsonDataArray['products'][$colors]['price'] = preg_replace('/[^0-9.]/', '', $combPrice);
					}
					
					$this->jsonDataArray['products'][$colors]['sku'] = $skuvar;
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
		$titles = $this->getValue($this->TITLE_SELECTOR);
		$title = array_shift($titles);
		if ($title) {
			return $title;
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

		$descriptions = $this->getValue($this->SHORT_DESCRIPTION_SELECTOR, true);

		if ($descriptions) {
			return implode('<br>', $descriptions);
		}
		return '';
	}

	public function getDescription() {

		$descriptions = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		
		if ($descriptions) {
			return array_shift($descriptions);
		}
		
		return $this->getDescription2();
	}

	public function getDescription2() {

		$descriptions = $this->getValue($this->DESCRIPTION_SELECTOR2, true);
		
		if ($descriptions) {
			return array_shift($descriptions);
		}
		return '';
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
		$urlArr = explode('?', $this->url);
		$skuArr = explode('/', array_shift($urlArr));
		$skus = array_pop($skuArr);
		if ($skus && stripos($skus, 'html') == false) {
			return $skus;
		} elseif ($skus) {
			$skuAr = explode('.', $skus);
			array_pop($skuAr);
			return array_pop($skuAr);
		}
	}
	
	public function getBrand() {
		$brands = $this->getValue($this->BRAND_SELECTOR);
		$brand = array_shift($brands);
		if ($brand) {
			return $brand;
		} elseif (isset($this->jsonDataArray['brand']['name'])) {
			return $this->jsonDataArray['brand']['name'];
		}
		return '';
	}

	public function getCoverImage() {
		$imgUrl = $this->getValue($this->COVER_IMAGE_SELECTOR);
		$img = array_shift($imgUrl);
		if ($img) {
			return $img;
		}
		return '';
	}
	
	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}
		$host = parse_url($this->url, PHP_URL_HOST);
		$images = $this->images;
		
		$imges = $this->getValue($this->IMAGE_SELECTOR);
		$imges1 = $this->getValue($this->IMAGE_SELECTOR1);
		if (!$images) {
			$images[0] = str_replace('/medias', 'https://' . $host . '/medias', $imges);
		}
		if ($imges1) {
			$images[0] = str_replace('735', '728', $imges1);
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
		
		$groups = $this->xpath->query($this->FEATURE_SELECTOR . '/table');

		if ($groups->length) {
			
			foreach ($groups as $key => $group) {
				
				$attributes = array();
			
				$features = @$this->xpath->query('.//tr', $group);
				
				if ($features->length) {
					
					foreach ($features as $feature) {
						$name = @$this->xpath->query('.//td[1]', $feature)->item(0)->nodeValue;
						$value = @$this->xpath->query('.//td[2]', $feature)->item(0)->nodeValue;
						if ($value) {
							$attributes[] = array(
								'name' => trim($name),
								'value' => preg_replace('/\s+/', ' ', $value)
							);
						}
					}
				}
				
				if ($attributes) {
					
					if ($this->xpath->query($this->FEATURE_SELECTOR . '/div')->length > $key) {
						$groupName = $this->xpath->query($this->FEATURE_SELECTOR . '/div')->item($key)->nodeValue;
					} else {
						$groupName = 'Características/Specifications';
					}
					
					$featureGroups[] = array(
						'name' => $groupName,
						'attributes' => $attributes,
					);
				}
			}
		}
		
		if (!$featureGroups) {
			$featureGroups = $this->getFeatures2();
		}
		
		return $featureGroups;
	}

	public function getFeatures2() {
		
		$featureGroups = array();
		$attributes = array();
		
		$features = $this->xpath->query($this->FEATURE_SELECTOR2);
				
		if ($features->length) {
			
			foreach ($features as $feature) {
				$name = @$this->xpath->query('.//div[1]', $feature)->item(0)->nodeValue;
				$value = @$this->xpath->query('.//div[2]', $feature)->item(0)->nodeValue;
				if ($value) {
					$attributes[] = array(
						'name' => trim($name),
						'value' => preg_replace('/\s+/', ' ', $value)
					);
				}
			}
		}
				
		if ($attributes) {
			
			$featureGroups[] = array(
				'name' => 'Características/Specifications',
				'attributes' => $attributes,
			);
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
					if (stripos($attr['name'], 'Peso') !== false) {
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
		$attrName = $this->getValue($this->ATTR_NAME_SELECTOR);
		$attrsValue = $this->xpath->query($this->ATTR_VALUE_SELECTOR);
		$color = array();
		if (isset($this->jsonDataArray['products']) && $this->jsonDataArray['products']) {
			
			foreach ($this->jsonDataArray['products'] as $attr) {
				$colorName = $attr['colorName'];
				$color[$attr['color']] = $attr['color'];
			}
			
			if ($color) {
				$attrGroups[] = array(
					'name' => $colorName,
					'is_color' => 1,
					'values' => $color
				);
			}
		}
		
		if ($size) {
			$sizeName = array_shift($size);
			if ($size) {
				$attrGroups[] = array(
					'name' => preg_replace('/\s+/', ' ', $sizeName),
					'is_color' => 0,
					'values' => $size
				);
			}
		}
		if (!$attrGroups) {
			if ($attrsValue->length) {
				foreach ($attrsValue as $attrs) {
					$name = $this->xpath->query('.//legend', $attrs)->item(0)->nodeValue;
					$attrVals = $this->xpath->query('.//fieldset[@role="radiogroup"]/span', $attrs);
					$value = array();
					if ($attrVals->length) {
						foreach ($attrVals as $values) {
							$value[] = trim($this->xpath->query('.//span', $values)->item(0)->nodeValue);
						}
					}
					$attrGroups[] = array(
						'name' => trim($name),
						'is_color' => stripos($name, 'color') !== false ? 1 : 0,
						'values' => $value
					);
				}
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

	public function getCustomerReviews( $maxReviews = 0, &$reviews = array(), $offset = 0) {
		$host = parse_url($this->url, PHP_URL_HOST);
		$id = $this->getSKU();
		$maxReviews = $maxReviews ? $maxReviews : 100;
		if ('www.costco.com.mx' == $host) {
			if (!$reviews) {
				$this->reviewLink = 'https://api.bazaarvoice.com/data/batch.json?passkey=caWWyJFG0ZwkzqxjG21P7PErVNvmezeR9ij6bluGfkvQo&apiversion=5.5&displaycode=14297-es_mx&resource.q0=reviews&filter.q0=isratingsonly:eq:false&filter.q0=productid:eq:' . $id . '&filter.q0=contentlocale:eq:es*,en*,es_MX&sort.q0=relevancy:a1&stats.q0=reviews&filteredstats.q0=reviews&include.q0=authors,products,comments&filter_reviews.q0=contentlocale:eq:es*,en*,es_MX&filter_reviewcomments.q0=contentlocale:eq:es*,en*,es_MX&filter_comments.q0=contentlocale:eq:es*,en*,es_MX&limit.q0=' . $maxReviews;
			}
		} elseif ('www.costco.com' == $host) {
			if (!$reviews) {
				$this->reviewLink = 'https://api.bazaarvoice.com/data/batch.json?passkey=bai25xto36hkl5erybga10t99&apiversion=5.5&displaycode=2070_2_0-en_us&resource.q0=reviews&filter.q0=isratingsonly:eq:false&filter.q0=productid:eq:' . $id . '&filter.q0=contentlocale:eq:en_CA,fr_CA,en_US&sort.q0=relevancy:a1&stats.q0=reviews&filteredstats.q0=reviews&include.q0=authors,products,comments&filter_reviews.q0=contentlocale:eq:en_CA,fr_CA,en_US&filter_reviewcomments.q0=contentlocale:eq:en_CA,fr_CA,en_US&filter_comments.q0=contentlocale:eq:en_CA,fr_CA,en_US&limit.q0=' . $maxReviews;
			}
		}

		$reviewLink = $this->reviewLink;
		$reviewLink .= '&offset.q0=' . $offset;
		
		if ($reviewLink) {
			$json = $this->getContent($reviewLink);
		}
	
		if ($json) {
			$reviewData = json_decode($json, true);
			$isMaxReached = false;
		}
		
		if (isset($reviewData['BatchedResults']['q0']['Results']) 
			&& $reviewData['BatchedResults']['q0']['Results']) {
			foreach ($reviewData['BatchedResults']['q0']['Results'] as $review) {
				$videos = array(); 
				$images = array(); 
				if (isset($review['Photos']) && $review['Photos']) {
					foreach ($review['Photos'] as $img) {
						$images[] = $img['Sizes']['normal']['Url']; 
					}
				}
				
				$reviews[] = array(
					'author' => $review['UserNickname'],
					'title' => $review['Title'],
					'images' => $images,
					'videos' => $videos,
					'content' => $review['ReviewText'],
					'rating' => (int) $review['Rating'],
					'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['SubmissionTime']))
				);

				if (0 < $maxReviews && count($reviews) >= $maxReviews) {
					$isMaxReached = true;
					break;
				}
			}

			if (false == $isMaxReached) {
				$this->getCustomerReviews($maxReviews, $reviews, $offset+20);
			}
		}
				
		return $reviews;
	}

}
