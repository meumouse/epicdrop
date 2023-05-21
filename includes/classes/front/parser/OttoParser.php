<?php
/**
 * Otto data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class OttoParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $host;
	private $jsonDataArray = array();
	private $attrDataArray = array();
	private $FEATURE_SELECTOR = '//tr';
	private $PRICE_SELECTOR = '//div[contains(@class, "prd_price__main")]/span/span';
	private $VIDEO_SELECTOR = '//div[@itemtype="http://schema.org/VideoObject"]/meta[@itemprop="contentURL"]/@content';
	private $REVIEW_SELECTOR = '//div[contains(@class, "cr_js_reviewList")]/div';
	private $CATEGORY_SELECTOR = '//ul[@class="nav_grimm-breadcrumb"]/li/a';
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
		$json = $this->getJson($this->content, '<script id="productDataJson" type="application/json">', '</script>');

		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = json_decode($json, true);
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
		if (isset($this->jsonDataArray['variations'])) {
			$title = $this->jsonDataArray['variations'];
			$title = array_shift($title);
			if (isset($title['name'])) {
				$tital = $title['name'];
			}
		}
		return $tital;
	}

	public function getCategories() {
		$categories = array();
		$categories = $this->getValue($this->CATEGORY_SELECTOR);
		array_shift($categories);
		return $categories;
	}

	public function getShortDescription() {
		$shortDescription = '';
		if (isset($this->jsonDataArray['variations'])) {
			$descript = $this->jsonDataArray['variations'];
			$descript = array_shift($descript);
			if (isset($descript['sellingPoints']['sellingPoint'])) {
				foreach ($descript['sellingPoints']['sellingPoint'] as $descripts) {
					$shortDescription .= preg_replace('/\s+/', ' ', $descripts);
				}
			}
		}
		return $shortDescription;
	}

	public function getDescription() {
		$description = '';
		if (isset($this->jsonDataArray['htmlCharacteristics'])) {
			$description .= $this->jsonDataArray['htmlCharacteristics'];
		}
		if (isset($this->jsonDataArray['description'])) {
			$description .= $this->jsonDataArray['description'];
		}

		if ($description) {
			return $description;
		}
		return '';
	}

	public function getPrice() {
		$price = 0;
		$prices = $this->getValue($this->PRICE_SELECTOR);

		if ($prices) {
			$price = array_shift($prices);
		}
		return str_replace(',', '.', $price);
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['id'])) {
			return $this->jsonDataArray['id'];
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['brand'])) {
			return $this->jsonDataArray['brand'];
		}
		return '';
	}
	public function getCoverImage() {
		return '';
	}
	public function getVideos() {
		return $this->getValue($this->VIDEO_SELECTOR);
	}
	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}

		if (isset($this->jsonDataArray['variations']) && $this->jsonDataArray['variations']) {
			foreach ($this->jsonDataArray['variations'] as $key => $imagses) {
				foreach ($imagses['images'] as $imgs) {
					$images[$key][] = 'https://i.otto.de/i/otto/' . $imgs['id'];
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
	public function getFeatures() {
		static $featureGroups = array();

		if ($featureGroups) {
			return $featureGroups;
		}
		$attributes = array();

		if (isset($this->jsonDataArray['variations'])) {
			$featur = $this->jsonDataArray['variations'];
			$featur = array_shift($featur);
			if (isset($featur['htmlCharacteristics'])) {
				$featurHtml = str_replace('#ft5_slash#', '/', $featur['htmlCharacteristics']);
				$dom = $this->getDomObj($featurHtml);
				$xpath = new \DomXPath($dom);

				$featureArrayObject = $xpath->query($this->FEATURE_SELECTOR);
				if ($featureArrayObject->length) {
					foreach ($featureArrayObject as $featureObject) {
						$featurNm = $xpath->query('.//td[@class="left"]/span', $featureObject)->item(0)->nodeValue;

						$featurVl = $xpath->query('.//td', $featureObject)->item(1)->nodeValue;

						$attributes[] = array(
							'name' => $featurNm,
							'value' => $featurVl
						);
					}
				}
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

		if (isset($this->jsonDataArray['distinctDimensions'])
			&& $this->jsonDataArray['distinctDimensions']) {
			foreach ($this->jsonDataArray['distinctDimensions'] as $comb) {
				$attrVals = array();

				foreach ($comb['values'] as $attr) {
					$attrVals[] = str_replace('#ft5_slash#', '/', $attr['value']);
				}

				$attrGroups[] = array(
					'name' => $comb['displayName'],
					'is_color' => ( false !== stripos($comb['type'], 'color') ) ? 1 : 0,
					'values' => $attrVals
				);
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

		if (isset($this->jsonDataArray['variations']) && $this->jsonDataArray['variations']) {
			foreach ($this->jsonDataArray['variations'] as $sku => $comb) {
				$attrVals = array();

				foreach ($comb['dimensions']['dimension'] as $attrVl) {
					$attrVl = array_shift($attrVl);
					$attrVals[] = array(
						'name' => $attrVl['displayName'],
						'value' => $attrVl['value'],
					);
				}

				$comboPrice = 0;
				if (isset($comb['displayPrice']['techPriceAmount'])) {
					$comboPrice = $comb['displayPrice']['techPriceAmount'];
				}

				$combinations[] = array(
					'sku' => $sku,
					'upc' => 0,
					'price' => $comboPrice ? $comboPrice : $price,
					'weight' => 0,
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

	public function getCustomerReviews() {
		$reviews = array();

		$reviewArrayObject = $this->xpath->query($this->REVIEW_SELECTOR);

		if ($reviewArrayObject->length) {
			foreach ($reviewArrayObject as $reviewObject) {
				$content = $this->xpath->query('.//p[@class="cr_review__text"]', $reviewObject);
				if ($content->length) {
					$stars = $this->xpath->query('.//span[contains(@class, "p_rating100")]', $reviewObject);
					$rating = (int) $stars->length;

					$reviews[] = array(
						'author' => $this->xpath->query('.//span[@class="cr_review__reviewer"]/span', $reviewObject)->item(0)->nodeValue,
						'title' => $this->xpath->query('.//h3[@class="cr_review__title"]', $reviewObject)->item(0)->nodeValue,
						'content' => trim($content->item(0)->nodeValue),
						'rating' => $rating,
						'timestamp' => preg_replace('/[^0-9.]/', '', $this->xpath->query('.//span[@class="cr_review__reviewer"]', $reviewObject)->item(0)->nodeValue)
					);
				}
			}
		}
		return $reviews;
	}
}
