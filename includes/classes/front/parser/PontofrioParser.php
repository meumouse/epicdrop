<?php
/**
 * Pontofrio data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class PontofrioParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $JSON_DATA_SELECTOR = '//script[@type="application/ld+json"]';
	private $PRICE_SELECTOR = '//span[@id="product-price"]';
	private $REVIEW_SELECTOR = '//ul[@class="reviews"]/li';
	private $DESCRIPTION_SELECTOR = '//div[contains(@class, "e1uc2v351")]';
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
		$json = $this->getJson($this->content, 'DATA__" type="application/json">', '</script>');
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
		if (isset($this->jsonDataArray['name'])) {
			return $this->jsonDataArray['name'];
		}
		return '';
	}

	public function getCategories() {
		$categories = array();
		if (isset($this->jsonDataArray['props']['initialState']['Product']['product']['categories']) && $this->jsonDataArray['props']['initialState']['Product']['product']['categories']) {
			foreach ($this->jsonDataArray['props']['initialState']['Product']['product']['categories'] as $categary) {
				$categories[] = $categary['description'];
			}
		}
		return $categories;
	}

	public function getShortDescription() {
		if (isset($this->jsonDataArray['description'])) {
			return $this->jsonDataArray['description'];
		}
		return '';
	}

	public function getDescription() {
		$description = '';
		$descript = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		$descripts = array_shift($descript);
		if ($descripts) {
			$description = $descripts;
		} elseif (isset($this->jsonDataArray['props']['initialState']['Product']['product']['description'])) {
			$description = $this->jsonDataArray['props']['initialState']['Product']['product']['description'];
		}
		return $description;
	}

	public function getPrice() {
		$price = 0;
		$price = $this->getValue($this->PRICE_SELECTOR);
		$price = array_shift($price);
		if ($price) {
			$price = str_replace(array(',', 'R$'), array('.', ''), $price);
		} elseif (isset($this->jsonDataArray['offers'][0]['lowPrice'])) {
			$price = $this->jsonDataArray['offers'][0]['lowPrice'];
		}
		return preg_replace('/[^0-9.]/', '', $price);
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['sku'])) {
			return $this->jsonDataArray['sku'];
		}
		return '';
	}
	public function getWeight() {
		$weight = array();

		if (isset($this->jsonDataArray['props']['initialState']['Product']['sku']['dimensions']['weight'])) {
			$weight = array(
				'unit' => 'g',
				'value' => str_replace(array(',', 'g'), array('.', ''), $this->jsonDataArray['props']['initialState']['Product']['sku']['dimensions']['weight'])
			);
		}
		return $weight;
	}

	public function getDimension() {
		static $dimensions = array();
		$length = 0;
		$width = 0;
		$height = 0;
		if (isset($this->jsonDataArray['props']['initialState']['Product']['sku']['dimensions']['depth'])) {
			$length = str_replace(array(',', 'mm'), array('.', ''), $this->jsonDataArray['props']['initialState']['Product']['sku']['dimensions']['depth']);
		}
		if (isset($this->jsonDataArray['props']['initialState']['Product']['sku']['dimensions']['width'])) {
			$width = str_replace(array(',', 'cm'), array('.', ''), $this->jsonDataArray['props']['initialState']['Product']['sku']['dimensions']['width']);
		}
		if (isset($this->jsonDataArray['props']['initialState']['Product']['sku']['dimensions']['height'])) {
			$height = str_replace(array(',', 'cm'), array('.', ''), $this->jsonDataArray['props']['initialState']['Product']['sku']['dimensions']['height']);
		}
		if ($length && $width && $height) {
			$dimensions = array(
				'length' => $length,
				'width' => $width,
				'height' => $height
			);
		}
		return $dimensions;
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

		if (isset($this->jsonDataArray['props']['initialState']['Product']['product']['skus'])
			&& $this->jsonDataArray['props']['initialState']['Product']['product']['skus']) {
			foreach ($this->jsonDataArray['props']['initialState']['Product']['product']['skus'] as $key => $imgsLink) {
				$linksId = $imgsLink['id'];
				$skuLink = 'https://pdp-api.pontofrio.com.br/api/v2/sku/source/EX?skuId=' . $linksId;
				$productJson = $this->getContent($skuLink);
				$productArray = json_decode($productJson, true);
				if (isset($productArray['sku']['zoomedImages']) && $productArray['sku']['zoomedImages']) {
					foreach ($productArray['sku']['zoomedImages'] as $image) {
						$images[$key][] = $image['url'];
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
		if (isset($this->jsonDataArray['props']['initialState']['Product']['product']['specGroups'])
			&& $this->jsonDataArray['props']['initialState']['Product']['product']['specGroups']) {
			foreach ($this->jsonDataArray['props']['initialState']['Product']['product']['specGroups'] as $features) {
				$attributes = array();
				foreach ($features['specs'] as $values) {
					if (isset($values['name'])) {
						$attributes[] = array(
							'name' => $values['name'],
							'value' => preg_replace('/\s+/', ' ', $values['value'])
						);
					}
				}
				if ($attributes) {
					$featureGroups[] = array(
						'name' => $features['name'],
						'attributes' => $attributes
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

		if (isset($this->jsonDataArray['props']['initialState']['Product']['product']['skus'])
			&& $this->jsonDataArray['props']['initialState']['Product']['product']['skus']) {
			$attribute = array();

			foreach ($this->jsonDataArray['props']['initialState']['Product']['product']['skus'] as $attrs) {
				$attribute[] = $attrs['name'];
			}
			if ($attribute) {
				$attrGroups[] = array(
					'name' => 'Selecione',
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
		$weight = $this->getWeight();

		if (isset($this->jsonDataArray['props']['initialState']['Product']['product']['skus'])
			&& $this->jsonDataArray['props']['initialState']['Product']['product']['skus']) {
			foreach ($this->jsonDataArray['props']['initialState']['Product']['product']['skus'] as $keys => $attrs) {
				if (isset($attrs['name'])) {
					$combinations[] = array(
						'sku' => isset($attrs['id']) ? $attrs['id'] : $sku,
						'upc' => 0,
						'price' => $price,
						'weight' => $weight,
						'image_index' => $keys,
						'attributes' =>  array(
							array(
								'name' => 'Selecione',
								'value' => $attrs['name']
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

	public function getCustomerReviews( $maxReviews = 0, &$reviews = array(), $page = 1) {
		if (!$reviews) {
			if (isset($this->jsonDataArray['props']['initialState']['Product']['product']['id'])) {
				$sku = $this->jsonDataArray['props']['initialState']['Product']['product']['id'];
			}
			$this->reviewLink = 'https://pdp-api.pontofrio.com.br/api/v2/reviews/product/' . $sku . '/source/EX?size=20&orderBy=DATE';
		}
		$reviewLink = $this->reviewLink;
		$reviewLink .= '&page=' . $page;

		if ($reviewLink) {
			$reviewJson = $this->getContent($reviewLink);
			if ($reviewJson) {
				$reviewArrayObject = json_decode($reviewJson, true);

				if (isset($reviewArrayObject['review']['userReviews']) && $reviewArrayObject['review']['userReviews']) {
					$isMaxReached = false;
					foreach ($reviewArrayObject['review']['userReviews'] as $reviewObject) {
						if (isset($reviewObject['name'])) {
							$reviews[] = array(
								'author' => $reviewObject['name'],
								'title' => '',
								'content' => $reviewObject['text'],
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
						$this->getCustomerReviews($maxReviews, $reviews, ++$page);
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
				$author = $this->xpath->query('.//span[@class="name"]', $reviewObject);
				$stars = $this->xpath->query('.//div[contains(@class, "eym5xli0")]/@title', $reviewObject)->item(0)->nodeValue;
				if ($stars) {
					$stars = explode(' ', $stars);
					$stars = array_shift($stars);
				}
				if ($author->length) {
					$reviews[] = array(
						'author' => $author->item(0)->nodeValue,
						'title' => '',
						'content' => trim($this->xpath->query('.//p[@class="text"]', $reviewObject)->item(0)->nodeValue),
						'rating' => $stars,
						'timestamp' => $this->xpath->query('.//time', $reviewObject)->item(0)->nodeValue
					);
				}
			}
		}
		return $reviews;
	}
}
