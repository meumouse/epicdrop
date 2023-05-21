<?php
/**
 * Wish data parser class
 *
 * @package: product-importer
 *
 */
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class WishParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $content;
	private $combColors = array();
	private $jsonDataArray = array();
	private $attrDataArray = array();
	private $REVIEW_SELECTOR = '//ul[@class="comments__list"]/li';
	private $DATA_SELECTOR = '//script[@id="item"]';
	private $SITE_LANG_SELECTOR = '//html/@lang';
	private $IMAGE_COVER_SELECTOR = '//img[@class="hover-zoom-hero-image"]/@src';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content) {
		$this->dom = $this->getDomObj($content);

		$content = iconv('UTF-8', 'UTF-8//IGNORE', $content);
		$this->content = preg_replace('/\s+/', ' ', $content);

		/* Create a new XPath object */
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
		$json = $this->getJson($this->content, '__PRELOADED_STATE__ = ', ' </script>');
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);

			$this->jsonDataArray = json_decode($json, true);
		}
		if (isset($this->jsonDataArray['data']['history']['histories'][0])) {
			
			$productLink = $this->jsonDataArray['data']['history']['histories'][0];
			
			if ($productLink) {
				$linkParams = explode('?', $productLink);
				$linkParams = explode('/', $linkParams[0]);
				$productId = array_pop($linkParams);
				$this->jsonDataArray['productId'] =  $productId;
			}
		}
		
		$json = $this->getJson($this->content, 'indow.Globals = ', '; window');
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = array_merge($this->jsonDataArray, json_decode($json, true));
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
		if (isset($this->jsonDataArray['productId'], $this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['name'])) {
			return $this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['name'];
		} elseif (isset($this->jsonDataArray['initial_data']['product']['contest']['meta_title'])) {
			return $this->jsonDataArray['initial_data']['product']['contest']['meta_title'];
		}
		return '';
	}

	public function getCategories() {
		return array();
	}

	public function getShortDescription() {
		return '';
	}

	public function getDescription() {
		$description = '';

		if (isset($this->jsonDataArray['productId'], $this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['description'])) {
			$description = $this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['description'];
		} elseif (isset($this->jsonDataArray['initial_data']['product']['contest']['description'])) {
			$description = $this->jsonDataArray['initial_data']['product']['contest']['description'];
		}
		return nl2br($description);
	}

	public function getPrice() {
		if (isset($this->jsonDataArray['productId'], $this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['commerceProductInfo']['variations'][0]['localized_price_before_personal_price']['localized_value'])) {
			return $this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['commerceProductInfo']['variations'][0]['localized_price_before_personal_price']['localized_value'];
		} elseif (isset($this->jsonDataArray['initial_data']['product']['contest']['localized_value']['localized_value'])) {
			return $this->jsonDataArray['initial_data']['product']['contest']['localized_value']['localized_value'];
		}
		return 0;
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['productId'], $this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['id'])) {
			return $this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['id'];
		} elseif (isset($this->jsonDataArray['initial_data']['product']['contest']['commerce_product_info']['id'])) {
			return $this->jsonDataArray['initial_data']['product']['contest']['commerce_product_info']['id'];
		}
		return '';
	}
	public function getWeight() {
		static $weight = array();

		if ($weight) {
			return $weight;
		}

		if (isset($this->jsonDataArray['productId'], $this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['commerceProductInfo']['product_weight'])) {
			$weight = $this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['commerceProductInfo']['product_weight'];
		} elseif (isset($this->jsonDataArray['initial_data']['product']['contest']['commerce_product_info']['product_weight'])) {
			$weight = $this->jsonDataArray['initial_data']['product']['contest']['commerce_product_info']['product_weight'];
		}

		if ($weight) {
			$weight = array(
				'value' => $weight,
				'unit' => 'kg'
			);
		}

		return $weight;
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['initial_data']['product']['contest']['authorized_brand']['display_name'])) {
			return $this->jsonDataArray['initial_data']['product']['contest']['authorized_brand']['display_name'];
		}
		return '';
	}

	public function getCoverImage() {
		if (isset($this->jsonDataArray['productId'], $this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['productPagePicture'])) {
			return $this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['productPagePicture'];
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}
		if (isset($this->jsonDataArray['productId'], $this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['extraPhotoUrls'])
			&& $this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['extraPhotoUrls']) {
			$images[$this->jsonDataArray['productId']] = str_replace('small', 'big', $this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['extraPhotoUrls']);
		} elseif (isset($this->jsonDataArray['initial_data']['product']['contest']['extra_photo_urls'])) {
			$images[] = str_replace('small', 'big', $this->jsonDataArray['initial_data']['product']['contest']['extra_photo_urls']);
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
		return array();
	}

	public function getAttributes() {
		static $attrGroups = array();

		if ($attrGroups) {
			return $attrGroups;
		}

		$colors = array();
		$sizes = array();

		if (isset($this->jsonDataArray['productId'], $this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['multipliedPrimeToVariationInfo'])
			&& $this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['multipliedPrimeToVariationInfo']
		) {
			foreach ($this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['multipliedPrimeToVariationInfo'] as $combis) {
				if (isset($combis['variation']['size'])) {
					$sizes[] = $combis['variation']['size'];
				}
			}
		} elseif (isset($this->jsonDataArray['initial_data']['product']['contest']['commerce_product_info']['variations'])
			&& $this->jsonDataArray['initial_data']['product']['contest']['commerce_product_info']['variations']
		) {
			foreach ($this->jsonDataArray['initial_data']['product']['contest']['commerce_product_info']['variations'] as $attributes) {
				if (isset($attributes['size'])) {
					$sizes[] = $attributes['size'];
				}
			}
		}
		$sizes = array_unique($sizes);
		if ($sizes) {
			$attrGroups[] = array(
				'name' => 'Size',
				'is_color' => 0,
				'values' => array_unique($sizes)
			);
		}
		if (isset($this->jsonDataArray['productId'], $this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['multipliedPrimeToVariationInfo'])
			&& $this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['multipliedPrimeToVariationInfo']
		) {
			foreach ($this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['multipliedPrimeToVariationInfo'] as $combis) {
				if (isset($combis['variation']['color'])) {
					$colors[] = $combis['variation']['color'];
				}
			}
		} elseif (isset($this->jsonDataArray['initial_data']['product']['contest']['commerce_product_info']['variations'])
			&& $this->jsonDataArray['initial_data']['product']['contest']['commerce_product_info']['variations']
		) {
			foreach ($this->jsonDataArray['initial_data']['product']['contest']['commerce_product_info']['variations'] as $attributes) {
				if (isset($attributes['color'])) {
					$colors[] = $attributes['color'];
				}
			}
		}
		$colors = array_unique($colors);
		if ($colors) {
			$attrGroups[] = array(
				'name' => 'Color',
				'is_color' => 1,
				'values' => $colors
			);
		}

		return $attrGroups;
	}

	public function getCombinations() {
		static $combinations = array();
		if ($combinations) {
			return $combinations;
		}

		$sku = $this->getSku();
		$weight = $this->getWeight();
		$price = $this->getPrice();

		if (isset($this->jsonDataArray['initial_data']['product']['contest']['commerce_product_info']['variations'])
			&& $this->jsonDataArray['initial_data']['product']['contest']['commerce_product_info']['variations']
		) {
			foreach ($this->jsonDataArray['initial_data']['product']['contest']['commerce_product_info']['variations'] as $combis) {
				$combPrice = 0;
				$attrVars = array();
				if (isset($combis['localized_price_before_personal_price']['localized_value'])) {
					$combPrice = $combis['localized_price_before_personal_price']['localized_value'];
				}

				if (isset($combis['color']) && $combis['color']) {
					$attrVars[] = array(
						'name' => 'Color',
						'value' => $combis['color']
					);
				}
				if (isset($combis['size']) && $combis['size']) {
					$attrVars[] = array(
						'name' => 'Size',
						'value' => $combis['size']
					);
				}

				$combinations[] = array(
					'sku' => $sku,
					'upc' => 0,
					'price' => $combPrice ? $combPrice : $price,
					'weight' => $weight,
					'image_index' => 0,
					'attributes' => $attrVars
				);
			}
		} elseif (isset($this->jsonDataArray['productId'], $this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['multipliedPrimeToVariationInfo'])
			&& $this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['multipliedPrimeToVariationInfo']
		) {
			foreach ($this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['multipliedPrimeToVariationInfo'] as $combis) {
				$combPrice = 0;
				$attrVars = array();
				if (isset($combis['variation']['localized_price_before_personal_price']['localized_value'])) {
					$combPrice = $combis['variation']['localized_price_before_personal_price']['localized_value'];
				}

				if (isset($combis['variation']['color']) && $combis['variation']['color']) {
					$attrVars[] = array(
						'name' => 'Color',
						'value' => $combis['variation']['color']
					);
				}
				if (isset($combis['variation']['size']) && $combis['variation']['size']) {
					$attrVars[] = array(
						'name' => 'Size',
						'value' => $combis['variation']['size']
					);
				}

				$combinations[] = array(
					'sku' => $sku,
					'upc' => 0,
					'price' => $combPrice ? $combPrice : $price,
					'weight' => $weight,
					'image_index' => 0,
					'attributes' => $attrVars
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
		if (isset($this->jsonDataArray['productId'], $this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['topRatings'])
			&& $this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['topRatings']
		) {
			foreach ($this->jsonDataArray['data']['product'][$this->jsonDataArray['productId']]['staticFields']['topRatings'] as $review) {
				if (isset($review) && $review) {
					$reviews[] = array(
						'author' => $review['user']['short_name'],
						'title' => '',
						'content' => $review['comment'],
						'rating' => $review['rating'],
						'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['time']))
					);
				}
			}
		} elseif (isset($this->jsonDataArray['initial_data']['product']['contest']['top_merchant_ratings'])
			&& $this->jsonDataArray['initial_data']['product']['contest']['top_merchant_ratings']
		) {
			foreach ($this->jsonDataArray['initial_data']['product']['contest']['top_merchant_ratings'] as $review) {
				if (isset($review) && $review) {
					$reviews[] = array(
						'author' => $review['user']['short_name'],
						'title' => '',
						'content' => $review['comment'],
						'rating' => $review['rating'],
						'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['time']))
					);
				}
			}
		}
		return $reviews;
	}
}
