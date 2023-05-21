<?php
/**
 * Lazada data parser class
 *
 * @package: product-importer
 *
 */
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class LazadaParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $url;
	private $content;
	private $jsonDataArray = array();
	private $DATA_SELECTOR = '//script[@id="item"]';
	private $REVIEW_SELECTOR = '//div[contains(@class, "j-expose__common-reviews__list-item-con")]';
	private $CATEGORY_SELECTOR = '//div[@class="bread-crumb__item"]/a';
	private $SKU_SELECTOR = '//div[@class="product-intro__head-sku"]';
	private $IMAGE_COVER_SELECTOR = '//div[@class="base-product-image"]/div/img/@src';
	private $DESCRIPTION_SELECTOR = '//div[contains(@class, "detail-content")]';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content, $url) {
		$this->url = $url;
		$this->dom = $this->getDomObj($content);

		$content = iconv('UTF-8', 'UTF-8//IGNORE', $content);
		$this->content = preg_replace('/\s+/', ' ', $content);

		/* Create a new XPath object */
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
		$json = $this->getJson($this->content, 'ar __moduleData__ = ', '; var __goog');
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
		if (isset($this->jsonDataArray['data']['root']['fields']['product']['title'])) {
			return $this->jsonDataArray['data']['root']['fields']['product']['title'];
		}
		return '';
	}

	public function getCategories() {
		$categories = array();
		if (isset($this->jsonDataArray['data']['root']['fields']['Breadcrumb']) &&
		$this->jsonDataArray['data']['root']['fields']['Breadcrumb']) {
			foreach ($this->jsonDataArray['data']['root']['fields']['Breadcrumb'] as $category) {
				$categories[] = $category['title'];
			}

			array_pop($categories);
		}
		return array_unique($categories);
	}

	public function getShortDescription() {
		if (isset($this->jsonDataArray['data']['root']['fields']['product']['highlights'])) {
			return $this->jsonDataArray['data']['root']['fields']['product']['highlights'];
		}
		return '';
	}

	public function getDescription() {
		return stripcslashes($this->getJson($this->content, ',"desc":"', '","highlights"'));
	}

	public function getPrice( $index = null) {
		$price = 0;
		
		$index = ( null == $index ) ? $this->getSKU() : $index;

		if (isset($this->jsonDataArray['data']['root']['fields']['skuInfos'][$index])) {
			$prices = $this->jsonDataArray['data']['root']['fields']['skuInfos'][$index];
			if (isset($prices['price']['salePrice']['text'])
				&& $prices['price']['salePrice']['text']
			) {
				$price = $prices['price']['salePrice']['text'];
			} elseif (isset($prices['price']['originalPrice']['text'])
				&& $prices['price']['originalPrice']['text']
			) {
				$price = $prices['price']['originalPrice']['text'];
			}
		}
		
		if (!$price && isset($this->jsonDataArray['data']['root']['fields']['price_grocer'][$index])) {
			$prices = $this->jsonDataArray['data']['root']['fields']['price_grocer'][$index];
			if (isset($prices['data']['price']['priceNumber'])
				&& $prices['data']['price']['priceNumber']
			) {
				$price = $prices['data']['price']['priceNumber'];
			}
		}
		
		if (strpos($price, '.') !== false
			&& strpos($price, ',') !== false
			&& strpos($price, '.') < strpos($price, ',')
		) {
			$price = str_replace(array('.',','), array('','.'), $price);
		} elseif (strpos($price, '.') !== false) {
			$price = str_replace(',', '', $price);
		} else {
			$price = str_replace(',', '.', $price);
		}
		
		return preg_replace('/[^0-9\.]/', '', $price);
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['data']['root']['fields']['primaryKey']['defaultSkuId'])) {
			return $this->jsonDataArray['data']['root']['fields']['primaryKey']['defaultSkuId'];
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['data']['root']['fields']['product']['brand']['name'])) {
			return $this->jsonDataArray['data']['root']['fields']['product']['brand']['name'];
		}
		return '';
	}

	public function getCoverImage() {
		if (isset($this->jsonDataArray['detail']['original_img'])) {
			return 'https:' . $this->jsonDataArray['detail']['original_img'];
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}
		
		if (isset($this->jsonDataArray['data']['root']['fields']['skuInfos'])
			&& $this->jsonDataArray['data']['root']['fields']['skuInfos']) {
			foreach ($this->jsonDataArray['data']['root']['fields']['skuInfos'] as $key => $imgs) {
				$images[$key][] =  'https:' . $imgs['dataLayer']['pdt_photo'];
			}
		}

		$sku = $this->getSKU();
		if (isset($this->jsonDataArray['data']['root']['fields']['skuGalleries'][$sku])
			&& $this->jsonDataArray['data']['root']['fields']['skuGalleries'][$sku]) {
			foreach ($this->jsonDataArray['data']['root']['fields']['skuGalleries'][$sku] as $attrs) {
				$images[$sku][] =  'https:' . $attrs['src'];
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

	public function getAttributes() {
		static $attrGroups = array();
		if ($attrGroups) {
			return $attrGroups;
		}

		if (isset($this->jsonDataArray['data']['root']['fields']['productOption']['skuBase']['properties'])
			&& $this->jsonDataArray['data']['root']['fields']['productOption']['skuBase']['properties']) {
			foreach ($this->jsonDataArray['data']['root']['fields']['productOption']['skuBase']['properties'] as $attrs) {
				$attrValues = array();

				foreach ($attrs['values'] as $attr) {
					if (isset($attr['value'])) {
						foreach ($attr['value'] as $atr) {
							$attrValues[$atr['vid']] = $atr['name'];
						}
					} else {
						$attrValues[$attr['vid']] = $attr['name'];
					}
				}

				$attrGroups[] = array(
					'name' => $attrs['name'],
					'is_color' =>  'Warna' == $attrs['name'] ? 1 : 0,
					'values' => $attrValues
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
		$price = $this->getPrice();

		if (isset($this->jsonDataArray['data']['root']['fields']['productOption']['skuBase']['skus'])
			&& $this->jsonDataArray['data']['root']['fields']['productOption']['skuBase']['skus']) {
			foreach ($this->jsonDataArray['data']['root']['fields']['productOption']['skuBase']['skus'] as $attrs) {
				$attrVals = array();
				$skuId = $attrs['skuId'];
				if (isset($attrs['propPath'])) {
					$proPath = explode(';', $attrs['propPath']);

					foreach ($proPath as $attrText) {
						$attValue = explode(':', $attrText);
						if (isset($attValue[1])) {
							$attrKey = $attValue[1];
							foreach ($attributes as $attribute) {
								if (isset($attribute['values'][$attrKey])) {
									$attrVals[] = array(
										'name' => $attribute['name'],
										'value' => $attribute['values'][$attrKey]
									);
								}
							}
						}
					}
					
					$combPrice = $this->getPrice($skuId);

					$combinations[] = array(
						'sku' => $skuId,
						'upc' => 0,
						'price' => $combPrice ? $combPrice : $price,
						'weight' => 0,
						'image_index' => $skuId,
						'attributes' => $attrVals
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

	public function getFeatures() {
		static $featureGroups = array();

		if ($featureGroups) {
			return $featureGroups;
		}

		$attributes = array();
		if (isset($this->jsonDataArray['data']['root']['fields']['specifications'])
			&& $this->jsonDataArray['data']['root']['fields']['specifications']) {
			$attr = $this->jsonDataArray['data']['root']['fields']['specifications'];
			$featur = array_shift($attr);

			foreach ($featur['features'] as $name => $value) {
				$attributes[] = array(
					'name' => $name,
					'value'=> $value
				);
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

	public function getCustomerReviews( $maxReviews = 0) {
		$reviews = array();

		$maxReviews = $maxReviews > 0 ? $maxReviews : 500;
		if (isset($this->jsonDataArray['data']['root']['fields']['productOption']['skuBase']['skus'])
			&& $this->jsonDataArray['data']['root']['fields']['productOption']['skuBase']['skus']) {
			$attr = $this->jsonDataArray['data']['root']['fields']['productOption']['skuBase']['skus'];
			$item = array_shift($attr);
			$itemId = $item['itemId'];

			$host = parse_url($this->url, PHP_URL_HOST);

			$reviewlink = 'https://' . $host . '/pdp/review/getReviewList?itemId=' . $itemId . '&pageSize=' . (int) $maxReviews;

			$json = $this->getContent($reviewlink);
			if ($json) {
				$reviewData = json_decode($json, true);

				if (isset($reviewData['model']['items']) && $reviewData['model']['items']) {
					foreach ($reviewData['model']['items'] as $review) {
						$reviews[] = array(
							'author' => isset($review['buyerName']) ? $review['buyerName'] : '',
							'title' => isset($review['reviewTitle']) ? $review['reviewTitle'] : '',
							'content' => $review['reviewContent'],
							'rating' => $review['rating'],
							'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['reviewTime']))
						);
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
		
		if (isset($this->jsonDataArray['data']['root']['fields']['review']['reviews']) && $this->jsonDataArray['data']['root']['fields']['review']['reviews']) {
			foreach ($this->jsonDataArray['data']['root']['fields']['review']['reviews'] as $review) {
				
				if (isset($review['reviewContent'])) {
					$reviews[] = array(
						'author' => $review['reviewer'],
						'title' => '',
						'content' => $review['reviewContent'],
						'rating' => $review['rating'],
						'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['reviewTime']))
					);
				}
			}
		}
		
		return $reviews;
	}

	public function getContent( $url) {
		$curl = curl_init();

		curl_setopt_array(
			$curl,
			array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER => array(
					"Origin: $url"
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
}
