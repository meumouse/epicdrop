<?php
/**
 * Bol data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.2 */

class BolParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $host;
	private $jsonDataArray = array();
	private $REVIEW_LINK = '//button[@data-test="review-load-more"]/@data-href';
	private $VARIANT_SELECTOR = '//div[@data-test="feature-options"]/a/@href|//div[contains(@class, "feature-list__options")]/a/@href';
	private $DESCRIPTION_SELECTOR = '//div[@class="product-description"]';
	private $OPTION_GROUP_SELECTOR = '//div[contains(@class, "feature-group")]';
	private $OPTION_SELECTOR = './/div[@data-test="feature-options"]/a';
	private $ATTRIBUTE_SELECTOR = '//div[@class="feature-list__text"]';
	private $REVIEW_SELECTOR = '//li[contains(@class, "review")]';
	private $CATEGORY_SELECTOR = '//li[@class="breadcrumbs__item"]/span/a/p';
	private $PRICE_SELECTOR = '//span[@data-test="buy-block-sticky-cta-price"]';
	private $VIDEO_AJX_SELECTOR = '//wsp-video-still/@data-href';
	private $FEATURE_SELECTOR = '//div[contains(@class, "js_specifications_content")]/div[@class="specs"]|//div[@data-test="specifications"]/div[@class="specs"]';
	private $IMAGE_SELECTOR = '//script[@data-image-slot-config]';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content, $url) {
		$this->content = preg_replace('/\s+/', ' ', $content);
		$this->url = $url;
		$this->dom = $this->getDomObj($content);
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
	public function getContent( $url) {
		$curl = curl_init();

		curl_setopt_array(
			$curl,
			array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'GET',
				CURLOPT_HTTPHEADER => array(
					'cache-control: no-cache',
				),
			)
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
		
		$json = $this->getJson( $this->content, '"application/ld+json">', '</script>');
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = json_decode($json, true);
		}
		
		$optionGroups = $this->xpath->query($this->OPTION_GROUP_SELECTOR);

		if ($optionGroups->length) {
			foreach ($optionGroups as $key => $optionGroup) {
				
				$name = $this->xpath->query('.//div[@data-test="header"]/div', $optionGroup)->item(0)->nodeValue;
				
				$name = current(explode('(', $name));
				/*$name = preg_replace('@<(\w+)\b.*?>.*?</\1\s*>@si', '', $name);*/
				$name = $this->removeUnwantedText($name);
				
				$isColor = (bool) $this->xpath->query('.//img', $optionGroup)->length;
				
				if (0 == $key) {
					
					$options = $this->xpath->query($this->OPTION_SELECTOR, $optionGroup);
					
					if ($options->length) {
						
						foreach ($options as $i => $option) {
					
							$varLink = $option->getAttribute('href');
							
							$varContent = $this->getContent('https://www.bol.com' . $varLink);
							
							$dom = $this->getDomObj($varContent);
							$xpath = new \DomXPath($dom);
							
							if ($isColor) {
								$imgJsons = $this->getValue($this->IMAGE_SELECTOR, false, $xpath);
								
								if ($imgJsons) {
									$imgJson = array_shift($imgJsons);	
									
									$imgs = json_decode($imgJson, true);
									
									if ($imgs) {
									
										foreach ($imgs as $img) {
											
											if ('image' == $img['type']) {
												
												if (isset($img['zoomImageUrl'])) {
													$this->jsonDataArray['images'][$i][] = $img['zoomImageUrl'];
												} elseif (isset($img['imageUrl'])) {
													$this->jsonDataArray['images'][$i][] = $img['imageUrl'];
												}
											}
										}
									}
								}
							
							}
							
							$this->jsonDataArray['prices'][$i] = $this->getPrice(
								$this->getValue($this->PRICE_SELECTOR, false, $xpath)
							);
							
							$attributes = $xpath->query($this->ATTRIBUTE_SELECTOR);
							
							if ($attributes->length) {
								foreach ($attributes as $attribute) {
									
									$attrName = trim($xpath->query('.//span[@class="feature-list__label"]', $attribute)->item(0)->nodeValue);
									
									if (strpos($attrName, $name) !== false ) {
										
										if (!isset($this->jsonDataArray['attributes'][$name])) {
											
											$this->jsonDataArray['attributes'][$name] = array(
												'name' => $name,
												'is_color' => $isColor,
												'values' => array()								
											);
										}
										
										$this->jsonDataArray['attributes'][$name]['values'][] = $this->removeUnwantedText(
											$xpath->query('.//span[@class="feature-list__value"]', $attribute)->item(0)->nodeValue
										);
									}
								}
							}
						}
					}
				} else {
					
					$options = $this->xpath->query($this->OPTION_SELECTOR, $optionGroup);
					
					if ($options->length) {
						
						if (!isset($this->jsonDataArray['attributes'][$name])) {
						
							$this->jsonDataArray['attributes'][$name] = array(
								'name' => $name,
								'is_color' => $isColor,
								'values' => array()								
							);
						}
						
						$attrValues = array();
						
						foreach ($options as $option) {
							$attrValues[] = $this->removeUnwantedText($option->nodeValue);
						}
						
						$this->jsonDataArray['attributes'][$name]['values'] = array_filter($attrValues);
					}					
				}
			}
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
		$categories = $this->getValue($this->CATEGORY_SELECTOR);
		return $categories;
	}

	public function getShortDescription() {
		return '';
	}

	public function getDescription() {
		
		$descriptions = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		
		if ($descriptions) {
			return array_shift($descriptions);
		}
		
		if (isset($this->jsonDataArray['description'])) {
			return nl2br($this->jsonDataArray['description']);
		}
		return '';
	}

	public function getPrice( $prices = null) {
		
		if (!$prices) {
			if (isset($this->jsonDataArray['offers']['price'])) {
				$prices = array($this->jsonDataArray['offers']['price']);
			} else {
				$prices = $this->getValue($this->PRICE_SELECTOR);
			}
		}
		
		if ($prices) {
			return str_replace(',', '.', trim(array_shift($prices)));
		}
		
		return 0;
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['productID'])) {
			return $this->jsonDataArray['productID'];
		}
		return '';
	}
	public function getWeight() {
		
		if (isset($this->jsonDataArray['weight']['value'])) {
			return array(
				'unit' => $this->jsonDataArray['weight']['unitText'],
				'value' => $this->jsonDataArray['weight']['value']
			);
		}
		return array();
	}

	public function getEAN() {
		static $ean = '';
		
		if ($ean) {
			return $ean;
		}
		
		$featureGroups = $this->getFeatures();
		foreach ($featureGroups as $featureGroup) {
			foreach ($featureGroup['attributes'] as $feature) {
				if ('EAN' == $feature['name']) {
					$ean = $feature['value'];
					break 2;
				}
			}
		}
		
		return $ean;
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['brand']['name'])) {
			return $this->jsonDataArray['brand']['name'];
		}
		return '';
	}
	public function getCoverImage() {
		if (isset($this->jsonDataArray['image']['url'])) {
			$cImage = $this->jsonDataArray['image']['url'];
			return str_replace(array('168x158', '110x210'), array('1200*1133', '1200*1133'), $cImage);
		}
		return '';
	}
	public function getVideos() {
		$videos = array();
		$videoAJx = $this->getValue($this->VIDEO_AJX_SELECTOR);
		
		if ($videoAJx) {
			$video = array_shift($videoAJx);
			$videoLink = 'https://www.bol.com' . $video;
			$videoHtml = $this->getContent($videoLink);
			preg_match('/source src="([^"]+)/i', $videoHtml, $source);
			array_shift($source);
			$videos = $source;
		}
		return $videos;
	}
	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}

			
		$imgJsons = $this->getValue($this->IMAGE_SELECTOR);
		
		if ($imgJsons) {
			$imgJson = array_shift($imgJsons);	
			
			$imgs = json_decode($imgJson, true);
			
			foreach ($imgs as $img) {
				
				if ('image' == $img['type']) {
					
					if (isset($img['zoomImageUrl'])) {
						$images[0][] = $img['zoomImageUrl'];
					} elseif (isset($img['imageUrl'])) {
						$images[0][] = $img['imageUrl'];						
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
		
		$featureGrps = $this->xpath->query($this->FEATURE_SELECTOR);
		
		if ($featureGrps->length) {
			foreach ($featureGrps as $featureGrp) {
				
				$attributes = array();
				$features = $this->xpath->query('.//div[@class="specs__row"]', $featureGrp);
				
				if ($features->length) {
					
					foreach ($features as $feature) {
						
						$name = $this->dom->saveHTML($this->xpath->query('.//dt[@class="specs__title"]', $feature)->item(0));
						
						$namePart = explode('<', html_entity_decode($name));
						
						if (isset($namePart[1]) && $namePart[1]) {
							$name = trim(strip_tags('<' . $namePart[1]));
						} else {
							$name = $name->nodeValue;
						}
						
						$attributes[] = array(
							'name' => $this->removeUnwantedText($name),
							'value' => $this->removeUnwantedText($this->xpath->query('.//dd[@class="specs__value"]', $feature)->item(0)->nodeValue)
						);
					}
					
					$featureGroups[] = array(
						'name' => $this->removeUnwantedText($this->xpath->query('.//h3', $featureGrp)->item(0)->nodeValue),
						'attributes' => $attributes
					);
				}
			}			
		}

		return $featureGroups;
	}
	
	public function getAttributes() {
		static $attrGroups = array();
		if ($attrGroups) {
			return $attrGroups;
		}
		
		if (isset($this->jsonDataArray['attributes'])) {
			$attrGroups = $this->jsonDataArray['attributes'];
		}
		
		return $attrGroups;
	}
	
	public function removeUnwantedText( $text) {
		$finds = array(
			'uitverkocht'
		);
		
		return trim(str_replace($finds, '', preg_replace('/\s+/', ' ', $text)));
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

		$sku = $this->getSKU();
		$price = $this->getPrice();
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
				
				if (isset($this->jsonDataArray['prices'][$imageIndex])) {
					$combPrice = $this->jsonDataArray['prices'][$imageIndex];
				} else {
					$combPrice = $price;
				}
				
				$combinations[] = array(
					'sku' => $sku,
					'upc' => 0,
					'price' => $combPrice,
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

	public function getCustomerReviews( $maxReviews = 0) {
		$reviews = array();
		$maxReviews = $maxReviews ? $maxReviews : 500;
		$rLink = $this->getValue($this->REVIEW_LINK);

		if ($rLink) {
			$link = array_shift($rLink);
			if ($link) {
				$reviewsLink = str_replace('offset=6&limit=10&loadMore=true', 'limit=' . $maxReviews, 'https://www.bol.com' . $link);
				if ($reviewsLink) {
					$reviewHtml = $this->getContent($reviewsLink);
					if ($reviewHtml) {
						$dom = $this->getDomObj($reviewHtml);
						$xpath = new \DomXPath($dom);
						
						$reviewArrayObject = $xpath->query($this->REVIEW_SELECTOR);
								
						if ($reviewArrayObject->length) {
							
							foreach ($reviewArrayObject as $reviewObject) {
								
								$content = $xpath->query('.//p[@data-test="review-body"]', $reviewObject);
								
								if ($content->length) {
									$stars = $xpath->query('.//input[@name="rating-value"]/@value', $reviewObject)->item(0)->nodeValue;
									$author = $xpath->query('.//li[@data-test="review-author-name"]', $reviewObject);
									if ($author->length) {
										$reviews[] = array(
											'author' => $author->item(0)->nodeValue,
											'title' => $xpath->query('.//strong[@data-test="review-title"]', $reviewObject)->item(0)->nodeValue,
											'content' => trim($content->item(0)->nodeValue),
											'rating' => $stars ? round($stars/20) : 0,
											'timestamp' => $this->parseTimeStamp($xpath->query('.//li[@data-test="review-author-date"]', $reviewObject)->item(0)->nodeValue)
										);
									}	
								}
							}
						}
					}
				}
			}
		}
		if (!$reviews) {
			return $this->getCustomerReviews2();
		}
		return $reviews;
	}
	
	public function getCustomerReviews2() {
		$reviews = array();
		
		$reviewArrayObject = $this->xpath->query($this->REVIEW_SELECTOR);
					
		if ($reviewArrayObject->length) {
			
			foreach ($reviewArrayObject as $reviewObject) {
				
				$content = $this->xpath->query('.//p[@data-test="review-body"]', $reviewObject);
				
				if ($content->length) {
					$stars = $this->xpath->query('.//input[@name="rating-value"]/@value', $reviewObject)->item(0)->nodeValue;
					$author = $this->xpath->query('.//li[@data-test="review-author-name"]', $reviewObject);
					if ($author->length) {
						$reviews[] = array(
							'author' => $author->item(0)->nodeValue,
							'title' => $this->xpath->query('.//strong[@data-test="review-title"]', $reviewObject)->item(0)->nodeValue,
							'content' => trim($content->item(0)->nodeValue),
							'rating' => $stars ? round($stars/20) : 0,
							'timestamp' => $this->parseTimeStamp($this->xpath->query('.//li[@data-test="review-author-date"]', $reviewObject)->item(0)->nodeValue)
						);
					}	
				}
			}
		}
		return $reviews;
	}
}
