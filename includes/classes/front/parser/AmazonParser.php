<?php
/**
 * Amazon data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 4.7 */

class AmazonParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $url;
	private $weight = 0;
	private $imageJsonArray = array();
	private $attrJsonArray = array();
	private $content;
	private $TITLE_SELECTOR = '//h1/span[@id="productTitle"]';
	private $CATEGORIES_SELECTOR = '//div[@id="wayfinding-breadcrumbs_feature_div"]/ul/li/span[@class="a-list-item"]/a';
	private $DESCRIPTION_SELECTOR = '//div[@id="productDescription"]|//div[@data-cel-widget="BtfImage"]';
	private $DESCRIPTION_SELECTOR2 = '//div[@id="editorialReviews_feature_div"]';
	private $DESCRIPTION_SELECTOR3 = '//div[@id="btfContent2_feature_div"]';
	private $DESCRIPTION_SELECTOR4 = '//div[@id="aplus"]';
	private $DESCRIPTION_SELECTOR5 = '//div[@id="tech"]';
	private $DESCRIPTION_SELECTOR6 = '//div[@id="important-information"]';
	private $SHORT_DESCRIPTION_SELECTOR = '//div[@id="productOverview_feature_div"]';
	private $SHORT_DESCRIPTION_SELECTOR2 = '//div[@id="feature-bullets"]/ul/li';
	private $SHORT_DESCRIPTION_SELECTOR3 = '//noscript';
	private $PRICE_SELECTOR = '//span[@id="priceblock_saleprice"]'; // Sale price
	private $PRICE_SELECTOR2 = '//span[@id="priceblock_dealprice"]'; // Sale price
	private $PRICE_SELECTOR3 = '//span[@id="priceblock_ourprice"]'; // Regular price
	private $PRICE_SELECTOR4 = '//span[@id="price"]'; // Regular price
	private $PRICE_SELECTOR5 = '//span[@id="newBuyBoxPrice"]'; // Regular price
	private $PRICE_SELECTOR6 = '//span[@id="price_inside_buybox"]'; // Regular price
	private $PRICE_SELECTOR7 = '//span[@id="kindle-price"]'; // Regular price
	private $PRICE_SELECTOR8 = '//span[@data-a-color="price"]/span[1]'; // Regular price
	private $PRICE_SELECTOR9 = '//span[contains(@class, "priceToPay")]/span[1]'; // Regular price
	private $PRICE_SELECTOR10 = '//span[@class="a-color-price"]'; // Regular price
	private $IMAGE_COVER_SELECTOR = '//div[@id="imgTagWrapperId"]/img/@data-old-hires';
	private $IMAGE_COVER_SELECTOR2 = '//img[@id="imgBlkFront"]/@data-a-dynamic-image';
	private $IMAGE_COVER_SELECTOR3 = '//img[@id="main-image"]/@src';
	private $IMAGE_COVER_SELECTOR4 = '//img[@id="ebooksImgBlkFront"]/@src';
	private $IMAGE_SELECTOR = '//div[@id="mainImageContainer"]/img/@data-a-dynamic-image'; // Book image
	private $SKU_SELECTOR = '//input[@id="ASIN"]/@value';
	private $SKU_SELECTOR2 = '//input[@id="twister-plus-asin"]/@value';
	private $BRAND_SELECTOR = '//a[@id="bylineInfo"]';
	private $FEATURE_SELECTOR = '//div[@id="detailBullets_feature_div"]/ul/li';
	private $FEATURE_SELECTOR2 = '//ul[contains(@class, "detail-bullet-list")]/li';
	private $FEATURE_SELECTOR3 = '//div[@id="prodDetails"]/div/div/div/div/div/div[@aria-live="polite"]';
	private $FEATURE_SELECTOR4 = '//table[contains(@class, "prodDetTable")]/tbody/tr';
	private $REVIEW_LINK_SELECTOR = '//a[@data-hook="see-all-reviews-link-foot"]/@href';
	private $REVIEW_SELECTOR = '//div[@data-hook="review"]';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content, $url) {
		$this->url = $url;
		$content = iconv('UTF-8', 'UTF-8//IGNORE', $content);
		$content = str_replace('//selectively not escaping this. ', '', $content);
		$content = str_replace('\n', '', $content);

		$this->content = preg_replace('/\s+/', ' ', $content);

		$this->dom = $this->getDomObj($content);

		/* Create a new XPath object */
		$this->xpath = new \DomXPath($this->dom);

		// Set json array
		$this->setJsonArray();
	}

	private function getDomObj( $content) {
		$dom = new \DomDocument('1.0', 'UTF-8');
		libxml_use_internal_errors(true);
		$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
		libxml_use_internal_errors(false);

		return $dom;
	}

	private function setJsonArray() {
		$json = $this->getJson($this->content, "jQuery.parseJSON('", "');");
		if ($json) {
			$this->imageJsonArray = json_decode(str_replace("'", '"', $json), true);
			if (!$this->imageJsonArray) {
				$this->imageJsonArray = json_decode(preg_replace('/\\\\/', '', $json), true);
			}
		}

		$json = '{ "isTabletWeb"' . $this->getJson($this->content, 'var dataToReturn = { "isTabletWeb"', '; return dataToReturn;');

		/*if (false === strpos($json, 'asinVariationValues')) {
			$json = $this->getJson($this->content, 'var dataToReturn = ', '; return dataToReturn;');
		}*/

		if ($json) {
			$this->attrJsonArray = json_decode($json, true);
			if (!$this->attrJsonArray) {
				$this->attrJsonArray = json_decode(preg_replace('/\\\\/', '', $json), true);
			}
		}

		if (!$this->attrJsonArray) {
			$json = $this->getJson($this->content, 'window.INITIAL_STATE = ', '; </script>');
			$this->attrJsonArray = json_decode($json, true);
			if (!$this->attrJsonArray) {
				$json = preg_replace('/"BtfReviews"[\s\S]+?"PlanSelection"/', '"PlanSelection"', $json);
				$this->attrJsonArray = json_decode($json, true);
			}
		}
	}

	private function getValue( $selector, $html = false) {
		if (empty($selector)) {
			return array();
		}
		$items = $this->xpath->query($selector);
		$response = array();
		foreach ($items as $item) {
			if ($html) {
				$element = $this->dom->saveHTML($item);
			} else {
				$element = $item->nodeValue;
			}
			$response[] = trim($element);
		}
		return $response;
	}

	public function getTitle() {
		$titles = $this->getValue($this->TITLE_SELECTOR);

		if ($titles) {
			return array_shift($titles);
		}

		if (isset($this->attrJsonArray['selectedAsin'])
			&& isset($this->attrJsonArray['variations'][$this->getSKU()]['Title']['text'])
		) {
			return $this->attrJsonArray['variations'][$this->getSKU()]['Title']['text'];
		}
	}

	public function getCategories() {
		$categories = array();
		foreach ($this->getValue($this->CATEGORIES_SELECTOR, true) as $c) {
			if (false === strpos($c, 'breadcrumb-back-link') && false === strpos($c, 'history.back()')) {
				$categories[] = trim(html_entity_decode(strip_tags($c)));
			}
		}
		return array_unique($categories);
	}

	public function getShortDescription() {
		$shortDescription = '';

		$shortDescriptions = $this->getValue($this->SHORT_DESCRIPTION_SELECTOR, true);
		if ($shortDescriptions) {
			$shortDescription .= array_shift($shortDescriptions);
		}

		$shortDescription .= $this->getShortDescription2();

		return $shortDescription;
	}

	public function getShortDescription2() {
		$shortDescriptions = $this->getValue($this->SHORT_DESCRIPTION_SELECTOR2, true);

		if ($shortDescriptions) {
			$firstLi = array_shift($shortDescriptions);
			if (strpos($firstLi, 'replacementPartsFitmentBullet') !== false) {
				$firstLi = '';
			}

			return  '<div><ul>' . $firstLi . implode('', $shortDescriptions) . '</ul></div>';
		} elseif (isset($this->attrJsonArray['variations'][$this->getSKU()]['BulletPoints']['featureBullets'])
		) {
			$shortDescriptions = $this->attrJsonArray['variations'][$this->getSKU()]['BulletPoints']['featureBullets'];

			return  '<div><ul><li>' . implode('</li><li>', $shortDescriptions) . '</li></ul></div>';
		}

		return $this->getShortDescription3();
	}

	public function getShortDescription3() {
		$shortDescriptions = $this->getValue($this->SHORT_DESCRIPTION_SELECTOR3);
		foreach ($shortDescriptions as $shortDescription) {
			$shortDescription = preg_replace('!<\!--.*?\-->!s', '', $shortDescription);
			if ($shortDescription) {
				break;
			}
		}

		return $shortDescription;
	}

	public function getDescription() {
		$descriptions = $this->getValue($this->DESCRIPTION_SELECTOR, true);

		$description = array_shift($descriptions) . $this->getDescription2() . $this->getDescription3() . $this->getDescription4() . $this->getDescription5() . $this->getDescription6();

		return $description;
	}

	public function getDescription2() {
		$descriptions = $this->getValue($this->DESCRIPTION_SELECTOR2, true);
		return implode('', $descriptions);
	}

	public function getDescription3() {
		$descriptions = $this->getValue($this->DESCRIPTION_SELECTOR3, true);
		return implode('', $descriptions);
	}

	public function getDescription4() {
		$descriptions = $this->getValue($this->DESCRIPTION_SELECTOR4, true);
		return implode('', $descriptions);
	}

	public function getDescription5() {
		$descriptions = $this->getValue($this->DESCRIPTION_SELECTOR5, true);
		return implode('', $descriptions);
	}

	public function getDescription6() {
		$descriptions = $this->getValue($this->DESCRIPTION_SELECTOR6, true);
		return implode('', $descriptions);
	}

	public function getPrice( $asin = 0) {
		static $prices = array();

		if (isset($prices[$asin])) {
			return $prices[$asin];
		}

		if (isset($this->attrJsonArray['selectedAsin'])) {
			if (isset($this->attrJsonArray['variations'][$asin]['PriceBlock']['price'])) {
				$prices[$asin] = $this->attrJsonArray['variations'][$asin]['PriceBlock']['price'];
			} elseif (isset($this->attrJsonArray['variations'][$this->getSKU()]['PriceBlock']['price'])) {
				$prices[$asin] = $this->attrJsonArray['variations'][$this->getSKU()]['PriceBlock']['price'];
			}

			if (isset($prices[$asin])) {
				return $prices[$asin];
			}
		}

		$priceText = '';

		if ($asin) {
			$immutableURLPrefix = $this->getJson($this->content, '"immutableURLPrefix":"', '","immutableParams"');

			$dataLink = 'https://' . parse_url($this->url, PHP_URL_HOST) . $immutableURLPrefix . '&asinList=' . $asin . '&id=' . $asin . '&mType=full&psc=1';

			$content = @file_get_contents($dataLink);

			if ($content) {
				$jsons = explode('&&&', $content);

				foreach ($jsons as $json) {
					$jsonData = json_decode(trim($json), true);
					if (isset($jsonData['FeatureName'])
						&& 'twister-slot-price_feature_div' == $jsonData['FeatureName']
					) {
						if (isset($jsonData['Value']['content']['priceToSet'])) {
							$priceText = $jsonData['Value']['content']['priceToSet'];
						}
						break;
					}
				}
			}
		}

		if (!$priceText) {
			$priceText = $this->getPriceText();
		}

		$prices[$asin] = $this->sanitizePrice($priceText);

		return $prices[$asin];
	}

	public function getPriceText() {
		$prices = $this->getValue($this->PRICE_SELECTOR);

		if (!$prices) {
			$prices = $this->getValue($this->PRICE_SELECTOR2);
			if (!$prices) {
				$prices = $this->getValue($this->PRICE_SELECTOR3);
				if (!$prices) {
					$prices = $this->getValue($this->PRICE_SELECTOR4);
					if (!$prices) {
						$prices = $this->getValue($this->PRICE_SELECTOR5);
						if (!$prices) {
							$prices = $this->getValue($this->PRICE_SELECTOR6);
							if (!$prices) {
								$prices = $this->getValue($this->PRICE_SELECTOR7);
								if (!$prices) {
									$prices = $this->getValue($this->PRICE_SELECTOR8);
									if (!$prices) {
										$prices = $this->getValue($this->PRICE_SELECTOR9);
										if (!$prices) {
											$prices = $this->getValue($this->PRICE_SELECTOR10);
										}
									}
								}
							}
						}
					}
				}
			}
		}

		return array_shift($prices);
	}

	public function sanitizePrice( $priceText) {
		if (!$priceText) {
			return 0;
		}

		if (strpos($priceText, '.') !== false
			&& strpos($priceText, ',') !== false
			&& strpos($priceText, '.') < strpos($priceText, ',')
		) {
			$priceText = str_replace(array('.',','), array('','.'), $priceText);
		} elseif (strpos($priceText, '.') !== false) {
			$priceText = str_replace(',', '', $priceText);
		} elseif (strpos($this->url, 'amazon.co.jp') === false) {
			$priceText = str_replace(',', '.', $priceText);
		}
		/*
		if(preg_match('#[\d]{1,5}\.[\d]{2}#is', $priceText, $match)) {
			return $match[0];
		}*/

		$priceText = filter_var(
			$priceText,
			FILTER_SANITIZE_NUMBER_FLOAT,
			FILTER_FLAG_ALLOW_FRACTION
		) . '-0';

		list($minPrice, $maxPrice) = explode('-', $priceText);

		return max(trim($maxPrice), trim($minPrice));
	}

	public function getWeight() {
		static $weight = array();

		if ($weight) {
			return $weight;
		}

		$this->getDimension();

		$weightText = '';

		if ($this->weight) {
			$weightText = $this->weight;
		} else {
			$strings = array(
				'weight',
				'gewicht',
				'peso',
				'articolo',
				'Vikt',
				'Volym',
				'重量',
			);

			$features = $this->getFeatures();

			if ($features) {
				foreach ($features as $feature) {
					foreach ($feature['attributes'] as $attr) {
						foreach ($strings as $string) {
							if (stripos($attr['name'], $string) !== false) {
								$weightText = $attr['value'];
								break 3;
							}
						}
					}
				}
			}
		}

		if ($weightText) {
			list($weight, $unit) = explode(' ', $weightText . ' ');

			$weight = array(
				'value' => (float) trim($weight),
				'unit' => trim($unit),
			);
		}

		return $weight;
	}

	public function getModel() {
		static $model = '';

		if ($model) {
			return $model;
		}

		$strings = array(
			'model',
			'モデル',
			'型号',
			'موديل',
		);

		$features = $this->getFeatures();

		if ($features) {
			foreach ($features as $feature) {
				foreach ($feature['attributes'] as $attr) {
					foreach ($strings as $string) {
						if (stripos($attr['name'], $string) !== false) {
							$model = $attr['value'];
							break 3;
						}
					}
				}
			}
		}

		return $model;
	}

	public function getDimension() {
		static $dimensions = array();

		if ($dimensions) {
			return $dimensions;
		}

		$strings = array(
			'dimension',
			'wymiary',
			'afmetingen',
			'サイズ',
			'dimensões',
			'measure',
			'messungen',
			'mått',
			'أبعاد'
		);

		$features = $this->getFeatures();
		$dimension = '';

		if ($features) {
			foreach ($features as $feature) {
				foreach ($feature['attributes'] as $attr) {
					foreach ($strings as $string) {
						if (stripos($attr['name'], $string) !== false) {
							$dimension = $attr['value'];
							break 3;
						}
					}
				}
			}
		}

		if ($dimension) {
			list($dimension, $weight) = explode(';', $dimension . ';0');

			$this->weight = trim($weight);

			$dimensions = explode('x', $dimension);

			$dimensions = array_combine(
				array(
					'length',
					'width',
					'height'
				),
				array_map('floatval', $dimensions)
			);

			$dimensions['unit'] = trim(substr(strrchr($dimension, ' '), 1));
		}

		return $dimensions;
	}

	public function getSKU() {
		if (isset($this->attrJsonArray['currentAsin'])) {
			return $this->attrJsonArray['currentAsin'];
		} elseif (isset($this->attrJsonArray['selectedAsin'])) {
			return $this->attrJsonArray['selectedAsin'];
		}

		$sku = $this->getValue($this->SKU_SELECTOR);
		if (!$sku) {
			$sku = $this->getValue($this->SKU_SELECTOR2);
		}
		if ($sku) {
			return array_shift($sku);
		}

		return 0;
	}

	public function getBrand() {
		static $brand = '';

		if ($brand) {
			return $brand;
		}

		if (isset($this->attrJsonArray['variations'][$this->getSKU()]['ByLine']['brandName'])) {
			$brand = $this->attrJsonArray['variations'][$this->getSKU()]['ByLine']['brandName'];
			return $brand;
		}

		$strings = array(
			'brand',
			'fabricant',
			'manufacturer',
			'produttore',
			'marka',
			'أبعاد'
		);

		$features = $this->getFeatures();

		if ($features) {
			foreach ($features as $feature) {
				foreach ($feature['attributes'] as $attr) {
					foreach ($strings as $string) {
						if (strtolower($attr['name']) == strtolower($string)) {
							//if (stripos($attr['name'], $string) !== false) {
							$brand = $attr['value'];
							return $brand;
						}
					}
				}
			}
		}


		$brand = $this->getValue($this->BRAND_SELECTOR);
		$brand = array_shift($brand);

		return trim(
			str_replace(
				array(
					'Brand:',
					'Marca:',
					'Marque :',
					'Marka:',
					'Merk:',
					'Visiter',
					'Visit the',
					'Visit',
					'Store',
					'Visita lo',
					'Store di',
					'Home and Garden',
					'Visiter la boutique',
					'TIGER',
					'قم بزيارة متجر',
					'العلامة التجارية',
					'la boutique'
				),
				'',
				$brand
			)
		);
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

	public function getCoverImage() {
		$images = $this->getValue($this->IMAGE_COVER_SELECTOR);
		$image = array_shift($images);
		if (!$image) {
			$imageJsons = $this->getValue($this->IMAGE_COVER_SELECTOR2);
			$imageJson = array_shift($imageJsons);
			$images = json_decode($imageJson, true);
			$image = array_shift(array_keys($images));
			if (!$image) {
				$images = $this->getValue($this->IMAGE_COVER_SELECTOR3);
				$image = array_shift($images);
				if (!$image) {
					$images = $this->getValue($this->IMAGE_COVER_SELECTOR4);
					$image = array_shift($images);
				}
			}
		}
		return $image;
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}

		if (isset($this->attrJsonArray['partialVariations'])
			&& $this->attrJsonArray['partialVariations']
		) {
			foreach ($this->attrJsonArray['partialVariations'] as $sku => $variation) {
				$images[$sku] = $variation['ImageBlock']['largeImages'];
			}

			return $images;
		}

		if (!isset($this->imageJsonArray['colorImages']) || !$this->imageJsonArray['colorImages']) {
			// Get default images if standard products (If combination does not exists)
			$json = $this->getJson(str_replace(array(", 'airyConfig'", 'Date.now()'), array(",'airyConfig'", '""'), $this->content), '{ var data = ', ",'airyConfig'") . '}';
			$this->imageJsonArray = json_decode(str_replace("'", '"', $json), true);
			if (!$this->imageJsonArray) {
				$this->imageJsonArray = json_decode(preg_replace('/\\\\/', '', $json), true);
			}
		}
		// Get book images
		if (!isset($this->imageJsonArray['colorImages']) || !$this->imageJsonArray['colorImages']) {
			$json = $this->getJson(str_replace(", 'centerColMargin'", ",'centerColMargin'", $this->content), "'imageGalleryData' :", ",'centerColMargin'");
			$imageArray = json_decode($json, true);
			if ($imageArray) {
				$this->imageJsonArray['colorImages'] = array();
				$this->imageJsonArray['colorImages'][] = $imageArray;
			}
		}

		if (isset($this->imageJsonArray['colorImages']) && $this->imageJsonArray['colorImages']) {
			foreach ($this->imageJsonArray['colorImages'] as $imageIndex => $imagesArray) {
				$imgs = array();
				foreach ($imagesArray as $image) {
					if (isset($image['hiRes'])) {
						$imgs[] = $image['hiRes'];
					} elseif (isset($image['large'])) {
						$imgs[] = $image['large'];
					} elseif (isset($image['mainUrl'])) {
						$imgs[] = $image['mainUrl'];
					} elseif (isset($image['main'])) {
						$main = array_keys($image['main']);
						if ($main) {
							$imgs[] = array_pop($main);
						}
					}
				}
				$images[$imageIndex] = array_filter($imgs);
			}
		}

		if (!$images) {
			$attrImgJsons = $this->getValue($this->IMAGE_SELECTOR);
			$attrImgJson = array_shift($attrImgJsons);
			if ($attrImgJson) {
				$imgs = array_keys(json_decode($attrImgJson, true));
				$images[] = array(array_shift($imgs));
			}
		}

		if (!$images) {
			$cover = $this->getCoverImage();

			if ($cover) {
				$images[$this->getSKU()][] = $cover;
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

		if (isset($this->attrJsonArray['familyFeatures']['Twister']['dimensions'])
			&& $this->attrJsonArray['familyFeatures']['Twister']['dimensions']
		) {
			foreach ($this->attrJsonArray['familyFeatures']['Twister']['dimensions'] as $attrGroup) {
				$attrGroups[] = array(
					'name' => $attrGroup['name'],
					'is_color' => ( stripos($attrGroup['name'], 'color') !== false ) ? 1 : 0,
					'values' => array_map(
						function ( $attr) {
							return $attr['text'];
						},
						$attrGroup['attributes']
					)
				);
			}

			return $attrGroups;
		}

		if (isset($this->attrJsonArray['variationDisplayLabels'])) {
			foreach ($this->attrJsonArray['variationDisplayLabels'] as $attrKey => $attrName) {
				$attrValues = array();
				if (isset($this->attrJsonArray['variationValues'][$attrKey])) {
					$attrValues = $this->attrJsonArray['variationValues'][$attrKey];
				}
				$name = ( 'Colour' == $attrName ) ? 'Color' : $attrName;
				$attrGroups[$attrKey] = array(
					'name' => $name,
					'is_color' => ( stripos($name, 'color') !== false ) ? 1 : 0,
					'values' => $attrValues
				);
			}
		}
		return $attrGroups;
	}

	public function getImageIndex( $attrs, $tempAttrs = '', $o = 0) {
		$images = $this->getImages();

		if (!is_array($tempAttrs)) {
			$tempAttrs = $attrs;
		}

		if ($tempAttrs && is_array($tempAttrs)) {
			$key = str_replace('/', '\/', implode(' ', $tempAttrs));

			if (isset($images[$key])) {
				return $key;
			}
		}

		if (is_array($tempAttrs) && count($tempAttrs) < 2) {
			$tempAttrs = $attrs;
			$o = 1;
		}

		if ($o) {
			array_shift($tempAttrs);

			if (!$tempAttrs) {
				return 0;
			}
		} else {
			array_pop($tempAttrs);
		}

		return $this->getImageIndex($attrs, $tempAttrs, $o);
	}

	public function getCombinations() {
		static $combinations = array();
		if ($combinations) {
			return $combinations;
		}

		$weight = $this->getWeight();
		$attrs = $this->getAttributes();

		if (isset($this->attrJsonArray['familyFeatures']['Twister']['variationAsins'])
			&& $this->attrJsonArray['familyFeatures']['Twister']['variationAsins']
		) {
			$attrs = array_values($attrs);

			foreach ($this->attrJsonArray['familyFeatures']['Twister']['variationAsins'] as $attrKeys => $sku) {
				$attributes = array();
				$attrKeys = explode('_', $attrKeys);
				foreach ($attrKeys as $attrGroupIndex => $attrIndex) {
					$attributes[] = array(
						'name' => $attrs[$attrGroupIndex]['name'],
						'value' => $attrs[$attrGroupIndex]['values'][$attrIndex]
					);
				}

				$combinations[] = array(
					'sku' => $sku,
					'upc' => null,
					'price' => $this->getPrice($sku),
					'weight' => $weight,
					'image_index' => $sku,
					'attributes' => $attributes
				);
			}

			return $combinations;
		}


		if (isset($this->attrJsonArray['dimensionValuesDisplayData'])
			&& $this->attrJsonArray['dimensionValuesDisplayData']
		) {
			$combs = $this->attrJsonArray['dimensionValuesDisplayData'];

			foreach ($combs as $asin => $combVals) {
				$attributes = array();
				$imageIndex = 0;

				foreach ($combVals as $key => $combVal) {
					if (isset($attrs[$this->attrJsonArray['dimensions'][$key]])) {
						$attributes[] = array(
							'name' => $attrs[$this->attrJsonArray['dimensions'][$key]]['name'],
							'value' => $combVal
						);
					}
				}

				$combinations[] = array(
					'sku' => $asin,
					'upc' => null,
					'price' => $this->getPrice($asin),
					'weight' => $weight,
					'image_index' => $this->getImageIndex($combVals),
					'attributes' => $attributes
				);
			}

			return $combinations;
		}

		if (isset($this->attrJsonArray['asinVariationValues'])
			&& $this->attrJsonArray['asinVariationValues']
		) {
			$combinations = $this->attrJsonArray['asinVariationValues'];

			$combinations = array_map(
				function ( $comb) use ( $attrs, $weight) {
					$attributes = array();
					foreach ($attrs as $attr => $attrVal) {
						if (isset($comb[$attr]) && isset($attrVal['values'][$comb[$attr]])) {
							$attributes[] = array(
								'name' => $attrVal['name'],
								'value' => $attrVal['values'][$comb[$attr]]
							);
						}
					}

					return array(
						'sku' => $comb['ASIN'],
						'upc' => null,
						'price' => $this->getPrice($comb['ASIN']),
						'weight' => $weight,
						'image_index' => $this->getImageIndex(array_column($attributes, 'value')),
						'attributes' => $attributes
					);
				},
				$combinations
			);
		}

		return $combinations;
	}

	public function getFeatures() {
		static $featureGroups = array();

		if ($featureGroups) {
			return $featureGroups;
		}

		$featureTexts = $this->getValue($this->FEATURE_SELECTOR);

		if (!$featureTexts) {
			$featureTexts = $this->getValue($this->FEATURE_SELECTOR2);
		}

		$attributes = array();
		foreach ($featureTexts as $f) {
			$f = $this->escape($f);
			$f = str_replace('::', ':', $f);
			list($name, $value) = explode(':', $f . ':');

			if (strpos($name, 'Reviews') === false && strpos($name, 'Sellers Rank') === false) {
				if (!empty($name)) {
					$attributes[] = array(
						'name' => trim($name),
						'value' => trim($value)
					);
				}
			}
		}

		if ($attributes) {
			$featureGroups[] = array(
				'name' => 'General',
				'attributes' => $attributes,
			);
		} else {
			$featureGroups = $this->getFeatures2();
		}

		return $featureGroups;
	}

	public function getFeatures2() {
		$featureGroups = array();

		$sections = $this->xpath->query($this->FEATURE_SELECTOR3);

		if ($sections->length) {
			foreach ($sections as $section) {
				$attributes = array();

				$items = @$this->xpath->query('.//table[contains(@class, "prodDetTable")]/tbody/tr', $section);

				if ($items->length) {
					foreach ($items as $item) {
						$name = $this->escape(@$this->xpath->query('.//th', $item)->item(0)->nodeValue);

						if (strpos($name, 'Reviews') === false && strpos($name, 'Sellers Rank') === false) {
							$attributes[] = array(
								'name' => $name,
								'value' => $this->escape(@$this->xpath->query('.//td', $item)->item(0)->nodeValue)
							);
						}
					}
				}

				if ($attributes) {
					$name = $this->escape(@$this->xpath->query('.//div[@class="a-row"]/span/a/span', $section)->item(0)->nodeValue);

					$featureGroups[] = array(
						'name' => $name ? $name : 'General',
						'attributes' => $attributes
					);
				}
			}
		} else {
			$featureGroups = $this->getFeatures3();
		}

		return $featureGroups;
	}

	public function getFeatures3() {
		$attributes = array();

		$items = $this->xpath->query($this->FEATURE_SELECTOR4);

		if ($items->length) {
			foreach ($items as $item) {
				$name = $this->escape(@$this->xpath->query('.//th', $item)->item(0)->nodeValue);

				if (strpos($name, 'Reviews') === false && strpos($name, 'Sellers Rank') === false) {
					$attributes[] = array(
						'name' => $name,
						'value' => $this->escape(@$this->xpath->query('.//td', $item)->item(0)->nodeValue)
					);
				}
			}
		}

		if ($attributes) {
			return array(
				array(
					'name' => 'General',
					'attributes' => $attributes
				)
			);
		}

		return array();
	}

	private function escape( $text) {
		return trim(preg_replace('/[^A-Za-z0-9 _:\-\+\&\#\.\;]/', '', strip_tags($text)));
	}

	private function parseTimeStamp( $text) {
		if (!empty($text)) {
			$replaceTexts = array(
				'january' => array(
					'janeiro',
					'1月',
					'janvier',
					'gennaio',
					'enero',
					'januari',
					'كانون الثاني',
					'يناير',
					'ocak'
				),
				'february' => array(
					'fevereiro',
					'2月',
					'février',
					'februar',
					'febbraio',
					'febrero',
					'februari',
					'فبراير',
					'Şubat'
				),
				'march' => array(
					'Março',
					'3月',
					'mars',
					'marzo',
					'märz',
					'marcha',
					'maart',
					'مارس',
					'mart'
				),
				'april' => array(
					'abril',
					'4月',
					'avril',
					'aprile',
					'أبريل',
					'nisan'
				),
				'may' => array(
					'maio',
					'5月',
					'mai',
					'maggio',
					'mayo',
					'mei',
					'مايو',
					'mayıs'
				),
				'june' => array(
					'junho',
					'6月',
					'juin',
					'junio',
					'juni',
					'giugno',
					'يونيو',
					'haziran'
				),
				'july' => array(
					'julho',
					'7月',
					'juillet',
					'luglio',
					'julio',
					'juli',
					'تموز',
					'temmuz'
				),
				'august' => array(
					'agosto',
					'8月',
					'août',
					'augustus',
					'أغسطس',
					'ağustos'
				),
				'september' => array(
					'setembro',
					'9月',
					'septembre',
					'settembre',
					'septiembre',
					'سبتمبر',
					'eylül',
				),
				'october' => array(
					'outubro',
					'10月',
					'octobre',
					'oktober',
					'ottobre',
					'octubre',
					'اكتوبر',
					'ekim'
				),
				'november' => array(
					'novembro',
					'11月',
					'novembre',
					'noviembre',
					'نوفمبر',
					'kasım'
				),
				'december' => array(
					'dezembro',
					'12月',
					'décembre',
					'dezember',
					'dicembre',
					'diciembre',
					'ديسمبر',
					'aralık',
				),
			);

			$text = strtolower($text);

			foreach ($replaceTexts as $replaceWith => $find) {
				$textReplaced = str_replace($find, $replaceWith, $text);

				$splitTexts = explode(' ', $textReplaced);

				$splitTexts = array_map('trim', $splitTexts);

				$newText = '';

				if (array_intersect($splitTexts, array_keys($replaceTexts))) {
					foreach ($splitTexts as $txt) {
						if (preg_match('/^[0-9,]+$/i', $txt) || in_array(trim($txt), array_keys($replaceTexts))) {
							$newText .= $txt . ' ';
						}
					}
				}



				if (!empty($newText)) {
					return gmdate('Y-m-d H:i:s', strtotime($newText));
				}
			}
		}

		return gmdate('Y-m-d H:i:s');
	}

	public function getCustomerReviews( $maxReviews = 0, &$reviews = array(), $reviewlink = null) {
		if (!$reviews && !$reviewlink) {
			$reviewPageLinks = $this->getValue($this->REVIEW_LINK_SELECTOR);

			$reviewlink = array_shift($reviewPageLinks);

			if ($reviewlink && !filter_var($reviewlink, FILTER_VALIDATE_URL)) {
				$websiteLinks = $this->getValue('//link[@rel="canonical"]/@href');

				$websiteLink = array_shift($websiteLinks);

				$reviewlink = 'https://' . parse_url($websiteLink, PHP_URL_HOST) . $reviewlink;
			}
		}

		if ($reviewlink) {
			$content = @file_get_contents($reviewlink);

			if ($content) {
				$dom = $this->getDomObj($content);
				$xpath = new \DomXPath($dom);

				$reviewArrayObject = $xpath->query($this->REVIEW_SELECTOR);

				if ($reviewArrayObject->length) {
					$isMaxReached = false;

					foreach ($reviewArrayObject as $reviewObject) {
						$images = array();
						$videos = array();

						$imgObj = $xpath->query('.//*[@class="review-image-tile-section"]/span/a/img/@src', $reviewObject);

						if ($imgObj->length) {
							foreach ($imgObj as $img) {
								if (stripos($img->nodeValue, 'pixel') === false) {
									$images[] = str_replace('_SY88', '_SY1000', $img->nodeValue);
								}
							}
						}

						if ($xpath->query('.//video/@src', $reviewObject)->length) {
							foreach ($xpath->query('.//video/@src', $reviewObject) as $video) {
								$videos[] = $video->nodeValue;
							}
						}

						$reviews[] = array(
							'author' => @$xpath->query('.//span[@class="a-profile-name"]', $reviewObject)
								->item(0)->nodeValue,
							'title' => @$xpath->query('.//*[@data-hook="review-title"]/span', $reviewObject)
								->item(0)->nodeValue,
							'content' => trim(strip_tags(@$xpath->query('.//span[@data-hook="review-body"]', $reviewObject)
								->item(0)->nodeValue)),
							'rating' => str_replace(',', '.', substr(@$xpath->query('.//span[@class="a-icon-alt"]', $reviewObject)
								->item(0)->nodeValue, 0, 3)),
							'images' => $images,
							'videos' => $videos,
							'timestamp' => $this->parseTimeStamp(
								$xpath->query('.//span[@data-hook="review-date"]', $reviewObject)
								->item(0)->nodeValue
							)

						);

						if (0 < $maxReviews && count($reviews) >= $maxReviews) {
							$isMaxReached = true;
							break;
						}
					}

					$nextPages = $xpath->query('//link[@rel="next"]/@href');

					$nextPage = '';

					if (!$nextPages->length) {
						$nextPages = $xpath->query('//ul[@class="a-pagination"]/li[@class="a-last"]/a/@href');
						if ($nextPages->length) {
							$nextPage = $nextPages->item(0)->nodeValue;
						}
					} else {
						$nextPage = $nextPages->item(0)->nodeValue;
					}

					if ($nextPage && substr($nextPage, 0, 4) !== 'http') {
						$nextPage = 'https://' . parse_url($this->url, PHP_URL_HOST) . $nextPage;
					}

					if ($nextPage && false == $isMaxReached) {
						$this->getCustomerReviews($maxReviews, $reviews, $nextPage);
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

		$reviewArrayObject = $this->xpath->query($this->REVIEW_SELECTOR);

		if ($reviewArrayObject->length) {
			foreach ($reviewArrayObject as $reviewObject) {
				$images = array();
				$videos = array();

				$imgObj = $this->xpath->query('.//*[@class="review-image-tile-section"]/span/a/img/@src', $reviewObject);

				if ($imgObj->length) {
					foreach ($imgObj as $img) {
						if (stripos($img->nodeValue, 'pixel') === false) {
							$images[] = str_replace('_SY88', '_SY1000', $img->nodeValue);
						}
					}
				}

				if ($this->xpath->query('.//video/@src', $reviewObject)->length) {
					foreach ($this->xpath->query('.//video/@src', $reviewObject) as $video) {
						$videos[] = $video->nodeValue;
					}
				}

				$reviews[] = array(
					'author' => @$this->xpath->query('.//span[@class="a-profile-name"]', $reviewObject)
						->item(0)->nodeValue,
					'title' => @$this->xpath->query('.//*[@data-hook="review-title"]/span', $reviewObject)
						->item(0)->nodeValue,
					'content' => trim(@$this->xpath->query('.//div[@data-hook="review-collapsed"]/span', $reviewObject)
						->item(0)->nodeValue),
					'rating' => str_replace(',', '.', substr(@$this->xpath->query('.//span[@class="a-icon-alt"]', $reviewObject)
						->item(0)->nodeValue, 0, 3)),
					'images' => $images,
					'videos' => $videos,
					'timestamp' => $this->parseTimeStamp(
						$this->xpath->query('.//span[@data-hook="review-date"]', $reviewObject)
						->item(0)->nodeValue
					)
				);
			}
		}

		return $reviews;
	}

	public function getVideos() {
		$videos = array();

		if (isset($this->imageJsonArray['videos'])) {
			foreach ($this->imageJsonArray['videos'] as $video) {
				$videos[] = $video['url'];
			}
		}

		$videofromText = $this->getJson($this->content, '["mediaSourceInfo"] = [', '];');

		if ($videofromText) {
			$videoList = explode(',', str_replace(array('"', "'"), '', $videofromText));

			foreach ($videoList as $video) {
				if (trim($video)) {
					$videos[] = trim($video);
				}
			}
		}

		return array_unique($videos);
	}

	protected function getJson( $string, $start, $end, $index = 0) {
		$json = parent::getJson($string, $start, $end, $index);
		return preg_replace('/,\s*]/', ']', $json);
	}
}
