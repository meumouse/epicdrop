<?php
/**
 * Jumia data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class JumiaParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $DESCRIPTION_SELECTOR = '//div[contains(@class, "-mhm")]';
	private $FEATURE_SELECTOR_VL = '//div[contains(@class, "-pam")]/ul/li|//ul[contains(@class, "-mvxs")]/li';
	private $SIZE_NM_SELECTOR = '//div[contains(@class, "-mhxs")]/span';
	private $TOTAL_REVIEW_SELECTOR = '//p[contains(@class, "-pts")]';
	private $REVIEW_SELECTOR = '//article[contains(@class, "_bet")]';
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
		$json = $this->getJson($this->content, '<script type="application/ld+json">', '</script>');
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = json_decode($json, true);
		}
		$json = $this->getJson($this->content, 'window.__STORE__=', ';</script>');
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = array_merge($this->jsonDataArray, json_decode($json, true));
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
		if (isset($this->jsonDataArray['mainEntity']['name'])) {
			return $this->jsonDataArray['mainEntity']['name'];
		}
		return '';
	}

	public function getCategories() {
		$categories = array();
		if (isset($this->jsonDataArray['breadcrumb']['itemListElement']) && $this->jsonDataArray['breadcrumb']['itemListElement']) {
			foreach ($this->jsonDataArray['breadcrumb']['itemListElement'] as $categary) {
				$categories[] = $categary['item']['name'];
			}
		}
		return $categories;
	}

	public function getShortDescription() {
		if (isset($this->jsonDataArray['mainEntity']['description'])) {
			return $this->jsonDataArray['mainEntity']['description'];
		}
		return '';
	}

	public function getDescription() {
		$description = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		if ($description) {
			$description = array_shift($description);
			return $description;
		}
		return '';
	}

	public function getPrice() {
		$price = 0;
		if (isset($this->jsonDataArray['mainEntity']['offers']['price'])) {
			$price = str_replace(',', '.', $this->jsonDataArray['mainEntity']['offers']['price']);
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
		if (isset($this->jsonDataArray['products'][0]['brand'])) {
			return $this->jsonDataArray['products'][0]['brand'];
		}
		return '';
	}

	public function getCoverImage() {
		if (isset($this->jsonDataArray['products'][0]['image'])) {
			return $this->jsonDataArray['products'][0]['image'];
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}

		if (isset($this->jsonDataArray['mainEntity']['image']['contentUrl'])) {
			$images[] = $this->jsonDataArray['mainEntity']['image']['contentUrl'];
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
		$featuresVL = $this->getValue($this->FEATURE_SELECTOR_VL);
		$attributes = array();

		if ($featuresVL) {
			foreach ($featuresVL as $featuresVLN) {
				$featuresVLN = explode(':', $featuresVLN);
				if (isset($featuresVLN[1])) {
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
		$sizeNm = $this->getValue($this->SIZE_NM_SELECTOR);

		if ($sizeNm) {
			$sizeNm = array_shift($sizeNm);
			$sizeNm = str_replace(':', '', $sizeNm);
		}
		if ($sizeNm) {
			if (isset($this->jsonDataArray['simples']) && $this->jsonDataArray['simples']) {
				$attrVals = array();
				foreach ($this->jsonDataArray['simples'] as $attrs) {
					$attrVals[] = $attrs['name'];
				}
				if ($attrVals) {
					$attrGroups[] = array(
						'name' => $sizeNm,
						'is_color' => 0,
						'values' => $attrVals
					);
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

		$sizeNm = $this->getValue($this->SIZE_NM_SELECTOR);
		if ($sizeNm) {
			$sizeNm = array_shift($sizeNm);
			$sizeNm = str_replace(':', '', $sizeNm);
		}
		if ($sizeNm) {
			if (isset($this->jsonDataArray['simples']) && $this->jsonDataArray['simples']) {
				foreach ($this->jsonDataArray['simples'] as $keys => $attrVals) {
					if (isset($attrVals['name'])) {
						$combinations[] = array(
							'sku' => $sku,
							'upc' => 0,
							'price' => isset($attrVals['prices']['rawPrice']) ? $attrVals['prices']['rawPrice'] : $price,
							'weight' => 0,
							'image_index' => $keys,
							'attributes' =>  array(
								array(
									'name' => $sizeNm,
									'value' => $attrVals['name']
								)
							)
						);
					}
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

	public function getCustomerReviews( $maxReviews = 0, &$reviews = array(), $page = 1) {
		$urlHost = parse_url($this->url, PHP_URL_HOST);
		$totalReviews = $this->getValue($this->TOTAL_REVIEW_SELECTOR);
		if ($totalReviews) {
			$totalReviews = array_shift($totalReviews);
			$totalReviews = preg_replace('/[^0-9]/', '', $totalReviews);
		}

		if (!$reviews) {
			$sku = $this->getSKU();
			$this->reviewLink = 'https://' . $urlHost . '/catalog/productratingsreviews/sku/' . $sku . '/';
		}
		$reviewLink = $this->reviewLink;
		$reviewLink .= '?page=' . $page;

		if ($reviewLink) {
			$reviewHtml = $this->getContent($reviewLink);

			if ($reviewHtml) {
				$dom = $this->getDomObj($reviewHtml);
				$xpath = new \DomXPath($dom);

				$reviewArrayObject = $xpath->query($this->REVIEW_SELECTOR);
				if ($reviewArrayObject->length) {
					$isMaxReached = false;
					foreach ($reviewArrayObject as $reviewObject) {
						$author = $xpath->query('.//div[@class="-pvs"]/span[2]', $reviewObject);
						$stars = $xpath->query('.//div[@class="in"]/@style', $reviewObject)->item(0)->nodeValue;
						if ($stars) {
							$stars = preg_replace('/[^0-9]/', '', $stars)/20;
						}

						if ($author->length) {
							$reviews[] = array(
								'author' => $author->item(0)->nodeValue,
								'title' => $xpath->query('.//h3[contains(@class, "-fs16")]', $reviewObject)->item(0)->nodeValue,
								'content' => trim($xpath->query('.//p[@class="-pvs"]', $reviewObject)->item(0)->nodeValue),
								'rating' => $stars,
								'timestamp' => $xpath->query('.//div[@class="-pvs"]/span[1]', $reviewObject)->item(0)->nodeValue
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
