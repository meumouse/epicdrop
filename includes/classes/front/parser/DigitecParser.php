<?php
/**
 * Digitec data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class DigitecParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $images = array();
	private $JSON_DATA_SELECTOR = '//script[@type="application/ld+json"]';
	private $JSON_DATA_SELECTOR2 = '//script[@type="application/json"]';
	private $SHORT_DESCRIPTION_SELECTOR = '//div[contains(@class, "productDetail")]/span';
	private $VARIANT_SELECTOR = '//div[contains(@class, "jvTRfi")]/div/a/@href';
	private $IMAGE_SELECTOR = '//button/picture/img/@src';
	private $PRICE_SELECTOR = '//strong[contains(@class, "gvrGle")]';
	private $ATTRIBUTE_SELECTOR = '//div[contains(@class, "jvTRfi")]';
	private $REVIEW_SELECTOR = '//article[@data-cy="review"]';
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
		$json = $this->getValue($this->JSON_DATA_SELECTOR2);

		if ($json) {
			foreach ($json as $jsonc) {
				$json = iconv('UTF-8', 'UTF-8//IGNORE', $jsonc);
				$data = json_decode($json, true);
				if ($data) {
					if (!$this->jsonDataArray) {
						$this->jsonDataArray = array();
					}
					$this->jsonDataArray = array_merge($this->jsonDataArray, $data);
				}
			}
		}

		$dataAttribute = $this->xpath->query($this->VARIANT_SELECTOR);
		$this->jsonDataArray['products'] = array();

		if ($dataAttribute->length) {
			foreach ($dataAttribute as $key => $dataVarlink) {
				$host = parse_url($this->url, PHP_URL_HOST);
				$skuvar = explode('-', $host . $dataVarlink->nodeValue);
				$skuvar = array_pop($skuvar);
				$dataHtml = $this->getContent($host . $dataVarlink->nodeValue);

				if ($dataHtml) {
					$this->jsonDataArray['products'][$skuvar]['attributes'] = array();
					$dom = $this->getDomObj($dataHtml);
					$xpath = new \DomXPath($dom);

					$imageArr = $this->getValue($this->IMAGE_SELECTOR, false, $xpath);
					if ($imageArr) {
						$this->images[$skuvar] = $imageArr;
					}

					$priceVar = $this->getValue($this->PRICE_SELECTOR, false, $xpath);
					$combPrice = array_shift($priceVar);

					if ($combPrice) {
						$this->jsonDataArray['products'][$skuvar]['price'] = preg_replace('/[^0-9]/', '', $combPrice);
					}

					$attribute = $xpath->query($this->ATTRIBUTE_SELECTOR);
					if ($attribute->length) {
						foreach ($attribute as $attrObject) {
							$attrName = @$xpath->query('.//h4', $attrObject)->item(0)->nodeValue;
							$attrValue = $xpath->query('.//a[contains(@class, "eesChl")]', $attrObject);

							if ($attrValue->length) {
								$this->jsonDataArray['products'][$skuvar]['attributes'][] = array(
									'name' =>  $attrName,
									'value' =>  trim($attrValue->item(0)->nodeValue)
								);
							}
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
		if (null == $xpath) {
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
		}
	}

	public function getCategories() {
		$categories = array();
		if (isset($this->jsonDataArray['itemListElement']) && $this->jsonDataArray['itemListElement']) {
			foreach ($this->jsonDataArray['itemListElement'] as $categorey) {
				$categories[] = $categorey['name'];
			}
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
		if (isset($this->jsonDataArray['description'])) {
			return $this->jsonDataArray['description'];
		}
		return '';
	}

	public function getPrice() {
		$price = 0;
		if (isset($this->jsonDataArray['offers']['price'])) {
			$price = $this->jsonDataArray['offers']['price'];
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
			return $this->jsonDataArray['image'][0];
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
			if (isset($this->jsonDataArray['image'])) {
				$images[0] = $this->jsonDataArray['image'];
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

	public function getWeight() {
		static $weight = array();

		if ($weight) {
			return $weight;
		}

		$features = $this->getFeatures();

		if ($features) {
			foreach ($features as $feature) {
				foreach ($feature['attributes'] as $attr) {
					if (stripos($attr['name'], 'weight') !== false) {
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
		$id = $this->getSKU();

		if (isset($this->jsonDataArray['props']['apolloState']['ROOT_QUERY']['productDetailsV3({"productId":' . $id . '})']['productDetails']['specifications'])
			&& $this->jsonDataArray['props']['apolloState']['ROOT_QUERY']['productDetailsV3({"productId":' . $id . '})']['productDetails']['specifications']) {
			foreach ($this->jsonDataArray['props']['apolloState']['ROOT_QUERY']['productDetailsV3({"productId":' . $id . '})']['productDetails']['specifications'] as $speci) {
				$attributes = array();

				foreach ($speci['properties'] as $feature) {
					$attributes[] = array(
						'name' => $feature['name'],
						'value' => $feature['values'][0]['value']
					);
				}

				if ($attributes) {
					$featureGroups[] = array(
						'name' => $speci['title'],
						'attributes' => $attributes,
					);
				}
			}
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
				if (isset($attrs['attributes']) && $attrs['attributes']) {
					foreach ($attrs['attributes'] as $attrVals) {
						$key = base64_encode($attrVals['name']);

						if (!isset($attrGroups[$key])) {
							$attrGroups[$key] = array(
							'name' => $attrVals['name'],
							'is_color' => stripos($attrVals['name'], 'Colour') !== false ? 1 : 0,
							'values' => array()
							);
						}

						if (!in_array($attrVals['value'], $attrGroups[$key]['values'])) {
							$attrGroups[$key]['values'][] = $attrVals['value'];
						}
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
						'image_index' => $keys,
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

	public function getCustomerReviews() {
		$reviews = array();

		$reviewArrayObject = $this->xpath->query($this->REVIEW_SELECTOR);
		if ($reviewArrayObject->length) {
			foreach ($reviewArrayObject as $reviewObject) {
				$author = $this->xpath->query('.//a[contains(@class, "fmXXCq")]', $reviewObject);

				if ($author->length) {
					$star = 3;
					$stars = explode(' ', $this->xpath->query('.//p[contains(@class, "sc-r181n3-0")]', $reviewObject)->item(0)->nodeValue);
					if ($stars) {
						$star = array_shift($stars);
					}

					$reviews[] = array(
						'author' => $author->item(0)->nodeValue,
						'title' => @$this->xpath->query('.//h4', $reviewObject)->item(0)->nodeValue,
						'content' => trim(@$this->xpath->query('.//span[contains(@class, "sc-1epymzn-0")]', $reviewObject)->item(0)->nodeValue),
						'rating' => $star,
						'timestamp' => gmdate('Y-m-d H:i:s', strtotime($this->xpath->query('.//p[contains(@class, "sc-9mbh1g-4")]/span[1]', $reviewObject)->item(0)->nodeValue))
					);
				}
			}
		}
		return $reviews;
	}
}
