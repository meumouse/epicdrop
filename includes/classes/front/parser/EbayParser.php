<?php
/**
 * EBay data parser class
 *
 * @package: product-importer
 *
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 3.4 */

class EbayParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $content;
	private $images = array();
	private $attrJsonArray = array();
	private $TITLE_SELECTOR = '//h1';
	private $TITLE_EXTRA_SELECTOR = '//h1/span[@class="g-hdn"]';
	private $CAEGORIES_SELECTOR = '//td[@id="vi-VR-brumb-lnkLst"]/table/tbody/tr/td/ul/li/a/span';
	private $CAEGORIES_SELECTOR2 = '//li[@id="vi-VR-brumb-lnkLst"]/ul/li[@role="listitem"]/a/span';
	private $DESCRIPTION_SELECTOR = '//div[@id="viTabs_0_pd"]';
	private $DESCRIPTION_SELECTOR2 = '//iframe[@id="desc_ifr"]/@src';
	private $PRICE_SELECTOR = '//span[@itemprop="price"]/@content|//span[@id="prcIsum"]';
	private $CURRENCY_SELECTOR = '//span[@itemprop="priceCurrency"]/@content';
	private $COVER_IMAGE_SELECTOR = '//img[@id="icImg"]/@src';
	private $IMAGE_SELECTOR = '//div[@id="vi_main_img_fs"]/ul/li/button/table/tbody/tr/td/div/img/@src|//div[@id="vi_main_img_fs"]/div/div/ul/li/a/div/img/@src|//div[@id="vi_main_img_fs"]/div/div/ul/li/a/div/img/@data-img-url';
	private $SKU_SELECTOR = '//div[@id="descItemNumber"]';
	private $BRAND_SELECTOR = '//*[@itemprop="brand"]/span';
	private $FEATURE_SELECTOR = '//div[@id="viTabs_0_is"]/div/table/tbody/tr/*';
	private $FEATURE_SELECTOR2 = '//div[contains(@class, "ux-layout-section--features")]/div/div/div/div[@class="ux-labels-values__labels-content"]|//div[contains(@class, "ux-layout-section--features")]/div/div/div/div[@class="ux-labels-values__values-content"]';
	private $REVIEW_SELECTOR_LINK = '//div[@class="reviews-right"]/div[@class="reviews-header"]/a/@href';
	private $REVIEW_SELECTOR = '//div[@itemprop="review"]|//div[@itemprop="reviews"]';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content) {
		$content = str_replace("\n", '', $content);
		$content = iconv('UTF-8', 'UTF-8//IGNORE', $content);
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
		$data = array();
		$patterns = array(
			'/init\((.*?)\);/',
			'/enImgCarousel\((.*?)\);/',
			'/w1-\d+\',(.*?),0,/',
			'/w1-\d+\',(.*?)\],\[/',
			'/w1-\d+\',(.*?)\]\]/',
		);
		foreach ($patterns as $pattern) {
			preg_match_all($pattern, $this->content, $jsons);

			foreach ($jsons[1] as $json) {
				if ($json) {
					
					$d = json_decode($json, true);
					
					if (!$d) {
					
						$json = str_replace("'", '"', $json);
						$d = json_decode($json, true);
					}		
					
					if (!$d) {					
						
						$json = preg_replace('/\"watchCountMessage\"\:\".*?\"\,/s', '"watchCountMessage":null,', $json);
						$d = json_decode($json, true);
						if (!$d) {
							$json = preg_replace('/watchCountMessage[\s\S]+?priceAmountValue/', 'priceAmountValue', $json);
							$d = json_decode($json, true);
							if (!$d) {
								$json = preg_replace('/watchCountMessage[\s\S]+?convertedPrice/', 'convertedPrice', $json);
								$d = json_decode($json, true);
							}
						}
					}
					if ($d) {
						$data = array_merge($data, $d);
					}
				}
			}
		}
		$this->attrJsonArray = $data;
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
		$titles = $this->getValue($this->TITLE_SELECTOR);
		$title = array_shift($titles);

		$extras = $this->getValue($this->TITLE_EXTRA_SELECTOR);
		$extra = array_shift($extras);

		return str_replace($extra, '', $title);
	}

	public function getCategories() {
		$categories = array_unique($this->getValue($this->CAEGORIES_SELECTOR));

		if (!$categories) {
			$categories = array_unique($this->getValue($this->CAEGORIES_SELECTOR2));
		}

		return $categories;
	}

	public function getDescription() {
		$descriptions = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		return array_shift($descriptions) . $this->getDescription2();
	}

	public function getDescription2() {
		$descriptionLinks = $this->getValue($this->DESCRIPTION_SELECTOR2);
		$link = array_shift($descriptionLinks);

		if ($link) {
			$stream_context = @stream_context_create(
				array(
					'ssl'=>array(
						'verify_peer'=>false,
						'verify_peer_name'=>false,
					)
				)
			);

			$content = @file_get_contents($link, false, $stream_context);

			return $content;
		}
	}

	public function getPrice() {
		$prices = $this->getValue($this->PRICE_SELECTOR);
		return $this->sanitizePrice(array_shift($prices));
	}

	public function sanitizePrice( $priceText) {
		if (strpos($priceText, '.') !== false
			&& strpos($priceText, ',') !== false
			&& strpos($priceText, '.') < strpos($priceText, ',')
		) {
			$priceText = str_replace(array('.',','), array('','.'), $priceText);
		} elseif (strpos($priceText, '.') !== false) {
			$priceText = str_replace(',', '', $priceText);
		} else {
			$priceText = str_replace(',', '.', $priceText);
		}

		return preg_replace('/[^0-9.]/', '', $priceText);
	}

	public function getPriceCurrency() {
		$currencies = $this->getValue($this->CURRENCY_SELECTOR);
		return array_shift($currencies);
	}

	public function getCoverImage() {
		$images = $this->getValue($this->COVER_IMAGE_SELECTOR);
		return array_shift($images);
	}

	public function getImages() {
		static $images = array();
		if ($images) {
			return $images;
		}

		$this->getAttributes();

		if (!isset($this->attrJsonArray['imgArr']) || !$this->attrJsonArray['imgArr']) {
			if (isset($this->attrJsonArray['fsImgList'])) {
				$this->attrJsonArray['imgArr'] = $this->attrJsonArray['fsImgList'];
			}
		}

		if (isset($this->attrJsonArray['imgArr']) && $this->attrJsonArray['imgArr']) {
			foreach ($this->attrJsonArray['imgArr'] as $key => $imgs) {
				$url = null;

				if (isset($imgs['maxImageUrl'])) {
					$url = $imgs['maxImageUrl'];
				} elseif (isset($imgs['displayImgUrl'])) {
					$url = $imgs['displayImgUrl'];
				}

				if ($url) {
					$images[$key][] = $url;
				}
			}
		} elseif ($this->images) {
			$images = $this->images;
		} else {
			$urls = array_unique($this->getValue($this->IMAGE_SELECTOR));
			
			$urls = array_map(
				function ( $img) {
					return str_replace('l64', 'l1600', $img);
				},
				$urls
			);

			$images[] = $urls;
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

	public function getSKU() {
		$sku = $this->getValue($this->SKU_SELECTOR);
		return array_shift($sku);
	}

	public function getBrand() {
		$brand = $this->getValue($this->BRAND_SELECTOR);
		return array_shift($brand);
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

	public function getAttributes() {
		static $attrGroups = array();
		if ($attrGroups) {
			return $attrGroups;
		}

		if (isset($this->attrJsonArray['itmVarModel']['menuModels'])) {
			foreach ($this->attrJsonArray['itmVarModel']['menuModels'] as $group) {
				$attrValues = array();
				
				foreach ($group['menuItemValueIds'] as $i) {
					$attrValues[$i] = $this->attrJsonArray['itmVarModel']['menuItemMap'][$i]['valueName'];
					
					if (isset($this->attrJsonArray['itmVarModel']['menuItemMap'][$i]['thumbnailUrl'])) {
						$img = $this->attrJsonArray['itmVarModel']['menuItemMap'][$i]['thumbnailUrl'];
						if ($img) {
							$this->images[$i][] = str_replace('l64', 'l1600', $img);
						}
					}
				}
				$attrGroups[] = array(
					'name' => $group['name'],
					'is_color' => (int) ( stripos($group['name'], 'color') !== false || ( isset($group['hasPictures']) && $group['hasPictures'] ) ),
					'values' => $attrValues
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
		$attrs = $this->getAttributes();

		$combs = array();
		if (isset($this->attrJsonArray['itmVarModel']['itemVariationsMap'])) {
			$combs = $this->attrJsonArray['itmVarModel']['itemVariationsMap'];
		}
		if ($combs) {
			$combinations = array_map(
				function ( $comb) use ( $attrs) {
					$attributes = array();
					$imageIndex = 0;
					foreach ($attrs as $attrVal) {
						if (isset($comb['traitValuesMap'][$attrVal['name']])) {
							$attributes[] = array(
								'name' => $attrVal['name'],
								'value' => $attrVal['values'][$comb['traitValuesMap'][$attrVal['name']]]
							);
						}
						if ($attrVal['is_color']
							&& isset($this->attrJsonArray['itmVarModel']['menuItemMap'][$comb['traitValuesMap'][$attrVal['name']]]['thumbnailIndex'])) {
							$imageIndex = (int) $this->attrJsonArray['itmVarModel']['menuItemMap'][$comb['traitValuesMap'][$attrVal['name']]]['thumbnailIndex'];
						}
					}
					return array(
						'sku' => isset($comb['variationId']) ? $comb['variationId'] : '',
						'upc' => null,
						'price' => $comb['priceAmountValue']['value'],
						'weight' => 0,
						'image_index' => $imageIndex,
						'attributes' => $attributes
					);
				},
				$combs
			);
		}

		return $combinations;
	}

	public function getFeatures() {
		static $featureGroups = array();

		if ($featureGroups) {
			return $featureGroups;
		}

		$attributes = array();

		$features = $this->getValue($this->FEATURE_SELECTOR);

		if ($features) {
			$features = array_chunk($features, 2);

			foreach ($features as $attr) {
				$name = array_shift($attr);
				$value = array_shift($attr);
				$value = preg_replace('/\s+/S', ' ', $value);

				$attributes[] = array(
					'name' => trim(str_replace(':', '', $name)),
					'value' => trim($value)
				);
			}
		}

		if (!$attributes) {
			$attributes = $this->getFeatures2();
		}

		if ($attributes) {
			$featureGroups[] = array(
				'name' => 'General',
				'attributes' => $attributes,
			);
		}

		return $featureGroups;
	}

	public function getFeatures2() {
		$features = array();

		$arrtibutes = $this->getValue($this->FEATURE_SELECTOR2);

		for ($i = 0; $i < count($arrtibutes); $i+= 2) {
			if (isset($arrtibutes[$i+1])) {
				$features[] = array(
					'name' => trim(str_replace(':', '', $arrtibutes[$i])),
					'value' => trim($arrtibutes[$i+1])
				);
			}
		}

		return $features;
	}

	public function getCustomerReviews( $maxReviews = 0, &$reviews = array(), $reviewlink = null) {
		if (!$reviews && !$reviewlink) {
			$reviewPageLinks = $this->getValue($this->REVIEW_SELECTOR_LINK);

			$reviewlink = array_shift($reviewPageLinks);
		}

		if ($reviewlink) {
			$stream_context = @stream_context_create(
				array(
					'ssl'=>array(
						'verify_peer'=>false,
						'verify_peer_name'=>false,
					)
				)
			);

			$content = @file_get_contents($reviewlink, false, $stream_context);

			if ($content) {
				$dom = $this->getDomObj($content);
				$xpath = new \DomXPath($dom);

				$reviewArrayObject = $xpath->query($this->REVIEW_SELECTOR);

				$isMaxReached = false;

				if ($reviewArrayObject->length) {
					foreach ($reviewArrayObject as $reviewObject) {
						$author = trim(@$xpath->query('.//a[@itemprop="author"]', $reviewObject)->item(0)->nodeValue);

						if ($author) {
							$reviews[] = array(
								'author' => $author,
								'title' => @$xpath->query('.//*[@itemprop="name"]', $reviewObject)
									->item(0)->nodeValue,
								'content' => trim(@$xpath->query('.//p[@itemprop="reviewBody"]', $reviewObject)
									->item(0)->nodeValue),
								'rating' => substr(
									@$xpath->query('.//div[@class="ebay-star-rating"]/@aria-label|.//*[@itemprop="ratingValue"]/@content', $reviewObject)
									->item(0)->nodeValue,
									0,
									3
								),
								'timestamp' => gmdate(
									'Y-m-d H:i:s',
									strtotime(
										trim(
											@$xpath->query('.//span[@itemprop="datePublished"]/@content', $reviewObject)
											->item(0)->nodeValue
										)
									)
								)
							);
						}

						if (0 < $maxReviews && count($reviews) >= $maxReviews) {
							$isMaxReached = true;
							break;
						}
					}
				}

				$nextPages = $xpath->query('//a[@rel="next"]/@href');

				if ($nextPages->length && false == $isMaxReached) {
					$this->getCustomerReviews($maxReviews, $reviews, $nextPages->item(0)->nodeValue);
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
				$reviews[] = array(
					'author' => @$this->xpath->query('.//a[@itemprop="author"]', $reviewObject)
						->item(0)->nodeValue,
					'title' => @$this->xpath->query('.//*[@itemprop="name"]', $reviewObject)
						->item(0)->nodeValue,
					'content' => trim(@$this->xpath->query('.//p[@itemprop="reviewBody"]', $reviewObject)
						->item(0)->nodeValue),
					'rating' => substr(
						@$this->xpath->query('.//div[@class="ebay-star-rating"]/@aria-label|.//*[@itemprop="ratingValue"]/@content', $reviewObject)
						->item(0)->nodeValue,
						0,
						3
					),
					'timestamp' => gmdate(
						'Y-m-d H:i:s',
						strtotime(
							trim(
								@$this->xpath->query('.//span[@itemprop="datePublished"]/@content', $reviewObject)
								->item(0)->nodeValue
							)
						)
					)
				);
			}
		}

		return $reviews;
	}
}
