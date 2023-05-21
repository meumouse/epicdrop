<?php
/**
 * Conforama data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class ConforamaParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $combPrice = array();
	private $jsonDataArray = array();
	private $JSON_DATA_SELECTOR = '//script[@type="application/ld+json"]';
	private $TITLE_SELECTOR = '//h1[@class="typo-h1"]/a';
	private $CATEGORY_SELECTOR = '//div[@id="breadcrumb"]/a';
	private $SHORT_DESCRIPTION_SELECTOR = '//ul[@class="pointForts"]';
	private $DESCRIPTION_SELECTOR = '//div[@id="tabs-1"]';
	private $PRICE_SELECTOR = '//div[@class="priceEco"]/div|//div[@class="price"]/span';
	private $SKU_SELECTOR = '//div[@class="awk-wishlist"]/button/@data-productid';
	private $COVER_IMG_SELECTOR = '//div[@class="productPics"]/a/@href';
	private $IMG_VARIANT_SELECTOR = '//div[@id="colors"]/div/a/@href';
	private $IMAGE_SELECTOR = '//ul[contains(@class, "sliderThumb")]/li/a/img/@src|//div[@class="productPics"]/a/@href';
	private $COLOR_KEY_SELECTOR = '//span[@class="m_color_label"]/span[2]';
	private $FEATURES_SELECTOR = '//div[@class="detailCaracts"]';
	private $ATTRIBUTE_SELECTOR = '//div[contains(@class, "m_choise_variant")]';
	private $VIDEO_SELECTOR = '//div[@class="product-video-desc"]/@data-url';
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
		$jsons = $this->getValue($this->JSON_DATA_SELECTOR);
		if ($jsons) {
			foreach ($jsons as $json) {
				if ($json) {
					$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
					$json = json_decode($json, true);
					if ($json) {
						$this->jsonDataArray = array_merge($this->jsonDataArray, $json);
					}
				}
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
			return $title;
		}
		return '';
	}

	public function getCategories() {
		$categories = $this->getValue($this->CATEGORY_SELECTOR);
		if ($categories) {
			return array_filter($categories);
		}
		return array_unique($categories);
	}

	public function getShortDescription() {
		$shortDescription = '';
		$descript = $this->getValue($this->SHORT_DESCRIPTION_SELECTOR, true);
		$descript = array_shift($descript);
		if ($descript) {
			$shortDescription = $descript;
		}
		return $shortDescription;
	}

	public function getDescription() {
		$description = '';
		$descript = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		$descript = array_shift($descript);
		if ($descript) {
			$description = $descript;
		}
		return $description;
	}

	public function getPrice() {
		$price = 0;
		$price = $this->getValue($this->PRICE_SELECTOR);
		$price = array_shift($price);
		if ($price) {
			$price = str_replace('€', '.', $price);
		}
		return $price;
	}

	public function getSKU() {
		$sku = $this->getValue($this->SKU_SELECTOR);
		$sku = array_shift($sku);
		if ($sku) {
			return preg_replace('/[^0-9]/', '', $sku);
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['brand']['name'])) {
			return $this->jsonDataArray['brand']['name'];
		}
		return '';
	}

	public function getVideos() {
		$videos = $this->getValue($this->VIDEO_SELECTOR);
		return $videos;
	}

	public function getCoverImage() {
		$cImage = $this->getValue($this->COVER_IMG_SELECTOR);
		$cImage = array_shift($cImage);
		if ($cImage) {
			$cImage = 'https:' . $cImage;
		}
		return $cImage;
	}

	public function getImages() {
		static $images = array();
		if ($images) {
			return $images;
		}
		$imageVarient = $this->xpath->query($this->IMG_VARIANT_SELECTOR);
		$img = $this->getValue($this->IMAGE_SELECTOR);
		if ($imageVarient->length) {
			foreach ($imageVarient as $imageVarLink) {
				if ('#' == $imageVarLink) {
					$dataHtml = $this->content;
				} else {
					$dataHtml = $this->getContent('https://' . parse_url($this->url, PHP_URL_HOST) . $imageVarLink->nodeValue);
				}
				if ($dataHtml) {
					$dom = $this->getDomObj($dataHtml);
					$xpath = new \DomXPath($dom);
					$imgsLinks = $this->getValue($this->IMAGE_SELECTOR, false, $xpath);
					$colors = $this->getValue($this->COLOR_KEY_SELECTOR, false, $xpath);
					if ($colors) {
						$key = array_shift($colors);
					}
					$attrPrice = $this->getValue($this->PRICE_SELECTOR, false, $xpath);
					if ($imgsLinks) {
						foreach ($imgsLinks as $image) {
							$images[$key][] = 'https:' . $image;
						}
					}
				}
			}
		} elseif ($img) {
			$images[0] = str_replace('//media', 'https://media', $img);
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
		$features = $this->xpath->query($this->FEATURES_SELECTOR);

		if ($features->length) {
			foreach ($features as $attrObject) {
				$featureGroupName = $this->xpath->query('.//td[@class="titleCaract"]/h3', $attrObject)->item(0)->nodeValue;
				$feature = $this->xpath->query('.//tr[@class="col"]', $attrObject);

				$attributes = array();
				if ($feature->length) {
					foreach ($feature as $featureVals) {
						$featuresName = $this->xpath->query('.//td[1]', $featureVals);
						$featuresValue = $this->xpath->query('.//td[2]', $featureVals);
						if ($featuresValue->length) {
							$attributes[] = array(
								'name' => str_replace(':', '', $featuresName->item(0)->nodeValue),
								'value' => $featuresValue->item(0)->nodeValue
							);
						}
					}
				}
				if ($attributes) {
					$featureGroups[] = array(
						'name' => $featureGroupName,
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
		$attributes = $this->xpath->query($this->ATTRIBUTE_SELECTOR);
		if ($attributes->length) {
			foreach ($attributes as $attrArray) {
				$attrNames = $this->xpath->query('.//span[@class="m_color_label"]/span[1]', $attrArray)->item(0)->nodeValue;
				$attrValues = $this->xpath->query('.//div[contains(@class, "subList")]/div', $attrArray);
				$attribute = array();
				if ($attrValues->length) {
					foreach ($attrValues as $attrVals) {
						$values = $this->xpath->query('.//a/span', $attrVals);
						if ($values->length) {
							$this->combPrice[$values->item(0)->nodeValue] = @$this->xpath->query('.//div[@class="price"]/span', $attrVals)->item(0)->nodeValue;
							$attribute[] = $values->item(0)->nodeValue;
						}
					}
				}
				$attrName = str_replace(':', '', $attrNames);

				if ($attribute) {
					$attrGroups[] = array(
					'name' => $attrName,
						'is_color' => stripos($attrName, 'Coloris') !== false ? 1 : 0,
						'values' => $attribute
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
		$colorAttrs = array();
		foreach ($attrs as $attr) {
			if ($attr['is_color']) {
				$colorAttrs = $attr['values'];
				break;
			}
		}
		$combs = $this->makeCombinations($attrs);
		if ($combs) {
			foreach ($combs as $attrVals) {
				$imageIndex = 0;
				if ($colorAttrs) {
					foreach ($colorAttrs as $key => $colorName) {
						if (in_array($colorName, array_column($attrVals, 'value'))) {
							$imageIndex = $colorName;
							break( 1 );
						}
					}
				}
				$attrValues = array_column($attrVals, 'value');
				$value = array_pop($attrValues);
				if (isset($this->combPrice[$value])) {
					$prices = $this->combPrice[$value];
				} else {
					$prices = $price;
				}

				$combinations[] = array(
					'sku' => $sku,
					'upc' => 0,
					'price' => str_replace('€', '.', $prices),
					'weight' => 0,
					'image_index' => $imageIndex,
					'attributes' => $attrVals
				);
			}
		}
		return $combinations;
	}

	public function getCombinations2() {
		static $combinations = array();

		if ($combinations) {
			return $combinations;
		}
		$attributes = $this->xpath->query($this->ATTRIBUTE_SELECTOR);
		$price = $this->getPrice();
		$sku = $this->getSKU();

		if ($attributes->length) {
			foreach ($attributes as $attrArray) {
				$attrNames = $this->xpath->query('.//span[@class="m_color_label"]/span[1]', $attrArray)->item(0)->nodeValue;
				$attrValues = $this->xpath->query('.//div[contains(@class, "subList")]/div', $attrArray);
				$attribute = array();
				if ($attrValues->length) {
					foreach ($attrValues as $key => $attrVals) {
						$values = $this->xpath->query('.//a/span', $attrVals);
						$this->combPrice[$values->item(0)->nodeValue] = $this->xpath->query('.//div[@class="price"]/span', $attrVals)->item(0)->nodeValue;

						if ($values->length) {
							$combinations[] = array(
								'sku' => $sku,
								'upc' => '',
								'price' => str_replace('€', '.', $combPrice ? $combPrice : $price),
								'weight' => 0,
								'image_index' => $values->item(0)->nodeValue,
								'attributes' => array(
									array(
										'name' => str_replace(':', '', $attrNames),
										'value' => $values->item(0)->nodeValue
									)
								)
							);
						}
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
				$author = $this->xpath->query('.//span[@itemtype="http://schema.org/Person"]/span', $reviewObject);
				if ($author->length) {
					$stars = 0;
					$stars = $this->xpath->query('.//span[@itemprop="ratingValue"]', $reviewObject)->item(0)->nodeValue;
					if ($stars) {
						$rating = $stars;
					}

					$reviews[] = array(
						'author' => trim($author->item(0)->nodeValue),
						'title' => @$this->xpath->query('.//span[3]', $reviewObject)->item(0)->nodeValue,
						'content' => @$this->xpath->query('.//span[@itemprop="description"]', $reviewObject)->item(0)->nodeValue,
						'rating' => $rating,
						'timestamp' => $this->xpath->query('.//meta[@itemprop="datePublished"]/@content', $reviewObject)->item(0)->nodeValue
					);
				}
			}
		}
		return $reviews;
	}
}
