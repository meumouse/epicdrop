<?php
/**
 * Tiki data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class TikiParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $TITLE_SELECTOR = '//h1[@class="product-name"]/meta[@itemprop="name"]/@content';
	private $CATEGORY_SELECTOR = '//p[@itemprop="itemListElement"]/a/@title';
	private $DESCRIPTION_SELECTOR = '//div[@id="desc"]';
	private $PRICE_SELECTOR = '//meta[@id="dyn_meta_price"]/@content';
	private $SKU_SELECTOR = '//h1[@class="product-name"]/meta[@itemprop="sku"]/@content';
	private $BRAND_SELECTOR = '//span[@class="product-brand"]';
	private $COVER_IMG_SELECTOR = '//img[@itemprop="image"]/@src';
	private $VARIANT_SELECTOR = '//div[@id="fp-couleur"]/a/@href';
	private $IMAGE_SELECTOR = '//ul[@id="gallery-3su"]/li/a/@href';
	private $ATTRIBUTE_SELECTOR = '//div[@id="fp-couleur"]';
	private $FEATURES = '//div[@class="caracteristique-box"]/div';
	private $REVIEW_SELECTOR = '//li[@class="avis-comments-item"]';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content, $url) {
		$this->content = preg_replace('/\s+/', ' ', $content);
		$this->dom = $this->getDomObj($content);
		$this->url = $url;
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
		$json = $this->getJson($this->content, '"initialState":', ',"initialProps"');
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = json_decode($json, true);
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
		if (isset($this->jsonDataArray['desktop']['product']['data']['name'])) {
			return $this->jsonDataArray['desktop']['product']['data']['name'];
		}
		return '';
	}

	public function getCategories() {
		$categories = array();
		if (isset($this->jsonDataArray['desktop']['product']['data']['breadcrumbs']) && $this->jsonDataArray['desktop']['product']['data']['breadcrumbs']) {
			foreach ($this->jsonDataArray['desktop']['product']['data']['breadcrumbs'] as $categary) {
				$categories[] = $categary['name'];
			}
		}
		return $categories;
	}

	public function getShortDescription() {
		if (isset($this->jsonDataArray['desktop']['product']['data']['short_description'])) {
			return $this->jsonDataArray['desktop']['product']['data']['short_description'];
		}
		return '';
	}

	public function getDescription() {
		if (isset($this->jsonDataArray['desktop']['product']['data']['description'])) {
			return $this->jsonDataArray['desktop']['product']['data']['description'];
		}
		return '';
	}

	public function getPrice() {
		$price = 0;
		if (isset($this->jsonDataArray['desktop']['product']['data']['price'])) {
			$price = $this->jsonDataArray['desktop']['product']['data']['price'];
		}
		return $price;
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['desktop']['product']['data']['sku'])) {
			return $this->jsonDataArray['desktop']['product']['data']['sku'];
		}
		return '';
	}

	public function getUPC() {
		if (isset($this->jsonDataArray['desktop']['product']['data']['id'])) {
			return $this->jsonDataArray['desktop']['product']['data']['id'];
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['desktop']['product']['data']['brand']['name'])) {
			return $this->jsonDataArray['desktop']['product']['data']['brand']['name'];
		}
		return '';
	}

	public function getVideos() {
		$videos = array();
		if (isset($this->jsonDataArray['desktop']['product']['data']['video_url'])) {
			$videos[] = $this->jsonDataArray['desktop']['product']['data']['video_url'];
		}
		return $videos;
	}

	public function getCoverImage() {
		if (isset($this->jsonDataArray['desktop']['product']['data']['thumbnail_url'])) {
			return $this->jsonDataArray['desktop']['product']['data']['thumbnail_url'];
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}
		$img = $this->getValue($this->IMAGE_SELECTOR);

		if (isset($this->jsonDataArray['desktop']['product']['data']['configurable_products'])
			&& $this->jsonDataArray['desktop']['product']['data']['configurable_products']) {
			foreach ($this->jsonDataArray['desktop']['product']['data']['configurable_products'] as $imgs) {
				foreach ($imgs['images'] as $imgses) {
					$images[$imgs['id']][] = $imgses['large_url'];
				}
			}
		} elseif (isset($this->jsonDataArray['desktop']['product']['data']['images'])
			&& $this->jsonDataArray['desktop']['product']['data']['images']) {
			foreach ($this->jsonDataArray['desktop']['product']['data']['images'] as $imgs) {
				$images[0][] = $imgs['large_url'];
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

		if (isset($this->jsonDataArray['desktop']['product']['data']['specifications'])
			&& $this->jsonDataArray['desktop']['product']['data']['specifications']) {
			foreach ($this->jsonDataArray['desktop']['product']['data']['specifications'] as $specifics) {
				foreach ($specifics['attributes'] as $feature) {
					$attributes[] = array(
						'name' => $feature['name'],
						'value' => $feature['value']
					);
				}
				if ($attributes) {
					$featureGroups[] = array(
						'name' => $specifics['name'],
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
		if (isset($this->jsonDataArray['desktop']['product']['data']['configurable_options'])
			&& $this->jsonDataArray['desktop']['product']['data']['configurable_options']) {
			foreach ($this->jsonDataArray['desktop']['product']['data']['configurable_options'] as $attri) {
				$attributes = array();
				foreach ($attri['values'] as $attrs) {
					$attributes[] = $attrs['label'];
				}
				if ($attributes) {
					$attrGroups[$attri['code']] = array(
						'name' => $attri['name'],
						'is_color' => ( stripos($attri['name'], 'Màu') !== false ) ? 1 : 0,
						'values' => $attributes
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
		$upc = $this->getUPC();
		$attri = $this->getAttributes();

		if (isset($this->jsonDataArray['desktop']['product']['data']['configurable_products'])
			&& $this->jsonDataArray['desktop']['product']['data']['configurable_products']) {
			foreach ($this->jsonDataArray['desktop']['product']['data']['configurable_products'] as $combi) {
				$attributes = array();
				foreach ($attri as $key => $attrVals) {
					if (isset($combi[$key])) {
						$attributes[] = array(
							'name' => $attrVals['name'],
							'value' => $combi[$key]
						);
					}
				}
				$combinations[] = array(
					'sku' => isset($combi['sku']) ? $combi['sku'] : $sku,
					'upc' => isset($combi['id']) ? $combi['id'] : $upc,
					'price' => isset($combi['price']) ? $combi['price'] : $price,
					'weight' => 0,
					'image_index' => $combi['id'],
					'attributes' => $attributes
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

	public function getCustomerReviews( $maxReviews = 0, &$reviews = array(), $page = 1) {
		if (!$reviews) {
			$upc = $this->getUPC();
			$this->reviewLink = 'https://tiki.vn/api/v2/reviews?limit=6&include=comments,contribute_info&spid=' . $upc . '&product_id=' . $upc;
		}
		$reviewLink = $this->reviewLink;
		$reviewLink .= '&page=' . $page;

		if ($reviewLink) {
			$reviewJson = $this->getContent($reviewLink);
			if ($reviewJson) {
				$reviewArrayObject = json_decode($reviewJson, true);

				if (isset($reviewArrayObject['data']) && $reviewArrayObject['data']) {
					$isMaxReached = false;
					foreach ($reviewArrayObject['data'] as $reviewObject) {
						if ($reviewObject['created_by']['name']) {
							$reviews[] = array(
								'author' => $reviewObject['created_by']['name'],
								'title' => isset($reviewObject['title']) ? $reviewObject['title'] : '',
								'content' => isset($reviewObject['content']) ? $reviewObject['content'] : '',
								'rating' =>$reviewObject['rating'],
								'timestamp' => gmdate('Y-m-d H:i:s', strtotime($reviewObject['created_by']['created_time']))
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
