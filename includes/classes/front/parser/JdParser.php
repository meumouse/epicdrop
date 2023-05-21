<?php
/**
 * Jd data parser class
 *
 * @package: product-importer
 *
 */
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class JdParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $imgColor;
	private $content;
	private $combColors = array();
	private $host;
	private $jsonDataArray = array();
	private $attrDataArray = array();
	private $REVIEW_SELECTOR = '//div[@class="comment-item"]';
	private $COLOR_SELECTOR = '//div[@id="choose-attr-1"]/div[@class="dd"]/div/@data-value';
	private $SKUS_SELECTOR = '//div[@id="choose-attr-1"]/div[@class="dd"]/div/@data-sku';
	private $COLOR_SELECTOR_op = '//div[@id="choose-attr-1"]/div[@class="dt "]';
	private $SIZE_PRICE_SELECTOR = '//div[@id="choose-attr-2"]/div[@class="dd"]/div/@data-value';
	private $SIZE_PRICE_SELECTOR_op = '//div[@id="choose-attr-2"]/div[@class="dt "]';
	private $PRICE_SELECTOR = '//div[contains(@class, "summary-price")]/div/span/span';
	private $TITLE_SELECTOR = '//div[@class="sku-name"]';
	private $CATEGORY_SELECTOR = '//div[contains(@class, "crumb")]/div/a';
	private $DESCRIPTION_SELECTOR = '//ul[contains(@class, "parameter2")]';
	private $WEIGHT_SELECTOR = '//ul[contains(@class, "parameter2")]/li';
	private $DESCRIPTION1_SELECTOR = '//div[@class="ssd-module-wrap"]';
	private $DESCRIPTION2_SELECTOR = '//div[@id="J-detail-content"]';
	private $BRAND_SELECTOR = '//ul[@id="parameter-brand"]/li/@title';
	private $FEATURE_SELECTOR = '//div[@class="Ptable"]/div[@class="Ptable-item"]';
	private $IMAGE_SELECTOR = '//div[@id="spec-list"]/ul/li/img/@src';
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
			return mb_convert_encoding($response, 'UTF-8', 'GB2312');
		}
	}

	private function setJsonData() {
		$json = $this->getJson($this->content, 'product: ', ' }; try');
		$json = 'product: ' . $json;

		$json = preg_replace('/(\w+):/i', '"\1":', $json);
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
		$title = $this->getValue($this->TITLE_SELECTOR);
		return array_shift($title);
	}

	public function getCategories() {
		$cate = $this->getValue($this->CATEGORY_SELECTOR);
		if ($cate) {
			$cate;
		}
		return $cate;
	}

	public function getShortDescription() {
		return '';
	}

	public function getDescription() {
		$description = '';
		$descript = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		if ($descript) {
			$description .= array_shift($descript);
		}
		$descript1 = $this->getValue($this->DESCRIPTION1_SELECTOR, true);
		if ($descript1) {
			$description .= array_shift($descript1);
		}
		$descript2 = $this->getValue($this->DESCRIPTION2_SELECTOR, true);
		if ($descript2) {
			$description .= array_shift($descript2);
		}
		if ($description) {
			return str_replace('url(//', 'url(https://', $description);
		}
		return '';
	}

	public function getPrice() {
		$price = 0;
		$price = $this->getValue($this->PRICE_SELECTOR);
		if ($price) {
			return array_pop($price);
		}
		return $price;
	}

	public function getSKU() {
		$skus = explode('/', $this->url);
		$sku = array_pop($skus);
		$sku = explode('.', $sku);
		$sku = array_shift($sku);
		if ($sku) {
			return $sku;
		}
		return '';
	}

	public function getBrand() {
		$brand = $this->getValue($this->BRAND_SELECTOR);
		if ($brand) {
			return array_shift($brand);
		}
	}
	public function getWeight() {
		$desList = $this->getValue($this->WEIGHT_SELECTOR);
		$weight = array();
		if ($desList) {
			foreach ($desList as $weigh) {
				if (strpos($weigh, '商品毛重') !==false) {
					$weight = array(
						'unit' => 'g',
						'value' => preg_replace('/[^0-9.]/', '', $weigh)
					);
				}
			}
		}
		return $weight;
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}
		$imgs = $this->getValue($this->IMAGE_SELECTOR);

		if ($imgs) {
			foreach ($imgs as $img) {
				$images[0][] = str_replace(array('n5', 's54x54_jfs'), array('n0', 'jfs'), 'https:' . $img);
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

		$features = $this->xpath->query($this->FEATURE_SELECTOR);

		if ($features->length) {
			foreach ($features as $feature) {
				$attributes = array();
				$fAttrs = $this->xpath->query('.//dl/dl', $feature);

				if ($fAttrs->length) {
					foreach ($fAttrs as $fAttr) {
						$attributes[] = array(
							'name' => trim($this->xpath->query('.//dt', $fAttr)->item(0)->nodeValue),
							'value' => trim($this->xpath->query('.//dd', $fAttr)->item(0)->nodeValue),
						);
					}

					$featureGroups[] = array(
						'name' => $this->xpath->query('.//h3', $feature)->item(0)->nodeValue,
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

		$coloroption = $this->getValue($this->COLOR_SELECTOR_op);
		$colorValues = $this->getValue($this->COLOR_SELECTOR);


		if ($coloroption) {
			$colorName = array_shift($coloroption);
		}

		$sizeoption = $this->getValue($this->SIZE_PRICE_SELECTOR_op);
		$attrValues = $this->getValue($this->SIZE_PRICE_SELECTOR);

		if ($sizeoption) {
			$attrName = array_shift($sizeoption);
		}

		if ($colorValues) {
			array_shift($colorValues);
			$attrGroups[] = array(
				'name' => $colorName,
				'is_color' => 1,
				'values' => $colorValues
			);
		}

		if ($attrValues) {
			array_shift($attrValues);
			$attrGroups[] = array(
				'name' => $attrName,
				'is_color' => 0,
				'values' => $attrValues
			);
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
		$weight = $this->getWeight();
		$attrs = $this->getAttributes();

		$combs = $this->makeCombinations($attrs);

		if ($combs) {
			foreach ($combs as $attrVals) {
				$imageIndex = 0;

				$combinations[] = array(
					'sku' => $sku,
					'upc' => 0,
					'price' => $price,
					'weight' => $weight,
					'image_index' => $imageIndex,
					'attributes' => $attrVals
				);
			}
		}

		return $combinations;
	}

	public function getMetaTitle() {
		return $this->getTitle();

		/*$metatitle = $this->getValue($this->META_TITLE_SELECTOR);
		return array_shift($metatitle);*/
	}

	public function getMetaDecription() {
		$metadescription = $this->getValue($this->META_DESCRIPTION_SELECTOR);
		return array_shift($metadescription);
	}

	public function getMetaKeywords() {
		$metakeywords = $this->getValue($this->META_KEYWORDS_SELECTOR);
		return array_shift($metakeywords);
	}

	public function getCustomerReviews( $maxReviews = 0, $reviews = array(), $page = 0) {
		$pdId = $this->getSKU();
		if (!$reviews) {
			$this->reviewLink = 'https://club.jd.com/comment/productPageComments.action?callback=fetchJSON_comment98&productId=' . $pdId . '&score=0&sortType=5&pageSize=10';
		}

		$reviewLink = $this->reviewLink;
		$reviewLink .= '&page=' . $page;

		if ($reviewLink) {
			$json = $this->getContent($reviewLink);

			if ($json) {
				$jsons = str_replace(array('fetchJSON_comment98(', ');'), array('', ''), $json);

				$jsons = iconv('UTF-8', 'UTF-8//IGNORE', $jsons);
				$reviewData = json_decode($jsons, true);

				if (isset($reviewData['comments'])) {
					$isMaxReached = false;

					foreach ($reviewData['comments'] as $commentInfo) {
						$reviews[] = array(
							'author' => $commentInfo['nickname'],
							'title' => isset($commentInfo['title']) ? $commentInfo['title'] : '',
							'content' => $commentInfo['content'],
							'rating' => (int) $commentInfo['score'],
							'timestamp' => gmdate('Y-m-d H:i:s', strtotime($commentInfo['creationTime']))
						);

						if (0 < $maxReviews && count($reviews) >= $maxReviews) {
							$isMaxReached = true;
							break;
						}
					}

					if (count($reviewData['comments']) > 10 && false == $isMaxReached) {
						$this->getCustomerReviews($maxReviews, $reviews, ++$page);
					}
				}
			}
		}

		return $reviews;
	}
}
