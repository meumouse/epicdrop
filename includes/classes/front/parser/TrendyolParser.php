<?php
/**
 * Trendyol data parser class
 *
 * @package: product-importer
 *
 */
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.1 */

class TrendyolParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $url;
	private $content;
	private $jsonDataArray = array();
	private $DATA_SELECTOR = '//script[@id="item"]';
	private $PRICE_SELECTOR = '//div[@class="pr-bx-pr-dsc"]/div/span|//div[contains(@class, "sellingPrice")]';
	private $TITLE_SELECTOR = '//h1[@class="pr-new-br"]/span|//h1[@class="brand"]';
	private $VARIATION_SELECTOR = '//div[@class="slicing-attributes"]/section/div/div/a/@href|//div[@id="slicing-attributes"]/div[@class="images"]/a/@href';
	private $COLOR_LABEL_SELECTOR = '//div[@id="slicing-attributes"]/div[@class="header"]/span';
	private $SIZE_LABEL_SELECTOR = '//div[@id="size-picker"]/div[@class="header"]/div/span';
	private $SIZE_SELECTOR = '//div[@id="size-picker"]/div[@class="variant-list"]/div/span';
	private $SIZE_SELECTOR2 = '//div[@class="slc-txt-w"]/a/@title';
	private $SIZE_SELECTOR3 = '//div[@class="variants"]/div';
	private $IMAGE_COVER_SELECTOR = '//div[@class="base-product-image"]/div/img/@src|//div[@class="carousel"]/ul/li[1]/img/@src';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content, $url) {
		$this->url = $url;
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
		
		$json = $this->getJson($this->content, '__PRODUCT_DETAIL_APP_INITIAL_STATE__ =', '; window.TYPageN');
		if ($json) {
			$data =  json_decode($json, true);
			if (isset($data['product'])) {
				$this->jsonDataArray = $data['product'];
			}
		}
		if (!$this->jsonDataArray) {
			$json = $this->getJson($this->content, '__PRODUCT_DETAIL_APP_INITIAL_STATE__=', ';window.TYPageName');
			if ($json) {
				$data =  json_decode($json, true);
				if (isset($data['product'])) {
					$this->jsonDataArray = $data['product'];
				}
			}
		}

		$this->jsonDataArray['products'] = array();

		$ids = array(
			array_pop(explode('-p-', parse_url($this->url, PHP_URL_PATH)))
		);
		
		$links = $this->getValue($this->VARIATION_SELECTOR);
		
		if ($links) {
			foreach ($links as $link) {
				$link = explode('-', $link);
				$ids[] = array_pop($link);
				
				
			}
		}
		
		if ($ids) {
			foreach (array_unique($ids) as $id) {
				$urlData = 'https://public.trendyol.com/discovery-web-productgw-service/api/productDetail/' . $id;
				$json = @file_get_contents($urlData);
				if ($json) {
					$data =  json_decode($json, true);
					if (isset($data['result'])) {
						$this->jsonDataArray['products'][$id] = $data['result'];
					}
				}
			}
		}
		
		if (!isset($this->jsonDataArray['originalCategory'])) {
			
			if (isset($this->jsonDataArray['products']) && $this->jsonDataArray['products']) {
				$products = $this->jsonDataArray['products'];
				$product = array_shift($products);
				
				if (isset($product['originalCategory'])) {
					$this->jsonDataArray = array_merge($product, $this->jsonDataArray);
				}
			}
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
		$title = $this->getValue($this->TITLE_SELECTOR);
		if (isset($this->jsonDataArray['seoMeta']['title'])) {
			return $this->jsonDataArray['seoMeta']['title'];
		} elseif ($title) {
			return array_shift($title);
		}

		return '';
	}

	public function getCategories() {
		
		if (isset($this->jsonDataArray['originalCategory']['hierarchy'])) {
			return array_unique(
				explode('/', $this->jsonDataArray['originalCategory']['hierarchy'])
			);
		}
		
		return array();
	}

	public function getShortDescription() {
		return '';
	}

	public function getDescription() {
		$description = '';
		if (isset($this->jsonDataArray['contentDescriptions']) && $this->jsonDataArray['contentDescriptions']) {
			foreach ($this->jsonDataArray['contentDescriptions'] as $descri) {
				$description .= '<li>' . $descri['description'] . '</li>';
			}
		}
		return $description ? '<ul>' . $description . '</ul>' : '';
	}

	public function getPrice( $product = array()) {
		if (!$product) {
			$product = $this->jsonDataArray;
		}

		if (isset($product['price']['discountedPrice']) && $product['price']['discountedPrice']) {
			return $product['price']['discountedPrice']['value'];
		} elseif (isset($product['price']['sellingPrice']) && $product['price']['sellingPrice']) {
			return $product['price']['sellingPrice']['value'];
		} else {
			$price = $this->getValue($this->PRICE_SELECTOR);
			return array_shift($price);
		}

		return 0;
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['id'])) {
			return  $this->jsonDataArray['id'];
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['brand']['name'])) {
			return $this->jsonDataArray['brand']['name'];
		}
		return '';
	}

	public function getCoverImage() {
		$images = $this->getValue($this->IMAGE_COVER_SELECTOR);
		if ($images) {
			return array_shift($images);
		}
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}

		foreach ($this->jsonDataArray['products'] as $id => $product) {
			if (isset($product['images'])
				&& $product['images']) {
				foreach ($product['images'] as $image) {
					$images[$id][] =  'https://cdn.dsmcdn.com' . $image;
				}
			}
		}
		if (!$images) {
			if (isset($this->jsonDataArray['images'])
				&& $this->jsonDataArray['images']) {
				foreach ($this->jsonDataArray['images'] as $image) {
					$images[$this->jsonDataArray['id']][] =  'https://cdn.dsmcdn.com' . $image;
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

	public function getAttributes() {
		static $attrGroups = array();

		if ($attrGroups) {
			return $attrGroups;
		}

		$colors = array();

		if (isset($this->jsonDataArray['products'])
			&& $this->jsonDataArray['products']) {
			foreach ($this->jsonDataArray['products'] as $comb) {
				if (isset($comb['color']) && $comb['color']) {
					$colors[] = $comb['color'];
				}
			}
			
			if ($colors) {
				$name = array_shift($this->getValue($this->COLOR_LABEL_SELECTOR));
				if (!$name) {
					$name = 'Renk';
				}
				$attrGroups[] = array(
					'name' => $name,
					'is_color' => 1,
					'values' => array_unique($colors)
				);
			}
		}
		
		$name = array_shift($this->getValue($this->SIZE_LABEL_SELECTOR));
		$sizes = $this->getValue($this->SIZE_SELECTOR);
		
		if (!$sizes) {
			$name = 'Dahili Hafıza';
			$sizes = $this->getValue($this->SIZE_SELECTOR2);
		}
		if (!$sizes) {
			$name = 'Beden';
			$sizes = $this->getValue($this->SIZE_SELECTOR3);
		}
		
		if ($sizes) {
			$attrGroups[] = array(
					'name' => $name ? $name : 'Size',
					'is_color' => 0,
					'values' => array_unique($sizes)
			);
		}
		
		
		return $attrGroups;
	}

	public function getCombinations() {
		static $combinations = array();
		if ($combinations) {
			return $combinations;
		}
		
		$weight = $this->getWeight();

		$attrGroup = $this->getAttributes();
		$colors = array();

		foreach ($attrGroup as $key => $attr) {
			if ($attr['is_color']) {
				$colors = $attrGroup[$key];
				unset($attrGroup[$key]);
				break;
			}
		}

		if ($attrGroup) {
			$attrGroup = array_shift($attrGroup);
		}

		if (isset($this->jsonDataArray['products']) && $this->jsonDataArray['products']) {
			foreach ($this->jsonDataArray['products'] as $key => $product) {
				$colorAttrVals = array(
					array(
						'name' => $colors ? $colors['name'] : 'Color',
						'value' => $product['color']
					)
				);

				if ($attrGroup) {
					foreach ($attrGroup['values'] as $attr) {
						$attrVals = array_merge(
							$colorAttrVals,
							array(
								array(
									'name' => $attrGroup['name'],
									'value' => $attr
								)
							)
						);

						$combinations[] = array(
							'sku' => $key,
							'upc' => 0,
							'price' => $this->getPrice($product),
							'weight' => $weight,
							'image_index' => $key,
							'attributes' => $attrVals
						);
					}
				} else {
					$combinations[] = array(
						'sku' => $key,
						'upc' => 0,
						'price' => $this->getPrice($product),
						'weight' => $weight,
						'image_index' => $key,
						'attributes' => $colorAttrVals
					);
				}
			}
		} elseif ($attrGroup) {
			$sku = $this->getSKU();
			$price = $this->getPrice();
			
			foreach ($attrGroup['values'] as $attr) {
				$attrVals = array(
					array(
						'name' => $attrGroup['name'],
						'value' => $attr
					)
				);

				$combinations[] = array(
					'sku' => $sku,
					'upc' => 0,
					'price' => $price,
					'weight' => $weight,
					'image_index' => $sku,
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

	public function getWeight() {
		static $weight = array();

		if ($weight) {
			return $weight;
		}

		$features = $this->getFeatures();

		if ($features) {
			foreach ($features as $feature) {
				foreach ($feature['attributes'] as $attr) {
					if (false !== stripos($attr['name'], 'ağırlığı')
						|| false !== stripos($attr['name'], 'weight')
					) {
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

	public function getFeatures() {
		static $featureGroups = array();

		if ($featureGroups) {
			return $featureGroups;
		}

		$attributes = array();

		if (isset($this->jsonDataArray['attributes']) && $this->jsonDataArray['attributes']) {
			foreach ($this->jsonDataArray['attributes'] as $attribute) {
				$attributes[] = array(
					'name' => $attribute['key']['name'],
					'value' => $attribute['value']['name']
				);
			}
		}

		if ($attributes) {
			$featureGroups[] = array(
				'name' => 'General',
				'attributes' => $attributes,
			);
		}

		return $featureGroups;
	}

	public function getCustomerReviews( $maxReviews = 0, &$reviews = array(), $page = 1) {
		if (!$reviews &&  1 == $page) {
			$this->reviewlink = 'https://public-sdc.trendyol.com/discovery-web-productgw-service/api/review/' . $this->jsonDataArray['id'];
		}

		if ($this->reviewlink) {
			$reviewlink = $this->reviewlink;

			if ($page > 1) {
				$reviewlink .= '?page=' . (int) $page;
			}
			$reviewlink;

			$json = @file_get_contents($reviewlink);

			if ($json) {
				$reviewData = json_decode($json, true);

				$isMaxReached = false;

				if (isset($reviewData['result']['productReviews']['content'])) {
					$this->setReviews($reviewData['result']['productReviews']['content'], $reviews);

					if (0 < $maxReviews && count($reviews) >= $maxReviews) {
						$isMaxReached = true;
					}

					if (isset($reviewData['result']['productReviews']['totalPages'])) {
						$totalPages = (int) $reviewData['result']['productReviews']['totalPages'];

						$nextPage = (int) $page < $totalPages ? ++$page : 1;

						if ($nextPage && false == $isMaxReached) {
							$this->getCustomerReviews($maxReviews, $reviews, $nextPage);
						}
					}
				}
			}
		}

		return $reviews;
	}

	private function setReviews( $customerReviews, &$reviews) {
		if ($customerReviews) {
			foreach ($customerReviews as $review) {
				$reviews[] = array(
					'author' => $review['userFullName'],
					'title' => $review['commentTitle'],
					'content' => $review['comment'],
					'rating' => (int) $review['rate'],
					'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['commentDateISOtype']))
				);
			}
		}
	}
}
