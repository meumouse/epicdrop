<?php 
/**
 * Shein data parser class
 *
 * @package: product-importer
 *
 */
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.1 */

class SheinParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $imageArray = array();
	private $reviewLink = '';
	private $REVIEW_SELECTOR = '//div[contains(@class, "j-expose__common-reviews__list-item-con")]';
	private $CATEGORY_SELECTOR = '//*[@class="bread-crumb__item-link"]';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content, $url) {
		$this->url = $url;
		$this->dom = $this->getDomObj($content);

		$content = str_replace("\n", '', $content);
		$content = iconv('UTF-8', 'UTF-8//IGNORE', $content);
		$this->content = preg_replace('/\s+/', ' ', $content);

		/* Create a new XPath object */
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

	private function setJsonData() {
		$this->jsonDataArray = $this->getJsonData($this->content);

		$host = $this->getJson($this->content, '"host":"', '","IMG"');

		$this->jsonDataArray['host'] = ( stripos($host, 'http') !== false ) ? $host : 'https://us.shein.com';

		$this->setAdditionalData();
	}

	private function getJsonData( $content) {
		$data = array();
		
		$json = $this->getJson($content, 'productIntroData: ', ', abt:');
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$data = json_decode($json, true);
		}

		return $data;
	}

	private function setAdditionalData() {
		if (isset($this->jsonDataArray['relation_color']) && $this->jsonDataArray['relation_color']) {
			foreach ($this->jsonDataArray['relation_color'] as $key => $related) {
				$combLink = preg_replace('/-p-(\d+)-c/', '-p-' . $related['goods_id'] . '-c', $this->url);

				$content = $this->getContent($combLink);

				if ($content) {
					$content = iconv('UTF-8', 'UTF-8//IGNORE', $content);
					$content = preg_replace('/\s+/', ' ', $content);
					$data = $this->getJsonData($content);

					if ($data) {
						$this->imageArray[$related['goods_id']][] = 'https:' . $data['goods_imgs']['main_image']['origin_image'];
					}

					if (isset($data['goods_imgs']['detail_image'])
						&& $data['goods_imgs']['detail_image']) {
						foreach ($data['goods_imgs']['detail_image'] as $img) {
							$this->imageArray[$related['goods_id']][] =  'https:' . $img['origin_image'];
						}
					}

					$this->jsonDataArray['relation_color'][$key]['price'] = $this->getPrice($data);
				} else {
					$this->imageArray[$related['goods_id']][] = 'https:' . $related['original_img'];
					$this->jsonDataArray['relation_color'][$key]['price'] = $this->getPrice();
				}
			}

			$this->jsonDataArray['relation_color'][] = array(
				'goods_id' => $this->getSKU(),
				'price' => $this->getPrice()
			);
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
		if (isset($this->jsonDataArray['detail']['goods_name'])) {
			return $this->jsonDataArray['detail']['goods_name'];
		}
		return '';
	}

	public function getCategories() {
		$categories = $this->getValue($this->CATEGORY_SELECTOR);

		array_shift($categories); // Remove first Home category
		array_pop($categories); // Remove last Product name

		return array_map('trim', $categories);
	}

	public function getShortDescription() {
		return '';
	}

	public function getDescription() {
		return '';
	}

	public function getPrice( $data = array()) {
		$data = $data ? $data : $this->jsonDataArray;

		return $this->getPriceValue($data['detail']);
	}

	public function getPriceValue( $data = array()) {
		if (isset($data['salePrice']['amount']) && $data['salePrice']['amount']) {
			return $data['salePrice']['amount'];
		} elseif (isset($data['retailPrice']['amount']) && $data['retailPrice']['amount']) {
			return $data['detail']['retailPrice']['amount'];
		}

		return 0;
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['detail']['goods_id'])) {
			return $this->jsonDataArray['detail']['goods_id'];
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['detail']['brand'])) {
			return $this->jsonDataArray['detail']['brand'];
		}
		return '';
	}

	public function getCoverImage() {
		if (isset($this->jsonDataArray['detail']['original_img'])) {
			$image = 'https:' . $this->jsonDataArray['detail']['original_img'];
		}
		return $image;
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}

		if (isset($this->jsonDataArray['goods_imgs']['main_image']['origin_image'])
			&& $this->jsonDataArray['goods_imgs']['main_image']['origin_image']
		) {
			$img = 'https:' . $this->jsonDataArray['goods_imgs']['main_image']['origin_image'];
			$this->imageArray[$this->jsonDataArray['detail']['goods_id']][] = $img;
		}

		if (isset($this->jsonDataArray['goods_imgs']['detail_image'])
			&& $this->jsonDataArray['goods_imgs']['detail_image']
		) {
			foreach ($this->jsonDataArray['goods_imgs']['detail_image'] as $img) {
				$img = 'https:' . $img['origin_image'];
				$this->imageArray[$this->jsonDataArray['detail']['goods_id']][] = $img;
			}
		}

		$images = $this->imageArray;

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

	public function getMainColor() {
		$color = '';
		$featureGroups = $this->getFeatures();

		foreach ($featureGroups as $featureGroup) {
			foreach ($featureGroup['attributes'] as $feature) {
				if (strtolower($feature['name']) == 'color') {
					$color = $feature['value'];
					break;
				}
			}
		}

		return $color;
	}

	public function getAttributes() {
		static $attrGroups = array();
		if ($attrGroups) {
			return $attrGroups;
		}

		$colorValues = array();
		$attrValues = array();

		$colorName = 'Color';
		$attrName = 'Size';

		if (isset($this->jsonDataArray['relation_color'])
			&& $this->jsonDataArray['relation_color']
		) {
			$color = $this->getMainColor();
			if ($color) {
				$colorValues[$this->jsonDataArray['detail']['goods_id']] = $color;
			}

			foreach ($this->jsonDataArray['relation_color'] as $comb) {
				if (isset($comb['productDetails'])) {
					foreach ($comb['productDetails'] as $attr) {
						if ('Color' == $attr['attr_name_en']) {
							$colorName = $attr['attr_name'];
							$colorValues[$comb['goods_id']] = $attr['attr_value'];
						}
					}
				}
			}

			if ($colorValues) {
				$attrGroups[] = array(
					'name' => $colorName,
					'is_color' => 1,
					'values' => array_unique($colorValues)
				);
			}
		}

		if (isset($this->jsonDataArray['attrSizeList']['attrSize'])
			&& $this->jsonDataArray['attrSizeList']['attrSize']) {
			foreach ($this->jsonDataArray['attrSizeList']['attrSize'] as $attr) {
				$attrName = $attr['attr_name'];
				$attrValues[] = $attr['attr_value'];
			}

			if ($attrValues) {
				$attrGroups[] = array(
					'name' => $attrName,
					'is_color' => 0,
					'values' => array_unique($attrValues)
				);
			}
		} elseif (isset($this->jsonDataArray['attrSizeList']['sale_attr_list'][$this->getSKU()]['skc_sale_attr'])) {
			$attrSizes = $this->jsonDataArray['attrSizeList']['sale_attr_list'][$this->getSKU()]['skc_sale_attr'];
			
			if ($attrSizes) {
				$attrSizes = array_shift($attrSizes);

				foreach ($attrSizes['attr_value_list'] as $attr) {
					$attrValues[] = $attr['attr_value_name'];
				}

				if ($attrValues) {
					$attrName = $attrSizes['attr_name'];
					$attrGroups[] = array(
						'name' => $attrName,
						'is_color' => 0,
						'values' => array_unique($attrValues)
					);
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

		$attrs = $this->getAttributes();

		$colorAttrs = array_shift($attrs);
		$sizeAttrs = array_shift($attrs);

		if ($colorAttrs && !$colorAttrs['is_color']) {
			$sizeAttrs = $colorAttrs;
			$colorAttrs = array();
		}

		if ($colorAttrs) {
			foreach ($colorAttrs['values'] as $combId => $color) {
				if ($sizeAttrs) {
					foreach ($sizeAttrs['values'] as $size) {
						foreach ($this->jsonDataArray['relation_color'] as $comb) {
							if ($comb['goods_id'] == $combId) {
								break 1;
							}
						}

						$combinations[] = array(
							'sku' => $combId,
							'upc' => 0,
							'price' => $comb['price'],
							'weight' => 0,
							'image_index' => $combId,
							'attributes' => array(
								array(
									'name' => $colorAttrs['name'],
									'value' => $color
								),
								array(
									'name' => $sizeAttrs['name'],
									'value' => $size
								),
							)
						);
					}
				} else {
					foreach ($this->jsonDataArray['relation_color'] as $comb) {
						if ($comb['goods_id'] == $combId) {
							break 1;
						}
					}

					$combinations[] = array(
							'sku' => $combId,
							'upc' => 0,
							'price' => $comb['price'],
							'weight' => 0,
							'image_index' => $combId,
							'attributes' => array(
								array(
									'name' => $colorAttrs['name'],
									'value' => $color
								)
							)
						);
				}
			}
		} elseif (isset($this->jsonDataArray['attrSizeList']['sale_attr_list'][$this->getSKU()]['sku_list'])
			&& $this->jsonDataArray['attrSizeList']['sale_attr_list'][$this->getSKU()]['sku_list']) {
			
			$attrSizes = $this->jsonDataArray['attrSizeList']['sale_attr_list'][$this->getSKU()]['sku_list'];

			$sku = $this->getSku();

			foreach ($attrSizes as $attrSize) {
				$attrValues = array();

				foreach ($attrSize['sku_sale_attr'] as $attr) {
					$attrValues[] = array(
						'name' => $attr['attr_name'],
						'value' => $attr['attr_value_name']
					);
				}

				if ($attrValues) {
					$combinations[] = array(
						'sku' => $attrSize['sku_code'],
						'upc' => 0,
						'price' => $this->getPriceValue($attrSize['price']),
						'weight' => 0,
						'image_index' => $sku,
						'attributes' => $attrValues
					);
				}
			}
		} elseif ($sizeAttrs) {
			$sku = $this->getSku();
			$price = $this->getPrice();

			foreach ($sizeAttrs['values'] as $size) {
				$combinations[] = array(
					'sku' => $sku,
					'upc' => 0,
					'price' => $price,
					'weight' => 0,
					'image_index' => $sku,
					'attributes' => array(
						array(
							'name' => $sizeAttrs['name'],
							'value' => $size
						),
					)
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

	public function getFeatures() {
		static $featureGroups = array();

		if ($featureGroups) {
			return $featureGroups;
		}

		$attributes = array();

		if (isset($this->jsonDataArray['detail']['productDetails']) && $this->jsonDataArray['detail']['productDetails']) {
			foreach ($this->jsonDataArray['detail']['productDetails'] as $attribute) {
				$attributes[] = array(
					'name' => $attribute['attr_name_en'],
					'value' => $attribute['attr_value']
				);
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

	public function getCustomerReviews( $maxReviews = 0, $reviews = array(), $page = 1) {
		$reviews = array();

		if (isset($this->jsonDataArray['commentInfo']['spu'])) {
			if (!$reviews) {
				$this->reviewLink = $this->jsonDataArray['host'];
				$this->reviewLink .= '/goods_detail_nsw/getCommentInfoByAbc?rule_id=recsrch_sort:A&limit=20&spu=';
				$this->reviewLink .= $this->jsonDataArray['commentInfo']['spu'];
			}

			$reviewLink = $this->reviewLink;

			if ($page > 1) {
				$reviewLink .= '&page=' . $page;
			}

			if ($reviewLink) {
				$json = $this->getContent($reviewLink);

				if ($json) {
					$reviewData = json_decode($json, true);

					if (isset($reviewData['info']['commentInfo'])) {
						$isMaxReached = false;

						foreach ($reviewData['info']['commentInfo'] as $commentInfo) {
							$reviews[] = array(
								'author' => $commentInfo['user_name'],
								'title' => '',
								'content' => $commentInfo['content'],
								'rating' => (int) $commentInfo['comment_rank'],
								'timestamp' => gmdate('Y-m-d H:i:s', strtotime($commentInfo['comment_time']))
							);

							if (0 < $maxReviews && count($reviews) >= $maxReviews) {
								$isMaxReached = true;
								break;
							}
						}

						if ($reviewData['info']['commentInfoTotal'] > ( $page * 20 ) && false == $isMaxReached) {
							$this->getCustomerReviews($maxReviews, $reviews, ++$page);
						}
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

		$reviewArrayObject = $this->xpath->query($this->REVIEW_SELECTOR);

		if ($reviewArrayObject->length) {
			foreach ($reviewArrayObject as $reviewObject) {
				$content = $this->xpath->query('.//div[@class="rate-des"]', $reviewObject);
				if ($content->length) {
					$stars = $this->xpath->query('.//i[contains(@class, "svgicon-star4")]', $reviewObject);
					$rating = (int) $stars->length;

					$reviews[] = array(
						'author' => $this->xpath->query('.//div[@class="nikename"]', $reviewObject)->item(0)->nodeValue,
						'title' => '',
						'content' => trim($content->item(0)->nodeValue),
						'rating' => $rating,
						'timestamp' => gmdate('Y-m-d H:i:s', strtotime(trim($this->xpath->query('.//div[@class="date"]', $reviewObject)->item(0)->nodeValue)))
					);
				}
			}
		}
		return $reviews;
	}

	public function getContent( $url) {
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

		$headers = array(
		   "Origin: $url",
		);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

		$resp = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);

		if ($err) {
			return false;
		}

		return $resp;
	}
}
