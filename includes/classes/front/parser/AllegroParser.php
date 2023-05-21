<?php
/**
 * Allegro data parser class
 *
 * @package: product-importer
 *
 */
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 2.0 */

class AllegroParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $DESCRIPTION_SELECTOR = '//div[@data-box-name="Description"]';
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
		
		$jsons = $this->getValue('//script[@type="application/json"]');
		
		foreach ($jsons as $json) {
			$data = json_decode(html_entity_decode($json), true);
			if ($data) {
				$this->jsonDataArray = array_merge($this->jsonDataArray, $data);
			}
		}
		
		if (!isset($this->jsonDataArray['variants']['attributeSet']) && isset($this->jsonDataArray['variants']['offerAttributeSet'])) {
			$this->jsonDataArray['variants']['attributeSet'] = $this->jsonDataArray['variants']['offerAttributeSet'];
		}
		
		if (!isset($this->jsonDataArray['variants']['attributeSet']) && isset($this->jsonDataArray['variants']['allSellersProductVariants'])) {
			$this->jsonDataArray['variants']['attributeSet'] = $this->jsonDataArray['variants']['allSellersProductVariants'];
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
		if (isset($this->jsonDataArray['schema']['name'])) {
			return $this->jsonDataArray['schema']['name'];
		}
		return '';
	}

	public function getCategories() {
		if (isset($this->jsonDataArray['metaData']['dataLayer']['headNavigation'])) {
			return array_unique(
				explode('|', $this->jsonDataArray['metaData']['dataLayer']['headNavigation'])
			);
		}
		return array();
	}

	public function getShortDescription() {
		if (isset($this->jsonDataArray['schema']['description'])) {
			return $this->jsonDataArray['schema']['description'];
		}
		return '';
	}

	public function getDescription() {
		$description = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		return array_shift($description);
	}

	public function getPrice() {
		if (isset($this->jsonDataArray['schema']['price'])) {
			return $this->jsonDataArray['schema']['price'];
		}
		return '';
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['schema']['sku'])) {
			return $this->jsonDataArray['schema']['sku'];
		}
		return '';
	}

	public function getCoverImage() {
		if (isset($this->jsonDataArray['schema']['image'])) {
			return $this->jsonDataArray['schema']['image'];
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}

		if (isset($this->jsonDataArray['standardized']['sections'])
			&& $this->jsonDataArray['standardized']['sections']
		) {
			foreach ($this->jsonDataArray['standardized']['sections'] as $section) {
				foreach ($section['items'] as $item) {
					if ('IMAGE' == $item['type']) {
						$images[0][] = $item['url'];
					}
				}
			}
		}

		if (isset($this->jsonDataArray['variants']['attributeSet']['groups'])
			&& $this->jsonDataArray['variants']['attributeSet']['groups']
		) {
			foreach ($this->jsonDataArray['variants']['attributeSet']['groups'] as $group) {
				foreach ($group['attributes'] as $variation) {
					if ($variation['image']) {
						$img = str_replace('/s64/', '/original/', $variation['image']);
						$images[$variation['variantOfferId']][] = $img;
					}
				}
			}
		} elseif (isset($this->jsonDataArray['variants']['attributeSet'])
			&& $this->jsonDataArray['variants']['attributeSet']
		) {
			foreach ($this->jsonDataArray['variants']['attributeSet'] as $item) {
				foreach ($item['attributes'] as $variation) {
					if ($variation['image']) {
						$img = str_replace('/s64/', '/original/', $variation['image']);
						$images[$variation['variantOfferId']][] = $img;
					}
				}
			}
		}
		
		if (isset($this->jsonDataArray['images']) && $this->jsonDataArray['images']) {
			$defaultSku = $this->getSKU();

			foreach ($this->jsonDataArray['images'] as $item) {
				$images[$defaultSku][] = $item['original'];
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

		return array_filter($images);
	}

	public function getAttributes() {
		static $attrGroups = array();
		if ($attrGroups) {
			return $attrGroups;
		}
		if (isset($this->jsonDataArray['variants']['attributeSet'])
			&& $this->jsonDataArray['variants']['attributeSet']
		) {
			if (isset($this->jsonDataArray['variants']['attributeSet']['groups'])) {
				foreach ($this->jsonDataArray['variants']['attributeSet']['groups'] as $attrGroup) {
					$attrValues = array();
					foreach ($attrGroup['attributes'] as $key => $attr) {
						if (!$attr['value']) {
							$attr['value'] = 'Opcja ' . ( $key+1 );
						}
						$attrValues[$attr['variantOfferId']] = $attr['value'];
					}

					$attrGroups[] = array(
						'name' => $attrGroup['name'],
						'is_color' => ( $attrGroup['hasImage'] || 'Kolor/wzór' == $attrGroup['name'] ) ? 1 : 0,
						'values' => $attrValues
					);
				}
			} else {
				foreach ($this->jsonDataArray['variants']['attributeSet'] as $attrGroup) {
					$attrValues = array();
					foreach ($attrGroup['attributes'] as $attr) {
						$attrValues[$attr['variantOfferId']] = $attr['value'];
					}

					$attrGroups[] = array(
						'name' => $attrGroup['name'],
						'is_color' => ( !isset($attrGroup['name']) || 'Kolor/wzór' == $attrGroup['name'] ) ? 1 : 0,
						'values' => $attrValues
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
		$sku = $this->getSKU();
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

				$combinations[] = array(
					'sku' => $sku,
					'upc' => 0,
					'price' => $price,
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

	public function getWeight() {
		static $weight = array();

		if ($weight) {
			return $weight;
		}

		$features = $this->getFeatures();

		if ($features) {
			foreach ($features as $feature) {
				foreach ($feature['attributes'] as $attr) {
					if (stripos($attr['name'], 'waga') !== false) {
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

	public function getBrand() {
		static $brand = '';

		if ($brand) {
			return $brand;
		}

		$features = $this->getFeatures();

		if ($features) {
			foreach ($features as $feature) {
				foreach ($feature['attributes'] as $attr) {
					if (stripos($attr['name'], 'marka') !== false) {
						$brand = $attr['value'];
						break 2;
					}
				}
			}
		}

		return $brand;
	}

	public function getFeatures() {
		static $featureGroups = array();

		if ($featureGroups) {
			return $featureGroups;
		}

		$attributes = array();

		if (isset($this->jsonDataArray['groups']) && $this->jsonDataArray['groups']) {
			foreach ($this->jsonDataArray['groups'] as $group) {
				foreach ($group['singleValueParams'] as $item) {
					$attributes[] = array(
						'name' => $item['name'],
						'value' => $item['value']['name']
					);
				}
				foreach ($group['multiValueParams'] as $item) {
					$attributes[] = array(
						'name' => $item['name'],
						'value' => $item['value']['name']
					);
				}
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
		$reviews = array();

		if (!$reviews &&  1 == $page) {
			$sku = $this->getSKU();
			$this->reviewlink = 'https://edge.allegro.pl/offers/' . $sku . '?include=product.reviews';
		}

		$reviewLink = $this->reviewlink . '&product.reviews.page=' . $page;

		if ($reviewLink) {
			$json = $this->getContent($reviewLink);

			if ($json) {
				$reviewData = json_decode($json, true);
				$isMaxReached = false;

				if (isset($reviewData['product']['reviews']['opinions'])
					&& $reviewData['product']['reviews']['opinions']) {
					foreach ($reviewData['product']['reviews']['opinions'] as $review) {
						$reviews[] = array(
							'author' => $review['author']['name'],
							'title' => 0,
							'content' => $review['opinion'],
							'rating' => (int) $review['rating']['label'],
							'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['createdAt']))
						);

						if (0 < $maxReviews && count($reviews) >= $maxReviews) {
							$isMaxReached = true;
							break;
						}
					}


					if (isset($reviewData['product']['reviews']['pagination']['totalPages'])) {
						$totalPages = (int) $reviewData['product']['reviews']['pagination']['totalPages'];

						$nextPage = (int) $page < $totalPages ? ++$page : 0;

						if ($nextPage > 1 && false == $isMaxReached) {
							$this->getCustomerReviews($maxReviews, $reviews, $nextPage);
						}
					}
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
		if (isset($this->jsonDataArray['reviews']) && $this->jsonDataArray['reviews']) {
			foreach ($this->jsonDataArray['reviews'] as $review) {
				if (isset($review['reviewBody']) && $review['reviewBody']) {
					$reviews[] = array(
						'author' => $review['author'],
						'title' => '',
						'content' => $review['reviewBody'],
						'rating' => $review['reviewRating']['value'],
						'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['datePublished']['value']))
					);
				}
			}
		}
		return $reviews;
	}

	public function getContent( $url) {
		$curl = curl_init();

		curl_setopt_array(
			$curl,
			array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER => array(
					"Origin: $url",
					'Accept: application/vnd.allegro.offer.view.internal.v1+json'
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
}
