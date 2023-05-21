<?php
/**
 * Mercadolibre data parser class
 *
 * @package: product-importer
 *
 */
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.2 */

class MercadolibreParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $images = array();
	private $attributes = array();
	private $PRICE_SELECTOR = '//span[@itemprop="offers"]/span[@class="andes-money-amount__fraction"]';    
	private $PRICE_SELECTOR2 = '//span[@class="price-tag-fraction"]';
	private $PRICE_CENTS_SELECTOR = '//span[@itemprop="offers"]/span[contains(@class, "andes-money-amount__cents")]';
	private $REVIEW_SELECTOR = '//article[@class="review-element"]';
	private $IMAGE_COVER_SELECTOR = '//img[contains(@class, "ui-pdp-gallery__figure__image")][1]/@src';
	private $CSRF_TOKEN_SELECTOR = '//meta[@name="csrf-token"]/@content';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content, $url) {
		$this->dom = $this->getDomObj($content);
		$this->url = $url;
		$content = iconv('UTF-8', 'UTF-8//IGNORE', $content);
		$content = preg_replace('!/\*.*?\*/!s', '', $content);
		$this->content = preg_replace('/\s+/', ' ', $content);

		/* Create a new XPath object */
		$this->xpath = new \DomXPath($this->dom);

		// Set json data array
		$this->setJsonData();
	}

	public function getContent( $url, $postData = array(), $additionalHeaders = array()) {
		$curl = curl_init($url);
		$headers = array(
			'cache-control: no-cache',
			'referer: ' . $url,
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

	private function getDomObj( $content) {
		$dom = new \DomDocument('1.0', 'UTF-8');
		libxml_use_internal_errors(true);
		$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
		libxml_use_internal_errors(false);

		return $dom;
	}

	private function setJsonData() {
		$json = $this->getJson($this->content, ',"initialState":', ',"site":"');
		
		if ($json) {
			$this->jsonDataArray = json_decode($json, true);
		}
		
		if (!$this->jsonDataArray) {
			$json = $this->getJson($this->content, ',"initialState":', '},"csrfToken":') . '}';
			if ($json) {
				$this->jsonDataArray = json_decode($json, true);
			}
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
		if (isset($this->jsonDataArray['components']['header']['title'])) {
			return $this->jsonDataArray['components']['header']['title'];
		}
		return '';
	}

	public function getCategories() {
		$categories = array();

		if (isset($this->jsonDataArray['breadcrumb']['categories']) && $this->jsonDataArray['breadcrumb']['categories']) {
			foreach ($this->jsonDataArray['breadcrumb']['categories'] as $category) {
				$categories[] = $category['label']['text'];
			}
		} elseif (isset($this->jsonDataArray['components']['breadcrumb']['categories']) && $this->jsonDataArray['components']['breadcrumb']['categories']) {
			foreach ($this->jsonDataArray['components']['breadcrumb']['categories'] as $category) {
				$categories[] = $category['label']['text'];
			}
		}
		return $categories;
	}

	public function getShortDescription() {
		$description = '';
		if (isset($this->jsonDataArray['metadata']['tags']) && $this->jsonDataArray['metadata']['tags']) {
			foreach ($this->jsonDataArray['metadata']['tags'] as $section) {
				if (isset($section['name']) && 'description' == $section['name']) {
					$description .= $section['content'];
				}
			}
		} elseif (isset($this->jsonDataArray['components']['metadata']['tags']) && $this->jsonDataArray['components']['metadata']['tags']) {
			foreach ($this->jsonDataArray['components']['metadata']['tags'] as $section) {
				if (isset($section['name']) && 'description' == $section['name']) {
					$description .= $section['content'];
				}
			}
		}

		return $description;
	}

	public function getDescription() {
		if (isset($this->jsonDataArray['components']['description']['content'])) {
			return nl2br($this->jsonDataArray['components']['description']['content']);
		}
		return '';
	}

	public function getPrice() {
		$priceValue = 0;
		
		$prices = $this->getValue($this->PRICE_SELECTOR);
		
		if (!$prices) {
			$prices = $this->getValue($this->PRICE_SELECTOR2);
		}
		
		if ($prices) {
			$priceValue = str_replace(',', '', array_shift($prices));
		}
		
		$cents = $this->getValue($this->PRICE_CENTS_SELECTOR);
		
		if ($cents) {
			$priceValue .= '.' . (int) array_shift($cents);
		}
		
		return $priceValue;
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['schema'][0]['sku'])) {
			return $this->jsonDataArray['schema'][0]['sku'];
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['technical_specifications']['attributes'])
			&&  $this->jsonDataArray['technical_specifications']['attributes']
		) {
			foreach ($this->jsonDataArray['technical_specifications']['attributes'] as $brand) {
				if (isset($brand['id']) && 'BRAND' == $brand['id']) {
					return $brand['value'];
				}
			}
		} elseif (isset($this->jsonDataArray['components']['technical_specifications']['attributes'])
			&&  $this->jsonDataArray['components']['technical_specifications']['attributes']
		) {
			foreach ($this->jsonDataArray['components']['technical_specifications']['attributes'] as $brand) {
				if (isset($brand['id']) && 'BRAND' == $brand['id']) {
					return $brand['value'];
				}
			}
		} elseif (isset($this->jsonDataArray['components']['track']['gtm_event']['brandId'])) {
			return $this->jsonDataArray['components']['track']['gtm_event']['brandId'];
		}

		return '';
	}

	public function getCoverImage() {
		$images = $this->getValue($this->IMAGE_COVER_SELECTOR);
		if ($images) {
			return str_replace('.webp', '.jpg', array_shift($images));
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}
		
		if (isset($this->jsonDataArray['components']['gallery']['pictures'])
				&& $this->jsonDataArray['components']['gallery']['pictures']
			) {
			$imageLink = $this->jsonDataArray['components']['gallery']['picture_config']['template_2x'];
			foreach ($this->jsonDataArray['components']['gallery']['pictures'] as $image) {
				$images[$this->getSKU()][] = str_replace(
					array('{id}', 'webp'),
					array($image['id'], 'jpg'),
					$imageLink
				);
			}
		}
		
		if (isset($this->jsonDataArray['components']['variations']['pickers'])
			&& $this->jsonDataArray['components']['variations']['pickers']) {
			foreach ($this->jsonDataArray['components']['variations']['pickers'] as $imges) {
				if (isset($imges['products'])) {
					foreach ($imges['products'] as $keys => $imgId) {
						if (isset($imgId['picture']['id'])) {
							$images[$imgId['id']][] = 'https://http2.mlstatic.com/D_NQ_NP_2X_' . $imgId['picture']['id'] . '-F.jpg';
						}
					}
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

	public function getAttributes() {
		static $attrGroups = array();
		if ($attrGroups) {
			return $attrGroups;
		}

		if (isset($this->jsonDataArray['components']['variations']['pickers'])
			&& $this->jsonDataArray['components']['variations']['pickers']) {
			foreach ($this->jsonDataArray['components']['variations']['pickers'] as $attrGroup) {
				if (isset($attrGroup['products'])) {
					$attrValues = array();
					foreach ($attrGroup['products'] as $attr) {
						$attrValues[$attr['id']] = str_replace(':', '', $attr['label']['text']);
					}

					$attrGroups[] = array(
						'name' => $attrGroup['label']['text'],
						'is_color' => ( stripos($attrGroup['label']['text'], 'color') !== false ) ? 1 : 0,
						'values' => $attrValues
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
		$weight = $this->getWeight();
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
							$imageIndex = $key;
							break( 1 );
						}
					}
				}

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

	public function getWeight() {
		static $weight = array();

		if ($weight) {
			return $weight;
		}
		
		$weightTexts = array(
			'weight',
			'peso',
		);

		$features = $this->getFeatures();

		if ($features) {
			foreach ($features as $feature) {
				foreach ($feature['attributes'] as $attr) {
					if (in_array(Tools::strtolower($attr['name']), $weightTexts)) {
						$weight = array(
							'value' => (float) preg_replace('/[^0-9.]/', '', $attr['value']),
							'unit' => preg_replace('/[0-9.]/', '', $attr['value'])
						);
						break 2;
					}
				}
			}
		}

		return $weight;
	}

	public function getFeatures() {
		static $featureGroups = array();

		if ($featureGroups) {
			return $featureGroups;
		}

		$attributes = array();

		if (isset($this->jsonDataArray['components']['highlighted_specs_attrs']['components']) && $this->jsonDataArray['components']['highlighted_specs_attrs']['components']) {
			foreach ($this->jsonDataArray['components']['highlighted_specs_attrs']['components'] as $component) {
				if (isset($component['specs'])) {
					foreach ($component['specs'] as $specs) {
						if (isset($specs['attributes'])) {
							foreach ($specs['attributes'] as $attribute) {
								if (isset($attribute['id'])) {
									$attributes[] = array(
										'name' => $attribute['id'],
										'value' => $attribute['text']
									);
								}
							}
						}
					}
				}
			}
		}
		if (isset($this->jsonDataArray['components']['technical_specifications']['specs']) && $this->jsonDataArray['components']['technical_specifications']['specs']) {
			foreach ($this->jsonDataArray['components']['technical_specifications']['specs'] as $specs) {
				if (isset($specs['attributes']) && $specs['attributes']) {
					foreach ($specs['attributes'] as $attribute) {
						if (isset($attribute['id'])) {
							$attributes[] = array(
								'name' => $attribute['id'],
								'value' => $attribute['text']
							);
						} elseif (isset($attribute['text'])) {
							foreach ($attribute['values'] as $value) {
								$val = trim(
									str_replace(
										array('{value_text}', ':'),
										'',
										$attribute['text']
									)
								);

								if ($val) {
									$attributes[] = array(
										'name' => $value['text'],
										'value' => $val
									);
								}
							}
						}
					}
				}
			}
		}
		if ($attributes) {
			$featureGroups[] = array(
				'name' => 'General',
				'attributes' => $attributes,
			);
		}
		return $featureGroups;
	}

	public function dateLanguage( $text) {
		$dateString = str_replace(
			array('Hace', 'meses', 'un', 	'mes',	'Más', 'de', 'años', 'año'),
			array('', 'months ago', '', 'month ago', '', '', 'years ago', 'year ago'),
			$text
		);
		return $dateString;
	}

	public function getCustomerReviews( $maxReviews = 0, &$reviews = array(), $offset = 0) {
		if (!$reviews) {
			$host = parse_url($this->url, PHP_URL_HOST);
			$id = $this->jsonDataArray['id'];
			$siteId = preg_replace('/[^A-Za-z]/', '', $id);

			$this->reviewLink = 'https://' . $host . '/noindex/catalog/reviews/' . $id . '/scroll?siteId=' . $siteId . '&type=all&isItem=true&limit=10';
		}
		$reviewLink = $this->reviewLink;

		$reviewLink .= '&offset=' . $offset;

		if ($reviewLink) {
			$reviewJson = $this->getContent($reviewLink);

			if ($reviewJson) {
				$reviewArrayObject = json_decode($reviewJson, true);

				if (isset($reviewArrayObject['reviews']) && $reviewArrayObject['reviews']) {
					$isMaxReached = false;

					foreach ($reviewArrayObject['reviews'] as $reviewObject) {
						if ($reviewObject['id']) {
							$reviews[] = array(
								'author' => $reviewObject['id'],
								'title' => isset($reviewObject['title']['text']) ? $reviewObject['title']['text'] : '',
								'content' => isset($reviewObject['comment']['content']['text']) ? $reviewObject['comment']['content']['text'] : '',
								'rating' =>$reviewObject['rating'],
								'timestamp' => gmdate('Y-m-d H:i:s', strtotime($this->dateLanguage($reviewObject['comment']['time']['text'])))
							);
							if (0 < $maxReviews && count($reviews) >= $maxReviews) {
								$isMaxReached = true;
								break;
							}
						}
					}
					if (false == $isMaxReached) {
						$this->getCustomerReviews($maxReviews, $reviews, $offset+10);
					}
				}
			}
		}
		if (!$reviews) {
			$reviews = $this->getCustomerReviews2();
		}
		return $reviews;
	}

	public function getCustomerReviews2() {
		$reviews = array();

		if (isset($this->jsonDataArray['components']['reviews_capability']['tabs'])
			&& $this->jsonDataArray['components']['reviews_capability']['tabs']
		) {
			foreach ($this->jsonDataArray['components']['reviews_capability']['tabs'] as $tabs) {
				foreach ($tabs['reviews'] as $reviewObject) {
					if ($reviewObject['id']) {
						$reviews[] = array(
							'author' => $reviewObject['id'],
							'title' => isset($reviewObject['title']['text']) ? $reviewObject['title']['text'] : '',
							'content' => isset($reviewObject['comment']['content']['text']) ? $reviewObject['comment']['content']['text'] : '',
							'rating' =>$reviewObject['rating'],
							'timestamp' => gmdate('Y-m-d H:i:s', strtotime($this->dateLanguage($reviewObject['comment']['time']['text'])))
						);
					}
				}
			}
		}
		return $reviews;
	}
}
