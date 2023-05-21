<?php
/**
 * Spartoo data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class SpartooParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $JSON_DATA_SELECTOR = '//script[@type="application/ld+json"]';
	private $TITLE_SELECTOR = '//title';
	private $CATEGORY_SELECTOR = '//div[@id="filArianeProdcard"]/a';
	private $DESCRIPTION_SELECTOR = '//div[@class="product_detailed_infos"]';
	private $PRICE_SELECTOR = '//span[@itemprop="price"]';
	private $SKU_SELECTOR = '//span[@itemprop="sku"]';
	private $BRAND_SELECTOR = '//div[@itemprop="brand"]/@content';
	private $COVER_IMG_SELECTOR = '//img[@id="productImage"]/@src';
	private $IMAGE_SELECTOR = '//div[@class="productView"]/a/img/@src';
	private $FEATURES_SELECTOR = '//span[@id="products_info_picto"]';
	private $ATTRIBUTE_SELECTOR = '//ul[@id="display_size_id"]/li';
	private $ATTR_NAME_SELECTOR = '//div[@class="sizeList_title"]';
	private $REVIEW_SELECTOR1 = '//div[@itemtype="https://schema.org/Review"]';
	private $REVIEW_SELECTOR2 = '//div[@class="avisClients"]/a/@onclick';
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
		$reviewArrayObject = $this->getValue($this->REVIEW_SELECTOR2);
		$reviewArrayObject = array_shift($reviewArrayObject);
		if ($reviewArrayObject) {
			$json = $this->getJson($reviewArrayObject, 'print_more_reviews(', '); return');
			if ($json) {
				$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
				$this->jsonDataArray = json_decode($json, true);
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
		return $categories;
	}

	public function getShortDescription() {
		$shortDescription = '';
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
		$price = array_shift($price);
		if ($price) {
			$price = str_replace(',', '.', preg_replace('/[^0-9,.]/', '', $price));
		}
		return $price;
	}

	public function getSKU() {
		$sku = $this->getValue($this->SKU_SELECTOR);
		$sku = array_shift($sku);
		if ($sku) {
			return preg_replace('/[^0-9]/', '', $sku);
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
			$cImage = str_replace('500', '1200', $cImage);
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
			$images[0] = str_replace('40', '1200', $img);
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
		return $featureGroups;
	}

	public function getAttributes() {
		static $attrGroups = array();
		if ($attrGroups) {
			return $attrGroups;
		}

		$attributes = $this->xpath->query($this->ATTRIBUTE_SELECTOR);
		$attrName = $this->getValue($this->ATTR_NAME_SELECTOR);
		$attrName = array_shift($attrName);
		$attribute = array();
		if ($attributes->length) {
			foreach ($attributes as $attrArray) {
				$attrValues = $this->xpath->query('.//div[@class="size_name"]', $attrArray);
				if ($attrValues->length) {
					$attribute[] = $attrValues->item(0)->nodeValue;
				}
			}
			if ($attribute) {
				$attrGroups[] = array(
					'name' => str_replace(':', '', $attrName),
					'is_color' => 0,
					'values' => $attribute
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

		$attributes = $this->xpath->query($this->ATTRIBUTE_SELECTOR);
		$attrName = $this->getValue($this->ATTR_NAME_SELECTOR);
		$attrName = array_shift($attrName);
		if ($attributes->length) {
			foreach ($attributes as $attrArray) {
				$attrValues = $this->xpath->query('.//div[@class="size_name"]', $attrArray);

				if ($attrValues->length) {
					$combinations[] = array(
						'sku' => $sku,
						'upc' => '',
						'price' => $price,
						'weight' => 0,
						'image_index' => 0,
						'attributes' => array(
							array(
								'name' => str_replace(':', '', $attrName),
								'value' => $attrValues->item(0)->nodeValue
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

	private function parseTimeStamp( $text) {
		if (!empty($text)) {
			$replaceTexts = array(
				'january' => array(
					'janeiro',
					'1月',
					'janvier',
					'gennaio',
					'enero',
					'januari',
					'كانون الثاني',
					'يناير',
				),
				'february' => array(
					'fevereiro',
					'2月',
					'février',
					'februar',
					'febbraio',
					'febrero',
					'februari',
					'فبراير',
				),
				'march' => array(
					'Março',
					'3月',
					'mars',
					'marzo',
					'märz',
					'marcha',
					'maart',
					'مارس',
				),
				'april' => array(
					'abril',
					'4月',
					'avril',
					'aprile',
					'أبريل',
				),
				'may' => array(
					'maio',
					'5月',
					'mai',
					'maggio',
					'mayo',
					'mei',
					'مايو',
				),
				'june' => array(
					'junho',
					'6月',
					'juin',
					'junio',
					'juni',
					'giugno',
					'يونيو',
				),
				'july' => array(
					'julho',
					'7月',
					'juillet',
					'luglio',
					'julio',
					'juli',
					'تموز',
				),
				'august' => array(
					'agosto',
					'8月',
					'août',
					'augustus',
					'أغسطس',
				),
				'september' => array(
					'setembro',
					'9月',
					'septembre',
					'settembre',
					'septiembre',
					'سبتمبر',
				),
				'october' => array(
					'outubro',
					'10月',
					'octobre',
					'oktober',
					'ottobre',
					'octubre',
					'اكتوبر',
				),
				'november' => array(
					'novembro',
					'11月',
					'novembre',
					'noviembre',
					'نوفمبر',
				),
				'december' => array(
					'dezembro',
					'12月',
					'décembre',
					'dezember',
					'dicembre',
					'diciembre',
					'ديسمبر',
				),
			);

			$text = strtolower($text);

			foreach ($replaceTexts as $replaceWith => $find) {
				$textReplaced = str_replace($find, $replaceWith, $text);

				$splitTexts = explode(' ', $textReplaced);

				$splitTexts = array_map('trim', $splitTexts);

				$newText = '';

				if (array_intersect($splitTexts, array_keys($replaceTexts))) {
					foreach ($splitTexts as $txt) {
						if (preg_match('/^[0-9,]+$/i', $txt) || in_array(trim($txt), array_keys($replaceTexts))) {
							$newText .= $txt . ' ';
						}
					}
				}
				if (!empty($newText)) {
					return gmdate('Y-m-d H:i:s', strtotime($newText));
				}
			}
		}
		return gmdate('Y-m-d H:i:s');
	}

	public function getCustomerReviews2() {
		$reviews = array();
		$reviewArrayObject = $this->xpath->query($this->REVIEW_SELECTOR1);
		if ($reviewArrayObject->length) {
			foreach ($reviewArrayObject as $reviewObject) {
				$author = $this->xpath->query('.//span[@itemprop="author"]', $reviewObject);
				if ($author->length) {
					$stars = 0;
					$stars = $this->xpath->query('.//span[@class="rating"]', $reviewObject)->item(0)->nodeValue;
					if ($stars) {
						$rating = $stars/2;
					}
					$my_str = $this->xpath->query('.//div[@itemprop="datePublished"]', $reviewObject)->item(0)->nodeValue;
					$my_str = array_reverse(explode(' ', $my_str));
					$date = $my_str[2] . ' ' . $my_str[1] . ' ' . $my_str[0];

					$reviews[] = array(
						'author' => trim($author->item(0)->nodeValue),
						'title' => trim($this->xpath->query('.//div', $reviewObject)->item(1)->nodeValue),
						'content' => @$this->xpath->query('.//div[3]', $reviewObject)->item(0)->nodeValue,
						'rating' => $rating,
						'timestamp' => $this->parseTimeStamp($date)
					);
				}
			}
		}
		return $reviews;
	}

	public function getCustomerReviews() {
		$reviews = array();
		if (isset($this->jsonDataArray) && $this->jsonDataArray) {
			$isMaxReached = false;
			foreach ($this->jsonDataArray as $reviewObject) {
				if ($reviewObject['customers_name']) {
					$reviews[] = array(
						'author' => $reviewObject['customers_name'],
						'title' => '',
						'content' => trim(isset($reviewObject['text']) ? $reviewObject['text'] : ''),
						'rating' =>$reviewObject['rating']/2,
						'timestamp' => gmdate('Y-m-d H:i:s', strtotime($reviewObject['date']))
					);
				}
			}
		}
		if (!$reviews) {
			$reviews = $this->getCustomerReviews2();
		}
		return $reviews;
	}
}
