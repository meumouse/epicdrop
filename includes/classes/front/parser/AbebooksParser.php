<?php
/**
 * Abebooks data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class AbebooksParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $TITLE_SELECTOR = '//meta[@itemprop="name"]/@content|//span[@class="main-heading"]|//div[@class="plp-title"]';
	private $CATEGORY_SELECTOR = '//span[@itemprop="itemListElement"]/a';
	private $SHORT_DESCRIPTION_SELECTOR = '//div[contains(@class, "publisher")]';
	private $DESCRIPTION_SELECTOR = '//div[@id="product"]|//div[contains(@class, "synopsis-review")]/div[@class="m-md-b"]';
	private $PRICE_SELECTOR = '//meta[@itemprop="price"]/@content|//meta[@itemprop="lowPrice"]/@content|//meta[@id="book-price"]';
	private $ISBN_SELECTOR = '//meta[@itemprop="isbn"]/@content';
	private $COVER_IMG_SELECTOR = '//meta[@itemprop="image"]/@content|//div[@id="thumbnail"]/img/@src|//div[@id="imageContainer"]/a/@href';
	private $IMAGE_SELECTOR = '//a[@class="gallery-link"]/@href';
	private $FEATURES = '//p[@class="biblio"]';
	private $REVIEW_LINK_SELECTOR = '//span[contains(@class, "book-rating-average")]/a/@href';
	private $REVIEW_SELECTOR = '//div[contains(@class, "friendReviews ")]';
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

	private function setJsonData() {
		$json = $this->getJson($this->content, 'digitalData = ', '</script>');
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = json_decode($json, true);
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
		$isbn = $this->getValue($this->ISBN_SELECTOR);
		return array_shift($isbn);
	}

	public function getBrand() {
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
		if ($img) {
			$images[0] = $img;
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
		$feature = $this->xpath->query($this->FEATURES);
		$attributes = array();
		if ($feature->length) {
			foreach ($feature as $attrObject) {
				$featureName = $this->xpath->query('.//strong', $attrObject);
				$featuresValue = $this->xpath->query('.//span', $attrObject);
				if ($featuresValue->length) {
					$attributes[] = array(
						'name' => str_replace(':', '', $featureName->item(0)->nodeValue),
						'value' => preg_replace('/\s+/', ' ', $featuresValue->item(0)->nodeValue)
					);
				}
			}
		}

		if (!$attributes) {
			$attributes[] = array(
				'name' => 'ISBN',
				'value' => $this->getSKU()
			);

			$authors = $this->getValue('//meta[@itemprop="author"]/@content');

			if ($authors) {
				$attributes[] = array(
					'name' => 'Author',
					'value' => implode(',', $authors)
				);
			}

			$publishers = $this->getValue('//meta[@itemprop="publisher"]/@content');

			if ($publishers) {
				$attributes[] = array(
					'name' => 'Publisher',
					'value' => implode(',', $publishers)
				);
			}

			$datePublished = $this->getValue('//meta[@itemprop="datePublished"]/@content');

			if ($datePublished) {
				$attributes[] = array(
					'name' => 'Published Date',
					'value' => array_shift($datePublished)
				);
			}

			$bookFormat = $this->getValue('//meta[@itemprop="bookFormat"]/@content');

			if ($bookFormat) {
				$attributes[] = array(
					'name' => 'Book Format',
					'value' => array_shift($bookFormat)
				);
			}
		}

		if ($attributes) {
			$featureGroups[] = array(
				'name' => 'Bibliographic Details',
				'attributes' => $attributes
			);
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
		$link = '';
		$r_link = $this->getValue($this->REVIEW_LINK_SELECTOR);
		$link = array_shift($r_link);
		if ($link) {
			$reviewHtml = $this->getContent($link);
			if ($reviewHtml) {
				$dom = $this->getDomObj($reviewHtml);
				$xpath = new \DomXPath($dom);
				$reviewArrayObject = $xpath->query($this->REVIEW_SELECTOR);
				if ($reviewArrayObject->length) {
					foreach ($reviewArrayObject as $reviewObject) {
						$stars = $xpath->query('.//span[contains(@class, "p10")]', $reviewObject);
						if ($stars->length) {
							$star = $stars->length;
						}
						$author = $xpath->query('.//span[@itemprop="author"]/a', $reviewObject);
						if ($author->length) {
							$reviews[] = array(
								'author' => $author->item(0)->nodeValue,
								'title' => '',
								'content' => trim(@$xpath->query('.//div[contains(@class, "reviewText")]', $reviewObject)->item(0)->nodeValue),
								'rating' => $star,
								'timestamp' => gmdate('Y-m-d H:i:s', strtotime($xpath->query('.//div[contains(@class, "reviewHeader ")]/a', $reviewObject)->item(0)->nodeValue))
							);
						}
					}
				}
			}
		}
		return $reviews;
	}
}
