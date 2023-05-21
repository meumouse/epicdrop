<?php
/**
 * Etsy data parser class
 *
 * @package: product-importer
 *
 */
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class EtsyParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $imgColor;
	private $content;
	private $combColors = array();
	private $host;
	private $jsonDataArray = array();
	private $attrDataArray = array();
	private $REVIEW_SELECTOR = '//div[@data-review-region]';
	private $ATTRIBUTE_SELECTOR = '//div[@id="variations"]/div/div[@data-buy-box-region="variation"]';
	private $PRODUCT_ID_SELECTOR = '//div[@data-tag="productOptions"]/div/div/span';
	private $FEATURE_SELECTOR = '//p[@id="legacy-materials"]/span';
	private $FEATURE_SELECTOR2 = '//p[@id="legacy-materials"]/span';
	private $IMAGE_SELECTOR = '//div[contains(@class, "image-carousel-container")]/ul/li';
	private $VIDEO_SELECTOR = '//video/source/@src';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content, $url) {
		$this->content = $content;
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

	private function setJsonData() {
		$json = $this->getJson($this->content, 'type="application/ld+json">', '</script>');
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = json_decode($json, true);
		}

		$json = $this->getJson(
			$this->content,
			'"image_ids_by_listing_variation_ids":',
			',"should_show_scrollable_thumbnails"'
		);

		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray['image_ids_by_variation_ids'] = json_decode($json, true);
		}
	}

	private function getValue( $selector, $html = false) {
		if (empty($selector)) {
			return array();
		}
		$itmes = $this->xpath->query($selector);
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
		if (isset($this->jsonDataArray['category'])  &&  $this->jsonDataArray['category']) {
			$categories = explode('<', $this->jsonDataArray['category']);
		}
		return array_filter($categories);
	}

	public function getShortDescription() {
		return '';
	}

	public function getDescription() {
		if (isset($this->jsonDataArray['description'])) {
			return nl2br($this->jsonDataArray['description']);
		}
		return '';
	}

	public function getPrice() {
		$price = 0;
		if (isset($this->jsonDataArray['offers']['lowPrice'])) {
			$price = $this->jsonDataArray['offers']['lowPrice'];
		} elseif (isset($this->jsonDataArray['offers']['highPrice'])) {
			$price = $this->jsonDataArray['offers']['highPrice'];
		}
		return $price;
	}

	public function getSKU() {
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['brand'])) {
			return $this->jsonDataArray['brand'];
		}
		return '';
	}

	public function getCoverImage() {
		if (isset($this->jsonDataArray['image'])) {
			$cImage = $this->jsonDataArray['image'];
		}
		return $cImage;
	}

	public function getVideos() {
		$videos = $this->getValue($this->VIDEO_SELECTOR);

		return array_unique($videos);
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}

		$imgElements = $this->xpath->query($this->IMAGE_SELECTOR);

		if ($imgElements->length) {
			foreach ($imgElements as $imgElement) {
				$id = $imgElement->getAttribute('data-image-id');
				$images[$id][] = $this->xpath->query('.//img/@src', $imgElement)->item(0)->nodeValue;
			}

			$images[key($images)] = call_user_func_array('array_merge', $images);
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
		$feature = $this->getValue($this->FEATURE_SELECTOR);

		$attributes = array();

		if ($feature) {
			$features = explode(':', array_shift($feature));
			$attributes[] = array(
				'name' => $features[0],
				'value' => $features[1]
			);
		}

		if ($attributes) {
			$featureGroups[] = array(
				'name' => 'General',
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

		$attrElementGroups = $this->xpath->query($this->ATTRIBUTE_SELECTOR);

		if ($attrElementGroups->length) {
			foreach ($attrElementGroups as $attrElementGroup) {
				$options = $this->xpath->query('.//select/option', $attrElementGroup);

				if ($options->length) {
					$attrValues = array();

					foreach ($options as $option) {
						$id = $option->getAttribute('value');
						if ($id) {
							$attrValues[$id] = trim($option->nodeValue);
						}
					}

					if ($attrValues) {
						$name = trim($this->xpath->query('.//label', $attrElementGroup)->item(0)->nodeValue);

						$attrGroups[] = array(
							'name' => $name,
							'is_color' => ( stripos($name, 'colo') !== false ) ? 1 : 0,
							'values' => $attrValues
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
		$attrs = $this->getAttributes();

		$colors = array();

		foreach ($attrs as $attr) {
			if ($attr['is_color']) {
				$colors = $attr['values'];
				break;
			}
		}

		$combs = $this->makeCombinations($attrs);

		if ($combs) {
			foreach ($combs as $attrVals) {
				$colorIndex = 0;
				$imageIndex = 0;
				$comboPrice = 0;

				foreach (array_column($attrVals, 'value') as $priceVals) {
					if (in_array($priceVals, $colors)) {
						$colorIndex = array_search($priceVals, $colors);
						if (isset($this->jsonDataArray['image_ids_by_variation_ids'][$colorIndex])) {
							$imageIndex = $this->jsonDataArray['image_ids_by_variation_ids'][$colorIndex];
						}
					}

					preg_match('/\((.*?)\)/', $priceVals, $matches);
					if ($matches) {
						$comboPrice = current(explode('-', array_pop($matches)));
						$comboPrice = preg_replace('/[^0-9.]/', '', $comboPrice);
						break( 1 );
					}
				}

				$combinations[] = array(
					'sku' => $colorIndex,
					'upc' => 0,
					'price' => $comboPrice ? $comboPrice : $price,
					'weight' => 0,
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

	public function getCustomerReviews() {
		$reviews = array();

		$reviewArrayObject = $this->xpath->query($this->REVIEW_SELECTOR);
		if ($reviewArrayObject->length) {
			foreach ($reviewArrayObject as $reviewObject) {
				$content = $this->xpath->query('.//p[contains(@id, "review-preview-toggle")]', $reviewObject);

				if ($content->length) {
					$stars = $this->xpath->query('.//input[@name="rating"]/@value', $reviewObject)->item(0)->nodeValue;

					$reviews[] = array(
						'author' => $this->xpath->query('.//p[contains(@class, "wt-text-caption")]/a', $reviewObject)->item(0)->nodeValue,
						'title' => '',
						'content' => trim($content->item(0)->nodeValue),
						'rating' => $stars,
						'timestamp' => gmdate('Y-m-d H:i:s')
					);
				}
			}
		}
		return $reviews;
	}
}
