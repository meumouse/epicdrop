<?php
/**
 * Myntra data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class MyntraParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $imagses = array();
	private $content;
	private $host;
	private $jsonDataArray = array();
	private $attrDataArray = array();
	private $CATEGORY_SELECTOR = '//div[@class="breadcrumbs-container"]/a';
	private $FEATURE_SELECTOR = '//table[contains(@class, "table-extended")]/tbody/tr/td';
	private $DESCRIPTION_SELECTOR = '//div[@class="product-page-description-text"]';
	private $DESCRIPTION_SELECTOR1 = '//div[@class="unf-info-context"]';
	private $REVIEW_SELECTOR = '//div[contains(@class, "prod-comment-section")]/div/div/ul/li';
	private $REVIEW_SELECTOR1 = '//div[@class="panelContent"]/div/ul/li';
	private $ATTRIBUTES_SELECTOR = '//div[@class="product-highlight "]';
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
				CURLOPT_HTTPHEADER => array(
					'cache-control: no-cache',
					"Origin: $url"
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
		$json = $this->getJson($this->content, 'window.__myx = ', '</script>');
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = json_decode($json, true);
		}
	}

	private function getValue( $selector, $html = false, $xpath = null) {
		if (empty($selector)) {
			return array();
		}
		if (null === $xpath) {
			$xpath = $this->xpath;
		}

		$itmes = $xpath->query($selector);
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
		if (isset($this->jsonDataArray['pdpData']['name'])) {
			return $this->jsonDataArray['pdpData']['name'];
		}
		return '';
	}

	public function getCategories() {
		$categories = array();
		$categories = $this->getValue($this->CATEGORY_SELECTOR);
		array_pop($categories);
		return array_unique($categories);
	}

	public function getDescription() {
		$description = '';
		if (isset($this->jsonDataArray['pdpData']['descriptors']) && $this->jsonDataArray['pdpData']['descriptors']) {
			foreach ($this->jsonDataArray['pdpData']['descriptors'] as $descript) {
				if ($descript['description']) {
					$description .= $descript['description'];
				}
			}
		}
		return $description;
	}

	public function getShortDescription() {
		return '';
	}

	public function getPrice() {
		if (isset($this->jsonDataArray['pdpData']['price']['discounted'])) {
			return $this->jsonDataArray['pdpData']['price']['discounted'];
		} else {
			return $this->jsonDataArray['pdpData']['price']['mrp'];
		}
		return '';
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['pdpData']['id'])) {
			return $this->jsonDataArray['pdpData']['id'];
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['pdpData']['brand']['name'])) {
			return $this->jsonDataArray['pdpData']['brand']['name'];
		}
		return '';
	}
	public function getCoverImage() {
		if (isset($this->jsonDataArray['image'])) {
			$img = array_shift($this->jsonDataArray['image']);
			return $img;
		}
		return '';
	}

	public function getImages() {
		static $images = array();
		if ($images) {
			return $images;
		}

		if (isset($this->jsonDataArray['pdpData']['media']['albums'])
			&& $this->jsonDataArray['pdpData']['media']['albums']
		) {
			foreach ($this->jsonDataArray['pdpData']['media']['albums'] as $imgs) {
				foreach ($imgs['images'] as $img) {
					$images[0][] = $img['imageURL'];
				}
			}
		}

		if (isset($this->jsonDataArray['pdpData']['colours']) && $this->jsonDataArray['pdpData']['colours']) {
			foreach ($this->jsonDataArray['pdpData']['colours'] as $key => $image) {
				if ($image['image']) {
					$images[++$key][] = $image['image'];
				}
			}
		}

		if (!$images) {
			$cover = $this->getCoverImage();
			if ($cover) {
				$images[0] = $cover;
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
		$attributes = array();

		if (isset($this->jsonDataArray['pdpData']['articleAttributes']) && $this->jsonDataArray['pdpData']['articleAttributes']) {
			foreach ($this->jsonDataArray['pdpData']['articleAttributes'] as $key => $features) {
				$attributes[] = array(
					'name' => $key,
					'value' => preg_replace('/\s+/', ' ', $features)
				);
			}
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
		$sizes = array();
		$colors = array();
		if (isset($this->jsonDataArray['pdpData']['colours'])
			&& $this->jsonDataArray['pdpData']['colours']
		) {
			if (isset($this->jsonDataArray['pdpData']['baseColour'])
				&& $this->jsonDataArray['pdpData']['baseColour']
			) {
				$colors[] = $this->jsonDataArray['pdpData']['baseColour'];
			}

			foreach ($this->jsonDataArray['pdpData']['colours'] as $color) {
				if (isset($color['label'])) {
					$colors[] = $color['label'];
				}
			}
			$attrGroups[] = array(
				'name' => 'Colors',
				'is_color' => 1,
				'values' => $colors
			);
		}

		if (isset($this->jsonDataArray['pdpData']['sizes']) && $this->jsonDataArray['pdpData']['sizes']) {
			foreach ($this->jsonDataArray['pdpData']['sizes'] as $size) {
				if ($size['label']) {
					$sizes[] = $size['label'];
				}
			}
			$attrGroups[] = array(
				'name' => 'Sizes',
				'is_color' => 0,
				'values' => $sizes
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
		$sku = $this->getSKU();
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

	public function getCustomerReviews2() {
		$reviews = array();
		if (isset($this->jsonDataArray['pdpData']['ratings']['reviewInfo']['topReviews'])
			&& $this->jsonDataArray['pdpData']['ratings']['reviewInfo']['topReviews']) {
			foreach ($this->jsonDataArray['pdpData']['ratings']['reviewInfo']['topReviews'] as $review) {
				$reviews[] = array(
					'author' => isset($review['userName']) ? $review['userName'] : 0,
					'title' => '',
					'content' => $review['reviewText'],
					'rating' => $review['userRating'],
					'timestamp' => gmdate('Y-m-d H:i:s', $review['timestamp']/1000)
				);
			}
		}

		return $reviews;
	}
	public function getCustomerReviews( $maxReviews = 0) {
		$reviews = array();
		$maxReviews = $maxReviews > 0 ? $maxReviews : 500;
		$pId = $this->getSKU();

		$reviewlink = 'https://www.myntra.com/gateway/v1/reviews/product/' . $pId . '?size=' . $maxReviews . '&sort=0&rating=0&page=2&includeMetaData=true';

		$json = $this->getContent($reviewlink);
		if ($json) {
			$reviewData = json_decode($json, true);

			if (isset($reviewData['reviews']) && $reviewData['reviews']) {
				foreach ($reviewData['reviews'] as $review) {
					$reviews[] = array(
						'author' => isset($review['userName']) ? $review['userName'] : 0,
						'title' => '',
						'content' => $review['review'],
						'rating' => $review['userRating'],
						'timestamp' => gmdate('Y-m-d H:i:s', $review['updatedAt'])
					);
				}
			}
		}
		if (!$reviews) {
			return $this->getCustomerReviews2();
		}
		return $reviews;
	}
}
