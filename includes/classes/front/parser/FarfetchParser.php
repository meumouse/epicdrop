<?php
/**
 * Farfetch data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class FarfetchParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $JSON_DATA_SELECTOR = '//script[@type="application/ld+json"]';
	private $TITLE_SELECTOR = '//h1[@class="_6b7f2f"]';
	private $CATEGORY_SELECTOR = '//li[@itemtype="http://schema.org/ListItem"]/a/span';
	private $DESCRIPTION_SELECTOR = '//div[@data-tstid="productDetails"]|//div[@class="_1bcfc9"]';
	private $PRICE_SELECTOR = '//span[@data-tstid="priceInfo-original"]';
	private $BRAND_SELECTOR = '//span[@itemprop="brand"]/meta/@content';
	private $IMAGE_SELECTOR = '//div[@class="_97b23b"]/img/@src';
	private $MORE_IMAGE_SELECTOR = '//div[contains(@class, "css-1qgarne-Container")]/img/@src';
	private $FEATURE_SELECTOR_VL_NM = '//p[@class="_aa69b4"]';
	private $FEATURE_SELECTOR_NM = '//h4[contains(@class, "_9898cd")]';
	private $ATTRIBUTE_SELECTOR = '//div[@data-tstid="sizesDropdownRow"]';
	private $ATTRIBUTE_SELECTOR_price = '//div[@data-tstid="sizesDropdownRow"]/span/span';
	private $ATTRIBUTE_SELECTOR_NM = '//div[@id="sizesDropdownTrigger"]/span/span[@class="_ec791b"]';
	private $REVIEW_SELECTOR = '//div[@itemtype="http://schema.org/Review"]';
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
		$title = $this->getValue($this->TITLE_SELECTOR);
		$title = array_shift($title);
		if ($title) {
			return preg_replace('/\s+/', ' ', $title);
			;
		}
		return '';
	}

	public function getCategories() {
		$categories = array();
		if (isset($this->jsonDataArray['itemListElement']) && $this->jsonDataArray['itemListElement']) {
			foreach ($this->jsonDataArray['itemListElement'] as $categary) {
				$categories[] = $categary['item']['name'];
			}
		}
		return $categories;
	}

	public function getDescription() {
		$description = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		$description = array_shift($description);
		if ($description) {
			return $description;
		}
		return '';
	}

	public function getPrice() {
		$price = 0;
		$price = $this->getValue($this->PRICE_SELECTOR);
		$price = array_shift($price);
		if ($price) {
			$price = preg_replace('/[^0-9.]/', '', $price);
		}
		return $price;
	}

	public function getSKU() {
		$url = explode('?', $this->url);
		$url = array_shift($url);
		$sku = preg_replace('/[^0-9]/', '', $url);
		if ($sku) {
			return $sku;
		}
		return '';
	}

	public function getUPC() {
		$url = explode('?', $this->url);
		$url = array_pop($url);
		$upc = preg_replace('/[^0-9]/', '', $url);
		if ($upc) {
			return $upc;
		}
		return '';
	}

	public function getBrand() {
		$brand = $this->getValue($this->BRAND_SELECTOR);
		$brand = array_shift($brand);
		if ($brand) {
			return $brand;
		}
		return '';
	}
	public function getCoverImage() {
		$cImage = $this->getValue($this->IMAGE_SELECTOR);
		$cImage = array_shift($cImage);
		if ($cImage) {
			return $cImage;
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}
		$Image = $this->getValue($this->MORE_IMAGE_SELECTOR);

		if ($Image) {
			foreach ($Image as $imgs) {
				$images[0][] = $imgs;
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
		$featureNM = $this->getValue($this->FEATURE_SELECTOR_NM);
		$featureArrayObject = $this->getValue($this->FEATURE_SELECTOR_VL_NM);
		$attributes = array();
		if ($featureNM) {
			$featureNM = array_shift($featureNM);
		}

		if ($featureArrayObject) {
			foreach ($featureArrayObject as $features) {
				$feature = explode(':', $features);
				if (isset($feature[1])) {
					$attributes[] = array(
						'name' => str_replace(':', '', $feature[0]),
						'value' => $feature[1]
					);
				}
			}
		}
		if ($attributes) {
			$featureGroups[] = array(
				'name' => $featureNM,
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
		$attributeNm = $this->getValue($this->ATTRIBUTE_SELECTOR_NM);

		if ($attributeNm) {
			$attributeNm = array_shift($attributeNm);
		}
		$attribute = $this->xpath->query($this->ATTRIBUTE_SELECTOR);
		$attributes = array();

		if ($attribute->length) {
			foreach ($attribute as $attrVals) {
				$attrVals1 = $this->xpath->query('.//span/span', $attrVals)->item(0)->nodeValue;
				$attrVals2 = $this->xpath->query('.//span/span', $attrVals)->item(1)->nodeValue;
				$attributes[] = $attrVals1 . ' ' . $attrVals2;
			}
			if ($attributes) {
				$attrGroups[] = array(
					'name' => $attributeNm,
					'is_color' => 0,
					'values' => $attributes
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
		$upc = $this->getUPC();
		$combinateValue = $this->xpath->query($this->ATTRIBUTE_SELECTOR);
		;
		$attributeNm = $this->getValue($this->ATTRIBUTE_SELECTOR_NM);

		if ($attributeNm) {
			$attributeNm = array_shift($attributeNm);
		}
		if ($combinateValue->length) {
			foreach ($combinateValue as $attrVals) {
				$attrVals1 = $this->xpath->query('.//span/span', $attrVals)->item(0)->nodeValue;
				$attrVals2 = $this->xpath->query('.//span/span', $attrVals)->item(1)->nodeValue;
				$comboPrice = @$this->xpath->query('.//span[@data-tstid="sizePrice"]', $attrVals)->item(0)->nodeValue;
				$attrVal = $attrVals1 . ' ' . $attrVals2;
				if ($attrVal) {
					$combinations[] = array(
						'sku' => $sku,
						'upc' => $upc,
						'price' => preg_replace('/[^0-9.]/', '', $comboPrice) ? preg_replace('/[^0-9.]/', '', $comboPrice) : $price,
						'weight' => 0,
						'image_index' => 0,
						'attributes' => array(
							array(
								'name' => $attributeNm,
								'value' => $attrVal
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

	public function getCustomerReviews( $maxReviews = 0) {
		$reviews = array();
		$url = explode('/', $this->url);
		$country = $url[3];
		$reviewLink = 'https://www.farfetch.com/' . $country . '/reviews';

		if ($reviewLink) {
			$reviewHtml = $this->getContent($reviewLink);

			if ($reviewHtml) {
				$dom = $this->getDomObj($reviewHtml);
				$xpath = new \DomXPath($dom);

				$reviewArrayObject = $xpath->query($this->REVIEW_SELECTOR);
				if ($reviewArrayObject->length) {
					foreach ($reviewArrayObject as $reviewObject) {
						$author = $xpath->query('.//span[@itemprop="author"]', $reviewObject);
						$stars = $xpath->query('.//span[contains(@class, "rateit-selected")]', $reviewObject);
						if ($stars) {
							$stars = count($stars);
						}
						if ($author->length) {
							$reviews[] = array(
								'author' => $author->item(0)->nodeValue,
								'title' => $xpath->query('.//span[@itemprop="itemReviewed"]', $reviewObject)->item(0)->nodeValue,
								'content' => trim(@$xpath->query('.//p[@itemprop="reviewBody"]', $reviewObject)->item(0)->nodeValue),
								'rating' => $stars,
								'timestamp' => preg_replace('/\s+/', ' ', $xpath->query('.//p[@itemprop="datePublished"]', $reviewObject)->item(0)->nodeValue)
							);
						}
					}
				}
			}
		}
		return $reviews;
	}
}
