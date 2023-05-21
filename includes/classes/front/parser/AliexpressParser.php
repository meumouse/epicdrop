<?php
/**
 * Aliexpress data parser class
 *
 * @package: product-importer
 *
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 4.0 */

class AliexpressParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $content;
	private $attrJsonArray = array();
	private $IMAGE_COVER_SELECTOR = '//div[@class="image-view-magnifier-wrap"]/img/@src';
	private $REVIEW_SELECTOR = '//div[@class="feedback-list-wrap"]/div';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content) {
		$this->dom = $this->getDomObj($content);

		$content = str_replace("\n", '', $content);
		$content = iconv('UTF-8', 'UTF-8//IGNORE', $content);
		$this->content = preg_replace('/\s+/', ' ', $content);

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
		$json = $this->getJson($this->content, '{ data:', ', csrfToken');
		$json = mb_convert_encoding($json, 'UTF-8', 'UTF-8');
		if ($json) {
			$this->attrJsonArray = json_decode($json, true);
			if (!$this->attrJsonArray) {
				$this->attrJsonArray = json_decode(preg_replace('/\\\\/', '', $json), true);
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
		$name = '';
		if (isset($this->attrJsonArray['titleModule']['subject'])) {
			$name = html_entity_decode($this->attrJsonArray['titleModule']['subject']);
		}

		return $name;
	}

	public function getCategories() {
		$categories = array();
		if (isset($this->attrJsonArray['crossLinkModule']['breadCrumbPathList'])) {
			$categories = $this->attrJsonArray['crossLinkModule']['breadCrumbPathList'];
		}

		return array_column($categories, 'name');
	}

	public function getDescription() {
		$description = '';
		if (isset($this->attrJsonArray['descriptionModule']['descriptionUrl'])) {
			$description = @file_get_contents($this->attrJsonArray['descriptionModule']['descriptionUrl']);
		}

		return $description;
	}

	public function getPrice() {
		return max($this->getPriceMax(), $this->getPriceMin());
	}

	public function getPriceMin() {
		$price = 0;
		// Discounted price
		if (isset($this->attrJsonArray['priceModule']['minActivityAmount']['value'])) {
			$price = $this->attrJsonArray['priceModule']['minActivityAmount']['value'];
		}
		// Regular price
		if (!$price && isset($this->attrJsonArray['priceModule']['minAmount']['value'])) {
			$price = $this->attrJsonArray['priceModule']['minAmount']['value'];
		}

		return $price;
	}

	public function getPriceMax() {
		$price = 0;
		// Discounted price
		if (isset($this->attrJsonArray['priceModule']['maxActivityAmount']['value'])) {
			$price = $this->attrJsonArray['priceModule']['maxActivityAmount']['value'];
		}
		// Regular price
		if (!$price && isset($this->attrJsonArray['priceModule']['maxAmount']['value'])) {
			$price = $this->attrJsonArray['priceModule']['maxAmount']['value'];
		}

		return $price;
	}

	public function getPriceCurrency() {
		if (isset($this->attrJsonArray['priceModule']['maxAmount']['currency'])) {
			return $this->attrJsonArray['priceModule']['maxAmount']['currency'];
		}

		return parent::getPriceCurrency();
	}

	public function getWeight() {
		$weight = array();

		if (isset($this->attrJsonArray['specsModule']['props'])
			&& $this->attrJsonArray['specsModule']['props']) {
				
			$weightTexts = array(
				'weight',
				'poids',
				'berat',
				'الوزن',
				'gewicht',
				'peso',
				'重量',
				'무게',
				'bес',
				'น้ำหนัก',
				'Ağırlık',
				'Trọng',
				'משקל',
			);
			
			foreach ($this->attrJsonArray['specsModule']['props'] as $prop) {
				if (in_array($prop['attrName'], $weightTexts)) {
					$prop['attrValue'] = explode('-', $prop['attrValue']);
					$prop['attrValue'] = array_pop($prop['attrValue']);
					$weight = array(
						'value' => (float) preg_replace('/[^0-9.]/', '', $prop['attrValue']),
						'unit' => preg_replace('/[0-9.]/', '', $prop['attrValue'])
					);

					break;
				}
			}
		}

		return $weight;
	}

	public function getSKU() {
		$sku = '';
		if (isset($this->attrJsonArray['skuModule']['skuPriceList'])) {
			$skus = array_shift($this->attrJsonArray['skuModule']['skuPriceList']);
			$sku = isset($skus['skuAttr']) ? $skus['skuAttr'] : '';
		}

		return preg_replace('/([\[\^<>;=\{\}\]\*\$])/', '', $sku);
	}

	public function getBrand() {
		$branName = '';
		if (isset($this->attrJsonArray['specsModule']['props'])
			&& $this->attrJsonArray['specsModule']['props']
		) {		
			
			$brandTexts = array(
				'brand',
				'marque',
				'merek',
				'العلامة',
				'marke',
				'marka',
				'marca',
				'銘柄',
				'브랜드',
				'merk',
				'бренда',
				'ชื่อยี่ห้อ',
				'hiệu',
				'מותג',
			);
			
			foreach ($this->attrJsonArray['specsModule']['props'] as $prop) {
				if (in_array($prop['attrName'], $brandTexts)) {
					$branName = $prop['attrValue'];
					break;
				}
			}
		}

		return $branName;
	}

	public function getMetaTitle() {
		$title = '';
		if (isset($this->attrJsonArray['pageModule']['title'])) {
			$title = $this->attrJsonArray['pageModule']['title'];
		}

		return $title;
	}

	public function getMetaDecription() {
		$description = '';
		if (isset($this->attrJsonArray['pageModule']['description'])) {
			$description = $this->attrJsonArray['pageModule']['description'];
		}

		return $description;
	}

	public function getMetaKeywords() {
		$metakeywords = $this->getValue($this->META_KEYWORDS_SELECTOR);
		return array_shift($metakeywords);
	}

	public function getCoverImage() {
		$images = $this->getValue($this->IMAGE_COVER_SELECTOR);
		return array_shift($images);
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}
		
		if (isset($this->attrJsonArray['imageModule']['imagePathList'])
			&& $this->attrJsonArray['imageModule']['imagePathList']
		) {
			foreach ($this->attrJsonArray['imageModule']['imagePathList'] as $img) {
				if ($img) {
					$images[0][] = $img;
				}
			}
		}

		// Get some additional images from attributes
		$attibutes = $this->getAttributes();
		if (isset($attibutes[14])) { // Color attribute id is 14 and only color attribute has additional images
			foreach ($attibutes[14]['images'] as $combId => $img) {
				if ($img) {
					$images[$combId] = array($img);
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

		if (isset($this->attrJsonArray['specsModule']['props'])
			&& $this->attrJsonArray['specsModule']['props']) {
			foreach ($this->attrJsonArray['specsModule']['props'] as $feature) {
				$attributes[] = array(
					'name' => $feature['attrName'],
					'value' => $feature['attrValue']
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

	public function getAttributes() {
		static $attrGroups = array();
		if ($attrGroups) {
			return $attrGroups;
		}

		$attrGroups = array();
		if (isset($this->attrJsonArray['skuModule']['productSKUPropertyList'])) {
			foreach ($this->attrJsonArray['skuModule']['productSKUPropertyList'] as $groups) {
				$attrValues = array();
				$images = array();
				foreach ($groups['skuPropertyValues'] as $attrVal) {
					$attrValues[$attrVal['propertyValueId']] = $attrVal['propertyValueDisplayName'];
					$images[$attrVal['propertyValueId']] = isset($attrVal['skuPropertyImagePath'])
						? str_replace('_640x640.jpg', '', $attrVal['skuPropertyImagePath'])
						: '';
				}
				$attrGroups[$groups['skuPropertyId']] = array(
					'name' => $groups['skuPropertyName'],
					'is_color' => (int) ( 14 == $groups['skuPropertyId'] && $images ),
					'images' => $images,
					'values' => $attrValues
				);
			}
		}

		return $attrGroups;
	}

	public function getCombinations() {
		$combinations = array();
		$weight = $this->getWeight();
		$attrs = $this->getAttributes();
		if (isset($this->attrJsonArray['skuModule']['skuPriceList']) && $attrs) {
			foreach ($this->attrJsonArray['skuModule']['skuPriceList'] as $comb) {
				$combAttrIds  = explode(',', $comb['skuPropIds']);
				$attributes = array();
				$imageIndex = 0;
				foreach ($attrs as $attrGroupId => $attrGroup) {
					foreach ($attrGroup['values'] as $attrId => $attrName) {
						if (in_array($attrId, $combAttrIds)) {
							$attributes[] = array(
								'name' => $attrGroup['name'],
								'value' => $attrName
							);
							if (14 == $attrGroupId) { // Color id is 14
								$imageIndex = $attrId;
							}
						}
					}
				}
				
				if (isset($comb['skuVal']['actSkuMultiCurrencyCalPrice']) && $comb['skuVal']['actSkuMultiCurrencyCalPrice']) {
					$combPrice = $comb['skuVal']['actSkuMultiCurrencyCalPrice'];
				} elseif (isset($comb['skuVal']['actSkuCalPrice']) && $comb['skuVal']['actSkuCalPrice']) {
					$combPrice = $comb['skuVal']['actSkuCalPrice'];
				} else {
					$combPrice = $comb['skuVal']['skuCalPrice'];
				}

				$combinations[] = array(
					'sku' => preg_replace('/([\[\^<>;=\{\}\]\*\$])/', '', $comb['skuAttr']),
					'upc' => null,
					'price' => $combPrice,
					'weight' => $weight,
					'image_index' => $imageIndex,
					'attributes' => $attributes
				);
			}
		}

		return  $combinations;
	}

	public function getCustomerReviews( $maxReviews = 0, &$reviews = array(), $page = 0) {
		if (isset($this->attrJsonArray['feedbackModule'])) {
			$reviewlink = 'https:' . $this->attrJsonArray['feedbackModule']['feedbackServer'];
			$reviewlink .= '/display/productEvaluation.htm?v=2&productId=';
			$reviewlink .= $this->attrJsonArray['feedbackModule']['productId'];
			$reviewlink .= '&ownerMemberId=' . $this->attrJsonArray['feedbackModule']['sellerAdminSeq'];
			$reviewlink .= '&companyId=' . $this->attrJsonArray['feedbackModule']['companyId'];
		}

		if ($page) {
			$reviewlink .= '&page=' . (int) $page;
		}

		if ($reviewlink) {
			$content = @file_get_contents($reviewlink);

			if ($content) {
				$dom = $this->getDomObj($content);
				$xpath = new \DomXPath($dom);

				$reviewCounts = $xpath->query('//div[@class="customer-reviews"]');

				$pageCount = $page;

				if ($reviewCounts->length) {
					$reviewCount = preg_replace('/[^0-9]/', '', $reviewCounts->item(0)->nodeValue);
					$pageCount = ceil($reviewCount / 10);
				}

				$reviewArrayObject = $xpath->query($this->REVIEW_SELECTOR);

				$isMaxReached = false;

				foreach ($reviewArrayObject as $reviewObject) {
					$author = trim(@$xpath->query('.//span[@class="user-name"]', $reviewObject)
							->item(0)->nodeValue);

					if ($author) {
						$reviews[] = array(
							'author' => $author,
							'title' => '',
							'content' => trim(@$xpath->query('.//dt[@class="buyer-feedback"]/span', $reviewObject)
								->item(0)->nodeValue),
							'rating' => (float) rtrim(
								substr(
									@$xpath->query('.//span[@class="star-view"]/span/@style', $reviewObject)
									->item(0)->nodeValue,
									6,
									3
								),
								'%'
							) / 20,
							'timestamp' => gmdate(
								'Y-m-d H:i:s',
								strtotime(
									trim(
										@$xpath->query('.//span[@class="r-time-new"]', $reviewObject)
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

				if ($pageCount > $page && false == $isMaxReached) {
					$this->getCustomerReviews($maxReviews, $reviews, ++$page);
				}
			}
		}

		return $reviews;
	}


	public function getVideos() {
		$videos = array();

		if (isset($this->attrJsonArray['imageModule']['videoUid'])
			&& $this->attrJsonArray['imageModule']['videoUid']
			&& isset($this->attrJsonArray['imageModule']['videoId'])
			&& $this->attrJsonArray['imageModule']['videoId']
		) {
			$video = 'https://cloud.video.taobao.com/play/u/';
			$video .= $this->attrJsonArray['imageModule']['videoUid'];
			$video .= '/p/1/e/6/t/10301/';
			$video .= $this->attrJsonArray['imageModule']['videoId'];
			$video .= '.mp4';

			$videos[] = $video;
		}

		return $videos;
	}
}
