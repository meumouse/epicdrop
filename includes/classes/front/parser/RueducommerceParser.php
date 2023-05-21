<?php
/**
 * Rueducommerce data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class RueducommerceParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $imagses = array();
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
		$this->jsonDataArray['products'] = array();
		$dataAttribute = $this->xpath->query($this->VARIANT_SELECTOR);

		if ($dataAttribute->length) {
			foreach ($dataAttribute as $key => $dataVarlink) {
				$dataHtml = $this->getContent('https://www.rueducommerce.fr/' . $dataVarlink->nodeValue);

				if ($dataHtml) {
					$this->jsonDataArray['products'][$key]['attributes'] = array();
					$dom = $this->getDomObj($dataHtml);
					$xpath = new \DomXPath($dom);
					$imgsLinks = $this->getValue($this->IMAGE_SELECTOR, false, $xpath);
					foreach ($imgsLinks as $imgs) {
						if ($imgs) {
							$this->imagses[$key][] = 'https://www.rueducommerce.fr/' . $imgs;
						}
					}

					$priceVar = $this->getValue($this->PRICE_SELECTOR, false, $xpath);
					if ($priceVar) {
						$priceVar = array_shift($priceVar);
						$this->jsonDataArray['products'][$key]['price'] = str_replace(',', '.', $priceVar);
					}

					$attributes = $xpath->query($this->ATTRIBUTE_SELECTOR);
					if ($attributes->length) {
						foreach ($attributes as $attrObject) {
							$attrsName = $xpath->query('.//p', $attrObject)->item(0)->nodeValue;
							$colorValue = $xpath->query('.//span[@class="active"]/img/@alt', $attrObject);
							$sizeValue = $xpath->query('.//span[contains(@class, "active")]/span', $attrObject);
							$colors = array();
							$sizes = array();
							if ($colorValue->length) {
								$this->jsonDataArray['products'][$key]['attributes'][] = array(
									'name' => $attrsName,
									'value' => preg_replace('/\s+/', '', $colorValue->item(0)->nodeValue)
								);
							}
							if ($sizeValue->length) {
								$this->jsonDataArray['products'][$key]['attributes'][] = array(
									'name' => $attrsName,
									'value' => preg_replace('/\s+/', '', $sizeValue->item(0)->nodeValue)
								);
							}
						}
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
		if ($title) {
			$title = array_shift($title);
			return $title;
		}
		return '';
	}

	public function getCategories() {
		$categories = $this->getValue($this->CATEGORY_SELECTOR);
		if ($categories) {
			foreach ($categories as $categary) {
				$categories[] = $categary;
			}
		}
		return array_unique($categories);
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
		if ($price) {
			$price = array_shift($price);
			$price = preg_replace('/[^0-9.]/', '', $price);
		}
		return $price;
	}

	public function getSKU() {
		$sku = $this->getValue($this->SKU_SELECTOR);
		$sku = array_shift($sku);
		if ($sku) {
			return $sku;
		}
		return '';
	}

	public function getBrand() {
		$brand = $this->getValue($this->BRAND_SELECTOR);
		$brand = array_shift($brand);
		if ($brand) {
			$brand = $brand;
		}
		return $brand;
	}

	public function getCoverImage() {
		$cImage = $this->getValue($this->COVER_IMG_SELECTOR);
		$cImage = array_shift($cImage);
		if ($cImage) {
			$cImage = str_replace('600x600', '1140x1140', 'https://www.rueducommerce.fr' . $cImage);
		}
		return $cImage;
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}
		$img = $this->getValue($this->COVER_IMG_SELECTOR);
		if ($this->imagses) {
			$images = $this->imagses;
		} else {
			$images[0] = str_replace('/media', 'https://www.rueducommerce.fr/media', $img);
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
		$features = $this->xpath->query($this->FEATURES);

		if ($features->length) {
			foreach ($features as $attrObject) {
				$featureGroupName = $this->xpath->query('.//div[@class="group-name"]/span', $attrObject)->item(0)->nodeValue;
				$feature = $this->xpath->query('.//div[@class="group-item"]', $attrObject);

				$attributes = array();
				if ($feature->length) {
					foreach ($feature as $featureVals) {
						$featuresName = $this->xpath->query('.//div[@class="label"]/span', $featureVals);
						$featuresValue = $this->xpath->query('.//div[@class="value"]/span', $featureVals);
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
		$sku = $this->getSKU();

		if (isset($this->jsonDataArray['products']) && $this->jsonDataArray['products']) {
			foreach ($this->jsonDataArray['products'] as $keys => $attrs) {
				if (isset($attrs['attributes'])) {
					$attrValues = array();
					foreach ($attrs['attributes'] as $attrVals) {
						$attrValues[] = array(
							'name' => $attrVals['name'],
							'value' => $attrVals['value']
						);
						$key = base64_encode($attrVals['name']);

						if (!isset($this->attributes[$key])) {
							$this->attributes[$key] = array(
								'name' => $attrVals['name'],
								'is_color' => ( stripos($attrVals['name'], 'couleur') !== false ) ? 1 : 0,
								'values' => array()
							);
						}
						$this->attributes[$key]['values'][] = $attrVals['value'];
					}
				}
				$combinations[] = array(
					'sku' => $sku,
					'upc' => 0,
					'price' => isset($attrs['price']) ? $attrs['price'] : $price,
					'weight' => 0,
					'image_index' => $keys,
					'attributes' => $attrValues
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

	public function getCustomerReviews() {
		$reviews = array();
		$reviewArrayObject = $this->xpath->query($this->REVIEW_SELECTOR);

		if ($reviewArrayObject->length) {
			foreach ($reviewArrayObject as $reviewObject) {
				$author = $this->xpath->query('.//p[@class="avis-comments-author"]', $reviewObject);
				$stars = $this->xpath->query('.//i[@class="fa fa-star"]', $reviewObject);
				if ($stars->length) {
					$stars = $stars->length;
				}
				if ($author->length) {
					$reviews[] = array(
						'author' => preg_replace('/\s+/', '', $author->item(0)->nodeValue),
						'title' => '',
						'content' => trim($this->xpath->query('.//p[@class="avis-comments-desc"]', $reviewObject)->item(0)->nodeValue),
						'rating' => $stars,
						'timestamp' => preg_replace('/\s+/', '', @$this->xpath->query('.//p[@class="avis-date-commande"]/span', $reviewObject)->item(0)->nodeValue)
					);
				}
			}
		}
		return $reviews;
	}
}
