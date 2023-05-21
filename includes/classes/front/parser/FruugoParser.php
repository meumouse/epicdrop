<?php
/**
 * Fruugo data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class FruugoParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $JSON_DATA_SELECTOR = '//script[@type="application/ld+json"]';
	private $CATEGORY_SELECTOR = '//ol[@class="breadcrumb"]/li';
	private $ID_VARIANT_SELECTOR = '//select[contains(@class, "custom-select")]/option/@value';
	private $FEATURES_SELECTOR = '//ul[contains(@class, "product-description-spec-list")]/li';
	private $ATTRIBUTE_SELECTOR = '//ul[@id="display_size_id"]/li';
	private $ATTR_NAME_SELECTOR = '//div[@class="sizeList_title"]';
	private $REVIEW_SELECTOR1 = '//a[@class="trustpilot-widget"]/@href';
	private $REVIEW_SELECTOR = '//section[@class="styles_reviewsContainer__3_GQw"]/div/article';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content, $url) {
		$this->content = preg_replace('/\s+/', ' ', $content);
		$this->dom = $this->getDomObj($content);
		$this->url = $url;
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

	private function setJsonData() {
		$json = $this->getJson($this->content, 'dow.skuInfo = ', '; windo');
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = json_decode($json, true);
		}
		$jsons = $this->getValue($this->JSON_DATA_SELECTOR);
		if ($jsons) {
			foreach ($jsons as $json) {
				if ($json) {
					$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
					$json = json_decode($json, true);
					if ($json) {
						$this->jsonDataArray = array_merge($this->jsonDataArray, $json);
					}
				}
			}
		}
		$this->jsonDataArray['variantIds'] = array();
		$variants = $this->getValue($this->ID_VARIANT_SELECTOR);
		if ($variants) {
			foreach ($variants as $variant) {
				preg_match('#\[(.*?)\]#', $variant, $match);
				$varIds = explode(',', $match[1]);
				if ($varIds) {
					foreach ($varIds as $ids) {
						$this->jsonDataArray['variantIds'][] = trim($ids);
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
		if (isset($this->jsonDataArray['titleBeforeTranslation'])) {
			return $this->jsonDataArray['titleBeforeTranslation'];
		}
		return '';
	}

	public function getCategories() {
		$categories = $this->getValue($this->CATEGORY_SELECTOR);
		if ($categories) {
			return array_unique($categories);
		}
		return $categories;
	}

	public function getShortDescription() {
		return '';
	}

	public function getDescription() {
		if (isset($this->jsonDataArray['descriptionBeforeTranslation'])) {
			return $this->jsonDataArray['descriptionBeforeTranslation'];
		}
		return '';
	}

	public function getPrice() {
		$price = 0;
		if (isset($this->jsonDataArray['price']['numericTotalInclTaxAfterDiscount'])) {
			$price = $this->jsonDataArray['price']['numericTotalInclTaxAfterDiscount'];
		} elseif (isset($this->jsonDataArray['price']['numericTotalExclTax'])) {
			$price = $this->jsonDataArray['price']['numericTotalExclTax'];
		}
		return $price;
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['id'])) {
			return $this->jsonDataArray['id'];
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['brand']['title'])) {
			return $this->jsonDataArray['brand']['title'];
		}
		return '';
	}

	public function getCoverImage() {
		if (isset($this->jsonDataArray['images']['urls'][0])) {
			return $this->jsonDataArray['images']['urls'][0];
		}
		return '';
	}

	public function getImages() {
		static $images = array();
		if ($images) {
			return $images;
		}
		$this->jsonDataArray['product'] = array();
		$skuIds = array_unique($this->jsonDataArray['variantIds']);
		if ($skuIds) {
			foreach ($skuIds as $key => $skus) {
				$mainUrl = explode('?', $this->url);
				$mainUrl1 = array_shift($mainUrl);
				$mainUrl2 = explode('-', $mainUrl1);
				array_pop($mainUrl2);
				$url = implode('-', $mainUrl2);

				$dataHtml = $this->getContent($url . '-' . $skus);
				$dataHtml = preg_replace('/\s+/', ' ', $dataHtml);
				$this->jsonDataArray['product'][$key]['attributes'] = array();

				if ($dataHtml) {
					$json = $this->getJson($dataHtml, 'dow.skuInfo = ', '; window');
					if ($json) {
						$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
						$jsonDataArray = json_decode($json, true);
					}
					if (isset($jsonDataArray['images']['urls'])) {
						$images[$key] = $jsonDataArray['images']['urls'];
					}
					$this->jsonDataArray['product'][$key]['sku'] = $skus;
					if (isset($jsonDataArray['price']['numericTotalInclTaxAfterDiscount'])) {
						$this->jsonDataArray['product'][$key]['price'] = $jsonDataArray['price']['numericTotalInclTaxAfterDiscount'];
					} elseif (isset($jsonDataArray['price']['numericTotalInclTax'])) {
						$this->jsonDataArray['product'][$key]['price'] = $jsonDataArray['price']['numericTotalInclTax'];
					}
					if (isset($jsonDataArray['attributes']) && $jsonDataArray['attributes']) {
						foreach ($jsonDataArray['attributes'] as $attr) {
							$this->jsonDataArray['product'][$key]['attributes'][] = array(
								'name' =>  $attr['title'],
								'value' =>  $attr['value']
							);
						}
					}
				}
			}
		} elseif (isset($this->jsonDataArray['images']['urls'])) {
			$images[0] = $this->jsonDataArray['images']['urls'];
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
		$features = $this->getValue($this->FEATURES_SELECTOR);
		$attributes = array();

		if ($features) {
			foreach ($features as $attrObject) {
				$features2 = explode(':', $attrObject);
				if ($features2[1]) {
					$attributes[] = array(
						'name' => $features2[0],
						'value' => trim($features2[1])
					);
				}
			}
			if ($attributes) {
				$featureGroups[] = array(
					'name' => ' ',
					'attributes' => $attributes
				);
			}
		}
		return $featureGroups;
	}

	public function getAttributes() {
		static $attrGroups = array();
		if ($attrGroups) {
			return $attrGroups;
		}
		$this->getImages();

		if (isset($this->jsonDataArray['product']) && $this->jsonDataArray['product']) {
			foreach ($this->jsonDataArray['product'] as $attrs) {
				foreach ($attrs['attributes'] as $attrVals) {
					$key = base64_encode($attrVals['name']);

					if (!isset($attrGroups[$key])) {
						$attrGroups[$key] = array(
							'name' => $attrVals['name'],
							'is_color' => 0,
							'values' => array()
						);
					}
					if (!in_array($attrVals['value'], $attrGroups[$key]['values'])) {
						$attrGroups[$key]['values'][] = $attrVals['value'];
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
		$this->getImages();
		$price = $this->getPrice();
		$sku = $this->getSKU();

		if (isset($this->jsonDataArray['product']) && $this->jsonDataArray['product']) {
			foreach ($this->jsonDataArray['product'] as $keys => $attrVals) {
				if (isset($attrVals['attributes'])) {
					$combinations[] = array(
						'sku' => isset($attrVals['sku']) ? $attrVals['sku'] : $price,
						'upc' => 0,
						'price' => isset($attrVals['price']) ? $attrVals['price'] : $price,
						'weight' => 0,
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

	public function getCustomerReviews() {
		$reviews = array();
		$link = '';
		$r_link = $this->getValue($this->REVIEW_SELECTOR1);
		$link = array_shift($r_link);
		if ($link) {
			$reviewHtml = $this->getContent($link);
			if ($reviewHtml) {
				$dom = $this->getDomObj($reviewHtml);
				$xpath = new \DomXPath($dom);
				$reviewArrayObject = $xpath->query($this->REVIEW_SELECTOR);
				if ($reviewArrayObject->length) {
					foreach ($reviewArrayObject as $reviewObject) {
						$stars = $xpath->query('.//div[@class="styles_reviewHeader__iU9Px"]/@data-service-review-rating', $reviewObject);
						if ($stars->length) {
							$star = $stars->item(0)->nodeValue;
						}
						$author = $xpath->query('.//a[@name="consumer-profile"]/div[1]', $reviewObject);
						if ($author->length) {
							$reviews[] = array(
								'author' => $author->item(0)->nodeValue,
								'title' => @$xpath->query('.//a[@name="review-title"]', $reviewObject)->item(0)->nodeValue,
								'content' => trim(@$xpath->query('.//p', $reviewObject)->item(0)->nodeValue),
								'rating' => $star,
								'timestamp' => gmdate('Y-m-d H:i:s', strtotime(@$xpath->query('.//div[contains(@class, "typography_typography__QgicV")]/time/@datetime', $reviewObject)->item(0)->nodeValue))
							);
						}
					}
				}
			}
		}
		return $reviews;
	}
}
