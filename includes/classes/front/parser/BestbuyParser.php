<?php
/**
 * Bestbuy data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class BestbuyParser extends AbstractParser {

	private $url;
	private $dom;
	private $priceVar = array();
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $ID_SELECTOR = '//div[contains(@id, "shop-product-variations-")]/@id';
	private $JSON_DATA_SELECTOR = '//script[@type="application/ld+json"]';
	private $TITLE_SELECTOR = '//h1[@class="productName_2KoPa"]';
	private $CATEGORY_SELECTOR = '//ol[@data-automation="breadcrumb-container"]/li/a/span';
	private $SHORT_DESCRIPTION_SELECTOR = '//div[@class="description_2Qiri"]';
	private $DESCRIPTION_SELECTOR = '//div[@class="overview-accordion-content-wrapper"]|//div[@class="moreInformation_1u1Nn"]';
	private $PRICE_SELECTOR = '//span[@data-automation="product-price"]/span|//div[contains(@class, "priceView-hero-price")]/span[1]';
	private $SKU_SELECTOR = '//div[@class="modelInformationContainer_20_bg"]/div';
	private $BRAND_SELECTOR = '//script/@data-flix-brand';
	private $VIDEO_SELECTOR = '//video[@class="video-player"]/source/@src';
	private $IMAGE_VR_SELECTOR = '//div[contains(@class, "variation-carousel")]/div/div/div/ul/li';
	private $IMAGE_SELECTOR = '//div[@class="displayingImage_3xp0y"]/img/@src|//li[@class="image-thumbnail"]/div/button/img/@src';
	private $FEATURE_SELECTOR_VL_NM = '//div[@id="detailsAndSpecs"]/div/div';
	private $ATTRIBUTE_SELECTOR = '//div[@class="shop-variation-wrapper"]';
	private $SELECTED_COLOR_SELECTOR = '//div[@class="hover-name"]';
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
				CURLOPT_HTTPHEADER => array(
					'cache-control: no-cache',
					'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.74 Safari/537.36'
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
		$id = $this->getValue($this->ID_SELECTOR);
		$id = array_shift($id);

		$json = $this->getJson($this->content, '__INITIAL_STATE__ = ', '; </script>');
		$jsons = $this->getJson($this->content, '{\"customerId\":\"\",', '"; window.getInitializer ? getI');
		$jsons = '{\"app\":	{\"customerId\":\"\",' . $jsons;

		$jsons = stripslashes($jsons);
		if ($jsons) {
			$jsons = iconv('UTF-8', 'UTF-8//IGNORE', $jsons);
			$this->jsonDataArray = json_decode($jsons, true);
		} elseif ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = json_decode($json, true);
		}

		$jsons = $this->getValue($this->JSON_DATA_SELECTOR);

		if ($jsons) {
			foreach ($jsons as $json) {
				$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
				$data = json_decode($json, true);
				if ($data) {
					if (!$this->jsonDataArray) {
						$this->jsonDataArray = array();
					}
					$this->jsonDataArray = array_merge($this->jsonDataArray, $data);
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
		if (isset($this->jsonDataArray['name'])) {
			return $this->jsonDataArray['name'];
		} elseif ($title) {
			return $title;
		}
		return '';
	}

	public function getCategories() {
		$categories = $this->getValue($this->CATEGORY_SELECTOR);

		if (!$categories && isset($this->jsonDataArray['itemListElement']) && $this->jsonDataArray['itemListElement']) {
			foreach ($this->jsonDataArray['itemListElement'] as $categary) {
				if (isset($categary['item']['name'])) {
					$categories[] = $categary['item']['name'];
				}
			}
		}
		return $categories;
	}

	public function getShortDescription() {
		$description = $this->getValue($this->SHORT_DESCRIPTION_SELECTOR, true);
		$description = array_shift($description);
		if (isset($this->jsonDataArray['description'])) {
			return $this->jsonDataArray['description'];
		} elseif ($description) {
			return $description;
		}
		return '';
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
		$price = $this->getValue($this->PRICE_SELECTOR);
		$price = array_shift($price);
		if ($price) {
			$price = str_replace(',', '.', $price);
			return preg_replace('/[^0-9.]/', '', $price);
		}
	}

	public function getSKU() {
		$sku = $this->getValue($this->SKU_SELECTOR);
		$sku = array_pop($sku);
		if ($sku) {
			return preg_replace('/[^0-9]/', '', $sku);
		} elseif (isset($this->jsonDataArray['sku'])) {
			return $this->jsonDataArray['sku'];
		}
	}

	public function getUPC() {
		$upc = $this->getValue($this->SKU_SELECTOR);
		$upc = array_shift($upc);
		if ($upc) {
			return preg_replace('/[^0-9]/', '', $upc);
		} elseif (isset($this->jsonDataArray['gtin13'])) {
			return $this->jsonDataArray['gtin13'];
		}
	}

	public function getBrand() {
		$brand = $this->getValue($this->BRAND_SELECTOR);
		$brand = array_shift($brand);
		if ($brand) {
			return $brand;
		} elseif (isset($this->jsonDataArray['brand']['name'])) {
			return $this->jsonDataArray['brand']['name'];
		}
	}

	public function getWeight() {
		$weight = array();
		if (isset($this->jsonDataArray['weight'])) {
			$weight = array(
				'unit' => preg_replace('/[^a-zA-Z]/', '', $this->jsonDataArray['weight']),
				'value' => preg_replace('/[^0-9.]/', '', $this->jsonDataArray['weight'])
			);
		}
		return $weight;
	}

	public function getDimension() {
		static $dimensions = array();
		$length = 0;
		$width = 0;
		$height = 0;
		if (isset($this->jsonDataArray['depth']['value'])) {
			$length = $this->jsonDataArray['depth']['value'];
		}
		if (isset($this->jsonDataArray['width']['value'])) {
			$width = $this->jsonDataArray['width']['value'];
		}
		if (isset($this->jsonDataArray['height']['value'])) {
			$height = $this->jsonDataArray['height']['value'];
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
		if (isset($this->jsonDataArray['image'])) {
			return $this->jsonDataArray['image'];
		}
		return '';
	}

	public function getVideos() {
		$videos = array();
		$sku = $this->getSKU();
		$url ='https://www.bestbuy.com/api/tcfb/model.json?paths=%5B%5B%22shop%22%2C%22videoDomain%22%2C%22v1%22%2C%22videometadatas%22%2C%22skus%22%2C' . $sku . '%2C%22pageSizes%22%2C100%2C%22results%22%2C0%2C%5B%22title%22%2C%22transcriptExists%22%2C%22videoDirectLink%22%5D%5D%5D&method=get';
		$urlJson = str_replace('skus%22%2C6487430%2C%22pageSizes', 'skus%22%2C' . $sku . '%2C%22pageSizes', $url);

		if ($urlJson) {
			$json = $this->getContent($urlJson);
			$urlData = json_decode($json, true);
			if (isset($urlData['jsonGraph']['shop']['videoDomain']['v1']['videometadatas']['skus'][$sku]['pageSizes'][100]['results']) &&
			$urlData['jsonGraph']['shop']['videoDomain']['v1']['videometadatas']['skus'][$sku]['pageSizes'][100]['results']) {
				foreach ($urlData['jsonGraph']['shop']['videoDomain']['v1']['videometadatas']['skus'][$sku]['pageSizes'][100]['results'] as $video) {
					if (isset($video['videoDirectLink']['value'])) {
						$videos[] = $video['videoDirectLink']['value'];
					}
				}
			}
		}
		return $videos;
	}

	public function getImages() {
		static $images = array();
		if ($images) {
			return $images;
		}
		$attrs = $this->getAttributes();
		$colors = array();
		foreach ($attrs as $attribute) {
			if ($attribute['is_color']) {
				$colors = $attribute['values'];
				break;
			}
		}

		$ImageLiArr = $this->xpath->query($this->IMAGE_VR_SELECTOR);
		$selectColor = $this->getValue($this->SELECTED_COLOR_SELECTOR);
		$selectColor = array_filter($selectColor);
		$selectColor = array_shift($selectColor);
		if (isset($this->jsonDataArray['brand']['dynamicContent']['sections']) && $this->jsonDataArray['brand']['dynamicContent']['sections']) {
			foreach ($this->jsonDataArray['brand']['dynamicContent']['sections'] as $imgs) {
				if (isset($imgs['items'][0]['values']['productVariantsUrl']) && $imgs['items'][0]['values']['productVariantsUrl']) {
					$imgUrl = $imgs['items'][0]['values']['productVariantsUrl'];
					$imageDataArray = $this->getContent($imgUrl);
					$imageDataArray = json_decode($imageDataArray, true);

					foreach ($imageDataArray as $key => $imgloop) {
						if (isset($imgloop['images'])) {
							$images[$key] = $imgloop['images'];
						}
					}
				}
			}
		} elseif ($ImageLiArr->length) {
			$imgLinks = $this->getValue($this->IMAGE_SELECTOR);
			$colorIndex = array_search($selectColor, $colors);
			$images[$colorIndex] = str_replace(';maxHeight=120;maxWidth=120', '', $imgLinks);

			$price = $this->getValue($this->PRICE_SELECTOR);
			$price = array_shift($price);
			$this->priceVar[$colorIndex] = preg_replace('/[^0-9.]/', '', $price);

			foreach ($ImageLiArr as $key => $imgAnchors) {
				$dataVarlink = $this->xpath->query('.//a/@href', $imgAnchors);
				if ($dataVarlink->length) {
					$link = $dataVarlink->item(0)->nodeValue;
					if (( stripos($link, '#') !== false )) {
						$link = str_replace('#', '&intl=nosplash#', $link);
					} else {
						$link .= '&intl=nosplash';
					}
					$dataHtml = $this->getContent($link);
					if ($dataHtml) {
						$dom = $this->getDomObj($dataHtml);
						$xpath = new \DomXPath($dom);
						$imgLink = $this->getValue($this->IMAGE_SELECTOR, false, $xpath);
						$images[$key] = str_replace(';maxHeight=120;maxWidth=120', '', $imgLink);
						$price = $this->getValue($this->PRICE_SELECTOR, false, $xpath);
						$price = array_shift($price);
						if ($price) {
							$this->priceVar[$key] = preg_replace('/[^0-9.]/', '', $price);
						}
					}
				}
			}
		} else {
			$imgLink = $this->getValue($this->IMAGE_SELECTOR);
			$images[0] = str_replace(array('500x500', '100x100', ';maxHeight=120;maxWidth=120'), array('1500x1500', '1500x1500', ''), $imgLink);
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
		$featureArrayObject = $this->xpath->query($this->FEATURE_SELECTOR_VL_NM);
		$sku = $this->getSKU();
		$url = 'https://www.bestbuy.com/api/tcfb/model.json?paths=%5B%5B%22shop%22%2C%22magellan%22%2C%22v2%22%2C%22hierarchy%22%2C%22skus%22%2C5981131%2C%22hierarchies%22%2C%22bbypres%22%2C0%2C%22id%22%5D%2C%5B%22shop%22%2C%22magellan%22%2C%22v1%22%2C%22specification%22%2C%22skus%22%2C5981131%2C%22groups%22%2C%5B0%2C3%5D%2C%22specifications%22%2C0%2C%5B%22definition%22%2C%22displayName%22%2C%22value%22%5D%5D%2C%5B%22shop%22%2C%22magellan%22%2C%22v1%22%2C%22specification%22%2C%22skus%22%2C5981131%2C%22groups%22%2C%5B1%2C5%5D%2C%22specifications%22%2C%7B%22from%22%3A0%2C%22to%22%3A4%7D%2C%5B%22definition%22%2C%22displayName%22%2C%22value%22%5D%5D%2C%5B%22shop%22%2C%22magellan%22%2C%22v1%22%2C%22specification%22%2C%22skus%22%2C5981131%2C%22groups%22%2C2%2C%22specifications%22%2C%7B%22from%22%3A0%2C%22to%22%3A3%7D%2C%5B%22definition%22%2C%22displayName%22%2C%22value%22%5D%5D%2C%5B%22shop%22%2C%22magellan%22%2C%22v1%22%2C%22specification%22%2C%22skus%22%2C5981131%2C%22groups%22%2C4%2C%22specifications%22%5D%5D&method=get';

		$urlJson = str_replace('5981131', $sku, $url);

		if ($featureArrayObject->length) {
			$featureName = 'General';
			foreach ($featureArrayObject as $reviewObject) {
				$fName = $this->xpath->query('.//h3[@class="groupName_3O9-v"]', $reviewObject);
				if ($fName->length) {
					$featureName = $fName->item(0)->nodeValue;
				}

				$name = $this->xpath->query('.//div[@class="itemName_GaNqp"]', $reviewObject);
				if ($name->length) {
					if (!isset($featureGroups[$featureName])) {
						$featureGroups[$featureName] = array(
							'name' => $featureName,
							'attributes' => array()
						);
					}
					$featureGroups[$featureName]['attributes'][] = array(
						'name' => $name->item(0)->nodeValue,
						'value' => $this->xpath->query('.//div[@class="itemValue_3FLTX"]', $reviewObject)->item(0)->nodeValue
					);
				}
			}
		} elseif ($urlJson) {
			$json = $this->getContent($urlJson);
			$urlData = json_decode($json, true);

			if (isset($urlData['jsonGraph']['shop']['magellan']['v1']['specification']['skus'][$sku]['groups']) &&
			$urlData['jsonGraph']['shop']['magellan']['v1']['specification']['skus'][$sku]['groups']) {
				foreach ($urlData['jsonGraph']['shop']['magellan']['v1']['specification']['skus'][$sku]['groups'] as $feature) {
					if (isset($feature['specifications']) && $feature['specifications']) {
						$attributes = array();
						foreach ($feature['specifications'] as $features) {
							if (isset($features['displayName']['value'])) {
								$attributes[] = array(
								'name' => $features['displayName']['value'],
								'value' => $features['value']['value']
								);
							}
						}
						if ($attributes) {
							$featureGroups[] = array(
							'name' => 'General',
							'attributes' => $attributes
							);
						}
					}
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
		if (isset($this->jsonDataArray['brand']['dynamicContent']['sections']) && $this->jsonDataArray['brand']['dynamicContent']['sections']) {
			foreach ($this->jsonDataArray['brand']['dynamicContent']['sections'] as $attr) {
				if (isset($attr['items'][0]['values']['productVariantsUrl']) && $attr['items'][0]['values']['productVariantsUrl']) {
					$attrUrl = $attr['items'][0]['values']['productVariantsUrl'];
					$attrsDataArray = $this->getContent($attrUrl);

					$attrsDataArray = json_decode($attrsDataArray, true);
					$color = array();
					$size = array();

					foreach ($attrsDataArray as $attrloop) {
						if (isset($attrloop['options']['color']['en'])) {
							$color[] = $attrloop['options']['color']['en'];
						}
						if (isset($attrloop['options']['capacity'])) {
							$size[] = $attrloop['options']['capacity'];
						}
					}
					if ($color) {
						$attrGroups[] = array(
							'name' => 'Color',
							'is_color' => 1,
							'values' => $color
						);
					}
					if ($size) {
						$attrGroups[] = array(
							'name' => 'Size',
							'is_color' => 0,
							'values' => $size
						);
					}
				}
			}
		} elseif (isset($this->jsonDataArray['categories']) && $this->jsonDataArray['categories']) {
			foreach ($this->jsonDataArray['categories'] as $variation) {
				$attrValues = array();
				if (isset($variation['variations']) && $variation['variations']) {
					foreach ($variation['variations'] as $attrVals) {
						$attrValues[] = $attrVals['name'];
					}
				}
				if ('Carrier' != $variation['displayName']) {
					if ($attrValues) {
						$attrGroups[] = array(
							'name' => $variation['displayName'],
							'is_color' => ( stripos($variation['displayName'], 'color') !== false ) ? 1 : 0,
							'values' => $attrValues
						);
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
		$sku = $this->getSKU();
		$upc = $this->getUPC();
		$weight = $this->getWeight();
		$colors = array();
		$attrs = $this->getAttributes();
		$combs = $this->makeCombinations($attrs);

		foreach ($attrs as $attribute) {
			if ($attribute['is_color']) {
				$colors = $attribute['values'];
				break;
			}
		}
		$this->getImages();
		if ($combs) {
			foreach ($combs as $attrVals) {
				$colorIndex = 0;
				foreach ($attrVals as $vals) {
					if (in_array($vals['value'], $colors)) {
						$colorIndex = array_search($vals['value'], $colors);
						break;
					}
				}
				if (isset($this->priceVar[$colorIndex])) {
					$comboPrice = $this->priceVar[$colorIndex];
				}
				$combinations[] = array(
					'sku' =>$sku,
					'upc' => $upc,
					'price' => trim(isset($comboPrice) ? $comboPrice : $price),
					'weight' => $weight,
					'image_index' => $colorIndex,
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

	public function getCustomerReviews( $maxReviews = 0, &$reviews = array(), $page = 1) {
		$maxReviews = $maxReviews ? $maxReviews : 50;
		if (!$reviews) {
			$sku = $this->getSKU();
			$this->reviewLink = 'https://www.bestbuy.com/ugc/v2/reviews?pageSize=' . $maxReviews . '&sku=' . $sku . '&sort=BEST_REVIEW&page=1';
		}
		$reviewLink = $this->reviewLink;
		$reviewLink .= '&page=' . $page;

		if ($reviewLink) {
			$reviewJson = $this->getContent($reviewLink);

			if ($reviewJson) {
				$reviewArrayObject = json_decode($reviewJson, true);

				if (isset($reviewArrayObject['topics']) && $reviewArrayObject['topics']) {
					$isMaxReached = false;
					foreach ($reviewArrayObject['topics'] as $reviewObject) {
						if (isset($reviewObject['author'])) {
							$reviews[] = array(
								'author' => $reviewObject['author'],
								'title' => isset($reviewObject['title']) ? $reviewObject['title'] : '',
								'content' => isset($reviewObject['text']) ? $reviewObject['text'] : '',
								'rating' =>$reviewObject['rating'],
								'timestamp' => gmdate('Y-m-d H:i:s', strtotime($reviewObject['submissionTime']))
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
		if (!$reviews) {
			$reviews = $this->getCustomerReviews2();
		}
		return $reviews;
	}

	public function getCustomerReviews2( $maxReviews = 0, &$reviews = array(), $page = 1) {
		$maxReviews = $maxReviews ? $maxReviews : 50;
		$sku = $this->getSKU();
		if (!$reviews) {
			$this->reviewLink = 'https://www.bestbuy.ca/api/reviews/v2/products/14398539/reviews?source=all&lang=en-CA&pageSize=' . $maxReviews . '&sortBy=relevancy';
		}

		$reviewLink = $this->reviewLink;
		$reviewLink .= '&page=' . $page;

		if ($reviewLink) {
			$reviewJson = $this->getContent($reviewLink);

			if ($reviewJson) {
				$reviewArrayObject = json_decode($reviewJson, true);

				if (isset($reviewArrayObject['reviews']) && $reviewArrayObject['reviews']) {
					$isMaxReached = false;

					foreach ($reviewArrayObject['reviews'] as $reviewObject) {
						if (isset($reviewObject['reviewerName'])) {
							$reviews[] = array(
								'author' => $reviewObject['reviewerName'],
								'title' => isset($reviewObject['title']) ? $reviewObject['title'] : '',
								'content' => isset($reviewObject['comment']) ? $reviewObject['comment'] : '',
								'rating' =>$reviewObject['rating'],
								'timestamp' => gmdate('Y-m-d H:i:s', strtotime($reviewObject['submissionTime']))
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
