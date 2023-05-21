<?php
/**
 * Dafiti data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class DafitiParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $urlHost;
	private $content;
	private $jsonDataArray = array();
	private $TITLE_SELECTOR = '//h1[@class="product-name"]|//p[@class="seller"]|//div[@class="prd-details"]/h3';
	private $BRAND_SELECTOR = '//a[@itemprop="brand"]|//h2[@itemprop="brand"]';
	private $DESCRIPTION_SELECTOR = '//div[@class="box-description"]|//div[@id="productDetails"]';
	private $PRICE_SELECTOR = '//span[@class="catalog-detail-price-value"]/@content|//div/@data-price';
	private $COVER_IMG_SELECTOR = '//div[@class="gallery-preview"]/@data-img-zoom|//div[@id="productZoom"]/@data-zoom-image';
	private $IMAGE_SELECTOR = '//ul[contains(@class, "carousel-items")]/li[contains(@class, "gallery-items")]/a/@href';
	private $ATTRIBUTE_SELECTOR = '//div[@id="product-campaigns"]/@data-product-campaign-sku|//div[@id="productOptionsWrapper"]/div/@data-sku';
	private $REVIEW_SELECTOR = '//div[contains(@class, "rating-item")]';
	private $CATEGORY_SELECTOR = '//span[@itemprop="title"]|//a[@itemprop="item"]/@title';
	private $FEATURE_SELECTOR_NM = '//div[@class="box-informations"]/h2|//div[@id="productDetails"]/h2[2]';
	private $FEATURE_SELECTOR_VL_NM = '//table[@class="product-informations"]/tbody/tr|//table[contains(@class, "prd-attributes")]/tbody/tr|//table[contains(@class, "prd-attributes")]/tr';
	private $COLOR_NM_SELECTOR = '//div[@class="catalog-detail-colors"]/h3|//script[@id="itemColorsTemplate"]';
	private $SIZE_NM_SELECTOR = '//h2[contains(@class, "stock-available-title")]|//div[@id="OptionsSingleDefault"]/p';
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
		$urlHost = parse_url($this->url, PHP_URL_HOST);
		$this->jsonDataArray['products'] = array();

		$sku = $this->getValue($this->ATTRIBUTE_SELECTOR);
		$sku = array_shift($sku);
		if ($sku) {
			$attrLink = 'https://' . $urlHost . '/catalog/detailJson?sku=' . $sku;
			$attrData = $this->getContent($attrLink);
			$this->jsonDataArray['products'] = json_decode($attrData, true);
		}

		if (!$this->jsonDataArray['products']) {
			$attrLink = 'https://' . $urlHost . '/catalog/additionaldata/?sku=' . $sku;
			$attrData = $this->getContent($attrLink);
			$this->jsonDataArray['products'] = json_decode($attrData, true);
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
		$title = $this->getValue($this->TITLE_SELECTOR);
		if ($title) {
			$title = array_shift($title);
			return $title;
		}
		return '';
	}

	public function getCategories() {
		$categories = array();
		$categories = $this->getValue($this->CATEGORY_SELECTOR);
		return $categories;
	}

	public function getDescription() {
		$description = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		if ($description) {
			$description = array_shift($description);
			return $description;
		}
		return '';
	}

	public function getPrice() {
		$price = 0;
		$price = $this->getValue($this->PRICE_SELECTOR);
		if ($price) {
			$price = array_shift($price);
			return $price;
		}
		return $price;
	}

	public function getSKU() {
		$sku = $this->getValue($this->ATTRIBUTE_SELECTOR);
		if ($sku) {
			$sku = array_shift($sku);
			return $sku;
		}
		return '';
	}

	public function getBrand() {
		$sku = $this->getValue($this->BRAND_SELECTOR);
		if ($sku) {
			$sku = array_shift($sku);
			return $sku;
		}
		return '';
	}

	public function getCoverImage() {
		$sku = $this->getValue($this->COVER_IMG_SELECTOR);
		if ($sku) {
			$sku = array_shift($sku);
			return $sku;
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}

		if (isset($this->jsonDataArray['products']['colors']) && $this->jsonDataArray['products']['colors']) {
			foreach ($this->jsonDataArray['products']['colors'] as $key => $imgs) {
				$dataHtml = $this->getContent('https://www.dafiti.com.br/' . $imgs['link']);

				$dom = $this->getDomObj($dataHtml);
				$xpath = new \DomXPath($dom);
				$imgsLink = $this->getValue($this->IMAGE_SELECTOR, false, $xpath);
				if ($imgsLink) {
					$images[$key] = $imgsLink;
				}
			}
		} elseif (isset($this->jsonDataArray['products']['grouped']) && $this->jsonDataArray['products']['grouped']) {
			foreach ($this->jsonDataArray['products']['grouped'] as $key => $imgs) {
				if ($imgs) {
					$images[$key] = str_replace('catalog-new', 'product', $imgs['images']);
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

	public function getFeatures( $xpath = null) {
		$featureGroups = array();
		$featuresNM = $this->getValue($this->FEATURE_SELECTOR_NM, false, $xpath);

		$xpath = $xpath ? $xpath : $this->xpath;
		$featuresVLNMs = $xpath->query($this->FEATURE_SELECTOR_VL_NM);
		$attributes = array();
		if ($featuresVLNMs->length) {
			$featuresNM = array_shift($featuresNM);
			foreach ($featuresVLNMs as  $featuresVLN) {
				$attributes[] = array(
					'name' => $xpath->query('.//td', $featuresVLN)->item(0)->nodeValue,
					'value' => preg_replace('/\s+/', ' ', $xpath->query('.//td', $featuresVLN)->item(1)->nodeValue)
				);
			}
		}
		if ($attributes) {
			$featureGroups[] = array(
				'name' => $featuresNM,
				'attributes' => $attributes
			);
		}
		return $featureGroups;
	}

	public function getAttributes() {
		static $attrGroups = array();
		if ($attrGroups) {
			return $attrGroups;
		}
		$colorNm = $this->getValue($this->COLOR_NM_SELECTOR, true);
		$sizeNm = $this->getValue($this->SIZE_NM_SELECTOR);
		if ($sizeNm) {
			$sizeNm = array_shift($sizeNm);
		}
		if ($colorNm) {
			$colorNm = array_shift($colorNm);
		}

		$urlHost = parse_url($this->url, PHP_URL_HOST);
		$color = array();
		$size = array();

		if (isset($this->jsonDataArray['products']['colors']) && $this->jsonDataArray['products']['colors']) {
			foreach ($this->jsonDataArray['products']['colors'] as $colorAttr) {
				$color[] = $colorAttr['color'];
			}
			$attrGroups[] = array(
				'name' => 'MAIS CORES',
				'is_color' => 1,
				'values' => $color
			);
		} elseif (isset($this->jsonDataArray['products']['grouped']) && $this->jsonDataArray['products']['grouped']) {
			foreach ($this->jsonDataArray['products']['grouped'] as $colorAttr) {
				$colorLink = 'https://' . $urlHost . $colorAttr['link'];

				$dataHtml = $this->getContent($colorLink);
				$dom = $this->getDomObj($dataHtml);
				$xpath = new \DomXPath($dom);
				$colorVal = $this->getFeatures($xpath);
				$colorVl = array_shift($colorVal);

				foreach ($colorVl['attributes'] as $colors) {
					if ('Color' == $colors['name']) {
						$color[] = $colors['value'];
					}
				}
			}
			$attrGroups[] = array(
				'name' => 'MÁS COLORES',
				'is_color' => 1,
				'values' => array_unique($color)
			);
		}

		if (isset($this->jsonDataArray['products']['sizes']) && $this->jsonDataArray['products']['sizes']) {
			foreach ($this->jsonDataArray['products']['sizes'] as $sizeAttr) {
				$size[] = $sizeAttr['name'];
			}
			$attrGroups[] = array(
				'name' => $sizeNm,
				'is_color' => 0,
				'values' => $size
			);
		} elseif (isset($this->jsonDataArray['products']['uniques']['sizes']) && $this->jsonDataArray['products']['uniques']['sizes']) {
			foreach ($this->jsonDataArray['products']['uniques']['sizes'] as $sizeAttr) {
				$size[] = $sizeAttr['label'];
			}
			$attrGroups[] = array(
				'name' => $sizeNm,
				'is_color' => 0,
				'values' => $size
			);
		}
		return $attrGroups;
	}

	public function getCombinations2() {
		static $combinations = array();
		$price = $this->getPrice();
		$sku = $this->getSKU();
		$weight = $this->getWeight();
		$urlHost = parse_url($this->url, PHP_URL_HOST);

		$sizeNm = $this->getValue($this->SIZE_NM_SELECTOR);
		if ($sizeNm) {
			$sizeNm = array_shift($sizeNm);
		}
		if (isset($this->jsonDataArray['products']['grouped']) && $this->jsonDataArray['products']['grouped']) {
			foreach ($this->jsonDataArray['products']['grouped'] as $keys => $attrVals) {
				if (isset($attrVals['link'])) {
					$colorLink = 'https://' . $urlHost . $attrVals['link'];

					$dataHtml = $this->getContent($colorLink);
					$dom = $this->getDomObj($dataHtml);
					$xpath = new \DomXPath($dom);
					$colorVal = $this->getFeatures($xpath);
					$comPrice = $xpath->query('.//div/@data-price')->item(0)->nodeValue;
					$colorVl = array_shift($colorVal);

					foreach ($colorVl['attributes'] as $colors) {
						if ('Color' == $colors['name']) {
							if (isset($this->jsonDataArray['products']['uniques']['sizes']) && $this->jsonDataArray['products']['uniques']['sizes']) {
								foreach ($this->jsonDataArray['products']['uniques']['sizes'] as $sizeVals) {
									$combinations[] = array(
										'sku' => $sizeVals['simple'],
										'upc' => 0,
										'price' => $comPrice ? $comPrice : $price,
										'weight' => 0,
										'image_index' => $keys,
										'attributes' => array(
											array(
												'name' => $sizeNm,
												'value' => $sizeVals['label'],
											),
											array(
												'name' => 'MÁS COLORES',
												'value' => $colors['value'],
											)
										)
									);
								}
							} else {
								$combinations[] = array(
									'sku' => $sizeVals['simple'],
									'upc' => 0,
									'price' => $comPrice ? $comPrice : $price,
									'weight' => 0,
									'image_index' => $keys,
									'attributes' => array(
										array(
											'name' => 'MÁS COLORES',
											'value' => $attrVals['color'],
										)
									)
								);
							}
						}
					}
				}
			}
		} elseif (isset($this->jsonDataArray['products']['uniques']['sizes']) && $this->jsonDataArray['products']['uniques']['sizes']) {
			foreach ($this->jsonDataArray['products']['uniques']['sizes'] as $sizeVals) {
				if (isset($sizeVals['name'])) {
					$combinations[] = array(
						'sku' => isset($sizeVals['sku']) ? $sizeVals['sku'] : $sku,
						'upc' => 0,
						'price' => $price,
						'weight' => 0,
						'image_index' => 0,
						'attributes' => array(
							array(
								'name' => $sizeNm,
								'value' => $sizeVals['name']
							)
						)
					);
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

		if (isset($this->jsonDataArray['products']['colors']) && $this->jsonDataArray['products']['colors']) {
			foreach ($this->jsonDataArray['products']['colors'] as $keys => $attrVals) {
				if (isset($attrVals['color'])) {
					if (isset($this->jsonDataArray['products']['sizes']) && $this->jsonDataArray['products']['sizes']) {
						foreach ($this->jsonDataArray['products']['sizes'] as $sizeVals) {
							$combinations[] = array(
								'sku' => $attrVals['sku_simple'] . '-' . $sizeVals['sku'],
								'upc' => 0,
								'price' => isset($attrVals['price']) ? $attrVals['price'] : $price,
								'weight' => 0,
								'image_index' => $keys,
								'attributes' => array(
									array(
										'name' => 'TAMANHO',
										'value' => $sizeVals['name'],
									),
									array(
										'name' => 'MAIS CORES',
										'value' => $attrVals['color'],
									)
								)
							);
						}
					} else {
						$combinations[] = array(
							'sku' => $sizeVals['sku_simple'],
							'upc' => 0,
							'price' => isset($attrVals['price']) ? $attrVals['price'] : $price,
							'weight' => 0,
							'image_index' => $keys,
							'attributes' => array(
								array(
									'name' => 'MAIS CORES',
									'value' => $attrVals['color'],
								)
							)
						);
					}
				}
			}
		} elseif (isset($this->jsonDataArray['products']['sizes']) && $this->jsonDataArray['products']['sizes']) {
			foreach ($this->jsonDataArray['products']['sizes'] as $sizeVals) {
				$combinations[] = array(
					'sku' => $sizeVals['sku'],
					'upc' => 0,
					'price' => $price,
					'weight' => 0,
					'image_index' => 0,
					'attributes' => array(
						array(
							'name' => 'TAMANHO',
							'value' => $sizeVals['name']
						)
					)
				);
			}
		}
		if (!$combinations) {
			$combinations = $this->getCombinations2();
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

	public function getCustomerReviews( $maxReviews = 0) {
		$reviews = array();
		$maxReviews = $maxReviews ? $maxReviews : 500;

		$suks = $this->getValue($this->ATTRIBUTE_SELECTOR);
		$suk = array_shift($suks);
		$urlHost = $this->urlHost;
		if ($suk) {
			$reviewsLink = 'https://' . $urlHost . '/catalog/PaginatedReviews?sku=' . $suk . '&limit=' . $maxReviews;
			if ($reviewsLink) {
				$reviewHtml = $this->getContent($reviewsLink);
				if ($reviewHtml) {
					$dom = $this->getDomObj($reviewHtml);
					$xpath = new \DomXPath($dom);

					$reviewArrayObject = $xpath->query($this->REVIEW_SELECTOR);

					if ($reviewArrayObject->length) {
						foreach ($reviewArrayObject as $reviewObject) {
							$content = $xpath->query('.//p[@class="rating-item-detail"]', $reviewObject);

							if ($content->length) {
								$stars = $xpath->query('.//meta[@itemprop="ratingValue"]/@content', $reviewObject)->item(0)->nodeValue;

								$author = $xpath->query('.//span[@itemprop="author"]', $reviewObject);
								if ($author->length) {
									$reviews[] = array(
										'author' => $author->item(0)->nodeValue,
										'title' => $xpath->query('.//p[@class="rating-item-title"]', $reviewObject)->item(0)->nodeValue,
										'content' => trim($content->item(0)->nodeValue),
										'rating' => $stars,
										'timestamp' => preg_replace('/[^0-9\/]/', '', $xpath->query('.//p[@class="rating-item-author"]', $reviewObject)->item(0)->nodeValue)
									);
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
				$content = $this->xpath->query('.//p[@class="rating-item-detail"]', $reviewObject);

				if ($content->length) {
					$stars = $this->xpath->query('.//meta[@itemprop="ratingValue"]/@content', $reviewObject)->item(0)->nodeValue;

					$author = $this->xpath->query('.//span[@itemprop="author"]', $reviewObject);
					if ($author->length) {
						$reviews[] = array(
							'author' => $author->item(0)->nodeValue,
							'title' => $this->xpath->query('.//p[@class="rating-item-title"]', $reviewObject)->item(0)->nodeValue,
							'content' => trim($content->item(0)->nodeValue),
							'rating' => $stars,
							'timestamp' => preg_replace('/[^0-9\/]/', '', $this->xpath->query('.//p[@class="rating-item-author"]', $reviewObject)->item(0)->nodeValue)
						);
					}
				}
			}
		}
		return $reviews;
	}
}
