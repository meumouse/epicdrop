<?php
/**
 * Zattini data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class ZattiniParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $images = array();
	private $JSON_DATA_SELECTOR = '//script[@type="application/ld+json"]';
	private $CATEGORY_SELECTOR = '//li[@itemtype="https://schema.org/ListItem"]';
	private $SHORT_DESCRIPTION_SELECTOR = '//div[@class="feature-description-values"]';
	private $DESCRIPTION_SELECTOR = '//p[@itemprop="description"]';
	private $COLOR_VARIANT_SELECTOR = '//a[@data-ga-element="link_"]/@href';
	private $IMAGE_SELECTOR = '//ul[@class="swiper-wrapper"]/li/figure/img/@data-src';
	private $COLOR_NAME_SELECTOR = '//label[@for="sku-select-color"]';
	private $SIZE_NAME_SELECTOR = '//label[@for="sku-select-size"]';
	private $FEATURE_SELECTOR = '//ul[@class="attributes"]/li';
	private $REVIEW_SELECTOR = '//div[contains(@class, "reviews__feedback-reviews")]';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content, $url) {
		$this->dom = $this->getDomObj($content);
		$this->url = $url;
		$content = iconv('UTF-8', 'UTF-8//IGNORE', $content);
		$content = preg_replace('!/\*.*?\*/!s', '', $content);
		$this->content = preg_replace('/\s+/', ' ', $content);

		/* Create a new XPath object */
		$this->xpath = new \DomXPath($this->dom);

		// Set json data array
		$this->setJsonData();
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
				$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
				$data = json_decode($json, true);
				if ($data) {
					if (!$this->jsonDataArray) {
						$this->jsonDataArray = array();
					}
					$this->jsonDataArray = array_merge($this->jsonDataArray, $data);
				}
			}
		}

		$json = $this->getJson($this->content, "der.js', '", "');});};");
		if ($json) {
			$this->jsonDataArray = array_merge($this->jsonDataArray, json_decode($json, true));
		}

		$attrsName2 = $this->getValue($this->COLOR_NAME_SELECTOR);
		$attrsName1 = $this->getValue($this->SIZE_NAME_SELECTOR);

		if ($attrsName1) {
			$attrsName1 = array_shift($attrsName1);
		}

		if ($attrsName2) {
			$attrsName2 = array_shift($attrsName2);
		}

		$this->jsonDataArray['products'] = array();
		$dataAttribute = $this->xpath->query($this->COLOR_VARIANT_SELECTOR);

		if ($dataAttribute->length) {
			foreach ($dataAttribute as $key => $dataVarlink) {
				$host = parse_url($this->url, PHP_URL_HOST);
				$dataHtml = $this->getContent($host . $dataVarlink->nodeValue);

				if ($dataHtml) {
					$json = $this->getJson($dataHtml, "der.js', '", "');});};");

					if ($json) {
						$productDataArray = json_decode($json, true);
					}

					$dom = $this->getDomObj($dataHtml);
					$xpath = new \DomXPath($dom);
					$imgsjArray = $this->getValue($this->IMAGE_SELECTOR, false, $xpath);
					$this->images[$key] = $imgsjArray;

					if (isset($productDataArray['product']['skus']) && $productDataArray['product']['skus']) {
						foreach ($productDataArray['product']['skus'] as $attrObject) {
							if (isset($productDataArray['product']['price'])) {
								$this->jsonDataArray['products'][$attrObject['sku']]['price'] = $productDataArray['product']['price'];
							}

							if (isset($attrObject['specs']['size'])) {
								$this->jsonDataArray['products'][$attrObject['sku']]['attributes'][] = array(
									'name' =>  $attrsName1 ? str_replace(':', '', $attrsName1) : 'Tamanho',
									'value' => $attrObject['specs']['size']
								);
							}

							if (isset($attrObject['specs']['color'])) {
								$this->jsonDataArray['products'][$attrObject['sku']]['attributes'][] = array(
									'name' =>  $attrsName2 ? str_replace(':', '', $attrsName2) : 'Cor',
									'value' =>  $attrObject['specs']['color']
								);
							}
							$this->jsonDataArray['products'][$attrObject['sku']]['imageIndex'] = $key;
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
		if (isset($this->jsonDataArray['name'])) {
			return $this->jsonDataArray['name'];
		} elseif (isset($this->jsonDataArray['product']['name'])) {
			return $this->jsonDataArray['product']['name'];
		}
		return '';
	}

	public function getCategories() {
		$categories = $this->getValue($this->CATEGORY_SELECTOR);
		if ($categories) {
			return array_unique($categories);
		}
		return $categories;
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
		if (isset($this->jsonDataArray['offers']['lowPrice'])) {
			$price = $this->jsonDataArray['offers']['lowPrice'];
		} elseif (isset($this->jsonDataArray['offers']['highPrice'])) {
			$price = $this->jsonDataArray['offers']['highPrice'];
		} elseif (isset($this->jsonDataArray['product']['price'])) {
			return $this->jsonDataArray['product']['price'];
		}
		return $price;
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['sku'])) {
			return $this->jsonDataArray['sku'];
		} elseif (isset($this->jsonDataArray['product']['id'])) {
			return $this->jsonDataArray['product']['id'];
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
			return 'https:' . $this->jsonDataArray['image'][0];
		} elseif (isset($this->jsonDataArray['product']['images']['default'])) {
			return 'https:' . $this->jsonDataArray['product']['images']['default'];
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}
		$images = $this->images;


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

	public function getWeight() {
		static $weight = array();

		if ($weight) {
			return $weight;
		}
		$features = $this->getFeatures();

		if ($features) {
			foreach ($features as $feature) {
				foreach ($feature['attributes'] as $attr) {
					if (stripos($attr['name'], 'Peso do Produto') !== false) {
						$weight = array(
							'value' => (float) preg_replace('/[^0-9.]/', '', $attr['value']),
							'unit' => preg_replace('/[0-9.]/', '', $attr['value'])
						);
						break 2;
					}
				}
			}
		}
		return $weight;
	}

	public function getFeatures() {
		static $featureGroups = array();

		if ($featureGroups) {
			return $featureGroups;
		}
		$features = $this->getValue($this->FEATURE_SELECTOR);
		$attributes = array();
		if ($features) {
			foreach ($features as $columes) {
				$columes = explode(':', $columes);

				if (isset($columes[1])) {
					$attributes[] = array(
						'name' => $columes[0],
						'value' => $columes[1]
					);
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

	public function getAttributes() {
		static $attrGroups = array();
		if ($attrGroups) {
			return $attrGroups;
		}

		if (isset($this->jsonDataArray['products']) && $this->jsonDataArray['products']) {
			foreach ($this->jsonDataArray['products'] as $attrs) {
				foreach ($attrs['attributes'] as $attrVals) {
					$key = base64_encode($attrVals['name']);

					if (!isset($attrGroups[$key])) {
						$attrGroups[$key] = array(
							'name' => $attrVals['name'],
							'is_color' => stripos($attrVals['name'], 'Cor') !== false ? 1 : 0,
							'values' => array()
						);
					}

					if (!in_array($attrVals['value'], $attrGroups[$key]['values'])) {
						$attrGroups[$key]['values'][] = $attrVals['value'];
					}
				}
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
		$attrs = $this->getAttributes();
		$weight = $this->getWeight();

		if (isset($this->jsonDataArray['products']) && $this->jsonDataArray['products']) {
			foreach ($this->jsonDataArray['products'] as $keys => $attrVals) {
				if (isset($attrVals['attributes'])) {
					$combinations[] = array(
						'sku' => $keys ? $keys : $sku,
						'upc' => 0,
						'price' => isset($attrVals['price']) ? $attrVals['price'] : $price,
						'weight' => $weight,
						'image_index' => $this->jsonDataArray['products'][$keys]['imageIndex'],
						'attributes' => $attrVals['attributes']
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

	public function getCustomerReviews( $maxReviews = 0, &$reviews = array(), $page = 1) {
		if (!$reviews) {
			$host = parse_url($this->url, PHP_URL_HOST);
			$id = $this->getSKU();
			$this->reviewLink = 'https://' . $host . '/reviews-2?perPage=10&sku=' . $id;
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
						$stars1 = 0;

						if ($author->length) {
							$stars = $xpath->query('.//i', $reviewObject);

							if ($stars->length) {
								$stars1 = $stars->item(0)->nodeValue;
							}

							$reviews[] = array(
								'author' => $author->item(0)->nodeValue,
								'title' => $xpath->query('.//div[@class="reviews__feedback-reviews-author"]/span[3]', $reviewObject)->item(0)->nodeValue,
								'content' => trim($xpath->query('.//p[@class="reviews__feedback-reviews-comments"]', $reviewObject)->item(0)->nodeValue),
								'rating' => $stars1 ? $stars1 : 1,
								'timestamp' => $this->parseTimeStamp($xpath->query('.//div[@class="reviews__feedback-reviews-author"]/span[2]', $reviewObject)->item(0)->nodeValue)
							);

							if (0 < $maxReviews && count($reviews) >= $maxReviews) {
								$isMaxReached = true;
								break;
							}
						}
					}

					if (false == $isMaxReached) {
						$this->getCustomerReviews($maxReviews, $reviews, $page++);
					}
				}
			}
		}
		if (!$reviews) {
			$reviews = $this->getCustomerReviews2();
		}
		return $reviews;
	}

	public function getCustomerReviews2() {
		$reviews = array();

		$reviewArrayObject = $this->xpath->query($this->REVIEW_SELECTOR);

		if ($reviewArrayObject->length) {
			foreach ($reviewArrayObject as $reviewObject) {
				$author = $this->xpath->query('.//div[@class="reviews__feedback-reviews-author"]/span[1]', $reviewObject);
				$stars = $this->xpath->query('.//i', $reviewObject);

				if ($stars->length) {
					$stars = $stars->item(0)->nodeValue;
				}

				if ($author->length) {
					$reviews[] = array(
						'author' => $author->item(0)->nodeValue,
						'title' => $this->xpath->query('.//div[@class="reviews__feedback-reviews-author"]/span[3]', $reviewObject)->item(0)->nodeValue,
						'content' => trim($this->xpath->query('.//p[@class="reviews__feedback-reviews-comments"]', $reviewObject)->item(0)->nodeValue),
						'rating' => $stars ? $stars : 1,
						'timestamp' => $this->parseTimeStamp($this->xpath->query('.//div[@class="reviews__feedback-reviews-author"]/span[2]', $reviewObject)->item(0)->nodeValue)
					);
				}
			}
		}
		return $reviews;
	}
}
