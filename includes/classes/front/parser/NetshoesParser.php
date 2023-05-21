<?php
/**
 * Netshoes data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class NetshoesParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $imagses = array();
	private $jsonDataArray = array();
	private $VARIANT_SELECTOR = '//ul[@data-type="color"]/li/a/@href';
	private $CATEGORY_SELECTOR = '//li[@itemtype="https://schema.org/ListItem"]/a/span';
	private $IMAGE_VR_SELECTOR = '//ul[@class="swiper-wrapper"]/li/figure/img/@data-src';
	private $PRICE_SELECTOR = '//div[@class="default-price"]/span/strong';
	private $IMAGE_SELECTOR = '//ul[contains(@class, "carousel-items")]/li[contains(@class, "gallery-items")]/a/@href';
	private $COLOR_SELECTOR = '//div[@data-color-flavor-size-content="color"]';
	private $SIZE_SELECTOR = '//ul[@data-type="size"]/li/a';
	private $SIZE_NM_SELECTOR = '//label[@for="sku-select-size"]';
	private $REVIEW_SELECTOR = '//div[contains(@class, "reviews__feedback-reviews")]';
	private $TOTAL_REVIEW_SELECTOR = '//div[@class="reviews__customerFeedback"]/h3';
	private $FEATURE_SELECTOR_VL = '//ul[@class="attributes"]/li';
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
				CURLOPT_FOLLOWLOCATION => true,
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
		$json = $this->getJson($this->content, 'name="structured-pdp">', '</script>');
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = json_decode($json, true);
		}
		$this->jsonDataArray['products'] = array();
		$dataAttribute = $this->xpath->query($this->VARIANT_SELECTOR);
		$urlHost = parse_url($this->url, PHP_URL_HOST);

		if ($dataAttribute->length) {
			foreach ($dataAttribute as $key => $dataVarlink) {
				$dataHtml = $this->getContent('https://' . $urlHost . $dataVarlink->nodeValue);
				if ($dataHtml) {
					$this->jsonDataArray['products'][$key]['attributes'] = array();

					$dom = $this->getDomObj($dataHtml);
					$xpath = new \DomXPath($dom);
					$imgss = $this->getValue($this->IMAGE_VR_SELECTOR, false, $xpath);
					if ($imgss) {
						$this->imagses[$key] = $imgss;
					}

					$priceVar = $this->getValue($this->PRICE_SELECTOR, false, $xpath);

					if ($priceVar) {
						$priceVar = array_shift($priceVar);
						$this->jsonDataArray['products'][$key]['price'] = str_replace(array('R$ ', ','), array('', '.'), $priceVar);
					}
					$attributes = $xpath->query($this->COLOR_SELECTOR);

					if ($attributes->length) {
						foreach ($attributes as $attrObject) {
							$attrsName = $xpath->query('.//label[@for="sku-select-color"]', $attrObject)->item(0)->nodeValue;
							$attrsValue = $xpath->query('.//span[@class="sku-select-title"]', $attrObject)->item(0)->nodeValue;
							$attrsName = str_replace(':', '', $attrsName);
							$this->jsonDataArray['products'][$key]['attributes'][] = array(
								'name' =>  $attrsName,
								'value' =>  $attrsValue,
							);
						}
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
		$categories = $this->getValue($this->CATEGORY_SELECTOR);
		return $categories;
	}

	public function getDescription() {
		if (isset($this->jsonDataArray['description'])) {
			return nl2br($this->jsonDataArray['description']);
		}
		return '';
	}

	public function getPrice() {
		$price = 0;
		if (isset($this->jsonDataArray['offers']['lowPrice'])) {
			$price = str_replace(',', '.', $this->jsonDataArray['offers']['lowPrice']);
		}
		return $price;
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['sku'])) {
			return $this->jsonDataArray['sku'];
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['brand']['name'])) {
			return $this->jsonDataArray['brand']['name'];
		}
		return '';
	}

	public function getCoverImage() {
		if (isset($this->jsonDataArray['image'][0])) {
			$cImage = 'https:' . $this->jsonDataArray['image'][0];
			return $cImage;
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}
		$images = $this->imagses;

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
		$featuresVL = $this->getValue($this->FEATURE_SELECTOR_VL);
		$attributes = array();
		if ($featuresVL) {
			foreach ($featuresVL as $featuresVLN) {
				if ($featuresVLN) {
					$featuresVLN = explode(':', $featuresVLN);
					$attributes[] = array(
						'name' => $featuresVLN[0],
						'value' => preg_replace('/\s+/', ' ', $featuresVLN[1])
					);
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

		if (isset($this->jsonDataArray['products']) && $this->jsonDataArray['products']) {
			$colorVl = array();
			foreach ($this->jsonDataArray['products'] as $attrs) {
				foreach ($attrs['attributes'] as $attrVals) {
					$colorVl[] = $attrVals['value'];
				}
			}
			$attrGroups[] = array(
				'name' => $attrVals['name'],
				'is_color' => 1,
				'values' => $colorVl
			);
		}
		$sizes = $this->getValue($this->SIZE_SELECTOR);
		$sizeNm = $this->getValue($this->SIZE_NM_SELECTOR);
		if ($sizeNm) {
			$sizeNm = array_shift($sizeNm);
			$sizeNm = str_replace(':', '', $sizeNm);
		}
		if ($sizes) {
			$attrGroups[] = array(
				'name' => $sizeNm,
				'is_color' => 0,
				'values' => $sizes
			);
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

		$sizes = $this->getValue($this->SIZE_SELECTOR);
		$sizeNm = $this->getValue($this->SIZE_NM_SELECTOR);
		if ($sizeNm) {
			$sizeNm = array_shift($sizeNm);
			$sizeNm = str_replace(':', '', $sizeNm);
		}

		if (isset($this->jsonDataArray['products']) && $this->jsonDataArray['products']) {
			foreach ($this->jsonDataArray['products'] as $keys => $attrVals) {
				foreach ($attrVals['attributes'] as $attrVal) {
					if ($sizes) {
						foreach ($sizes as $size) {
							$combinations[] = array(
								'sku' => $sku,
								'upc' => 0,
								'price' => isset($attrVals['price']) ? $attrVals['price'] : $price,
								'weight' => 0,
								'image_index' => $keys,
								'attributes' =>  array(
									array(
										'name' => $attrVal['name'],
										'value' => $attrVal['value']
									),
									array(
										'name' => $sizeNm,
										'value' => $size
									)
								)
							);
						}
					} else {
						$combinations[] = array(
							'sku' => $sku,
							'upc' => 0,
							'price' => isset($attrVals['price']) ? $attrVals['price'] : $price,
							'weight' => 0,
							'image_index' => $keys,
							'attributes' =>  array(
								array(
									'name' => $attrVal['name'],
									'value' => $attrVal['value']
								),
							)
						);
					}
				}
			}
		} elseif ($sizes) {
			foreach ($sizes as $size) {
				$combinations[] = array(
					'sku' => $sku,
					'upc' => 0,
					'price' => isset($attrVals['price']) ? $attrVals['price'] : $price,
					'weight' => 0,
					'image_index' => $keys,
					'attributes' =>  array(
						array(
							'name' => $sizeNm,
							'value' => $size
						),
					)
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

	public function getCustomerReviews( $maxReviews = 0) {
		$reviews = array();
		$maxReviews = $maxReviews ? $maxReviews : 500;
		$reviewArrayObject = $this->xpath->query($this->REVIEW_SELECTOR);

		if ($reviewArrayObject->length) {
			$isMaxReached = false;
			foreach ($reviewArrayObject as $reviewObject) {
				$author = $this->xpath->query('.//div[@class="reviews__feedback-reviews-author"]/span[1]', $reviewObject);
				$stars = $this->xpath->query('.//span[contains(@class, "stars")]/i', $reviewObject);

				if ($author->length) {
					$reviews[] = array(
						'author' => $author->item(0)->nodeValue,
						'title' => '',
						'content' => trim($this->xpath->query('.//p[@class="reviews__feedback-reviews-comments"]', $reviewObject)->item(0)->nodeValue),
						'rating' => $stars->item(0)->nodeValue,
						'timestamp' => $this->parseTimeStamp($this->xpath->query('.//span[@class="date"]', $reviewObject)->item(0)->nodeValue)
					);
				}
			}
		}
		if (!$reviews) {
			return $this->getCustomerReviews2();
		}
		return $reviews;
	}

	public function getCustomerReviews2( $maxReviews = 0, &$reviews = array(), $page = 1) {
		$totalReviews = $this->getValue($this->TOTAL_REVIEW_SELECTOR);
		if ($totalReviews) {
			$totalReviews = array_shift($totalReviews);
			$totalReviews = preg_replace('/[^0-9]/', '', $totalReviews);
		}

		if (!$reviews) {
			$sku = $this->getSKU();
			$this->reviewLink = 'https://www.netshoes.com.br/reviews-2?limit=20&sku=' . $sku . '&show=positive';
		}
		$reviewLink = $this->reviewLink;
		$reviewLink .= '&page=' . $page;

		if ($reviewLink) {
			$reviewHtml = $this->getContent($reviewLink);

			if ($reviewHtml) {
				$dom = $this->getDomObj($reviewHtml);
				$xpath = new \DomXPath($dom);

				$reviewArrayObject = $xpath->query($this->REVIEW_SELECTOR);

				if ($reviewArrayObject->length) {
					$isMaxReached = false;
					foreach ($reviewArrayObject as $reviewObject) {
						$author = $xpath->query('.//div[@class="reviews__feedback-reviews-author"]/span[1]', $reviewObject);
						$stars = $xpath->query('.//span[contains(@class, "stars")]/i', $reviewObject);

						if ($author->length) {
							$reviews[] = array(
								'author' => $author->item(0)->nodeValue,
								'title' => '',
								'content' => trim($xpath->query('.//p[@class="reviews__feedback-reviews-comments"]', $reviewObject)->item(0)->nodeValue),
								'rating' => $stars->item(0)->nodeValue,
								'timestamp' => preg_replace('/[^0-9\/]/', '', $xpath->query('.//span[@class="date"]', $reviewObject)->item(0)->nodeValue)
							);

							if (0 < $maxReviews && count($reviews) >= $maxReviews) {
								$isMaxReached = true;
								break;
							}
						}
					}
					if ($totalReviews > ( $page * 10 ) && false == $isMaxReached) {
						$this->getCustomerReviews($maxReviews, $reviews, ++$page);
					}
				}
			}
		}
		return $reviews;
	}
}
