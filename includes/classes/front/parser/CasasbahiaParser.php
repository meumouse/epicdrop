<?php
/**
 * Casasbahia data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class CasasbahiaParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $imgColor;
	private $content;
	private $combColors = array();
	private $host;
	private $jsonDataArray = array();
	private $attrDataArray = array();
	private $DATA_SELECTOR = '//script[@id="item"]';
	private $PRICE_SELECTOR = '//span[@class="product-price-value"]/font/font';
	private $PRICE_SELECTOR2 = '//span[@class="product-price-value"]';
	private $DESCRIPTION_SELECTOR = '//section[@id="product-description-title"]';
	private $COVER_IMG_SELECTOR = '//div[@class="magnify-container"]/div/img/@src';
	private $SELECTOR_OP = '//label[@for="select-sku"]';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content, $url) {
		$this->url = $url;
		$this->dom = $this->getDomObj($content);
		$this->xpath = new \DomXPath($this->dom);
		$this->content = $content;
		// Set json data array
		$this->setJsonData();
		// print_r($this->getCustomerReviews(23));
		// print_r($this->getAttributes());
		//print_r($this->getImages()); die();
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
		$json = $this->getJson($this->content, '"application/json">', '</script>');
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = json_decode($json, true);
		}
	}

	private function getValue( $selector, $html = false) {
		if (empty($selector)) {
			return array();
		}
		$itmes = $this->xpath->query($selector);
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
		if (isset($this->jsonDataArray['props']['initialState']['Product']['product']['name'])) {
			return $this->jsonDataArray['props']['initialState']['Product']['product']['name'];
		}
		return '';
	}

	public function getCategories() {
		$categories = array();
		if (isset($this->jsonDataArray['props']['initialState']['Product']['product']['categories'])  &&  $this->jsonDataArray['props']['initialState']['Product']['product']['categories']) {
			foreach ($this->jsonDataArray['props']['initialState']['Product']['product']['categories'] as $category) {
				$categories[] = $category['description'];
			}
		}
		return $categories;
	}

	public function getDescription() {
		$descript = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		if ($descript) {
			$descript = array_shift($descript);
			return $descript;
		} elseif (isset($this->jsonDataArray['props']['initialState']['Product']['product']['description'])) {
			return $this->jsonDataArray['props']['initialState']['Product']['product']['description'];
		}
		return '';
	}

	public function getPrice() {
		$price = 0;
		$pric = $this->getValue($this->PRICE_SELECTOR);
		$pric2 = $this->getValue($this->PRICE_SELECTOR2);
		if ($pric) {
			$price = preg_replace('/[^0-9.]/', '', array_shift($pric));
		} elseif ($pric2) {
			$price = preg_replace('/[^0-9,]/', '', array_shift($pric2));
		}

		if (strpos($price, '.') !==false
		&& strpos($price, ',') !==false
		&& strpos($price, '.') < strpos($price, ',')
		) {
			$price = str_replace(array('.', ','), array('', '.'), $price);
		} elseif (strpos($price, '.') !==false) {
			$price = str_replace(',', '', $price);
		} else {
			$price = str_replace(',', '.', $price);
		}
		return $price;
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['props']['initialState']['Product']['sku']['id'])) {
			return $this->jsonDataArray['props']['initialState']['Product']['sku']['id'];
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['props']['initialState']['Product']['product']['brand']['name'])) {
			return $this->jsonDataArray['props']['initialState']['Product']['product']['brand']['name'];
		}
		return '';
	}

	public function getSeoUrl() {
		$title = $this->getTitle();
		return AbstractParser::slugify($title);
	}

	public function getCoverImage() {
		$coverImg = $this->getValue($this->COVER_IMG_SELECTOR);
		$coverImg = array_shift($coverImg);
		if ($coverImg) {
			return str_replace('500', '1000', $coverImg);
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}
		if (isset($this->jsonDataArray['props']['initialState']['Product']['product']['skus']) && $this->jsonDataArray['props']['initialState']['Product']['product']['skus']) {
			foreach ($this->jsonDataArray['props']['initialState']['Product']['product']['skus'] as $group) {
				if (isset($group['id'])) {
					$imgUrl = 'https://pdp-api.casasbahia.com.br/api/v2/sku/source/CB?skuId=' . $group['id'];
					$json = $this->getContent($imgUrl);
					if ($json) {
						$imgData = json_decode($json, true);

						if (isset($imgData['sku']['zoomedImages']) && $imgData['sku']['zoomedImages']) {
							foreach ($imgData['sku']['zoomedImages'] as $imgs) {
								$images[$group['id']][] = $imgs['url'];
							}
						}
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

		if (isset($this->jsonDataArray['props']['initialState']['Product']['product']['specGroups']) && $this->jsonDataArray['props']['initialState']['Product']['product']['specGroups']) {
			foreach ($this->jsonDataArray['props']['initialState']['Product']['product']['specGroups'] as $group) {
				if (isset($group['specs'])) {
					$featureGroups[] = array(
						'name' => $group['name'],
						'attributes' => $group['specs']
					);
				}
			}
		}
		if (isset($this->jsonDataArray['props']['initialState']['Product']['sku']['dimensions']) && $this->jsonDataArray['props']['initialState']['Product']['sku']['dimensions']) {
			$attributes = array();
			foreach ($this->jsonDataArray['props']['initialState']['Product']['sku']['dimensions'] as $name => $value) {
				if ($value) {
					$attributes[] = array(
						'name' => $name,
						'value' => $value
					);
				}
			}
			$featureGroups[] = array(
				'name' => 'Dimensions',
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
		$option1 = $this->getValue($this->SELECTOR_OP);
		$option = 'Selecione';
		if ($option1) {
			$option = array_pop($option1);
		}
		$attrVals = array();
		if (isset($this->jsonDataArray['props']['initialState']['Product']['product']['skus'])
			&& $this->jsonDataArray['props']['initialState']['Product']['product']['skus']) {
			foreach ($this->jsonDataArray['props']['initialState']['Product']['product']['skus'] as $attr) {
				$attrVals[] = $attr['name'];
			}
			if ($attrVals) {
				$attrGroups[] = array(
					'name' =>  $option,
					'is_color' => 0,
					'values' => $attrVals
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

		$attributes = $this->getAttributes();
		$attributes = array_shift($attributes);
		$price = $this->getPrice();

		if (isset($this->jsonDataArray['props']['initialState']['Product']['product']['skus'])
			&& $this->jsonDataArray['props']['initialState']['Product']['product']['skus']) {
			foreach ($this->jsonDataArray['props']['initialState']['Product']['product']['skus'] as $comb) {
				$skuId = $comb['id'];
				if (isset($comb['name'])) {
					$combinations[] = array(
					'sku' => $skuId,
					'upc' => 0,
					'price' => $price,
					'weight' => 0,
					'image_index' => $skuId,
					'attributes' => array(
							array(
								'name' => $attributes['name'],
								'value' => $comb['name']
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
				$productId = $this->jsonDataArray['props']['initialState']['Product']['product']['id'];
			}
			$this->reviewLink = 'https://pdp-api.casasbahia.com.br/api/v2/reviews/product/' . $productId . '/source/CB?size=' . $maxReviews;
		}
		$reviewLink = $this->reviewLink;
		$reviewLink .= '&page=' . $page;

		if ($reviewLink) {
			$json = $this->getContent($reviewLink);

			if ($json) {
				$reviewData = json_decode($json, true);

				if (isset($reviewData['review']['userReviews']) && $reviewData['review']['userReviews']) {
					foreach ($reviewData['review']['userReviews'] as $review) {
						if (isset($review['name'])) {
							$reviews[] = array(
								'author' => $review['name'],
								'title' => '',
								'content' => isset($review['text']) ? $review['text'] : '',
								'rating' => $review['rating'],
								'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['date']))
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
		return $reviews;
	}
}
