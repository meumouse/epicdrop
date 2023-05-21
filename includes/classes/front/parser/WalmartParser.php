<?php
/**
 * Walmart data parser class
 *
 * @package: product-importer
 *
 */
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 3.5 */

class WalmartParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $images = array();
	private $DATA_SELECTOR = '//script[@id="item"]';
	private $DATA_SELECTOR2 = '//script[@id="__NEXT_DATA__"]';
	private $UPC_SELECTOR = '//meta[@itemprop="productID"]/@content|//meta[@itemprop="productID"]/@content';
	private $SKU_SELECTOR = '//meta[@itemprop="sku"]/@content';
	private $SKU_SELECTOR1 = '//meta[@property="og:url"]/@content';
	private $DESCRIPTION_SELECTOR = '//div[@role="treeitem"]|//div[@id="product-description-section"]|//div[contains(@class, "e1mpbtcv2")]';
	private $PRICE_SELECTOR = '//h4[@itemprop="price"]|//h4[@data-testid="priceRange"]|//meta[@itemprop="price"]/@content|//span[@data-automation="buybox-price"]';
	private $CATEGORY_SELECTOR = '//div[@class="breadcrumb_container__z4iAF"]/a|//div[@class="sub-header_secondaryLeftLinks__190x9"]/a|//nav/ol/li/a';
	private $IMAGE_COVER_SELECTOR = '//img[@class="hover-zoom-hero-image"]/@src';
	private $IMAGE_COVER_SELECTOR2 = '//section[@data-testid="vertical-hero-carousel"]/div/img/@src|//div[@class="image-picker_mainImageContainer__g8rrD"]/img/@src|//div[@data-automation="image-container"]/img/@src';
	private $IMAGE_SELECTOR = '//div[@class="image-picker_thumbnails__3xRRL"]/div/div/img/@src';
	private $FEATURE_SELECTOR = '//section[@id="product-specification-section"]/div/div/div/div|//section[@id="product-specification-section"]/div/div/div';
	private $REVIEW_SELECTOR_LINK = '//a[@data-tl-id="ProductPage-see_all_reviews"]/@href';
	private $REVIEW_SELECTOR_LINK2 = '//a[@link-identifier="seeAllReviews"]/@href';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';
	private $CANONICAL_SELECTOR = '//link[@rel="canonical"]/@href';

	public function __construct( $content, $url) {
		$this->dom = $this->getDomObj($content);
		$this->url = $url;
		$content = str_replace("\n", '', $content);
		$content = iconv('UTF-8', 'UTF-8//IGNORE', $content);
		$this->content = preg_replace('/\s+/', ' ', $content);
		/* Create a new XPath object */
		$this->xpath = new \DomXPath($this->dom);

		// Set json array
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
		$jsons = $this->getValue($this->DATA_SELECTOR);
		if ($jsons) {
			$json = array_shift($jsons);
			$this->jsonDataArray = json_decode($json, true);
		}

		if (!$this->jsonDataArray) {
			$jsons = $this->getValue($this->DATA_SELECTOR2);
			if ($jsons) {
				$json = array_shift($jsons);
				$this->jsonDataArray = json_decode($json, true);
			}
		}
		
		$host = parse_url($this->url, PHP_URL_HOST);
		$upcId = $this->getValue($this->UPC_SELECTOR);
		$skuId = $this->getValue($this->SKU_SELECTOR);
		$productUrl = '';
		if ($upcId) {
			$id = array_shift($upcId);
			$productUrl = 'https://' . $host . '/api/rest/model/atg/commerce/catalog/ProductCatalogActor/getProduct?id=' . $id;
		}
		
		if ($skuId) {
			$id = array_shift($skuId);
			$productUrl = 'https://' . $host . '/api/rest/model/atg/commerce/catalog/ProductCatalogActor/getSkuSummaryDetails?skuId=' . $id;
		}
		
		if (!$this->jsonDataArray && $productUrl) {
			$jsons = $this->getContent($productUrl);
			if ($jsons) {
				$json = iconv('UTF-8', 'UTF-8//IGNORE', $jsons);
				$this->jsonDataArray = json_decode($json, true);
			}
		}
		
		if (!$this->jsonDataArray) {
			$jsons = $this->getJson($this->content, '__PRELOADED_STATE__=', ';</script>');
			if ($jsons) {
				$json = iconv('UTF-8', 'UTF-8//IGNORE', $jsons);
				$this->jsonDataArray = json_decode($json, true);
			}
		}
		
		$this->jsonDataArray['products'] = array();
		
		if (isset($this->jsonDataArray['entities']['skus']) 
			&& $this->jsonDataArray['entities']['skus']) {
				
			foreach ($this->jsonDataArray['entities']['skus'] as $key => $attrGroup) {
				$this->jsonDataArray['products'][$key]['sku'] = $key;
				if (isset($attrGroup['upc'][0])) {
					$this->jsonDataArray['products'][$key]['upc'] = $attrGroup['upc'][0];
				}
				
				if (isset($attrGroup['images'])) {
					foreach ($attrGroup['images'] as $imag) {
						if (isset($imag['enlarged']['url'])) {
							$this->images[$key][] = $imag['enlarged']['url'];
						}
					}
				}
				$sku = $this->getSKU();
				$skuId1 = $this->getValue($this->SKU_SELECTOR1);
				$siteUrl = array_pop($skuId1);
				$skuUrl = str_replace($sku, $key, $siteUrl);
				$dataHtml = $this->getContent($skuUrl);
				
				if (isset($this->jsonDataArray['entities']['variants']) && $this->jsonDataArray['entities']['variants']) {
					
					foreach ($this->jsonDataArray['entities']['variants'] as $attrName => $attrGroups) {
						$values = array_intersect(array_column($attrGroups, 'value'), $attrGroup['variants']);
						if ($values) {
							
							$this->jsonDataArray['products'][$key]['attributes'][] = array(
								'name' => $attrName,
								'value' => current($values)
							);
						}
					}
				}
				
				if ($dataHtml) {
					$dom = $this->getDomObj($dataHtml);
					$xpath = new \DomXPath($dom);
					$priceVar = $this->getValue($this->PRICE_SELECTOR, false, $xpath);
					$combPrice = array_shift($priceVar);
					
					if ($combPrice) {
						$this->jsonDataArray['products'][$key]['price'] = preg_replace('/[^0-9.]/', '', $combPrice);
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
		if (isset($this->jsonDataArray['item']['product']['midasContext']['query'])) {
			return $this->jsonDataArray['item']['product']['midasContext']['query'];
		} elseif (isset($this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['name'])) {
			return $this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['name'];
		} elseif (isset($this->jsonDataArray['product']['displayName'])) {
			return $this->jsonDataArray['product']['displayName'];
		} elseif (isset($this->jsonDataArray['skuDisplayNameText'])) {
			return $this->jsonDataArray['skuDisplayNameText'];
		} elseif (isset($this->jsonDataArray['product']['item']['name'])) {
			return current($this->jsonDataArray['product']['item']['name']);
		}
		return '';
	}

	public function getCategories() {
		$categories = $this->getValue($this->CATEGORY_SELECTOR);
		if (isset($this->jsonDataArray['item']['product']['midasContext']['categoryPathName'])) {
			return array_unique(
				explode('/', $this->jsonDataArray['item']['product']['midasContext']['categoryPathName'])
			);
		} elseif (isset($this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['category']['path'])) {
			return array_filter(
				array_column(
					$this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['category']['path'],
					'name'
				)
			);
		} elseif ($categories) {
			return array_filter($categories);
		}
	}

	public function getShortDescription() {
		if (isset($this->jsonDataArray['item']['product']['buyBox']['products'][0]['shortDescription'])) {
			return $this->jsonDataArray['item']['product']['buyBox']['products'][0]['shortDescription'];
		} elseif (isset($this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['shortDescription'])) {
			return $this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['shortDescription'];
		} elseif (isset($this->jsonDataArray['product']['description'])) {
			return $this->jsonDataArray['product']['description'];
		} elseif (isset($this->jsonDataArray['seoDetailsOutput']['seoDescription'])) {
			return $this->jsonDataArray['seoDetailsOutput']['seoDescription'];
		} elseif (isset($this->jsonDataArray['product']['item']['description'])) {
			return $this->jsonDataArray['product']['item']['description'];
		}
		return '';
	}

	public function getDescription() {
		
		$descriptions = $this->getValue($this->DESCRIPTION_SELECTOR, true);

		if ($descriptions) {
			return array_shift($descriptions);
		}
  
		if (isset($this->jsonDataArray['item']['product']['buyBox']['products'][0]['idmlSections']['idmlShortDescription'])) {
			return $this->jsonDataArray['item']['product']['buyBox']['products'][0]['idmlSections']['idmlShortDescription'];
		}

		if (isset($this->jsonDataArray['item']['product']['buyBox']['products'][0]['detailedDescription'])) {
			return $this->jsonDataArray['item']['product']['buyBox']['products'][0]['detailedDescription'];
		}

		if (isset($this->jsonDataArray['props']['pageProps']['initialData']['data']['idml']['longDescription'])) {
			return $this->jsonDataArray['props']['pageProps']['initialData']['data']['idml']['longDescription'];
		}
		
		if (isset($this->jsonDataArray['product']['longDescription'])) {
			return $this->jsonDataArray['product']['longDescription'];
		}
		
		if (isset($this->jsonDataArray['longDescription'])) {
			return $this->jsonDataArray['longDescription'];
		}

		return '';
	}

	public function getPrice() {

		$price = $this->getValue($this->PRICE_SELECTOR);

		if (isset($this->jsonDataArray['item']['product']['midasContext']['price'])) {
			return $this->jsonDataArray['item']['product']['midasContext']['price'];
		} elseif (isset($this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['priceInfo']['currentPrice']['price'])) {
			return $this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['priceInfo']['currentPrice']['price'];
		} elseif (isset($this->jsonDataArray['product']['priceRanges']['minVariancePrice']['currencyAmount'])) {
			return $this->jsonDataArray['product']['priceRanges']['minVariancePrice']['currencyAmount'];
		} elseif ($price) {
			return preg_replace('/[^0-9.]/', '', array_shift($price));
		}
		return 0;
	}

	public function getSKU() {
		$upcId = $this->getValue($this->UPC_SELECTOR);
		$upcId = array_shift($upcId);
		$skuId = $this->getValue($this->SKU_SELECTOR);
		$skuId = array_shift($skuId);
		$skuId1 = $this->getValue($this->SKU_SELECTOR1);
		$skuId2 = array_pop($skuId1);
		$skuI = explode('/', $skuId2);
		$skuId = array_pop($skuI);
		if (isset($this->jsonDataArray['item']['product']['midasContext']['productId'])) {
			return $this->jsonDataArray['item']['product']['midasContext']['productId'];
		} elseif (isset($this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['usItemId'])) {
			return $this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['usItemId'];
		} elseif ($upcId) {
			return $upcId;
		} else {
			return $skuId;
		}
		return '';
	}

	public function getUPC() {
		$upcId = $this->getValue($this->UPC_SELECTOR);
		$upcId = array_shift($upcId);
		$skuId = $this->getValue($this->SKU_SELECTOR);
		$skuId = array_shift($skuId);
		$upc = $this->getJson($this->content, 'upc":["', '"],"');
		if (isset($this->jsonDataArray['item']['product']['buyBox']['products'][0]['upc'])) {
			return $this->jsonDataArray['item']['product']['buyBox']['products'][0]['upc'];
		} elseif (isset($this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['upc'])) {
			return $this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['upc'];
		} elseif ($upcId) {
			return $upcId;
		} elseif ($upc) {
			return $upc;
		} else {
			return $skuId;
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['item']['product']['midasContext']['brand'])) {
			return $this->jsonDataArray['item']['product']['midasContext']['brand'];
		} elseif (isset($this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['brand'])) {
			return $this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['brand'];
		} elseif (isset($this->jsonDataArray['product']['brand'])) {
			return $this->jsonDataArray['product']['brand'];
		} elseif (isset($this->jsonDataArray['entities']['skus'])) {
			$skuAttr = current($this->jsonDataArray['entities']['skus']);
			return $skuAttr['brand']['name'];
		}
		
		return '';
	}

	public function getCoverImage() {
		$images = $this->getValue($this->IMAGE_COVER_SELECTOR);

		if ($images) {
			return 'https:' . strstr(array_shift($images), '?', true);
		}

		$images = $this->getValue($this->IMAGE_COVER_SELECTOR2);

		if ($images) {
			return array_shift($images);
		} else {
			$skuId = $this->getValue($this->SKU_SELECTOR);

			if ($skuId) {
				return 'https://res.cloudinary.com/walmart-labs/image/upload/w_960,dpr_auto,f_auto,q_auto:best/gr/images/product-images/img_large/' . array_shift($skuId) . 'L.jpg';
			}
		}
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}
		$images = $this->images;

		if (isset($this->jsonDataArray['item']['product']['buyBox']['images'])
			&& $this->jsonDataArray['item']['product']['buyBox']['images']) {
			foreach ($this->jsonDataArray['item']['product']['buyBox']['images'] as $key => $imgs) {
				foreach ($imgs as $img) {
					$images[$key][] = $img['url'];
				}
			}
		}
		if (!$images) {
			if (isset($this->jsonDataArray['item']['product']['buyBox']['products'][0]['images'])) {
				foreach ($this->jsonDataArray['item']['product']['buyBox']['products'][0]['images'] as $img) {
					$images[0][] = $img['url'];
				}
			}
		}

		if (!$images && isset($this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['variantsMap'])
			&& $this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['variantsMap']) {
			foreach ($this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['variantsMap'] as $comb) {
				$imageIndex = $comb['id'];

				if (isset($comb['variants']) && $comb['variants']) {
					foreach ($comb['variants'] as $v) {
						if (strpos($v, 'actual_color') !== false) {
							$imageIndex = $v;
							break 1;
						}
					}
				}

				if (isset($comb['imageInfo']['allImages'])
					&& $comb['imageInfo']['allImages']) {
					foreach ($comb['imageInfo']['allImages'] as $img) {
						$images[$imageIndex][] = $img['url'];
					}
				}
			}
		}

		if (!$images && isset($this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['imageInfo']['allImages'])) {
			foreach ($this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['imageInfo']['allImages'] as $img) {
				if ($img['url']) {
					$images[0][] = $img['url'];
				}
			}
		}
		
		if (!$images && isset($this->jsonDataArray['availableVariances'])) {
			foreach ($this->jsonDataArray['availableVariances'] as $varImg) {
				foreach ($varImg['varianceValuesMap'] as $key => $imgs) {
					foreach ($imgs['applicableSkus'] as $img) {
						if ($img) {
							$images[$key][] = 'https://res.cloudinary.com/walmart-labs/image/upload/w_960,dpr_auto,f_auto,q_auto:best/gr/images/product-images/img_large/' . $img . 'L.jpg';
						}
					}
				}
			}
		} else {
			$imgs = $this->getValue($this->IMAGE_SELECTOR);
			if ($imgs) {
				$images[0] = str_replace(array('w_225', 'm.', 'm1', 'm2', 'm3',  'img_medium'), array('w_960', 'L.', 'L1', 'L2', 'L3', 'img_large'), $imgs);
			}
		}
		
		if (!$images && isset($this->jsonDataArray['product']['variantSkuOptions'])) {
			foreach ($this->jsonDataArray['product']['variantSkuOptions'] as $key => $img) {
				if ($img['images']['large']) {
					$images[$key][] = 'https://www.walmart.com.mx' . $img['images']['large'];
				}
				if (isset($img['secondaryImages'])) {
					foreach ($img['secondaryImages'] as $imgloop) {
						$images[$key][] = 'https://www.walmart.com.mx' . $imgloop['large'];
					}
				}
			}
		} elseif (!$images && isset($this->jsonDataArray['product']['childSKUs'])) {
			foreach ($this->jsonDataArray['product']['childSKUs'] as $key => $img) {
				if ($img['images']['large']) {
					$images[$key][] = 'https://www.walmart.com.mx' . $img['images']['large'];
				}
				if (isset($img['secondaryImages'])) {
					foreach ($img['secondaryImages'] as $imgloop) {
						$images[$key][] = 'https://www.walmart.com.mx' . $imgloop['large'];
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

		if (isset($this->jsonDataArray['item']['product']['buyBox']['criteria'])
			&& $this->jsonDataArray['item']['product']['buyBox']['criteria']) {
			foreach ($this->jsonDataArray['item']['product']['buyBox']['criteria'] as $attrGroup) {
				$attrValues = array();
				foreach ($attrGroup['values'] as $attr) {
					if (isset($attr['images'])) {
						$attrValues[$attr['images']] = $attr['title'];
					} else {
						$attrValues[] = $attr['title'];
					}
				}

				$attrGroups[] = array(
					'name' => $attrGroup['name'],
					'is_color' => ( stripos($attrGroup['name'], 'color') !== false ) ? 1 : 0,
					'values' => $attrValues
				);
			}
		}

		if (!$attrGroups
			&& isset($this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['variantCriteria'])
			&& $this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['variantCriteria']) {
			foreach ($this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['variantCriteria'] as $attrGroup) {
				$attrValues = array();
				foreach ($attrGroup['variantList'] as $attr) {
					$attrValues[$attr['id']] = $attr['name'];
				}

				$attrGroups[] = array(
					'name' => $attrGroup['name'],
					'is_color' => ( stripos($attrGroup['name'], 'color') !== false ) ? 1 : 0,
					'values' => $attrValues
				);
			}
		}
		
		if (!$attrGroups && isset($this->jsonDataArray['availableVariances'])) {
			foreach ($this->jsonDataArray['availableVariances'] as $attrGroup) {
				$attrValues = array();
				foreach ($attrGroup['varianceValuesMap'] as $attr) {
					$attrValues[$attr['varianceValue']] = $attr['varianceValue'];
				}

				$attrGroups[] = array(
					'name' => $attrGroup['varianceName'],
					'is_color' => ( stripos($attrGroup['varianceName'], 'color') !== false ) ? 1 : 0,
					'values' => $attrValues
				);
			}
		}
		
		if (!$attrGroups && isset($this->jsonDataArray['product']['availableVariances'])) {
			foreach ($this->jsonDataArray['product']['availableVariances'] as $attrGroup) {
				$attrValues = array();
				foreach ($attrGroup['varianceValuesMap'] as $attr) {
					$attrValues[$attr['varianceValue']] = $attr['varianceValue'];
				}

				$attrGroups[] = array(
					'name' => $attrGroup['varianceName'],
					'is_color' => ( stripos($attrGroup['varianceName'], 'Código de la Talla') !== false ) ? 1 : 0,
					'values' => $attrValues
				);
			}
		}
		
		if (isset($this->jsonDataArray['products']) && $this->jsonDataArray['products']) {
			foreach ($this->jsonDataArray['products'] as $attrs) {
				if (isset($attrs['attributes']) && $attrs['attributes']) {
					foreach ($attrs['attributes'] as $attrVals) {
				
						$key = base64_encode($attrVals['name']);
				
						if (!isset($attrGroups[$key])) {
							$attrGroups[$key] = array(
							'name' => $attrVals['name'],
							'is_color' => stripos($attrVals['name'], 'Colour') !== false ? 1 : 0,
							'values' => array()
							);
						}
					
						if (!in_array($attrVals['value'], $attrGroups[$key]['values'])) {
							$attrGroups[$key]['values'][] = $attrVals['value'];
						}
					}
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
		$attrs = $this->getAttributes();
		$sku = $this->getSKU();
		$upc = $this->getUPC();

		if (isset($this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['variantsMap'])
			&& $this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['variantsMap']) {
			foreach ($this->jsonDataArray['props']['pageProps']['initialData']['data']['product']['variantsMap'] as $comb) {
				$imageIndex = $comb['id'];

				$attrVals = array();
				foreach ($comb['variants'] as $var) {
					foreach ($attrs as $attr) {
						if (in_array($var, array_keys($attr['values']))) {
							$attrVals[] = array(
								'name' => $attr['name'],
								'value' => $attr['values'][$var],
							);
							break 1;
						}
					}

					if (strpos($var, 'actual_color') !== false) {
						$imageIndex = $var;
					}
				}
				if (isset($comb['priceInfo']['currentPrice']['price'])
					&& $comb['priceInfo']['currentPrice']['price']) {
					$price = (float) $comb['priceInfo']['currentPrice']['price'];
				}

				$combinations[] = array(
					'sku' => $comb['id'],
					'upc' => 0,
					'price' => $price,
					'weight' => $weight,
					'image_index' => $imageIndex,
					'attributes' => $attrVals
				);
			}
		} elseif (isset($this->jsonDataArray['products']) && $this->jsonDataArray['products']) {
			foreach ($this->jsonDataArray['products'] as $keys => $attrVals) {
				if (isset($attrVals['attributes'])) {
					$combinations[] = array(
						'sku' => isset($attrVals['sku']) ? $attrVals['sku'] : $sku,
						'upc' => isset($attrVals['upc']) ? $attrVals['upc'] : $upc,
						'price' => isset($attrVals['price']) ? $attrVals['price'] : $price,
						'weight' => isset($attrVals['weight']) ? $attrVals['weight'] : $weight,
						'image_index' => $keys,
						'attributes' => $attrVals['attributes']
					);
				}
			}
		} else {
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
						'sku' => null,
						'upc' => 0,
						'price' => $price,
						'weight' => $weight,
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

	public function getWeight() {
		static $weight = array();

		if ($weight) {
			return $weight;
		}

		$features = $this->getFeatures();

		if ($features) {
			foreach ($features as $feature) {
				foreach ($feature['attributes'] as $attr) {
					if (in_array($attr['name'], array('weight', 'Peso')) !== false) {
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
		if (isset($this->jsonDataArray['item']['product']['buyBox']['products'][0]['idmlSections']['specifications'])) {
			$attributes = $this->jsonDataArray['item']['product']['buyBox']['products'][0]['idmlSections']['specifications'];
		} elseif (isset($this->jsonDataArray['props']['pageProps']['initialData']['data']['idml']['specifications'])) {
			$attributes = $this->jsonDataArray['props']['pageProps']['initialData']['data']['idml']['specifications'];
		} elseif (isset($this->jsonDataArray['product']['childSKUs'][0]['dynamicFacets'])) {
			
			foreach ($this->jsonDataArray['product']['childSKUs'][0]['dynamicFacets'] as $attri) {
				if (isset($attri['value'])) {
					$attributes[] = array(
						'name' => $attri['attrName'],
						'value' => trim($attri['value'])
					);
				}
			}
		} elseif (isset($this->jsonDataArray['attributesMap'])) {
			
			foreach ($this->jsonDataArray['attributesMap'] as $attri) {
				if (isset($attri['value'])) {
					$attributes[] = array(
						'name' => $attri['attrName'],
						'value' => trim($attri['value'])
					);
				}
			}
		}
		
		if (!$attributes) {
			
			if (isset($this->jsonDataArray['entities']['skus'])) {
				
				$skus = current($this->jsonDataArray['entities']['skus']);
				
				if (isset($skus['endecaDimensions'])) {
					
					foreach ($skus['endecaDimensions'] as $attri) {
						if (isset($attri['value'])) {
							$attributes[] = array(
								'name' => $attri['name'],
								'value' => trim($attri['value'])
							);
						}
					}
				}
			}
		}
			
		if (!$attributes) {
			$features = $this->xpath->query($this->FEATURE_SELECTOR);
			
			if ($features->length) {
				
				foreach ($features as $speci) {
					$name = @$this->xpath->query('.//div[1]', $speci)->item(0)->nodeValue;
					$value = @$this->xpath->query('.//div[2]', $speci)->item(0)->nodeValue;
					if ($value) {
						$attributes[] = array(
							'name' => trim($name),
							'value' => preg_replace('/\s+/', ' ', $value)
						);
					}
				}
			} else {
			
				$features = $this->xpath->query($this->FEATURE_SELECTOR . '/table/tbody/tr');
				if ($features->length) {
					
					foreach ($features as $speci) {
						
						$attr = @$this->xpath->query('.//th|td', $speci);
						
						if ($attr->length >= 2) {
							
							$value = $attr->item(1)->nodeValue;
							
							if ($value) {
								$attributes[] = array(
									'name' => trim($attr->item(0)->nodeValue),
									'value' => preg_replace('/\s+/', ' ', $value)
								);
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

	public function getCustomerReviews( $maxReviews = 0, &$reviews = array(), $reviewlink = null) {
		$host = parse_url($this->url, PHP_URL_HOST);
		
		if (!$reviews && !$reviewlink) {
			$reviewPageLinks = $this->getValue($this->REVIEW_SELECTOR_LINK);

			if (!$reviewPageLinks) {
				$reviewPageLinks = $this->getValue($this->REVIEW_SELECTOR_LINK2);
			}

			if (!$reviewPageLinks) {
				$canonicalLinks = $this->getValue($this->CANONICAL_SELECTOR);
				if ($canonicalLinks) {
					$canonicalLink = array_shift($canonicalLinks);
					$reviewPageLinks[] = '/reviews/product/' . preg_replace('/[^0-9]/', '$1', $canonicalLink);
				}
			}

			if ($reviewPageLinks) {
				$reviewlink = 'https://' . $host . array_shift($reviewPageLinks);
				$this->reviewlink = $reviewlink;
			}
		}

		if ($reviewlink) {
			$content = @file_get_contents($reviewlink);

			if ($content) {
				$json = $this->getJson($content, '__WML_REDUX_INITIAL_STATE__ = ', ';<');

				if (!$json) {
					$jsons = $this->getValue($this->DATA_SELECTOR2);
					$json = array_shift($jsons);
				}

				$reviewArray = json_decode($json, true);

				$isMaxReached = false;

				$nextPages = '';

				if (isset($reviewArray['product']['primaryProduct']) && isset($reviewArray['reviews'][$reviewArray['product']['primaryProduct']]['customerReviews'])) {
					$this->setReviewData(
						$reviews,
						$reviewArray['reviews'][$reviewArray['product']['primaryProduct']]['customerReviews']
					);

					if (isset($reviewArray['reviews'][$reviewArray['product']['primaryProduct']]['pagination']['next']['url'])
						&& $reviewArray['reviews'][$reviewArray['product']['primaryProduct']]['pagination']['next']['url']
					) {
						$nextPages = $this->reviewlink . '?' . $reviewArray['reviews'][$reviewArray['product']['primaryProduct']]['pagination']['next']['url'];
					}
				} elseif (isset($reviewArray['props']['pageProps']['initialData']['data']['reviews']['customerReviews'])) {
					$this->setReviewData(
						$reviews,
						$reviewArray['props']['pageProps']['initialData']['data']['reviews']['customerReviews']
					);

					if (isset($reviewArray['props']['pageProps']['initialData']['data']['reviews']['pagination']['next']['url'])) {
						$nextPages = $this->reviewlink . '?' . $reviewArray['props']['pageProps']['initialData']['data']['reviews']['pagination']['next']['url'];
					}
				}

				if (0 < $maxReviews && count($reviews) >= $maxReviews) {
					$isMaxReached = true;
				}

				if ($nextPages && false == $isMaxReached) {
					$this->getCustomerReviews($maxReviews, $reviews, $nextPages);
				}
			}
		}

		if (!$reviews) {
			$reviews = $this->getCustomerReviews2();
		}

		return $reviews;
	}
	
	protected function setReviewData( &$reviews, $customerReviews) {
		foreach ($customerReviews as $review) {
			if (isset($review['reviewText']) && $review['reviewText']) {
				$reviews[] = array(
					'author' => isset($review['userNickname']) ? $review['userNickname'] : '',
					'title' => isset($review['reviewTitle']) ? $review['reviewTitle'] : '',
					'content' => $review['reviewText'],
					'rating' => $review['rating'],
					'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['reviewSubmissionTime']))
				);
			}
		}
	}

	public function getCustomerReviews2() {
		$reviews = array();

		if (isset($this->jsonDataArray['item']['product']['reviews']['customerReviews'])) {
			$this->setReviewData(
				$reviews,
				$this->jsonDataArray['item']['product']['reviews']['customerReviews']
			);
		} elseif (isset($this->jsonDataArray['props']['pageProps']['initialData']['data']['reviews']['customerReviews'])) {
			$this->setReviewData(
				$reviews,
				$this->jsonDataArray['props']['pageProps']['initialData']['data']['reviews']['customerReviews']
			);
		}
		if (!$reviews) {
			$reviews = $this->getCustomerReviews3();
		}
		return $reviews;
	}

	public function getCustomerReviews3( $maxReviews = 0, &$reviews = array(), $offset = 0) {
		$maxReviews = $maxReviews ? $maxReviews : 100;
		if (!$reviews) {
			$id = $this->getSKU();
			$this->reviewLink = 'https://api.bazaarvoice.com/data/batch.json?resource.q0=reviews&filter.q0=productid:eq:' . $id . '&filter.q0=contentlocale:eq:en_CA,en_GB,en_US,en_CA&filter.q0=isratingsonly:eq:false&filter_reviews.q0=contentlocale:eq:en_CA,en_GB,en_US,en_CA&include.q0=authors,products&filteredstats.q0=reviews&limit.q0=' . $maxReviews . '&sort.q0=submissiontime:desc&passkey=e6wzzmz844l2kk3v6v7igfl6i&apiversion=5.5&displaycode=2036-en_ca';
		}	

		$reviewLink = $this->reviewLink;
		$reviewLink .= '&offset.q0=' . $offset;
		
		if ($reviewLink) {
			$json = $this->getContent($reviewLink);
			if ($json) {
				$reviewData = json_decode($json, true);
				$isMaxReached = false;
				if (isset($reviewData['BatchedResults']['q0']['Results']) 
					&& $reviewData['BatchedResults']['q0']['Results']) {
					foreach ($reviewData['BatchedResults']['q0']['Results'] as $review) {
						$videos = array(); 
						$images = array(); 
						if (isset($review['Photos']) && $review['Photos']) {
							foreach ($review['Photos'] as $img) {
								$images[] = $img['Sizes']['normal']['Url']; 
							}
						}
						
						$reviews[] = array(
							'author' => $review['UserNickname'],
							'title' => $review['Title'],
							'images' => $images,
							'videos' => $videos,
							'content' => $review['ReviewText'],
							'rating' => (int) $review['Rating'],
							'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['SubmissionTime']))
						);

						if (0 < $maxReviews && count($reviews) >= $maxReviews) {
							$isMaxReached = true;
							break;
						}
					}

					if (false == $isMaxReached) {
						$this->getCustomerReviews($maxReviews, $reviews, $offset+20);
					}
				}
			}
		}
		return $reviews;
	}
}
