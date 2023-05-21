<?php
/**
 * Coupang data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class CoupangParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $images = array();
	private $content;
	private $combColors = array();
	private $host;
	private $jsonDataArray = array();
	private $attrDataArray = array();
	private $SH_DESCRIPTION_SELECTOR = '//ul[@class="prod-description-attribute"]';
	private $DESCRIPTION_SELECTOR = '//div[@id="productDetail"]';
	private $REVIEW_SELECTOR = '//article[contains(@class, "sdp-review__article__list")]';
	private $CATEGORY_SELECTOR = '//ul[@id="breadcrumb"]/li/a';
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
		$json = $this->getJson($this->content, '{ exports.sdp = ', '; exports.sdpIssueTypes');
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
		if (isset($this->jsonDataArray['title'])) {
			return $this->jsonDataArray['title'];
		}
		return '';
	}

	public function getCategories() {
		$categories = array();
		$categories = $this->getValue($this->CATEGORY_SELECTOR);
		return $categories;
	}

	public function getShortDescription() {
		$shortDescriptions = $this->getValue($this->SH_DESCRIPTION_SELECTOR, true);

		return array_shift($shortDescriptions);
	}

	public function getDescription() {
		$descriptions = $this->getValue($this->DESCRIPTION_SELECTOR, true);

		if ($descriptions) {
			$description = array_shift($descriptions);

			return preg_replace_callback(
				'/src="(.*?)"/',
				function ( $link) {
					$link = substr($link[1], 0, 2) == '//' ? 'https:' . $link[1] : $link[1];
					return 'src="' . $link . '"';
				},
				$description
			);
		}

		return '';
	}

	public function getPrice() {
		$price = 0;
		if (isset($this->jsonDataArray['quantityBase'][0]['price']['salePrice'])) {
			$price = str_replace(',', '.', $this->jsonDataArray['quantityBase'][0]['price']['salePrice']);
		}
		return $price;
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['productId'])) {
			return $this->jsonDataArray['productId'];
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['brand'])) {
			return $this->jsonDataArray['brand'];
		}
		return '';
	}

	public function getCoverImage() {
		if (isset($this->jsonDataArray['images'][0]['origin'])) {
			$cImage = 'https:' . $this->jsonDataArray['images'][0]['origin'];
		}
		return $cImage;
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}

		if (isset($this->jsonDataArray['options']['attributeVendorItemMap']) && $this->jsonDataArray['options']['attributeVendorItemMap']) {
			foreach ($this->jsonDataArray['options']['attributeVendorItemMap'] as $keys => $imgs) {
				foreach ($imgs['images'] as $img) {
					$images[$keys][] = 'https:' . $img['origin'];
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

		if (isset($this->jsonDataArray['sellingInfoVo']['sellingInfo'])
			&& $this->jsonDataArray['sellingInfoVo']['sellingInfo']
		) {
			$attributes = array();

			foreach ($this->jsonDataArray['sellingInfoVo']['sellingInfo'] as $attr) {
				list($name, $value) = explode(':', $attr . ':');

				$attributes[] = array(
					'name' => $name,
					'value' => $value
				);
			}

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

		if (isset($this->jsonDataArray['options']['optionRows'])
			&& $this->jsonDataArray['options']['optionRows']) {
			foreach ($this->jsonDataArray['options']['optionRows'] as $comb) {
				$attrVals = array();
				foreach ($comb['attributes'] as $attr) {
					$attrVals[$attr['valueId']] = $attr['name'];
				}

				if ($attrVals) {
					$attrGroups[] = array(
						'name' => $comb['name'],
						'is_color' => ( 'IMAGE' == $comb['displayType'] ) ? 1 : 0,
						'values' => $attrVals
					);
				}
			}
		}
		return $attrGroups;
	}

	public function makeCombination( $data, &$all = array(), $group = array(), $val = null, $i = 0) {
		if (isset($val)) {
			array_push($group, $val);
		}
		if ($i >= count($data)) {
			array_push($all, $group);
		} else {
			foreach ($data[$i] as $v) {
				$this->makeCombination($data, $all, $group, $v, $i + 1);
			}
		}
		return $all;
	}

	public function makeCombinations( $attributes) {
		static $combinations = array();
		if ($combinations) {
			return $combinations;
		}
		$attributeValues = array_map(function ( $a1) {
			return array_map(function ( $a2) {
				return $a2;
			}, $a1);
		}, array_column($attributes, 'values'));

		$atrCombs = $this->makeCombination($attributeValues);
		foreach ($atrCombs as $i => $atrComb) {
			foreach ($atrComb as $attrName) {
				foreach ($attributes as $attribute) {
					if (in_array($attrName, $attribute['values'])) {
						$combinations[$i][] = array(
							'name' => $attribute['name'],
							'value' => $attrName
						);
						break 1;
					}
				}
			}
		}
		return $combinations;
	}

	public function getCombinations() {
		static $combinations = array();
		if ($combinations) {
			return $combinations;
		}

		$price = $this->getPrice();
		$sku = $this->getSKU();
		$attrs = $this->getAttributes();


		if (isset($this->jsonDataArray['options']['attributeVendorItemMap']) && $this->jsonDataArray['options']['attributeVendorItemMap']) {
			foreach ($this->jsonDataArray['options']['attributeVendorItemMap'] as $sku => $comb) {
				$attrIds = explode(':', $sku);

				$attrVals = array();

				foreach ($attrIds as $key => $attrId) {
					$attrVals[] = array(
						'name' => $attrs[$key]['name'],
						'value' => $attrs[$key]['values'][$attrId],
					);
				}
				if (isset($comb['images']) && $comb['images']) {
					foreach ($comb['images'] as $colorImg) {
						$this->images[$sku][] = 'https:' . $colorImg['origin'];
					}
				}

				$comboPrice = 0;
				$prices = array_shift($comb['quantityBase']);
				if (isset($prices['price']['salePrice'])) {
					$comboPrice = $prices['price']['salePrice'];
				}

				$combinations[] = array(
					'sku' => $sku,
					'upc' => 0,
					'price' => $comboPrice ? $comboPrice : $price,
					'weight' => 0,
					'image_index' => $sku,
					'attributes' => $attrVals
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

	public function getCustomerReviews( $maxReviews = 0) {
		$reviews = array();
		$maxReviews = $maxReviews ? $maxReviews : 500;
		$sku = $this->getSKU();

		$reviewsLink = 'https://www.coupang.com/vp/product/reviews?productId=' . $sku . '&page=1&size=' . $maxReviews;

		if ($reviewsLink) {
			$reviewHtml = $this->getContent($reviewsLink);

			if ($reviewHtml) {
				$dom = $this->getDomObj($reviewHtml);
				$xpath = new \DomXPath($dom);

				$reviewArrayObject = $xpath->query($this->REVIEW_SELECTOR);

				if ($reviewArrayObject->length) {
					foreach ($reviewArrayObject as $reviewObject) {
						$content = $xpath->query('.//div[contains(@class, "sdp-review__article__list__review__content")]', $reviewObject);

						if ($content->length) {
							$reviews[] = array(
								'author' => $xpath->query('.//span[contains(@class, "sdp-review__article__list__info__user__name")]', $reviewObject)->item(0)->nodeValue,
								'title' => 0,
								'content' => trim($content->item(0)->nodeValue),
								'rating' => $xpath->query('.//div[contains(@class, "sdp-review__article__list__info__product-info__star-orange")]/@data-rating', $reviewObject)->item(0)->nodeValue,
								'timestamp' => $xpath->query('.//div[@class="sdp-review__article__list__info__product-info__reg-date"]', $reviewObject)->item(0)->nodeValue
							);
						}
					}
				}
			}
		}

		if (!$reviews) {
			$reviewArrayObject = $this->xpath->query($this->REVIEW_SELECTOR);

			if ($reviewArrayObject->length) {
				foreach ($reviewArrayObject as $reviewObject) {
					$content = $this->xpath->query('.//div[contains(@class, "sdp-review__article__list__review__content")]', $reviewObject);

					if ($content->length) {
						$reviews[] = array(
							'author' => $this->xpath->query('.//span[contains(@class, "sdp-review__article__list__info__user__name")]', $reviewObject)->item(0)->nodeValue,
							'title' => 0,
							'content' => trim($content->item(0)->nodeValue),
							'rating' => $this->xpath->query('.//div[contains(@class, "sdp-review__article__list__info__product-info__star-orange")]/@data-rating', $reviewObject)->item(0)->nodeValue,
							'timestamp' => $this->xpath->query('.//div[@class="sdp-review__article__list__info__product-info__reg-date"]', $reviewObject)->item(0)->nodeValue
						);
					}
				}
			}
		}

		return $reviews;
	}
}
