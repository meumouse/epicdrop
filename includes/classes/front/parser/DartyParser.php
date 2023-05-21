<?php
/**
 * Darty data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class DartyParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $TITLE_SELECTOR = '//span[@itemprop="name"]';
	private $CATEGORY_SELECTOR = '//ul[@id="dartyCom_fil_ariane"]/li/a';
	private $SHORT_DESCRIPTION_SELECTOR = '//ul[contains(@class, "product_details_items")]';
	private $DESCRIPTION_SELECTOR = '//div[contains(@class, "product_bloc_content")]';
	private $PRICE_SELECTOR = '//div[@data-automation-id="product_price"]';
	private $SKU_SELECTOR = '//meta[@itemprop="sku"]/@content';
	private $UPC_SELECTOR = '//meta[@itemprop="gtin13"]/@content';
	private $BRAND_SELECTOR = '//div[@itemprop="brand"]/meta/@content';
	private $COVER_IMG_SELECTOR = '//img[@data-tracking-rollevent="pdp_roll-over-zoom"]/@src';
	private $IMAGE_SELECTOR = '//li[@data-tracking-event="pdp_visuel-vignette"]/div/img/@src';
	private $FEATURES = '//div[contains(@class, "product_bloc_content")]/table/tbody/tr';
	private $REVIEW_SELECTOR = '//div[contains(@class, "bloc_reviews_item ")]';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content, $url) {
		$this->content = preg_replace('/\s+/', ' ', $content);
		$this->dom = $this->getDomObj($content);
		$this->url = $url;
		$this->xpath = new \DomXPath($this->dom);
	}

	private function getDomObj( $content) {
		$dom = new \DomDocument('1.0', 'UTF-8');
		libxml_use_internal_errors(true);
		$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
		libxml_use_internal_errors(false);

		return $dom;
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
		$title = array_shift($title);
		if ($title) {
			return $title;
		}
		return '';
	}

	public function getCategories() {
		$categories = $this->getValue($this->CATEGORY_SELECTOR);
		if ($categories) {
			return array_filter($categories);
		}
		return array_unique($categories);
	}

	public function getShortDescription() {
		$shortDescription = '';
		$descript = $this->getValue($this->SHORT_DESCRIPTION_SELECTOR, true);
		$descript = array_shift($descript);
		if ($descript) {
			$shortDescription = $descript;
		}
		return $shortDescription;
	}

	public function getDescription() {
		$description = '';
		$descript = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		$descript = array_shift($descript);
		if ($descript) {
			$description = $descript;
		}
		return $description;
	}

	public function getPrice() {
		$price = 0;
		$price = $this->getValue($this->PRICE_SELECTOR);
		if ($price) {
			$price = array_shift($price);
			$price = preg_replace('/[^0-9.]/', '', $price);
		}
		return $price;
	}

	public function getSKU() {
		$sku = $this->getValue($this->SKU_SELECTOR);
		$sku = array_shift($sku);
		if ($sku) {
			return $sku;
		}
		return '';
	}

	public function getUPC() {
		$upc = $this->getValue($this->UPC_SELECTOR);
		$upc = array_shift($upc);
		if ($upc) {
			return $upc;
		}
		return '';
	}

	public function getBrand() {
		$brand = $this->getValue($this->BRAND_SELECTOR);
		$brand = array_shift($brand);
		if ($brand) {
			return $brand;
		}
		return '';
	}

	public function getCoverImage() {
		$cImage = $this->getValue($this->COVER_IMG_SELECTOR);
		$cImage = array_shift($cImage);
		if ($cImage) {
			$cImage = str_replace(array('400', '300'), array('1000', '1200'), $cImage);
		}
		return $cImage;
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}
		$img = $this->getValue($this->IMAGE_SELECTOR);
		if (stripos(current($img), 'width') !== false) {
			$images[0] = str_replace(array('104', '69'), array('1040', '690'), $img);
		} elseif ($img) {
			foreach ($img as $imges) {
				if ($imges) {
					$parse = explode('/', $imges);
					$parse1 = explode('_', $parse[6]);
					$parse1[count($parse1)-2] = 'l' . substr($parse1[count($parse1)-2], 1);
					$parse[6] = implode('_', $parse1);
					$images[0][] = implode('/', $parse);
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
		$features = $this->xpath->query($this->FEATURES);
		if ($features->length) {
			$attributes = array();
			foreach ($features as $attrObject) {
				$featureName = $this->xpath->query('.//th', $attrObject);
				$featuresValue = $this->xpath->query('.//td', $attrObject);
				if ($featuresValue->length) {
					$attributes[] = array(
						'name' => $featureName->item(0)->nodeValue,
						'value' => preg_replace('/\s+/', ' ', $featuresValue->item(0)->nodeValue)
					);
				}
			}
			if ($attributes) {
				$featureGroups[] = array(
					'name' => 'Caractéristiques',
					'attributes' => $attributes
				);
			}
		}
		return $featureGroups;
	}

	public function getAttributes() {
		return array();
	}

	public function getCombinations() {
		return array();
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
				$stars = $this->xpath->query('.//div[@class="bloc_reviews_note"]', $reviewObject);
				$content = $this->xpath->query('.//div[contains(@class, "bloc_reviews_text_truncated ")]', $reviewObject);
				if ($content->length) {
					$reviews[] = array(
						'author' => 'nommer',
						'title' => trim(@$this->xpath->query('.//h3[@class="review_title"]', $reviewObject)->item(0)->nodeValue),
						'content' => trim($content->item(0)->nodeValue),
						'rating' => substr($stars->item(0)->nodeValue, -1),
						'timestamp' => substr(preg_replace('/[^0-9\/]/', '', $this->xpath->query('.//span[@class="review_date"]', $reviewObject)->item(0)->nodeValue), 10)
					);
				}
			}
		}
		return $reviews;
	}
}
