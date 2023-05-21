<?php
/**
 * Barnesandnoble data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class BarnesandnobleParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $TITLE_SELECTOR = '//h1[contains(@class, "pdp-header-title")]';
	private $CATEGORY_SELECTOR = '//li[@itemtype="http://schema.org/ListItem"]/a/span';
	private $DESCRIPTION_SELECTOR = '//div[@id="overviewSection"]|//div[@id="EditorialReviews"]';
	private $PRICE_SELECTOR = '//span[@id="pdp-cur-price"]';
	private $BRAND_SELECTOR = '//span[@id="key-contributors"]/a';
	private $IMAGE_SELECTOR = '//img[@id="pdpMainImage"]/@src';
	private $MORE_IMAGE_SELECTOR = '//div[contains(@class, "product-thumb")]/a/img/@src';
	private $FEATURE_SELECTOR_VL_NM = '//table[contains(@class, "plain")]/tbody/tr';
	private $FEATURE_SELECTOR_NM = '//div[@id="ProductDetailsTab"]/h2';
	private $ATTRIBUTE_SELECTOR = '//a[contains(@class, "format-chiklet")]';
	private $REVIEW_SELECTOR = '//li[@itemprop="review"]';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content, $url) {
		$this->content = preg_replace('/\s+/', ' ', $content);
		$this->url = $url;
		$this->dom = $this->getDomObj($content);
		$this->xpath = new \DomXPath($this->dom);
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
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_TIMEOUT => 30,
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
		$categories = array();
		$categories = $this->getValue($this->CATEGORY_SELECTOR);
		return $categories;
	}

	public function getDescription() {
		$description = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		$description = array_shift($description);
		if ($description) {
			return $description;
		}
		return '';
	}

	public function getPrice() {
		$price = 0;
		$price = $this->getValue($this->PRICE_SELECTOR);
		$price = array_shift($price);
		if ($price) {
			$price = preg_replace('/[^0-9.]/', '', $price);
		}
		return $price;
	}

	public function getSKU() {
		$url = explode('?', $this->url);
		$url = array_shift($url);
		$sku = preg_replace('/[^0-9]/', '', $url);
		if ($sku) {
			return $sku;
		}
		return '';
	}

	public function getUPC() {
		$url = explode('?', $this->url);
		$url = array_pop($url);
		$upc = preg_replace('/[^0-9]/', '', $url);
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
		$cImage = $this->getValue($this->IMAGE_SELECTOR);
		$cImage = array_shift($cImage);
		if ($cImage) {
			return $cImage;
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}
		$cImage = $this->getValue($this->IMAGE_SELECTOR);
		$cImage = array_pop($cImage);
		$Image = $this->getValue($this->MORE_IMAGE_SELECTOR);

		if ($cImage) {
			$images[0][] = $cImage;
		}
		if ($Image) {
			foreach ($Image as $imgs) {
				$images[0][] = str_replace(array('//', '90x140'), array('https://', '550x406'), $imgs);
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
		$featureNM = $this->getValue($this->FEATURE_SELECTOR_NM);
		$featureArrayObject = $this->xpath->query($this->FEATURE_SELECTOR_VL_NM);
		$attributes = array();

		if ($featureNM) {
			$featureNM = array_shift($featureNM);
		}

		if ($featureArrayObject->length) {
			foreach ($featureArrayObject as $features) {
				$name = $this->xpath->query('.//th', $features);
				$value = $this->xpath->query('.//td', $features);
				if ($value->length) {
					$attributes[] = array(
						'name' => str_replace(':', '', $name->item(0)->nodeValue),
						'value' => $value->item(0)->nodeValue
					);
				}
			}
		}

		if ($attributes) {
			$featureGroups[] = array(
				'name' => $featureNM,
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
		$attribute = $this->getValue($this->ATTRIBUTE_SELECTOR);
		$attributes = array();
		if ($attribute) {
			foreach ($attribute as $attrVals) {
				$attributes[] = preg_replace('/[^a-zA-Z]/', '', $attrVals);
			}
			if ($attributes) {
				$attrGroups[] = array(
					'name' => 'Select',
					'is_color' => 0,
					'values' => $attributes
				);
			}
		}

		return $attrGroups;
	}

	public function getCombinations() {
		static $combinations = array();

		if ($combinations) {
			return $combinations;
		}
		$price = $this->getPrice();
		$sku = $this->getSKU();
		$upc = $this->getUPC();
		$combinateValue = $this->getValue($this->ATTRIBUTE_SELECTOR);

		if ($combinateValue) {
			foreach ($combinateValue as $attrVals) {
				if ($attrVals) {
					$combinations[] = array(
						'sku' => $sku,
						'upc' => $upc,
						'price' => preg_replace('/[^0-9.]/', '', $attrVals) ? preg_replace('/[^0-9.]/', '', $attrVals) : $price,
						'weight' => 0,
						'image_index' => 0,
						'attributes' => array(
							array(
								'name' => 'Select',
								'value' => preg_replace('/[^a-zA-Z]/', '', $attrVals)
							)
						)
					);
				}
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

	public function getCustomerReviews( $maxReviews = 0) {
		$reviews = array();
		$id = $this->getUPC();
		$maxReviews = $maxReviews ? $maxReviews : 100;
		$reviewLink = 'https://api.bazaarvoice.com/data/batch.json?passkey=caC2Xb0kazery1Vgcza74qqETLsDbclQWr3kbWiGXSvjI&apiversion=5.5&resource.q0=reviews&filter.q0=isratingsonly:eq:false&filter.q0=productid:eq:' . $id . '&filter.q0=contentlocale:eq:en_US&sort.q0=isfeatured:desc&stats.q0=reviews&filteredstats.q0=reviews&include.q0=authors,products,comments&filter_reviews.q0=contentlocale:eq:en_US&filter_reviewcomments.q0=contentlocale:eq:en_US&filter_comments.q0=contentlocale:eq:en_US&limit.q0=' . $maxReviews . '&offset.q0=8&limit_comments.q0=3';

		if ($reviewLink) {
			$reviewJson = $this->getContent($reviewLink);
			if ($reviewJson) {
				$reviewArrayObject = json_decode($reviewJson, true);
				if (isset($reviewArrayObject['BatchedResults']['q0']['Results']) && $reviewArrayObject['BatchedResults']['q0']['Results']) {
					$isMaxReached = false;
					foreach ($reviewArrayObject['BatchedResults']['q0']['Results'] as $reviewObject) {
						if ($reviewObject['UserNickname']) {
							$reviews[] = array(
								'author' => $reviewObject['UserNickname'],
								'title' => isset($reviewObject['Title']) ? $reviewObject['Title'] : '',
								'content' => isset($reviewObject['ReviewText']) ? $reviewObject['ReviewText'] : '',
								'rating' => $reviewObject['Rating'],
								'timestamp' => gmdate('Y-m-d H:i:s', strtotime($reviewObject['SubmissionTime']))
							);
						}
					}
				}
			}
		}
		if (!$reviews) {
			return $this->getCustomerReviews2();
		}
		return $reviews;
	}

	public function getCustomerReviews2() {
		$reviews = array();

		$reviewArrayObject = $this->xpath->query($this->REVIEW_SELECTOR);
		if ($reviewArrayObject->length) {
			foreach ($reviewArrayObject as $reviewObject) {
				$content = $this->xpath->query('.//div[@class="bv-content-summary-body-text"]/p', $reviewObject);

				if ($content->length) {
					$stars = $this->xpath->query('.//span[@class="bv-off-screen"]', $reviewObject)->item(0)->nodeValue;
					$stars = explode(' ', $stars);
					$stars = array_shift($stars);
					$author = $this->xpath->query('.//button/h3', $reviewObject);

					if ($author->length) {
						$reviews[] = array(
							'author' => $author->item(0)->nodeValue,
							'title' => $this->xpath->query('.//h3[@class="bv-content-title"]', $reviewObject)->item(0)->nodeValue,
							'content' => trim($content->item(0)->nodeValue),
							'rating' => $stars ? $stars : 0,
							'timestamp' => $this->xpath->query('.//meta[@itemprop="datePublished"]/@content', $reviewObject)->item(0)->nodeValue
						);
					}
				}
			}
		}
		return $reviews;
	}
}
