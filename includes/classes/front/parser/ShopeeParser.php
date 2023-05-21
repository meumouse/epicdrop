<?php
/**
 * Shopee data parser class
 *
 * @package: product-importer
 *
 */
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class ShopeeParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $imgColor;
	private $content;
	private $combColors = array();
	private $itemId = 0;
	private $shopId = 0;
	private $host;
	private $jsonDataArray = array();
	private $attrDataArray = array();
	private $REVIEW_SELECTOR = '//ul[@class="comments__list"]/li';
	private $DATA_SELECTOR = '//script[@id="item"]';
	private $PRICE_SELECTOR = '//span[@class="price-block__final-price"]';
	private $LANG_SELECTOR = '//html/@lang';
	private $PRODUCT_ID_SELECTOR = '//div[@data-tag="productOptions"]/div/div/span';
	private $IMAGE_COVER_SELECTOR = '//img[@class="hover-zoom-hero-image"]/@src';
	private $IMAGE_SELECTOR = '//div[@class="sw-slider-kt-mix__wrap"]/div/ul/li/div/img/@src';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content, $url) {
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
		$parse = parse_url($this->url);

		if (stripos($parse['path'], '/product/') !== false) {
			$urlId = explode('/', $parse['path']);
			$urlIds = array_reverse($urlId);
			$this->itemId = $urlIds[0];
			$this->shopId = $urlIds[1];
		} else {
			$urlId = explode('.', $parse['path']);
			$urlIds = array_reverse($urlId);
			$this->itemId = $urlIds[0];
			$this->shopId = $urlIds[1];
		}

		$this->host = $parse['host'];
		$urlData = 'https://' . $this->host . '/api/v4/item/get?itemid=' . $this->itemId . '&shopid=' . $this->shopId;

		$json = $this->getContent($urlData);

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
		if (isset($this->jsonDataArray['data']['name'])) {
			return $this->jsonDataArray['data']['name'];
		}
		return '';
	}

	public function getCategories() {
		$categories = array();
		if (isset($this->jsonDataArray['data']['categories'])  &&  $this->jsonDataArray['data']['categories']) {
			foreach ($this->jsonDataArray['data']['categories'] as $category) {
				$categories[] = $category['display_name'];
			}
		}
		return $categories;
	}

	public function getShortDescription() {
		return '';
	}

	public function getDescription() {
		if (isset($this->jsonDataArray['data']['description'])) {
			return nl2br($this->jsonDataArray['data']['description']);
		}
		return '';
	}

	public function getPrice() {
		$price = 0;
		if (isset($this->jsonDataArray['data']['price'])) {
			$price = $this->convertPrice($this->jsonDataArray['data']['price']);
		}
		return $price;
	}
	
	public function convertPrice( $price) {
		switch ($this->host) {
			case 'shopee.vn':
			case 'shopee.cl':
			case 'shopee.co.id':
			case 'shopee.com.com':
				return $price / 100000000;

			default:
				return $price / 100000;
		}

	}

	public function getSKU() {
		if (isset($this->jsonDataArray['data']['itemid'])) {
			return $this->jsonDataArray['data']['itemid'];
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['data']['brand'])) {
			return $this->jsonDataArray['data']['brand'];
		}
		return '';
	}
	public function getCoverImage() {
		if (isset($this->jsonDataArray['data']['image'])) {
			$cImage = $this->jsonDataArray['data']['image'];
			return 'https://' . str_replace(array('www', 'shopee'), array('', 'cf.shopee'), $this->host) . '/file/' . $cImage;
		}
		return '';
	}
	public function getVideos() {
		$videos = array();
		if (isset($this->jsonDataArray['data']['video_info_list']) && $this->jsonDataArray['data']['video_info_list']) {
			foreach ($this->jsonDataArray['data']['video_info_list'] as $video) {
				$videos[] = $video['default_format']['url'];
			}
		}
		return $videos;
	}
	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}
		$this->getAttributes();
		if ($this->imgColor) {
			foreach ($this->imgColor as $imgId) {
				$images[][0] = 'https://' . str_replace(array('www', 'shopee'), array('', 'cf.shopee'), $this->host) . '/file/' . $imgId;
			}
		}


		if (isset($this->jsonDataArray['data']['images'])
			&& $this->jsonDataArray['data']['images']) {
			foreach ($this->jsonDataArray['data']['images'] as $comb) {
				$images[0][] = 'https://' . str_replace(array('www', 'shopee'), array('', 'cf.shopee'), $this->host) . '/file/' . $comb;
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

		$attributes = array();

		if (isset($this->jsonDataArray['data']['attributes']) && $this->jsonDataArray['data']['attributes']) {
			foreach ($this->jsonDataArray['data']['attributes'] as $attribute) {
				if (isset($attribute['value'])) {
					$attributes[] = array(
						'name' => $attribute['name'],
						'value' => $attribute['value']
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

		if (isset($this->jsonDataArray['data']['tier_variations'])
			&& $this->jsonDataArray['data']['tier_variations']) {
			foreach ($this->jsonDataArray['data']['tier_variations'] as $attr) {
				$colorVr = array('color', 'colour', 'warna', '顏色', 'Màu Sắc', 'สี', 'cor', 'La Couleur',);
				$attrGroups[] = array(
					'name' =>  $attr['name'],
					'is_color' => ( in_array(strtolower($attr['name']), $colorVr) ) ? 1 : 0,
					'values' => $attr['options']
				);

				if (isset($attr['images']) && $attr['images']) {
					$this->imgColor = $attr['images'];
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

		$attributes = $this->getAttributes();

		if (isset($this->jsonDataArray['data']['models'])
			&& $this->jsonDataArray['data']['models']) {
			foreach ($this->jsonDataArray['data']['models'] as $attrs) {
				$attrVals = array();
				$skuId = $attrs['itemid'];
				if (isset($attrs['name']) && $attrs['name']) {
					$imageIndex = 0;

					foreach ($attributes as $attribute) {
						foreach ($attribute['values'] as $key => $vals) {
							if (strpos($attrs['name'], $vals) !== false) {
								if ($attribute['is_color']) {
									$imageIndex = $key;
								}

								$attrVals[] = array(
									'name' => $attribute['name'],
									'value' => $vals
								);
								break ( 1 );
							}
						}
					}

					$combinations[] = array(
						'sku' => $skuId,
						'upc' => 0,
						'price' => $this->convertPrice($attrs['price']),
						'weight' => 0,
						'image_index' => $imageIndex,
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

	public function getCustomerReviews( $maxReviews = 0) {
		$reviews = array();
		$maxReviews = $maxReviews > 0 ? $maxReviews : 500;

		$reviewlink = 'https://' . $this->host . '/api/v2/item/get_ratings?filter=0&flag=1&itemid=' . $this->itemId . '&limit=' . (int) $maxReviews . '&shopid=' . $this->shopId;
		$json = $this->getContent($reviewlink);
		if ($json) {
			$reviewData = json_decode($json, true);
			if (isset($reviewData['data']['ratings'])) {
				foreach ($reviewData['data']['ratings'] as $review) {
					$reviews[] = array(
						'author' => isset($review['author_username']) ? $review['author_username'] : '',
						'title' => '',
						'content' => $review['comment'],
						'rating' => $review['rating_star'],
						'timestamp' => gmdate('Y-m-d H:i:s', $review['mtime'])
					);
				}
			}
		}
		return $reviews;
	}
}
