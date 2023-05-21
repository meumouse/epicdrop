<?php
/**
 * Kaufland data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class KauflandParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $priceVar = array();
	private $skuVar = array();
	private $TITLE_SELECTOR = '//h1[@class="rd-title"]';
	private $CATEGORY_SELECTOR = '//div[@class="rd-breadcrumb__item"]/a';
	private $JSON_DATA_SELECTOR = '//script[@type="application/ld+json"]';
	private $SHORT_DESCRIPTION_SELECTOR = '//div[@class="rd-description-teaser__description-text
            "]/p';
	private $DESCRIPTION_SELECTOR = '//div[@class="rd-product-description__top-accordion-content"]';
	private $PRICE_SELECTOR = '//div[@class="rd-buybox__price"]';
	private $IMAGE_VR_SELECTOR = '//div[@class="rd-variant__options"]/a';
	private $IMAGE_SELECTOR = '//div[@class="displayingImage_3xp0y"]/img/@src|//li[@class="image-thumbnail"]/div/button/img/@src';
	private $FEATURE_SELECTOR_VL_NM = '//div[@id="detailsAndSpecs"]/div/div';
	private $ATTRIBUTE_SELECTOR = '//div[@class="shop-variation-wrapper"]';
	private $SELECTED_COLOR_SELECTOR = '//div[@class="hover-name"]';
	private $ATTRIBUTE_SELECTOR_price = '//div[@data-tstid="sizesDropdownRow"]/span/span';
	private $ATTRIBUTE_SELECTOR_NM = '//div[@id="sizesDropdownTrigger"]/span/span[@class="_ec791b"]';
	private $REVIEW_SELECTOR = '//div[@itemtype="http://schema.org/Review"]';
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

	public function getContent( $url) {
		$curl = curl_init();

		curl_setopt_array(
			$curl,
			array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_HTTPHEADER => array(
					'cache-control: no-cache',
					'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.74 Safari/537.36'
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
		$sku = $this->getSKU();
		if ($sku) {
			$productDataLink = 'https://api.cloud.kaufland.de/pdp-frontend/v1/' . $sku . '/mrv';
			$json = $this->getContent($productDataLink);

			if ($json) {
				$this->jsonDataArray = json_decode($json, true);
			}
			$this->jsonDataArray['attributes'] = array();
			$productDataLink = 'https://www.kaufland.de/backend/product-detail-page/v1/' . $sku . '/product-variants';
			$json = $this->getContent($productDataLink);

			if ($json) {
				$this->jsonDataArray['attributes'] = json_decode($json, true);
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

	public function getShortDescription() {
		$discript = $this->getValue($this->SHORT_DESCRIPTION_SELECTOR, true);
		$discript = array_shift($discript);
		if ($discript) {
			return $discript;
		}
		return '';
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

		$price = $this->getValue($this->PRICE_SELECTOR);
		if ($price) {
			$price = array_shift($price);
			$price = preg_replace('/[^0-9,.]/', '', $price);
			return str_replace(',', '.', $price);
		}
		return 0;
	}

	public function getSKU() {
		$url = explode('?', $this->url);
		$url = array_shift($url);
		$sku = preg_replace('/[^0-9]/', '', $url);
		if ($sku) {
			return $sku;
		}
		return '';
	}

	public function getUPC() {
		$url = explode('?', $this->url);
		$url = array_pop($url);
		$sku = preg_replace('/[^0-9]/', '', $url);
		if ($sku) {
			return $sku;
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['product']['manufacturer']['name'])) {
			return  $this->jsonDataArray['product']['manufacturer']['name'];
		}
		return '';
	}

	public function getCoverImage() {
		if (isset($this->jsonDataArray['product']['pictureData']['heroImage'])) {
			$cImage = $this->jsonDataArray['product']['pictureData']['heroImage'];
			return  'https://media.cdn.kaufland.de/product-images/2048x2048/' . $cImage . '.webp';
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}
		if (isset($this->jsonDataArray['attributes']) && $this->jsonDataArray['attributes']) {
			foreach ($this->jsonDataArray['attributes'] as $varImage) {
				if (isset($varImage['variantItems']) && $varImage['variantItems']) {
					foreach ($varImage['variantItems'] as $key => $imageId) {
						if (isset($imageId['idItem'])) {
							$id = $imageId['idItem'];

							$imageLink = 'https://api.cloud.kaufland.de/pdp-frontend/v1/' . $id . '/mrv';
							$json = $this->getContent($imageLink);
							$imgJson = json_decode($json, true);

							if (isset($imgJson['product']['pictureData']['pictures']) && $imgJson['product']['pictureData']['pictures']) {
								foreach ($imgJson['product']['pictureData']['pictures'] as $imgsId) {
									if (isset($imgsId['hash'])) {
										$images[$key][] = 'https://media.cdn.kaufland.de/product-images/1024x1024/' . $imgsId['hash'] . '.jpg';
										$this->skuVar[$key] = $imageId['idItem'];
										if (isset($imageId['price'])) {
											$this->priceVar[$key] = str_replace(',', '.', $imageId['price']);
										}
									}
								}
							}
						}
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

	public function getFeatures( $xpath = null) {
		$featureGroups = array();

		$attributes = array();
		if (isset($this->jsonDataArray['product']['attributes']) && $this->jsonDataArray['product']['attributes']) {
			foreach ($this->jsonDataArray['product']['attributes'] as  $featuresGroup) {
				foreach ($featuresGroup as $features) {
					if (isset($features['values'][0]['text'])) {
						$attributes[] = array(
						'name' => $features['name'],
						'value' => $features['values'][0]['text']
						);
					}
				}
			}
		}
		if ($attributes) {
			$featureGroups[] = array(
				'name' => 'Produktdaten',
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
		if (isset($this->jsonDataArray['attributes']) && $this->jsonDataArray['attributes']) {
			foreach ($this->jsonDataArray['attributes'] as $attrVals) {
				$attributes = array();
				if (isset($attrVals['variantItems']) && $attrVals['variantItems']) {
					foreach ($attrVals['variantItems'] as $attrs) {
						$attributes[] = $attrs['title'];
					}
					if ($attributes) {
						$attrGroups[] = array(
							'name' => $attrVals['title'],
							'is_color' => 'Farbe' == $attrVals['title'] ? 1 : 0,
							'values' => $attributes
						);
					}
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
		$sku = $this->getSKU();
		$upc = $this->getUPC();

		$colors = array();
		$attrs = $this->getAttributes();
		$combs = $this->makeCombinations($attrs);

		foreach ($attrs as $attribute) {
			if ($attribute['is_color']) {
				$colors = $attribute['values'];
				break;
			}
		}
		$this->getImages();
		if ($combs) {
			foreach ($combs as $attrVals) {
				$colorIndex = 0;
				foreach ($attrVals as $vals) {
					if (in_array($vals['value'], $colors)) {
						$colorIndex = array_search($vals['value'], $colors);
						break;
					}
				}
				if ($this->priceVar) {
					$comboPrice = $this->priceVar[$colorIndex];
				}
				if ($this->skuVar) {
					$skuId = $this->skuVar[$colorIndex];
				}
				$combinations[] = array(
					'sku' =>isset($skuId) ? $skuId : $sku,
					'upc' => $upc,
					'price' => trim(isset($comboPrice) ? $comboPrice : $price),
					'weight' => 0,
					'image_index' => $colorIndex,
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

	public function getCustomerReviews() {
		$reviews = array();
		if (!$reviews) {
			$sku = $this->getSKU();
			$this->reviewLink = 'https://api.cloud.kaufland.de/pdp-frontend/v1/' . $sku . '/reviews';
		}
		$reviewLink = $this->reviewLink;
		if ($reviewLink) {
			$reviewJson = $this->getContent($reviewLink);

			if ($reviewJson) {
				$reviewArrayObject = json_decode($reviewJson, true);

				if (isset($reviewArrayObject['reviews'])) {
					$isMaxReached = false;
					foreach ($reviewArrayObject['reviews'] as $reviewObject) {
						if ($reviewObject['author']) {
							$reviews[] = array(
								'author' => $reviewObject['author'],
								'title' => isset($reviewObject['title']) ? $reviewObject['title'] : '',
								'content' => isset($reviewObject['text']) ? $reviewObject['text'] : '',
								'rating' =>$reviewObject['rating'],
								'timestamp' => gmdate('Y-m-d H:i:s', strtotime($reviewObject['datePublished']))
							);
						}
					}
				}
			}
		}
		return $reviews;
	}
}
