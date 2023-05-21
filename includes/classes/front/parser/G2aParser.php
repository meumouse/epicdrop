<?php
/**
 * G2a data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class G2aParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $JSON_DATA_SELECTOR = '//script[@type="application/ld+json"]';
	private $DESCRIPTION_SELECTOR = '//div[@data-locator="product-description"]';
	private $DESCRIPTION_SELECTOR1 = '//div[@id="collapsible-panel-system-requirements"]';
	private $IMAGE_SELECTOR = '//div[@data-locator="ppa-gallery-thumbnail"]/img/@src';
	private $FEATURES_SELECTOR = '//div[contains(@class, "itmCeB")]/div/p';
	private $ATTRIBUTE_SELECTOR = '//ul[@id="display_size_id"]/li';
	private $ATTR_NAME_SELECTOR = '//div[@class="sizeList_title"]';
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

	private function setJsonData() {
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
		if (isset($this->jsonDataArray['name'])) {
			return $this->jsonDataArray['name'];
		}
		return '';
	}

	public function getCategories() {
		$categories = array();
		if (isset($this->jsonDataArray['itemListElement']) && $this->jsonDataArray['itemListElement']) {
			foreach ($this->jsonDataArray['itemListElement'] as $categary) {
				$categories[] = $categary['item']['name'];
			}
		}
		return $categories;
	}

	public function getShortDescription() {
		if (isset($this->jsonDataArray['description'])) {
			return $this->jsonDataArray['description'];
		}
		return '';
	}

	public function getDescription() {
		$description = '';
		$descript = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		$descript1 = $this->getValue($this->DESCRIPTION_SELECTOR1, true);
		$descript = array_shift($descript);
		$descript1 = array_shift($descript1);
		if ($descript) {
			$description .= $descript;
		}
		if ($descript1) {
			$description .= $descript1;
		}
		return $description;
	}

	public function getPrice( $priceVar = array()) {
		$price = 0;
		if (isset($this->jsonDataArray['offers']['lowPrice'])) {
			$price = $this->jsonDataArray['offers']['lowPrice'];
		} elseif (isset($this->jsonDataArray['offers']['highPrice'])) {
			$price = $this->jsonDataArray['offers']['highPrice'];
		}
		return $price;
	}

	public function getSKU() {
		$urls = explode('?', $this->url);
		$url = array_shift($urls);
		$idarr = explode('-', $url);
		$sku = array_pop($idarr);
		return $sku;
	}

	public function getBrand() {
		return '';
	}

	public function getCoverImage() {
		if (isset($this->jsonDataArray['image'])) {
			return str_replace('90x121', '900x1210', $this->jsonDataArray['image']);
		}
		return '';
	}

	public function getImages() {
		static $images = array();
		if ($images) {
			return $images;
		}
		$imgses = $this->getValue($this->IMAGE_SELECTOR);
		if ($imgses) {
			foreach ($imgses as $img) {
				$img1 = explode('/', $img);
				$img1[3] = '900x1210';
				$images[0][] = implode('/', $img1);
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
		$features = $this->getValue($this->FEATURES_SELECTOR);
		$attributes = array();
		if ($features) {
			for ($i=0; $i<count($features); $i=$i+2) {
				$attributes[] = array(
					'name' => str_replace(':', '', $features[$i]),
					'value' => $features[$i+1]
				);
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
		return $attrGroups;
	}

	public function getCombinations() {
		static $combinations = array();
		if ($combinations) {
			return $combinations;
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
		return $reviews;
	}
}
