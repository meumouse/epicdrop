<?php
/**
 * Flipkart data parser class
 *
 * @package: product-importer
 *
 */
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 2.2 */

class FlipkartParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $images = array();
	private $reviewLink = '';
	private $DATA_SELECTOR = '//script[@id="is_script"]';
	private $DATA_SELECTOR2 = '//link[@rel="canonical" ]/@href';

	public function __construct( $content) {
		$content = str_replace("\n", '', $content);
		$content = iconv('UTF-8', 'UTF-8//IGNORE', $content);
		$content = str_replace(array('\n','<div', '</div>'), array('','<p','</p>'), $content);
		$this->content = preg_replace('/\s+/', ' ', $content);

		$this->dom = $this->getDomObj($this->content);
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
		$sciptContents = $this->getValue($this->DATA_SELECTOR);
		if ($sciptContents) {
			$sciptContent = array_shift($sciptContents);
			$json = trim(
				str_replace(
					array('window.__INITIAL_STATE__ =', 'window.__INITIAL_STATE__='),
					'',
					$sciptContent
				)
			);

			$this->jsonDataArray = json_decode($json, true);
		}

		if (!$this->jsonDataArray) {
			$links = $this->getValue($this->DATA_SELECTOR2);
			if ($links) {
				$link = array_shift($links);
				$content = file_get_contents($link);

				$json = $this->getJson(
					str_replace(
						array('__INITIAL_STATE__ =', '; </script>'),
						array('__INITIAL_STATE__=', ';</script>'),
						$content
					),
					'__INITIAL_STATE__=',
					';</script>'
				);

				$this->jsonDataArray = json_decode($json, true);
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

	public function search( $array, $key, $parent = false) {
		$results = array();

		if (is_array($array)) {
			if (isset($array[$key]) && false == $parent) {
				$results[] = $array[$key];
			}
			
			$oldParent = $parent;
			if (is_array($array)) {
				foreach ($array as $subarray) {
					if ($parent && isset($subarray[$parent])) {
						$subarray = $subarray[$parent];
						$parent = false;
					} else {
						$parent = $oldParent;
					}
					$results = array_merge($results, $this->search($subarray, $key, $parent));
				}
			}
		}

		return $results;
	}

	public function getTitle() {
		$titles = $this->search($this->jsonDataArray, 'title', 'seo');
		if ($titles) {
			return array_shift($titles);
		}
	}

	public function getCategories() {
		$breadcrumbs = $this->search($this->jsonDataArray, 'productBreadcrumbs');
		if ($breadcrumbs) {
			$breadcrumb = array_shift($breadcrumbs);
			$categories = array_column($breadcrumb, 'title');
			array_pop($categories);

			return $categories;
		}
		return array();
	}

	public function getShortDescription() {
		return '';
	}

	public function getDescription() {
		$details = $this->search($this->jsonDataArray, 'details', 'renderableComponent');
		if ($details) {
			array_shift($details);
			return array_shift($details);
		}
		return '';
	}

	public function getPrice() {
		$prices = $this->search($this->jsonDataArray, 'decimalValue');
		if ($prices) {
			return array_shift($prices);
		}
		return 0;
	}

	public function getSKU() {
		$productIds = $this->search($this->jsonDataArray, 'productId');
		if ($productIds) {
			return array_shift($productIds);
		}
		return '';
	}

	public function getBrand() {
		$brands = $this->search($this->jsonDataArray, 'brand');
		if ($brands) {
			return array_shift($brands);
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}

		// Set combinations images
		$this->getCombinations();

		// Other images
		$imageArray = $this->search($this->jsonDataArray, 'value', 'multimediaComponents');
		if ($imageArray) {
			//$imageArray = array_shift($imageArray);
			foreach ($imageArray as $image) {
				$this->images[0][] = $image['url'];
			}
		}

		$images = array();

		foreach ($this->images as $key => $comb) {
			foreach ($comb as $img) {
				$img = str_replace(
					array('{@width}', '{@height}', '{@quality}'),
					array(1920, 1920, 100),
					$img
				);
				$images[$key][] = $img;
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
		
		$attributes = $this->search($this->jsonDataArray, 'attributes', 'swatchComponent');
		if ($attributes) {
			//array_shift($attributes);
			$attributes = array_shift($attributes);

			foreach ($attributes as $key => $attrGroup) {
				$attrGroups[] = array(
					'name' => $attrGroup['text'],
					'is_color' => 'Color' == $attrGroup['text'] ? 1 : 0,
					'values' => $this->getAttributeValues($key)
				);
			}
		}
		return $attrGroups;
	}

	public function getAttributeValues( $attributeIndex) {
		static $attrValues = array();
		if (isset($attrValues[$attributeIndex])) {
			return $attrValues[$attributeIndex];
		}
		
		$attributeOptions = $this->search($this->jsonDataArray, 'attributeOptions', 'swatchComponent');
		if ($attributeOptions) {
			//array_shift($attributeOptions);
			$attributeOptions = array_shift($attributeOptions);

			foreach ($attributeOptions as $key => $values) {
				$attrValues[$key] = array_map(
					function ( $v) {
						return $v['value'];
					},
					$values
				);
			}
		}
		return $attrValues[$attributeIndex];
	}

	public function getCombinations() {
		static $combinations = array();
		if ($combinations) {
			return $combinations;
		}

		$products = $this->search($this->jsonDataArray, 'products', 'swatchComponent');
		if ($products) {
			//array_shift($products);
			//array_shift($products);
			$products = array_shift($products);

			$price = $this->getPrice();
			$weight = $this->getWeight();
			$attrs = $this->getAttributes();

			foreach ($products as $product) {
				foreach ($product['images'] as $image) {
					$this->images[$product['listingId']][] = $image['url'];
				}

				$attrVals = array();
				foreach ($product['attributeIndexes'] as $key => $index) {
					$attrVals[] = array(
						'name' => $attrs[$key]['name'],
						'value' => $attrs[$key]['values'][$index]
					);
				}

				$combinations[] = array(
					'sku' => $product['listingId'],
					'upc' => 0,
					'price' => $price,
					'weight' => $weight,
					'image_index' => $product['listingId'],
					'attributes' => $attrVals
				);
			}
		}
		return $combinations;
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
					if (stripos($attr['name'], 'weight') !== false) {
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

	public function getMetaTitle() {
		return $this->getTitle();
	}

	public function getMetaDecription() {
		$descriptions = $this->search($this->jsonDataArray, 'description', 'seo');
		if ($descriptions) {
			array_shift($descriptions);
			return array_shift($descriptions);
		}
		return '';
	}

	public function getMetaKeywords() {
		$keywords = $this->search($this->jsonDataArray, 'keywords', 'seo');
		if ($keywords) {
			array_shift($keywords);
			return array_shift($keywords);
		}
		return '';
	}

	public function getFeatures() {
		static $featureGroups = array();

		if ($featureGroups) {
			return $featureGroups;
		}

		$renderableComponents = $this->search($this->jsonDataArray, 'renderableComponents');

		if ($renderableComponents) {
			foreach ($renderableComponents as $attributeGroups) {
				foreach ($attributeGroups as $attributeGroup) {
					if (isset($attributeGroup['value']['attributes'])
						&& $attributeGroup['value']['attributes']
					) {
						$attributes = array();

						foreach ($attributeGroup['value']['attributes'] as $attribute) {
							if (!empty($attribute['name']) && $attribute['values']) {
								$attributes[] = array(
									'name' => trim($attribute['name']),
									'value' => implode(', ', $attribute['values'])
								);
							}
						}

						if ($attributes) {
							$featureGroups[] = array(
								'name' => $attributeGroup['value']['key'],
								'attributes' => $attributes
							);
						}
					}
				}
			}
		}

		if (!$featureGroups) {
			$attributes = array();
			
			$attributes = $this->search($this->jsonDataArray, 'specification');
			if ($attributes) {
				$attributes = array_shift($attributes);
				$attributes = array_map(
					function ( $f) {
						$f['value'] = implode(', ', $f['values']);
						unset($f['values']);
						return $f;
					},
					$attributes
				);
			}

			if ($attributes) {
				$featureGroups[] = array(
					'name' => 'General',
					'attributes' => $attributes,
				);
			}
		}

		return $featureGroups;
	}

	public function getCustomerReviews( $maxReviews = 0, &$reviews = array(), $page = 1) {
		if (!$reviews && 1 == $page) {
			$data = $this->search($this->jsonDataArray, 'action', 'ratingsAndReviews');
			if ($data) {
				$data = array_shift($data);
				$this->reviewLink = 'https://www.flipkart.com' . $data['url'];
			}

			$reviewCount = 100;

			$data = $this->search($this->jsonDataArray, 'value', 'ratingsAndReviews');
			if ($data) {
				$data = array_shift($data);
				$reviewCount = (int) $data['reviewCount'];
			}

			$maxReviews = !$maxReviews ? $reviewCount : min($maxReviews, $reviewCount);
		}

		$reviewLink = $this->reviewLink;

		if ($page > 1) {
			$reviewLink .= '&page=' . $page;
		}

		$content = @file_get_contents($reviewLink);

		if ($content) {
			$json = $this->getJson(
				$content,
				'"pageDataV4":',
				',"payments"'
			);

			$reviewData = json_decode($json, true);

			if (isset($reviewData['page']['data']) && $reviewData['page']['data']) {
				$isMaxReached = false;

				foreach ($reviewData['page']['data'] as $pageData) {
					foreach ($pageData as $type) {
						if (isset($type['widget']['type']) && 'REVIEWS' == $type['widget']['type']) {
							if (isset($type['widget']['data']['renderableComponents'])
								&& $type['widget']['data']['renderableComponents']) {
								$this->setReviewData($type['widget']['data']['renderableComponents'], $reviews);

								if (count($reviews) >= $maxReviews) {
									$isMaxReached = true;
									break 2;
								}
							}
						}
					}
				}

				if (false == $isMaxReached) {
					$this->getCustomerReviews($maxReviews, $reviews, ++$page);
				}
			}
		}

		if (!$reviews) {
			$reviews = $this->getCustomerReviews2();
		}

		return $reviews;
	}

	public function getCustomerReviews2() {
		$reviews = array();
		$renderableComponents = $this->search($this->jsonDataArray, 'renderableComponents', 'reviewData');
		if ($renderableComponents) {
			//array_shift($renderableComponents);
			$customerReviews = array_shift($renderableComponents);

			$this->setReviewData($customerReviews, $reviews);
		}
		return $reviews;
	}

	public function setReviewData( $customerReviews, &$reviews) {
		if ($customerReviews) {
			foreach ($customerReviews as $review) {
				$reviews[] = array(
					'author' => $review['value']['author'],
					'title' => $review['value']['title'],
					'content' => $review['value']['text'],
					'rating' => $review['value']['rating'],
					'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['value']['created']))
				);
			}
		}
	}
	
	protected function getJson( $string, $start, $end, $index = 0) {
		$json = parent::getJson($string, $start, $end, $index);
		return preg_replace('/,\s*]/', ']', $json);
	}
}
