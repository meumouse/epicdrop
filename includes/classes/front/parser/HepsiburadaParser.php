<?php
/**
 * Hepsiburada data parser class
 *
 * @package: product-importer
 *
 */
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class HepsiburadaParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $content;
	private $combColors = array();
	private $jsonDataArray = array();
	private $attrDataArray = array();
	private $attributes = array();
	private $REVIEW_SELECTOR = '//ul[@class="comments__list"]/li';
	private $DATA_SELECTOR = '//script[@id="item"]';
	private $DESCRIPTION_SELECTOR = '//div[@id="tabProductDesc"]';
	private $SITE_LANG_SELECTOR = '//html/@lang';
	private $IMAGE_COVER_SELECTOR = '//img[@class="hover-zoom-hero-image"]/@src';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content) {
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
		$json = $this->getJson($this->content, 'utagData = ', '; var utagObject');
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = json_decode($json, true);
		}
		
		$json = $this->getJson($this->content, '{"data":{"keyFeatures":', ' } })</script>');
		if ($json) {
			$json = '{"data":{"keyFeatures":' . $json;
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = array_merge($this->jsonDataArray, json_decode($json, true));
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
		if (isset($this->jsonDataArray['product_name_array'])) {
			return $this->jsonDataArray['product_name_array'];
		}
		return '';
	}

	public function getCategories() {
		if (isset($this->jsonDataArray['category_name_hierarchy'])) {
			return explode('>', $this->jsonDataArray['category_name_hierarchy']);
		}
		return '';
	}

	public function getShortDescription() {
		return '';
	}

	public function getDescription() {
		$description = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		if ($description) {
			return array_shift($description);
		}
		return '';
	}

	public function getPrice() {
		if (isset($this->jsonDataArray['product_prices'][0])) {
			return $this->jsonDataArray['product_prices'][0];
		}
		return '';
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['product_skus'][0])) {
			return $this->jsonDataArray['product_skus'][0];
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['product_brand'])) {
			return $this->jsonDataArray['product_brand'];
		}
		return '';
	}

	public function getCoverImage() {
		if (isset($this->jsonDataArray['data']['selectedVariant']['media'][0]['linkFormat'])) {
			return str_replace('{size}', '1500', $this->jsonDataArray['data']['selectedVariant']['media'][0]['linkFormat']);
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}
		$sku = $this->getSKU();
		if (isset($this->jsonDataArray['data']['selectedVariant']['media']) && $this->jsonDataArray['data']['selectedVariant']['media']) {
			foreach ($this->jsonDataArray['data']['selectedVariant']['media'] as $totalImg) {
				$images[$sku][] = str_replace('{size}', '1500', $totalImg['linkFormat']);
			}
		}

		$this->getCombinations();

		$images = array_merge($this->images, $images);

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
		return array();
	}

	public function getAttributes() {
		static $attrGroups = array();

		if ($attrGroups) {
			return $attrGroups;
		}
		$this->getCombinations();

		$attrGroups = array_map(
			function ( $attr) {
				$attr['values'] = array_unique($attr['values']);
				return $attr;
			},
			$this->attributes
		);
		return $attrGroups;
	}

	public function getCombinations() {
		static $combinations = array();
		if ($combinations) {
			return $combinations;
		}

		$price = $this->getPrice();

		if (isset($this->jsonDataArray['data']['product']['variantList'])
			&& $this->jsonDataArray['data']['product']['variantList']) {
			foreach ($this->jsonDataArray['data']['product']['variantList'] as $variantList) {
				if (isset($variantList['media']) && $variantList['media']) {
					foreach ($variantList['media'] as $media) {
						if (isset($media['linkFormat']) && $media['linkFormat']) {
							$this->images[$variantList['sku']][] = str_replace('{size}', '1500', $media['linkFormat']);
						}
					}
				}


				$attrVals = array();

				if (isset($variantList['variantProperty']) && $variantList['variantProperty']) {
					foreach ($variantList['variantProperty'] as $attr) {
						$attrVals[] = array(
							'name' => $attr['name'],
							'value' => $attr['value']
						);

						$key = base64_encode($attr['name']);

						if (!isset($this->attributes[$key])) {
							$this->attributes[$key] = array(
								'name' => $attr['name'],
								'is_color' => ( strpos(strtolower($attr['name']), 'renk') !== false ) ? 1 : 0,
								'values' => array()

							);
						}

						$this->attributes[$key]['values'][] = $attr['value'];
					}
				}

				if ($attrVals) {
					$combPrice = 0;
					if (isset($variantList['variantListing'][0]['finalPriceOnSale'])) {
						$combPrice = $variantList['variantListing'][0]['finalPriceOnSale'];
					}

					$combinations[] = array(
						'sku' => $variantList['sku'],
						'upc' => 0,
						'price' => ( $combPrice ? $combPrice : $price ),
						'weight' => 0,
						'image_index' => $variantList['sku'],
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


	public function getCustomerReviews2() {
		$reviews = array();

		if (isset($this->jsonDataArray['data']['userReviews']['data']['approvedUserContent']['approvedUserContentList']) && $this->jsonDataArray['data']['userReviews']['data']['approvedUserContent']['approvedUserContentList']) {
			foreach ($this->jsonDataArray['data']['userReviews']['data']['approvedUserContent']['approvedUserContentList'] as $review) {
				$reviews[] = array(
					'author' => $review['customer']['name'],
					'title' => '',
					'content' => $review['review']['content'],
					'rating' => $review['star'],
					'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['createdAt']))
				);
			}
		}

		return $reviews;
	}
	public function getCustomerReviews( $maxReviews = 100) {
		$reviews = array();
		$sku = $this->getSKU();

		$reviewLink = 'https://user-content-gw-hermes.hepsiburada.com/queryapi/v2/ApprovedUserContents?skuList=' . $sku . '&from=1&size=' . $maxReviews;

		$json = $this->getContent($reviewLink);
		if ($json) {
			$reviewData = json_decode($json, true);

			if (isset($reviewData['data']['approvedUserContent']['approvedUserContentList']) && $reviewData['data']['approvedUserContent']['approvedUserContentList']) {
				foreach ($reviewData['data']['approvedUserContent']['approvedUserContentList'] as $review) {
					$reviews[] = array(
						'author' => $review['customer']['name'],
						'title' => '',
						'content' => $review['review']['content'],
						'rating' => $review['star'],
						'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['createdAt']))
					);
				}
			}
		}
		if (!$reviews) {
			return $this->getCustomerReviews2();
		}
		return $reviews;
	}

	public function getContent( $url) {
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

		$headers = array(
		   "Origin: $url",
		);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

		$resp = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);

		if ($err) {
			return false;
		}

		return $resp;
	}
}
