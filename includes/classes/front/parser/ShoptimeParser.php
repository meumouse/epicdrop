<?php
/**
 * Shoptime data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class ShoptimeParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $JSON_DATA_SELECTOR = '//script[@type="application/ld+json"]';
	private $DESCRIPTION_SELECTOR = '//div[@id="info-section"]';
	private $PRICE_SELECTOR = '//div[contains(@class, "priceSales")]';
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

	private function setJsonData() {
		$jsons = $this->getValue($this->JSON_DATA_SELECTOR);
		if ($jsons) {
			foreach ($jsons as $json) {
				$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
				$this->jsonDataArray = array_merge($this->jsonDataArray, json_decode($json, true));
			}
		}

		$json = $this->getJson($this->content, 'window.__APOLLO_STATE__ = ', '</script>');
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = array_merge($this->jsonDataArray, json_decode($json, true));
		}
		$this->jsonDataArray['product'] = array();
		$url = explode('?', $this->url);
		$url = array_shift($url);
		$id = preg_replace('/[^0-9]/', '', $url);

		if (isset($this->jsonDataArray['ROOT_QUERY'])) {
			$this->jsonDataArray['product'] = $this->jsonDataArray['ROOT_QUERY']['product:{"productId":"' . $id . '"}'];
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
		if (isset($this->jsonDataArray['@graph']) && $this->jsonDataArray['@graph']) {
			foreach ($this->jsonDataArray['@graph'] as $data) {
				if ('Product' == $data['@type']) {
					return $data['name'];
				}
			}
		}
		return '';
	}

	public function getCategories() {
		$categories = array();
		if (isset($this->jsonDataArray['@graph']) && $this->jsonDataArray['@graph']) {
			foreach ($this->jsonDataArray['@graph'] as $data) {
				if ('BreadcrumbList' == $data['@type']) {
					foreach ($data['itemListElement'] as $cate) {
						$categ = explode(';', $cate['item']['name']);
						$categ = array_shift($categ);
						$categories[] = $categ;
					}
				}
			}
		}
		return $categories;
	}

	public function getShortDescription() {
		if (isset($this->jsonDataArray['@graph']) && $this->jsonDataArray['@graph']) {
			foreach ($this->jsonDataArray['@graph'] as $data) {
				if ('Product' == $data['@type']) {
					return $data['description'];
				}
			}
		}
		return '';
	}

	public function getDescription() {
		$description = '';
		$descript = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		$descripts = array_shift($descript);

		if (isset($this->jsonDataArray['product']['description']['rawDescription'])) {
			$description .= $this->jsonDataArray['product']['description']['rawDescription'];
		}
		if (isset($this->jsonDataArray['product']['description']['content'])) {
			$description .= $this->jsonDataArray['product']['description']['content'];
		}
		if ($descripts) {
			$description .= $descripts;
		}
		return $description;
	}

	public function getPrice() {
		$price = 0;
		$mainPrice = $this->getValue($this->PRICE_SELECTOR);
		$mainPrice = array_shift($mainPrice);
		if ($mainPrice) {
			$price = str_replace(',', '.', $mainPrice);
		} elseif (isset($this->jsonDataArray['@graph']) && $this->jsonDataArray['@graph']) {
			foreach ($this->jsonDataArray['@graph'] as $data) {
				if ('Product' == $data['@type']) {
					if (isset($data['offers']['lowPrice'])) {
						$price = $data['offers']['lowPrice'];
					}
				}
			}
		}
		return preg_replace('/[^0-9.]/', '', $price);
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['@graph']) && $this->jsonDataArray['@graph']) {
			foreach ($this->jsonDataArray['@graph'] as $data) {
				if ('Product' == $data['@type']) {
					return $data['sku'];
				}
			}
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['ROOT_QUERY']['config']['brand'])) {
			return $this->jsonDataArray['ROOT_QUERY']['config']['brand'];
		}
		return '';
	}
	public function getCoverImage() {
		if (isset($this->jsonDataArray['@graph']) && $this->jsonDataArray['@graph']) {
			foreach ($this->jsonDataArray['@graph'] as $data) {
				if ('Product' == $data['@type']) {
					return $data['image']['url'];
				}
			}
		}
		return '';
	}

	public function getVideos() {
		$video = array();
		if (isset($this->jsonDataArray['product']['video'])) {
			$video[] = $this->jsonDataArray['product']['video'];
		}
		return '';
	}

	public function getImages() {
		static $images = array();
		if ($images) {
			return $images;
		}

		if (isset($this->jsonDataArray['product']['skus']) && $this->jsonDataArray['product']['skus']) {
			foreach ($this->jsonDataArray['product']['skus'] as $key => $imgsLink) {
				$linksId = $imgsLink['__ref'];
				if (isset($this->jsonDataArray[$linksId]['images']) && $this->jsonDataArray[$linksId]['images']) {
					foreach ($this->jsonDataArray[$linksId]['images'] as $image) {
						$images[$key][] = $image['extraLarge'];
					}
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
		if (isset($this->jsonDataArray['product']['attributes']) && $this->jsonDataArray['product']['attributes']) {
			$attributes = array();
			foreach ($this->jsonDataArray['product']['attributes'] as $features) {
				if (isset($features['name'])) {
					$attributes[] = array(
						'name' => $features['name'],
						'value' => preg_replace('/\s+/', ' ', $features['value'])
					);
				}
			}
			if ($attributes) {
				$featureGroups[] = array(
					'name' => 'Ficha técnica',
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
		if (isset($this->jsonDataArray['product']['diffs']) && $this->jsonDataArray['product']['diffs']) {
			foreach ($this->jsonDataArray['product']['diffs'] as $attrs) {
				$attribute = array();
				if (isset($attrs['values']) && $attrs['values']) {
					foreach ($attrs['values'] as $attr) {
						$attribute[] = $attr['value'];
					}
				}
				if (isset($attrs['type'])) {
					$attrGroups[] = array(
						'name' => $attrs['type'],
						'is_color' => 'Cor' == $attrs['type'] ? 1 : 0,
						'values' => $attribute
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

		if (isset($this->jsonDataArray['product']['skus']) && $this->jsonDataArray['product']['skus']) {
			foreach ($this->jsonDataArray['product']['skus'] as $key => $imgsLink) {
				$linksId = $imgsLink['__ref'];

				if (isset($this->jsonDataArray[$linksId]['diffs']) && $this->jsonDataArray[$linksId]['diffs']) {
					$attribute = array();
					foreach ($this->jsonDataArray[$linksId]['diffs'] as $attrs) {
						if (isset($attrs['type']) && $attrs['type']) {
							$attribute[] = array(
								'name' => $attrs['type'],
								'value' => $attrs['value']
							);
						}
					}
					$comboPrice = '';
					if (isset($this->jsonDataArray[$linksId]['offers({"promoted":true,"sellerId":""})']['result'][0]['__ref'])) {
						$comboPrice = $this->jsonDataArray[$linksId]['offers({"promoted":true,"sellerId":""})']['result'][0]['__ref'];
					}
					if ($attribute) {
						$combinations[] = array(
							'sku' => isset($this->jsonDataArray[$linksId]['id']) ? $this->jsonDataArray[$linksId]['id'] : $sku,
							'upc' => 0,
							'price' => isset($this->jsonDataArray[$comboPrice]['salesPrice']) ? preg_replace('/[^0-9.]/', '', $this->jsonDataArray[$comboPrice]['salesPrice']) : $price,
							'weight' => 0,
							'image_index' => $key,
							'attributes' =>  $attribute
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

	public function getCustomerReviews( $maxReviews = 0, &$reviews = array(), $offset = 0) {
		$id = $this->jsonDataArray['product']['id'];

		$reviewLink = 'https://catalogo-bff-v2-shoptime.b2w.io/graphql?operationName=productReviews&variables={"productId":"' . $id . '","offset":' . $offset . ',"filters":null,"sort":"Helpfulness:desc"}&extensions={"persistedQuery":{"version":1,"sha256Hash":"fdc9e92cb4f538144d2e5f659bdba0ceaaf6cf03a4220438acc7f8327e92bef8"}}';
		if ($reviewLink) {
			$reviewJson = $this->getContent($reviewLink);
			if ($reviewJson) {
				$reviewArrayObject = json_decode($reviewJson, true);
				if (isset($reviewArrayObject['data']['product']['reviews']['result']) && $reviewArrayObject['data']['product']['reviews']['result']) {
					$isMaxReached = false;
					foreach ($reviewArrayObject['data']['product']['reviews']['result'] as $reviewObject) {
						if (isset($reviewObject['user'])) {
							$reviews[] = array(
								'author' => $reviewObject['user'],
								'title' => isset($reviewObject['title']) ? $reviewObject['title'] : '',
								'content' => isset($reviewObject['review']) ? $reviewObject['review'] : '',
								'rating' =>$reviewObject['rating'],
								'timestamp' => $reviewObject['date']
							);
							if (0 < $maxReviews && count($reviews) >= $maxReviews) {
								$isMaxReached = true;
								break;
							}
						}
					}
					if (false == $isMaxReached) {
						$this->getCustomerReviews($maxReviews, $reviews, ( $offset+6 ));
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

		if (isset($this->jsonDataArray['@graph']) && $this->jsonDataArray['@graph']) {
			foreach ($this->jsonDataArray['@graph'] as $data) {
				if ('Product' == $data['@type']) {
					if (isset($data['review']) && $data['review']) {
						foreach ($data['review'] as $reviewObject) {
							if (isset($reviewObject['author']['name'])) {
								$reviews[] = array(
									'author' => $reviewObject['author']['name'],
									'title' => isset($reviewObject['name']) ? $reviewObject['name'] : '',
									'content' => isset($reviewObject['reviewBody']) ? $reviewObject['reviewBody'] : '',
									'rating' =>$reviewObject['reviewRating'][0]['ratingValue'],
									'timestamp' => $reviewObject['datePublished']
								);
							}
						}
					}
				}
			}
		}
		return $reviews;
	}
}
