<?php
/**
 * Houzz data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class HouzzParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $host;
	private $jsonDataArray = array();
	private $FEATURE_NAME = '//li[@data-component="product_specs"]/span';
	private $IMAGE_SELECTOR = '//div[@class="alt-images__thumb "]/img/@src';
	private $ATTR_NAME = '//p[contains(@class, "product-variations-theme-option-label")]/span';
	private $REVIEW_SELECTOR = '//ul[@class="reviews-list__list"]/li';
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

	public function getContent( $url, $postVar = array()) {
		$curl = curl_init();
		$curlopts = array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_HTTPHEADER => array(
				'cache-control: no-cache',
				"Origin: $url",
				'x-jsid: 1ODShha17gPatGdIey4madREH4XK7wYuD3T2Ju4ECxSjghdEyLn4eeS07JSvTwHsBOcrJMz1E1j/HAZwezK20Yv9t4sLhC3W50c=',
				'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36',
				'x-origin-host: www.houzz.com',
				'x-reqid: ae1e4d92cb9b611f4b4af1ac60a8e825',
				'etag: W/"744e-BQdxj4Rmnph0bcCnxV8oNEeNZeE"',
				'x-request-id: 7bd4af18-c82c-4e15-b5a8-2df0a51845a4'

			),
		);
		if ($postVar) {
			$curlopts[CURLOPT_POST] = true;
			$curlopts[CURLOPT_POSTFIELDS] = http_build_query($postVar);
		} else {
			$curlopts[CURLOPT_CUSTOMREQUEST] = 'GET';
		}
		curl_setopt_array(
			$curl,
			$curlopts
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

	public function getContentJson( $content) {
		$response = array();
		$json = $this->getJson($content, '<script type="application/ld+json">', '</script>');
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$response = json_decode($json, true);
		}

		$json = $this->getJson($content, '="application/json">', '</script>');
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$response = array_merge($response, json_decode($json, true));
		}
		return $response;
	}

	private function setJsonData() {
		$this->jsonDataArray = $this->getContentJson($this->content);

		$this->jsonDataArray['products'] = array();
		$productId = $this->mainProductId();

		if (isset($this->jsonDataArray['data']['stores']['data']['ProductVariationsStore']['data'][$productId]['variationsMap'])
			&& $this->jsonDataArray['data']['stores']['data']['ProductVariationsStore']['data'][$productId]['variationsMap']) {
			foreach ($this->jsonDataArray['data']['stores']['data']['ProductVariationsStore']['data'][$productId]['variationProducts'] as $key => $linkObject) {
				if (isset($linkObject['url'])) {
					$mainUrl = $linkObject['url'];
				}
				$dataHtml = $this->getContent($mainUrl);

				if ($dataHtml) {
					$jsonDataArray = $this->getContentJson($dataHtml);
					if ($jsonDataArray) {
						$this->jsonDataArray['products'][$key] = $jsonDataArray;
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
		if (isset($this->jsonDataArray[0]['name'])) {
			return $this->jsonDataArray[0]['name'];
		}
		return '';
	}

	public function getCategories() {
		$categoryes = array();
		if (isset($this->jsonDataArray['data']['stores']['data']['ViewProductBreadcrumbStore']['data']['breadcrumbs']['options'])
			&& $this->jsonDataArray['data']['stores']['data']['ViewProductBreadcrumbStore']['data']['breadcrumbs']['options']) {
			foreach ($this->jsonDataArray['data']['stores']['data']['ViewProductBreadcrumbStore']['data']['breadcrumbs']['options'] as $category) {
				$categoryes[] = $category['label'];
			}
		}
		return $categoryes;
	}
	public function mainProductId( $jsonArray = array()) {
		if (!$jsonArray) {
			$jsonArray = $this->jsonDataArray;
		}
		if (isset($jsonArray['data']['pageContentData']['spaceId'])) {
			return $jsonArray['data']['pageContentData']['spaceId'];
		}
		return '';
	}

	public function getDescription() {
		$description = '';
		$productId = $this->mainProductId();
		if (isset($this->jsonDataArray['data']['stores']['data']['ProductDataStore']['data'][$productId]['description'])) {
			$description = $this->jsonDataArray['data']['stores']['data']['ProductDataStore']['data'][$productId]['description'];
		}
		return $description;
	}

	public function getShortDescription() {
		if (isset($this->jsonDataArray[0]['description'])) {
			return $this->jsonDataArray[0]['description'];
		}
		return '';
	}

	public function getPrice( $jsonArray = array()) {
		if (!$jsonArray) {
			$jsonArray = $this->jsonDataArray;
		}
		if (isset($jsonArray[0]['offers']['price'])) {
			return $jsonArray[0]['offers']['price'];
		}
		return '';
	}

	public function getSKU( $jsonArray = array()) {
		if (!$jsonArray) {
			$jsonArray = $this->jsonDataArray;
		}
		if (isset($jsonArray[0]['sku'])) {
			return $jsonArray[0]['sku'];
		}
		return '';
	}

	public function getWeight( $jsonArray = array()) {
		$weight = array();
		if (!$jsonArray) {
			$jsonArray = $this->jsonDataArray;
		}
		if (isset($jsonArray[0]['weight']['description'])) {
			$weight = array(
				'unit' => preg_replace('/[^a-zA-Z]/', '', $jsonArray[0]['weight']['description']),
				'value' => preg_replace('/[^0-9.]/', '', $jsonArray[0]['weight']['description'])
			);
		}
		return $weight;
	}

	public function getUPC( $jsonArray = array()) {
		if (!$jsonArray) {
			$jsonArray = $this->jsonDataArray;
		}
		if (isset($jsonArray[0]['productID'])) {
			return $jsonArray[0]['productID'];
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray[0]['manufacturer']['name'])) {
			return $this->jsonDataArray[0]['manufacturer']['name'];
		}
		return '';
	}

	public function getDimension() {
		static $dimensions = array();
		$length = 0;
		$width = 0;
		$height = 0;
		if (isset($this->jsonDataArray[0]['depth']['description'])) {
			$length = $this->jsonDataArray[0]['depth']['description'];
		}
		if (isset($this->jsonDataArray[0]['width']['description'])) {
			$width = $this->jsonDataArray[0]['width']['description'];
		}
		if (isset($this->jsonDataArray[0]['height']['description'])) {
			$height = $this->jsonDataArray[0]['height']['description'];
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

	public function getCoverImage() {
		if (isset($this->jsonDataArray[0]['image'])) {
			return $this->jsonDataArray[0]['image'];
		}
		return '';
	}

	public function getImages() {
		static $images = array();
		if ($images) {
			return $images;
		}
		$productId = $this->mainProductId();
		$pageImage = $this->getValue($this->IMAGE_SELECTOR);

		if ($this->jsonDataArray['products']) {
			foreach ($this->jsonDataArray['products'] as $key => $product) {
				$productId = $this->mainProductId($product);
				if (isset($product['data']['stores']['data']['ProductDataStore']['data'][$productId]['imageIds']) &&
				$product['data']['stores']['data']['ProductDataStore']['data'][$productId]['imageIds']) {
					foreach ($product['data']['stores']['data']['ProductDataStore']['data'][$productId]['imageIds'] as $imgs) {
						$images[$key][] = 'https://st.hzcdn.com/simgs/' . $imgs . '_9-2261/home-design.jpg';
					}
				} elseif (isset($this->jsonDataArray['data']['stores']['data']['ProductDataStore']['data'][$productId]['imageIds'])) {
					foreach ($this->jsonDataArray['data']['stores']['data']['ProductDataStore']['data'][$productId]['imageIds'] as $imgs) {
						$images[0][] = 'https://st.hzcdn.com/simgs/' . $imgs . '_9-2261/home-design.jpg';
					}
				}
			}
		} elseif ($pageImage) {
			$images[0] = str_replace(array('w44', 'h54'), array('w1000', 'h1000'), $pageImage);
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
		$featureNM = $this->getValue($this->FEATURE_NAME);

		if ($featureNM) {
			$featureNM = array_shift($featureNM);
		}
		$productId = $this->mainProductId();
		$attributes = array();
		if (isset($this->jsonDataArray['data']['stores']['data']['ProductDataStore']['data'][$productId]['productSpec']['productSpecItems'])
			&& $this->jsonDataArray['data']['stores']['data']['ProductDataStore']['data'][$productId]['productSpec']['productSpecItems']) {
			foreach ($this->jsonDataArray['data']['stores']['data']['ProductDataStore']['data'][$productId]['productSpec']['productSpecItems'] as $groupFeature) {
				$attributes[] = array(
					'name' => $groupFeature['name'],
					'value' => preg_replace('/\s+/', ' ', $groupFeature['value'])
				);
			}
			if ($attributes) {
				$featureGroups[] = array(
					'name' => $featureNM,
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
		$productId = $this->mainProductId();
		$attrName = $this->getValue($this->ATTR_NAME);

		if (isset($this->jsonDataArray['data']['stores']['data']['ProductVariationsStore']['data'][$productId]['variationsMap'])
			&& $this->jsonDataArray['data']['stores']['data']['ProductVariationsStore']['data'][$productId]['variationsMap']) {
			$i = 0;
			foreach ($this->jsonDataArray['data']['stores']['data']['ProductVariationsStore']['data'][$productId]['variationsMap'] as $key => $groupName) {
				$attrValue = array();

				foreach ($groupName as $attrVl => $attributes) {
					$attrValue[] = preg_replace('/\s+/', ' ', $attrVl);
				}

				$name = explode(':', $attrName[$i]);
				$name = array_shift($name);
				$name = preg_replace('/[^a-zA-Z]/', '', $name);

				$attrGroups[] = array(
					'name' => $name,
					'is_color' => ( 'c' == $key ) ? 1 : 0,
					'values' => $attrValue
				);
				$i++;
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
		$productId = $this->mainProductId();
		$attrName = $this->getValue($this->ATTR_NAME);

		if ($this->jsonDataArray['products']) {
			foreach ($this->jsonDataArray['products'] as $key => $product) {
				$productId = $this->mainProductId($product);

				if (isset($product['data']['stores']['data']['ProductVariationsStore']['data'][$productId]['currentVariations'])
					&& $product['data']['stores']['data']['ProductVariationsStore']['data'][$productId]['currentVariations']) {
					$attrs = array();
					foreach ($product['data']['stores']['data']['ProductVariationsStore']['data'][$productId]['currentVariations'] as $keys => $groupComb) {
						$name = '';
						foreach ($attributes as $attribute) {
							if (in_array($groupComb, $attribute['values'])) {
								$name = $attribute['name'];
								break( 1 );
							}
						}
						$attrs[] = array(
							'name' => $name,
							'value' => $groupComb
						);
					}
					if ($attrs) {
						$combinations[] = array(
							'sku' => $this->getSKU($product),
							'upc' => $this->getUPC($product),
							'price' => $this->getPrice($product),
							'weight' => $this->getWeight($product),
							'image_index' => $key,
							'attributes' => $attrs
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

	public function getCustomerReviews() {
		$reviews = array();

		$reviewArrayObject = $this->xpath->query($this->REVIEW_SELECTOR);

		if ($reviewArrayObject->length) {
			foreach ($reviewArrayObject as $reviewObject) {
				$content = $this->xpath->query('.//p[@class="review-item__comment"]', $reviewObject);

				if ($content->length) {
					$stars = $this->xpath->query('.//span[contains(@class, "star-icon")]', $reviewObject);
					if ($stars->length) {
						$stars = $stars->length;
					}
					$author = $this->xpath->query('.//div[@class="review-item__user__name"]/span', $reviewObject);
					if ($author->length) {
						$reviews[] = array(
							'author' => $author->item(0)->nodeValue,
							'title' => @$this->xpath->query('.//div[@class="review-item__title"]', $reviewObject)->item(0)->nodeValue,
							'content' => trim($content->item(0)->nodeValue),
							'rating' => $stars,
							'timestamp' => preg_replace('/[^0-9\/]/', '', $this->xpath->query('.//span[@class="review-item__user__date"]', $reviewObject)->item(0)->nodeValue)
						);
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

		if (isset($this->jsonDataArray['data']['stores']['data']['ProductReviewsStore']['data']['productReviews'])
			&& $this->jsonDataArray['data']['stores']['data']['ProductReviewsStore']['data']['productReviews']) {
			foreach ($this->jsonDataArray['data']['stores']['data']['ProductReviewsStore']['data']['productReviews'] as $review) {
				if (isset($review['comment'])) {
					$reviews[] = array(
						'author' => $review['user']['displayName'],
						'title' => $review['title'],
						'content' => $review['comment'],
						'rating' => $review['rating'],
						'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['created']))
					);
				}
			}
		}
		return $reviews;
	}
}
