<?php
/**
 * Emag data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class EmagParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $imagses = array();
	private $content;
	private $host;
	private $jsonDataArray = array();
	private $attrDataArray = array();
	private $VARIANT_SELECTOR = '//ul[@id="product_colors"]/li/a/@href';
	private $IMAGE_VR_SELECTOR = '//div[contains(@class, "thumbnail-wrapper")]/a/img/@src';
	private $PRICE_SELECTOR = '//div[contains(@class, "product-page-pricing")]/div/div/div/p[contains(@class, "product-new-price")]';
	private $SIZE_PRICE_SELECTOR_nm = '//span[@class="sku-label-name"]';
	private $FEATURE_SELECTOR = '//div[@class="pad-top-sm"]';
	private $DESCRIPTION_SELECTOR = '//div[@class="product-page-description-text"]';
	private $DESCRIPTION_SELECTOR1 = '//div[@class="unf-info-context"]';
	private $REVIEW_SELECTOR = '//div[contains(@class, "prod-comment-section")]/div/div/ul/li';
	private $REVIEW_SELECTOR1 = '//div[@class="panelContent"]/div/ul/li';
	private $ATTRIBUTES_SELECTOR = '//div[@class="product-highlight "]';
	private $VIDEOS_SELECTOR = '//div[@data-ph-id="video"]/a/@data-videos';
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
		$json = $this->getJson($this->content, '<script type="application/ld+json">', '</script>', 1);

		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = json_decode($json, true);
		}
		$json = $this->getJson($this->content, '<script type="application/ld+json">', '</script>', 2);
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = array_merge($this->jsonDataArray, json_decode($json, true));
		}
		$this->jsonDataArray['products'] = array();
		$dataAttribute = $this->xpath->query($this->VARIANT_SELECTOR);
		if ($dataAttribute->length) {
			foreach ($dataAttribute as $key => $dataVarlink) {
				$dataHtml = $this->getContent($dataVarlink->nodeValue);

				if ($dataHtml) {
					$this->jsonDataArray['products'][$key]['attributes'] = array();

					$dom = $this->getDomObj($dataHtml);
					$xpath = new \DomXPath($dom);
					$imges = $this->getValue($this->IMAGE_VR_SELECTOR, false, $xpath);
					if ($imges) {
						foreach ($imges as $imgs) {
							$imgs = explode('?', $imgs);
							$imgs = array_shift($imgs);
							$this->imagses[$key][] = $imgs;
						}
					}



					$priceVar = $this->getValue($this->PRICE_SELECTOR, false, $xpath);
					if ($priceVar) {
						$priceVar = array_shift($priceVar);
						$this->jsonDataArray['products'][$key]['price'] = preg_replace('/[^0-9]/', '', $priceVar)/100;
					}
					$attrsArrayObject = $xpath->query($this->ATTRIBUTES_SELECTOR);

					if ($attrsArrayObject->length) {
						$attrbutes = array();

						foreach ($attrsArrayObject as $attrsObject) {
							$attrName = $xpath->query('.//p[contains(@class, "text-label")]', $attrsObject)->item(0)->nodeValue;
							$attrValues = $xpath->query('.//a[contains(@class, "active")]', $attrsObject);
							if ($attrValues->length) {
								$attrbutes[] = array(
									'name' => $attrName,
									'value' => preg_replace('/\s+/', ' ', $attrValues->item(0)->nodeValue)
								);
							}
						}
						$this->jsonDataArray['products'][$key]['attributes'] = $attrbutes;
					}
				}
			}
		}
	}

	private function getValue( $selector, $html = false, $xpath = null) {
		if (empty($selector)) {
			return array();
		}
		if (null === $xpath) {
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
		if (isset($this->jsonDataArray['name'])) {
			return $this->jsonDataArray['name'];
		}
		return '';
	}

	public function getCategories() {
		$category = array();
		if (isset($this->jsonDataArray['itemListElement']) && $this->jsonDataArray['itemListElement']) {
			foreach ($this->jsonDataArray['itemListElement'] as $categorys) {
				$category[] = $categorys['item']['name'];
			}
		}
		return $category;
	}

	public function getShortDescription() {
		if (isset($this->jsonDataArray['description'])) {
			return $this->jsonDataArray['description'];
		}
		return '';
	}

	public function getDescription() {
		$descript = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		$descript1 = $this->getValue($this->DESCRIPTION_SELECTOR1, true);
		$description = '';
		if ($descript) {
			return $description .= array_shift($descript);
		}
		if ($descript1) {
			return $description .= array_shift($descript1);
		}

		return '';
	}

	public function getPrice() {
		if (isset($this->jsonDataArray['offers']['price'])) {
			return $this->jsonDataArray['offers']['price'];
		}
		return '';
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['sku'])) {
			return $this->jsonDataArray['sku'];
		}
		return '';
	}
	public function getWeight() {
		return '';
	}

	public function getUPC() {
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['brand']['name'])) {
			return $this->jsonDataArray['brand']['name'];
		}
		return '';
	}
	public function getCoverImage() {
		if (isset($this->jsonDataArray['image'])) {
			return $this->jsonDataArray['image'];
		}
		return '';
	}

	public function getImages() {
		static $images = array();
		if ($images) {
			return $images;
		}

		$images = $this->imagses;

		if (!$images) {
			$imges = $this->getValue($this->IMAGE_VR_SELECTOR);
			if ($imges) {
				foreach ($imges as $imgs) {
					$imgs = explode('?', $imgs);
					$imgs = array_shift($imgs);
					$images[0][] = $imgs;
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
		$attributes = array();

		$featureArrayObject = $this->xpath->query($this->FEATURE_SELECTOR);
		if ($featureArrayObject->length) {
			foreach ($featureArrayObject as $featureObject) {
				$genral = $this->xpath->query('.//p[@class="text-uppercase"]/strong', $featureObject)->item(0)->nodeValue;
				$features = $this->xpath->query('.//td', $featureObject);

				if ($features->length) {
					for ($i=0; $i<$features->length; $i=$i+2) {
						$attributes[] = array(
							'name' => $features->item($i)->nodeValue,
							'value' => preg_replace('/\s+/', ' ', $features->item($i+1)->nodeValue)
						);
					}
				}

				if ($attributes) {
					$featureGroups[] = array(
						'name' => $genral,
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

		if (isset($this->jsonDataArray['products']) && $this->jsonDataArray['products']) {
			foreach ($this->jsonDataArray['products'] as $attrs) {
				foreach ($attrs['attributes'] as $attrVals) {
					$key = base64_encode($attrVals['name']);

					if (!isset($attrGroups[$key])) {
						$attrGroups[$key] = array(
							'name' => $attrVals['name'],
							'is_color' => 0,
							'values' => array()
						);
					}

					if (!in_array($attrVals['value'], $attrGroups[$key]['values'])) {
						$attrGroups[$key]['values'][] = $attrVals['value'];
					}
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

		if (isset($this->jsonDataArray['products']) && $this->jsonDataArray['products']) {
			foreach ($this->jsonDataArray['products'] as $keys => $attrVals) {
				if (isset($attrVals['attributes'])) {
					$combinations[] = array(
						'sku' => $sku,
						'upc' => 0,
						'price' => isset($attrVals['price']) ? $attrVals['price'] : $price,
						'weight' => 0,
						'image_index' => $keys,
						'attributes' => $attrVals['attributes']
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
		$maxReviews = $maxReviews > 0 ? $maxReviews : 100;

		$reviewlink = 'https://' . parse_url($this->url, PHP_URL_HOST);
		$reviewlink .= '/product-feedback' . parse_url($this->url, PHP_URL_PATH);
		$reviewlink .= 'reviews/list?source_id=0&token=&page[offset]=0&page[limit]=' . $maxReviews;

		$json = $this->getContent($reviewlink);
		if ($json) {
			$reviewData = json_decode($json, true);
			if (isset($reviewData['reviews']['items']) && $reviewData['reviews']['items']) {
				foreach ($reviewData['reviews']['items'] as $review) {
					$reviews[] = array(
						'author' => isset($review['user']['name']) ? $review['user']['name'] : 0,
						'title' => $review['title'],
						'content' => $review['content'],
						'rating' => $review['rating'],
						'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['published']))
					);
				}
			}
		}
		return $reviews;
	}

	public function getVideos() {
		$videos = array();

		$videoJsons = $this->getValue($this->VIDEOS_SELECTOR);

		if ($videoJsons) {
			$videoList = json_decode(array_shift($videoJsons), true);

			if ($videoList) {
				foreach ($videoList as $video) {
					if (isset($video['mp4']['high']['url'])) {
						$videos[] = trim($video['mp4']['high']['url']);
					} elseif (isset($video['mp4']['medium']['url'])) {
						$videos[] = trim($video['mp4']['medium']['url']);
					}
				}
			}
		}

		return array_unique($videos);
	}
}
