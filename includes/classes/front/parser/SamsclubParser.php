<?php
/**
 * Samsclub data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}
/* Parser version 1.0 */

class SamsclubParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $images = array();
	private $JSON_DATA_SELECTOR = '//script[@type="application/json"]';
	private $CATEGORY_SELECTOR = '//li[@property="itemListElement"]/a|//li[@itemprop="itemListElement"]/span/a/span';
	private $TITLE_SELECTOR = '//div[@class="sc-pc-legacy-title-full-desktop"]/h1|//h1[@itemprop="name"]';
	private $COVER_IMAGE_SELECTOR = '//meta[@itemprop="image"]/@content';
	private $SHORT_DESCRIPTION_SELECTOR = '//div[@class="sc-pc-about-this-item"]|//h2[@class="desc-text-label"]';
	private $DESCRIPTION_SELECTOR = '//div[@class="sc-description-about-long"]|//div[@class="sc-manufacturing-info-spec"]|//div[@id="Descripción"]/div[2]';
	private $PRICE_SELECTOR = '//meta[@itemprop="price"]/@content|//span[@class="Price-group"]/@title|//span[@itemprop="price"]';
	private $IMAGE_SELECTOR = '//div[@class="thumb-image-container"]/div/meta/@content';
	private $BRAND_SELECTOR = '//div[@itemprop="brand"]/meta/@content|//a[@class="prod-brand"]';
	private $FEATURE_SELECTOR = '//div[@class="sc-manufacturing-info"]/table/tr';
	private $FEATURE_SELECTOR2 = '//table[@class="characteristic-table"]/tbody/tr';
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
				
		$json0 = $this->getValue($this->JSON_DATA_SELECTOR);
		if ($json0) {
			foreach ($json0 as $jsonc) {
				
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
		$sku = $this->getSKU();
		
		if (isset($this->jsonDataArray['cache']['products'][$sku]['variantSummary']['variantInfoMap']) 
			&& $this->jsonDataArray['cache']['products'][$sku]['variantSummary']['variantInfoMap']) {
			foreach ($this->jsonDataArray['cache']['products'][$sku]['variantSummary']['variantInfoMap'] 
			as $dataVarlink) {
				$skuvar = $dataVarlink['variantSkuId'];
				
				if (isset($this->jsonDataArray['cache']['products'][$sku]['skus']) 
					&& $this->jsonDataArray['cache']['products'][$sku]['skus']) {
						
					foreach ($this->jsonDataArray['cache']['products'][$sku]['skus'] as $dataVar) {
						if ($dataVar['skuId'] == $skuvar) {
							
							if (isset($dataVar['assets']['image'])) {
								$upc = preg_replace('/[^0-9]/', '', $dataVar['assets']['image']);
								$imgUrl = 'https://www.samsclub.com/api/product/' . $upc . '/images?spin=true';
								$imgData = $this->getContent( $imgUrl);
								$imgData = json_decode($imgData, true);
								if (isset($imgData['Images']) && $imgData['Images']) {
									foreach ($imgData['Images'] as  $imgs ) {
										$this->images[$skuvar][] = $imgs['ImageUrl'] . '?$DT_Zoom$';
									}
								}
							}
							
							if (isset($dataVar['onlineOffer']['price']['finalPrice']['amount'])) {
								$this->jsonDataArray['products'][$skuvar]['price'] = 
								$dataVar['onlineOffer']['price']['finalPrice']['amount'];
							} elseif (isset($dataVar['onlineOffer']['price']['startPrice']['amount'])) {
								$this->jsonDataArray['products'][$skuvar]['price'] = 
								$dataVar['onlineOffer']['price']['startPrice']['amount'];
							}
							
							if (isset($dataVar['onlineOffer']['generatedUPC'])) {
								$this->jsonDataArray['products'][$skuvar]['upc'] = $dataVar['onlineOffer']['generatedUPC'];
							}
							if (isset($dataVar['skuLogistics']['weight']['value'])) {
								$this->jsonDataArray['products'][$skuvar]['weight'] = array(
									'value' => (float) $dataVar['skuLogistics']['weight']['value'],
									'unit' => $dataVar['skuLogistics']['weight']['unitOfMeasure']
								);
							}
						}
					}
				}
				$this->jsonDataArray['products'][$skuvar]['sku'] = $skuvar;
				
				if (isset($dataVarlink['values']) && $dataVarlink['values']) {
					
					foreach ($dataVarlink['values'] as $attrGroups) {
						
						if (isset($attrGroups['value'])) {
							$this->jsonDataArray['products'][$skuvar]['attributes'][] = array(
								'name' => $attrGroups['name'],
								'value' => $attrGroups['value']
							);
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
		
		$sku = $this->getSKU();
		if (isset($this->jsonDataArray['cache']['products'][$sku]['descriptors']['shortDescription'])) {
			return $this->jsonDataArray['cache']['products'][$sku]['descriptors']['shortDescription'];
		}
		
		$shortDescriptions = $this->getValue($this->SHORT_DESCRIPTION_SELECTOR, true);
		if ($shortDescriptions) {
			return implode('<br>', $shortDescriptions);
		}
		
		return '';
	}

	public function getDescription() {

		$sku = $this->getSKU();
		
		if (isset($this->jsonDataArray['cache']['products'][$sku]['descriptors']['longDescription'])) {
			return nl2br($this->jsonDataArray['cache']['products'][$sku]['descriptors']['longDescription']);
		}
		if (isset($this->jsonDataArray['cache']['products'][$sku]['descriptors']['productdescription'])) {
			return nl2br($this->jsonDataArray['cache']['products'][$sku]['descriptors']['productdescription']);
		}
		
		$descriptions = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		
		if ($descriptions) {
			return implode('<br>', $descriptions);
		}
		
		return '';
	}

	public function getPrice() {

		$prices = $this->getValue($this->PRICE_SELECTOR);

		if ($prices) {
			return str_replace(',', '.', preg_replace('/[^0-9,.]/', '', array_shift($prices)));
		}
		return 0;
	}

	public function getSKU() {
		$urlArr = explode('?', $this->url);
		$skuArr = explode('/', array_shift($urlArr));
		$sku = array_pop($skuArr);
		if ($sku) {
			return $sku;
		}
		return '';
	}
	
	public function getUPC() {
		$imgUrl = $this->getValue($this->COVER_IMAGE_SELECTOR);
		if ($imgUrl) {
			$urlArr = explode('/', array_shift($imgUrl));
			$upc = preg_replace('/[^0-9]/', '', array_pop($urlArr));
			if ($upc) {
				return $upc;
			}
		}
		return '';
	}

	public function getBrand() {
		$brands = $this->getValue($this->BRAND_SELECTOR);

		if ($brands) {
			return array_shift($brands);
		}
		return '';
	}

	public function getCoverImage() {
		$imgUrl = $this->getValue($this->COVER_IMAGE_SELECTOR);

		if ($imgUrl) {
			return array_shift($imgUrl) . '?$DT_Zoom$';
		}
		return '';
	}
	
	public function getImages() {
		static $images = array();
		$sku = $this->getSKU();

		if ($images) {
			return $images;
		}
		$upc = $this->getUPC();
		$images = $this->images;
		
		$imges = $this->getValue($this->IMAGE_SELECTOR);
		if ($imges) {
			$images[0] = str_replace(array('img_icon', 'i.jpg'), array('img_large', 'l.jpg'), $imges);
		}
		if (!$images) {
			
			$imgUrl = 'https://www.samsclub.com/api/product/' . $upc . '/images?spin=true';
			$imgData = $this->getContent( $imgUrl);
			$imgData = json_decode($imgData, true);
			if (isset($imgData['Images']) && $imgData['Images']) {
				foreach ($imgData['Images'] as  $imgs ) {
					$images[0][] = $imgs['ImageUrl'] . '?$DT_Zoom$';
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
		
		$attributes = array();

		if ($featureGroups) {
			return $featureGroups;
		}
		$features = $this->xpath->query($this->FEATURE_SELECTOR);
	
		if ($features->length) {
			
			foreach ($features as $speci) {
				$attr = @$this->xpath->query('.//td|.//th', $speci);
				
				if ($attr->length >= 2) {
					$value = $attr->item(1)->nodeValue;
					if ($value) {
						$attributes[] = array(
							'name' => trim($attr->item(0)->nodeValue),
							'value' => preg_replace('/\s+/', ' ', $value)
						);
					}
				}
			}
			if ($attributes) {
				$featureGroups[] = array(
					'name' => 'Características',
					'attributes' => $attributes,
				);
			}
		}
		
		if (!$featureGroups) {
			$featureGroups = $this->getFeatures2();
		}
			
		return $featureGroups;
	}
	
	public function getFeatures2() {
		
		$attributes = array();
		
		$features = $this->xpath->query($this->FEATURE_SELECTOR2);
	
		if ($features->length) {
			
			foreach ($features as $speci) {
				$attr = @$this->xpath->query('.//td|.//th', $speci);
				
				if ($attr->length >= 2) {
					$value = $attr->item(1)->nodeValue;
					if ($value) {
						$attributes[] = array(
							'name' => trim($attr->item(0)->nodeValue),
							'value' => preg_replace('/\s+/', ' ', $value)
						);
					}
				}
			}
			if ($attributes) {
				$featureGroups[] = array(
					'name' => 'Características',
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
						'upc' => isset($attrVals['upc']) ? $attrVals['upc'] : 0,
						'price' => isset($attrVals['price']) ? $attrVals['price'] : $price,
						'weight' => isset($attrVals['weight']) ? $attrVals['weight'] : $weight,
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
			
	public function getCustomerReviews( $maxReviews = 0, &$reviews = array(), $offset = 0) {
		$maxReviews = $maxReviews ? $maxReviews : 100;
		if (!$reviews) {
			$id = $this->getSKU();
			$this->reviewLink = 'https://api.bazaarvoice.com/data/batch.json?passkey=dap59bp2pkhr7ccd1hv23n39x&apiversion=5.5&displaycode=1337-en_us&resource.q0=reviews&filter.q0=isratingsonly:eq:false&filter.q0=productid:eq:' . $id . '&filter.q0=contentlocale:eq:en_US&sort.q0=submissiontime:desc&stats.q0=reviews&filteredstats.q0=reviews&include.q0=authors,products,comments&filter_reviews.q0=contentlocale:eq:en_US&filter_reviewcomments.q0=contentlocale:eq:en_US&filter_comments.q0=contentlocale:eq:en_US&limit.q0=' . $maxReviews . '&limit_comments.q0=3';
		}

		$reviewLink = $this->reviewLink;
		$reviewLink .= '&offset.q0=' . $offset;
		
		if ($reviewLink) {
			$json = $this->getContent($reviewLink);
			if ($json) {
				$reviewData = json_decode($json, true);
				$isMaxReached = false;
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
			}
		}
		return $reviews;
	}
	
}
