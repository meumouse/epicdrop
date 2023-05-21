<?php
/**
 * Reverb data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class ReverbParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $TITLE_SELECTOR = '//div[@class="item2-title__inner"]/h1|//a[@class="featured-listing-module__title"]';
	private $CATEGORY_SELECTOR = '//li[@class="breadcrumbs__breadcrumb"]/a';
	private $SHORT_DESCRIPTION_SELECTOR = '//div[@class="item2-description__content"]';
	private $DESCRIPTION_SELECTOR = '//div[@id="rc-toggled-content__description-content"]';
	private $PRICE_SELECTOR = '//div[@class="price-with-shipping__price__amount"]/span';
	private $BRAND_SELECTOR = '//meta[@itemprop="brand"]/@content';
	private $COVER_IMG_SELECTOR = '//div[@class="lightbox-image__primary"]/div/div/img/@src';
	private $IMAGE_SELECTOR = '//div[contains(@class, "lightbox-image__thumb")]/div/img/@src';
	private $FEATURES = '//table[@class="spec-list"]/tbody/tr/td';
	private $REVIEW_SELECTOR = '//div[@itemtype="http://schema.org/Review"]';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content, $url) {
		$this->content = preg_replace('/\s+/', ' ', $content);
		$this->dom = $this->getDomObj($content);
		$this->url = $url;
		$this->xpath = new \DomXPath($this->dom);

		// Set json data array
	}

	private function getDomObj( $content) {
		$dom = new \DomDocument('1.0', 'UTF-8');
		libxml_use_internal_errors(true);
		$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
		libxml_use_internal_errors(false);

		return $dom;
	}

	public function getContent( $url, $postData = array(), $additionalHeaders = array()) {
		$curl = curl_init($url);
		$headers = array(
			'cache-control: no-cache',
			'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.74 Safari/537.36'
		);
		if ($additionalHeaders) {
			$headers = array_merge($headers, $additionalHeaders);
		}
		if ($postData) {
			$curlOpt = array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_POST => true,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_POSTFIELDS => json_encode($postData),
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_HTTPHEADER => $headers
			);
		} else {
			$curlOpt = array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_HTTPHEADER => $headers
			);
		}
		curl_setopt_array(
			$curl,
			$curlOpt
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
		if ($title) {
			$title = array_shift($title);
			return $title;
		}
		return '';
	}

	public function getCategories() {
		$categories = $this->getValue($this->CATEGORY_SELECTOR);
		if ($categories) {
			foreach ($categories as $categary) {
				$categories[] = $categary;
			}
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
		} else {
			$description = $this->getShortDescription();
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
		$url = explode('/', $this->url);
		$url = array_pop($url);
		$url = explode('-', $url);
		$sku = array_shift($url);
		if ($sku) {
			return $sku;
		}
		return '';
	}

	public function getBrand() {
		$brand = $this->getValue($this->BRAND_SELECTOR);
		$brand = array_shift($brand);
		if ($brand) {
			$brand = $brand;
		}
		return $brand;
	}

	public function getCoverImage() {
		$cImage = $this->getValue($this->COVER_IMG_SELECTOR);
		$cImage = array_shift($cImage);
		if ($cImage) {
			$cImage = $cImage;
		}
		return $cImage;
	}

	public function getImages() {
		static $images = array();
		if ($images) {
			return $images;
		}
		$imgs = $this->getValue($this->IMAGE_SELECTOR);
		if ($imgs) {
			foreach ($imgs as $img) {
				$images[0][] = str_replace('t_card-square', 'f_auto,t_large', $img);
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
		$features = $this->getValue($this->FEATURES);
		if ($features) {
			for ($i = 0; $i<=count($features); $i+=2) {
				if (isset($features[$i+1])) {
					$attributes[] = array(
						'name' => $features[$i],
						'value' => $features[$i+1]
					);
				}
			}
		}
		if ($attributes) {
			$featureGroups[] = array(
				'name' => 'Product Specs',
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
		$reviewArrayObject = $this->xpath->query($this->REVIEW_SELECTOR);
		if ($reviewArrayObject->length) {
			foreach ($reviewArrayObject as $reviewObject) {
				$author = $this->xpath->query('.//div[@itemtype="https://schema.org/Person"]/span', $reviewObject);
				$stars = $this->xpath->query('.//meta[@itemprop="ratingValue"]/@content', $reviewObject);

				if ($author->length) {
					$reviews[] = array(
						'author' => $author->item(0)->nodeValue,
						'title' => trim(@$this->xpath->query('.//h4', $reviewObject)->item(0)->nodeValue),
						'content' => trim($this->xpath->query('.//meta[@itemprop="reviewBody"]/@content', $reviewObject)->item(0)->nodeValue),
						'rating' => $stars->item(0)->nodeValue,
						'timestamp' => gmdate($this->xpath->query('.//meta[@itemprop="datePublished"]/@content', $reviewObject)->item(0)->nodeValue)
					);
				}
			}
		}
		return $reviews;
	}
}
