<?php
/**
 * Wayfair data parser class
 *
 * @package: product-importer
 *
 */
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class WayfairParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $colors = array();
	private $images = array();
	private $attributes = array();
	private $COLOR_SELECTOR1 = '//div[@data-enzyme-id="visual-option-group"]/div';
	private $COLOR_SELECTOR2 = '//ul[@id="option-list"]/li';
	private $DATA_SELECTOR = '//script[@id="item"]';
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
		$json = $this->getJson($this->content, '["WEBPACK_ENTRY_DATA"]=', ';</script>');
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = json_decode($json, true);
		}
		
		$json = $this->getJson($this->content, 'type="application/ld+json">', '</script>', 1);
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
		if (isset($this->jsonDataArray['application']['props']['title']['name'])) {
			return $this->jsonDataArray['application']['props']['title']['name'];
		} else {
			return $this->jsonDataArray['name'];
		}
		return '';
	}

	public function getCategories() {
		$categories = array();
		if (isset($this->jsonDataArray['application']['props']['breadcrumbs']['breadcrumbs'])
			&& $this->jsonDataArray['application']['props']['breadcrumbs']['breadcrumbs']
		) {
			foreach ($this->jsonDataArray['application']['props']['breadcrumbs']['breadcrumbs'] as $category) {
				$categories[] = $category['title'];
			}
		}
		return $categories;
	}

	public function getShortDescription() {
		if (isset($this->jsonDataArray['application']['props']['card_marketing_view']['top_line_html'])) {
			return $this->jsonDataArray['application']['props']['card_marketing_view']['top_line_html'];
		}
		return '';
	}

	public function getDescription() {
		$description = array();
		if (isset($this->jsonDataArray['application']['props']['productOverviewInformation']['description'])) {
			$description[] = $this->jsonDataArray['application']['props']['productOverviewInformation']['description'];
		}
		if (isset($this->jsonDataArray['application']['props']['productOverviewInformation']['extraDetails']['productDetailsWithoutHeaders'])
			&& $this->jsonDataArray['application']['props']['productOverviewInformation']['extraDetails']['productDetailsWithoutHeaders']
		) {
			foreach ($this->jsonDataArray['application']['props']['productOverviewInformation']['extraDetails']['productDetailsWithoutHeaders'] as $descriptions) {
				$description[] = $descriptions['content'];
			}
		}
		if (isset($this->jsonDataArray['application']['props']['productOverviewInformation']['overallDimensionTag'])
			&& $this->jsonDataArray['application']['props']['productOverviewInformation']['overallDimensionTag']
		) {
			foreach ($this->jsonDataArray['application']['props']['productOverviewInformation']['overallDimensionTag'] as $descriptions) {
				$description[] = $descriptions['content'];
			}
		}
		if (isset($this->jsonDataArray['application']['props']['productOverviewInformation']['distressedFinish'])
			&& $this->jsonDataArray['application']['props']['productOverviewInformation']['distressedFinish']
		) {
			foreach ($this->jsonDataArray['application']['props']['productOverviewInformation']['distressedFinish'] as $attribut) {
				foreach ($attribut['items'] as $attribute) {
					if (isset($attribute['description'])) {
						$description[] = $attribute['description'];
					}
				}
			}
		}
		return implode('<br>', array_filter($description));
	}

	public function getPrice() {
		if (isset($this->jsonDataArray['application']['props']['price']['salePrice'])) {
			return $this->jsonDataArray['application']['props']['price']['salePrice'];
		}
		return '';
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['application']['props']['waycommProps']['sku'])) {
			return $this->jsonDataArray['application']['props']['waycommProps']['sku'];
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['brand']['name'])) {
			return $this->jsonDataArray['brand']['name'];
		} elseif (isset($this->jsonDataArray['application']['pageLevelTags']['brand'])
			&& $this->jsonDataArray['application']['pageLevelTags']['brand']
		) {
			return $this->jsonDataArray['application']['pageLevelTags']['brand'];
		}
		return '';
	}

	public function getCoverImage() {
		if (isset($this->jsonDataArray['image'])) {
			return $this->jsonDataArray['image'];
		}
		return '';
	}

	public function getImageId( $img, &$id) {
		$parts = explode('/', $img);
		$parts = array_reverse($parts);

		$id = $parts[1];

		$parts[3] = 'compr-r85';

		$parts = array_reverse($parts);

		return implode('/', $parts);
	}

	public function getImages() {
		static $images = array();
		$imgUrl = $this->getCoverImage();
		if ($images) {
			return $images;
		}
		if ($imgUrl) {
			$imgUrl = explode('/', $imgUrl);
			$imgUrl = array_reverse($imgUrl);
		}

		$getColors1 = $this->xpath->query($this->COLOR_SELECTOR1);
		$getColors2 = $this->xpath->query($this->COLOR_SELECTOR2);
		$id = 0;
		if ($getColors1->length) {
			foreach ($getColors1 as $getColor) {
				if ($this->xpath->query('.//img/@src', $getColor)->length) {
					$image = $this->xpath->query('.//img/@src', $getColor)->item(0)->nodeValue;
					$image = $this->getImageId($image, $id);
					$this->colors[$id] = $this->xpath->query('.//p/span', $getColor)->item(0)->nodeValue;
					$images[$id][] = $image;
				}
			}
		} elseif ($getColors2->length) {
			foreach ($getColors2 as $getColor) {
				if ($this->xpath->query('.//img/@src', $getColor)->length) {
					$image = $this->xpath->query('.//img/@src', $getColor)->item(0)->nodeValue;
					$image = $this->getImageId($image, $id);
					$this->colors[$id] = $this->xpath->query('.//p', $getColor)->item(0)->nodeValue;
					$images[$id][] = $image;
				}
			}
		}

		if (isset($this->jsonDataArray['application']['props']['mainCarousel']['items'])
			&& $this->jsonDataArray['application']['props']['mainCarousel']['items']
		) {
			foreach ($this->jsonDataArray['application']['props']['mainCarousel']['items'] as $imgIds) {
				$imgUrl[1] = $imgIds['imageId'];
				$images[$imgIds['imageId']][] = implode('/', array_reverse($imgUrl))  ;
			}
		}


		if (isset($this->jsonDataArray['application']['props']['options']['standardOptions'])
			&& $this->jsonDataArray['application']['props']['options']['standardOptions']
		) {
			foreach ($this->jsonDataArray['application']['props']['options']['standardOptions'] as $combs) {
				if (false !== strpos($combs['category_name'], 'Color')) {
					foreach ($combs['options'] as $comb) {
						$imgUrl[1] = $comb['thumbnail_id'];
						$images[$comb['thumbnail_id']][] = implode('/', array_reverse($imgUrl));
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

	public function getAttributes() {
		static $attrGroups = array();
		if ($attrGroups) {
			return $attrGroups;
		}

		if (isset($this->jsonDataArray['application']['props']['options']['standardOptions'])
			&& $this->jsonDataArray['application']['props']['options']['standardOptions']) {
			foreach ($this->jsonDataArray['application']['props']['options']['standardOptions'] as $combs) {
				if (false === strpos($combs['category_name'], 'Color')) {
					$attrValues = array();
					foreach ($combs['options'] as $comb) {
						$attrName = $comb['category'];
						$key = base64_encode($comb['name']);
						$attrValues[$key] = $comb['name'];
					}
					$attrGroups[] = array(
						'name' => $attrName,
						'is_color' => 0,
						'values' => $attrValues
					);
				}
			}
		}
		$this->getImages();

		if ($this->colors) {
			$attrGroups[] = array(
				'name' => 'Color',
				'is_color' => 1,
				'values' => $this->colors
			);
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
					'sku' => $imageIndex,
					'upc' => 0,
					'price' => $price,
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

	public function getFeatures() {
		static $featureGroups = array();

		if ($featureGroups) {
			return $featureGroups;
		}

		$attributes = array();

		if (isset($this->jsonDataArray['application']['props']['productOverviewInformation']['extraDetails']['productDetailsWithoutHeaders'])
			&& $this->jsonDataArray['application']['props']['productOverviewInformation']['extraDetails']['productDetailsWithoutHeaders']
		) {
			foreach ($this->jsonDataArray['application']['props']['productOverviewInformation']['extraDetails']['productDetailsWithoutHeaders'] as $attribute) {
				if (isset($attribute['content'])) {
					$feature = explode(':', $attribute['content']);
					$attributes[] = array(
						'name' => array_shift($feature),
						'value' => implode('-', $feature)
					);
				}
			}
		}

		if (isset($this->jsonDataArray['application']['props']['productOverviewInformation']['extraDetails']['productDetailsWithHeaders'])
			&& $this->jsonDataArray['application']['props']['productOverviewInformation']['extraDetails']['productDetailsWithHeaders']
		) {
			foreach ($this->jsonDataArray['application']['props']['productOverviewInformation']['extraDetails']['productDetailsWithHeaders'] as $attribut) {
				foreach ($attribut['items'] as $attribute) {
					if (isset($attribute['content'])) {
						$feature = explode(':', $attribute['content']);
						$attributes[] = array(
							'name' => array_shift($feature),
							'value' => implode('-', $feature)
						);
					}
				}
			}
		}

		if (isset($this->jsonDataArray['application']['props']['visualContent']['data'])
			&& $this->jsonDataArray['application']['props']['visualContent']['data']
		) {
			foreach ($this->jsonDataArray['application']['props']['visualContent']['data'] as $attribute) {
				if (isset($attribute['imageHeader'])) {
					$feature = explode('-', $attribute['imageHeader']);
					$attributes[] = array(
						'name' => array_shift($feature),
						'value' => implode('-', $feature)
					);
				}
			}
		}

		if (isset($this->jsonDataArray['application']['props']['productOverviewInformation']['distressedFinish'])
			&& $this->jsonDataArray['application']['props']['productOverviewInformation']['distressedFinish']
		) {
			foreach ($this->jsonDataArray['application']['props']['productOverviewInformation']['distressedFinish'] as $attribut) {
				foreach ($attribut['items'] as $attribute) {
					if (isset($attribute['content']) && $attribute['description']) {
						$attributes[] = array(
							'name' => $attribute['content'],
							'value' => $attribute['description']
						);
					}
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

	public function getCustomerReviews() {
		$reviews = array();
		if (isset($this->jsonDataArray['review']) && $this->jsonDataArray['review']) {
			foreach ($this->jsonDataArray['review'] as $review) {
				$reviews[] = array(
					'author' => isset($review['author']['name']) ? $review['author']['name'] : '',
					'title' => 0,
					'content' => isset($review['description']) ? $review['description'] : '',
					'rating' => isset($review['reviewRating']['ratingValue']) ? $review['reviewRating']['ratingValue'] : '',
					'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['datePublished']))
				);
			}
		}
		return $reviews;
	}

	public function getVideos() {
		$videos = array();

		if (isset($this->jsonDataArray['application']['props']['mainCarousel']['videos'])
			&& $this->jsonDataArray['application']['props']['mainCarousel']['videos']
		) {
			foreach ($this->jsonDataArray['application']['props']['mainCarousel']['videos'] as $sources) {
				if (isset($sources['sources']) && $sources['sources']) {
					foreach ($sources['sources'] as $video) {
						$videos[] = $video['source'];
					}
				}
			}
		}

		return array_unique($videos);
	}
}
