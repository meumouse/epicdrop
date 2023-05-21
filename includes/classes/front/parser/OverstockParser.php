<?php
/**
 * Overstock data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class OverstockParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $JSON_SELECTOR = '//script[@id="__NEXT_DATA__"]';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content, $url) {
		$this->content = preg_replace('/\s+/', ' ', $content);
		$this->url = $url;
		$this->dom = $this->getDomObj($content);
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
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'GET',
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
		$jsons = $this->getValue($this->JSON_SELECTOR);

		if ($jsons) {
			$json = array_shift($jsons);
			$data = json_decode($json, true);

			if (!$data) {
				$json = preg_replace('/(\w+):/i', '"\1":', $json);
				$data = json_decode($json, true);
			}

			if (isset($data['props']['pageProps'])) {
				$this->jsonDataArray = $data['props']['pageProps'];
			}
		}
	}

	private function getValue( $selector, $html = false, $xpath = null) {
		if (empty($selector)) {
			return array();
		}
		if (null === $xpath) {
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
		if (isset($this->jsonDataArray['product']['name'])) {
			return $this->jsonDataArray['product']['name'];
		}
		return '';
	}

	public function getCategories() {
		$categories = array();

		if (isset($this->jsonDataArray['product']['breadcrumbs'])
			&& $this->jsonDataArray['product']['breadcrumbs']
		) {
			$categories = array_column($this->jsonDataArray['product']['breadcrumbs'], 'label');
		}

		return array_unique($categories);
	}

	public function getDescription() {
		if (isset($this->jsonDataArray['product']['description'])) {
			return nl2br($this->jsonDataArray['product']['description']);
		}
		return '';
	}

	public function getShortDescription() {
		return '';
	}

	public function getPrice() {
		if (isset($this->jsonDataArray['product']['memberPrice'])) {
			return $this->jsonDataArray['product']['memberPrice'];
		}
		return '';
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['product']['defaultOptionId'])) {
			return $this->jsonDataArray['product']['defaultOptionId'];
		} elseif (isset($this->jsonDataArray['product']['id'])) {
			return $this->jsonDataArray['product']['id'];
		}

		return '';
	}
	public function getWeight() {
		return '';
	}

	public function getUPC() {
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['product']['brandName'])) {
			return $this->jsonDataArray['product']['brandName'];
		}
		return '';
	}
	public function getCoverImage() {
		if (isset($this->jsonDataArray['product']['imageLarge'])) {
			return 'https://ak1.ostkcdn.com/images/products/' . $this->jsonDataArray['product']['imageLarge'];
		}
		return '';
	}

	public function getImages() {
		static $images = array();
		if ($images) {
			return $images;
		}

		if (isset($this->jsonDataArray['product']['oViewerImages'])
			&& $this->jsonDataArray['product']['oViewerImages']
		) {
			foreach ($this->jsonDataArray['product']['oViewerImages'] as $img) {
				$images[$img['id']][] = 'https://ak1.ostkcdn.com/images/products/' . $img['cdnPath'];
			}
		}

		if (!$images) {
			$cover = $this->jsonDataArray['image'];
			if ($cover) {
				$images[0] = $cover;
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

		if (isset($this->jsonDataArray['product']['specificationAttributes']['attributeGroups'])
			&& $this->jsonDataArray['product']['specificationAttributes']['attributeGroups']
		) {
			foreach ($this->jsonDataArray['product']['specificationAttributes']['attributeGroups'] as $group) {
				$attributes = array();

				foreach ($group['attributes'] as $attr) {
					$attributes[] = array(
						'name' => $attr['label'],
						'value' => implode(', ', $attr['values'])
					);
				}

				$featureGroups[] = array(
					'name' => $group['title'],
					'attributes' => $attributes
				);
			}
		}

		return $featureGroups;
	}
	public function getAttributes() {
		static $attrGroups = array();
		if ($attrGroups) {
			return $attrGroups;
		}

		if (isset($this->jsonDataArray['product']['facetGroups'])
			&& $this->jsonDataArray['product']['facetGroups']
		) {
			foreach ($this->jsonDataArray['product']['facetGroups'] as $group) {
				if (isset($group['facetGroupId'])) {
					$attrVals = array();
					$attrImgs = array();

					if (isset($this->jsonDataArray['product']['facets'])
						&& $this->jsonDataArray['product']['facets']
					) {
						foreach ($this->jsonDataArray['product']['facets'] as $attr) {
							if ($attr['groupId'] == $group['facetGroupId']) {
								$attrVals[$attr['facetId']] = $attr['facetDisplayName'];
								$attrImgs[$attr['facetId']] = $attr['oViewerImageId'];
							}
						}
					}

					$attrGroups[$group['facetGroupId']] = array(
						'name' => $group['groupDisplayName'],
						'is_color' => (int) $group['selectableByImage'],
						'values' => $attrVals,
						'images' => $attrImgs,
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

		$attributes = $this->getAttributes();

		if (isset($this->jsonDataArray['product']['options'])
			&& $this->jsonDataArray['product']['options']
		) {
			foreach ($this->jsonDataArray['product']['options'] as $option) {
				$attrVals = array();
				$imageIndex = 0;

				foreach ($option['optionFacetGroups'] as $group) {
					if ($attributes[$group['facetGroupId']]['is_color']) {
						$imageIndex = $attributes[$group['facetGroupId']]['images'][$group['facetId']];
					}
					$attrVals[] = array(
						'name' => $attributes[$group['facetGroupId']]['name'],
						'value' => $attributes[$group['facetGroupId']]['values'][$group['facetId']]
					);
				}

				if ($attrVals) {
					$combinations[] = array(
						'sku' => $option['optionId'],
						'upc' => 0,
						'price' => $option['price'],
						'weight' => 0,
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

	public function getCustomerReviews( $maxReviews = 0) {
		$reviews = array();

		if (isset($this->jsonDataArray['meta']['currentLocation']['query']['productId'])) {
			$pId = $this->jsonDataArray['meta']['currentLocation']['query']['productId'];

			$maxReviews = $maxReviews > 0 ? $maxReviews : 500;

			$reviewlink = 'https://api.overstock.com/ugc/reviews?productId=' . $pId . '&sortBy=relevancy&limit=' . $maxReviews;
			$json = $this->getContent($reviewlink);
			if ($json) {
				$reviewData = json_decode($json, true);
				if (isset($reviewData['items']) && $reviewData['items']) {
					foreach ($reviewData['items'] as $review) {
						$reviews[] = array(
							'author' => isset($review['screenName']) ? $review['screenName'] : 0,
							'title' => $review['reviewTitle'],
							'content' => $review['reviewText'],
							'rating' => $review['rating'],
							'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['submittedOn']))
						);
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
		if (isset($this->jsonDataArray['reviews']['initialReviews'])
			&& $this->jsonDataArray['reviews']['initialReviews']
		) {
			foreach ($this->jsonDataArray['reviews']['initialReviews'] as $review) {
				$reviews[] = array(
					'author' => $review['screenName'],
					'title' => '',
					'content' => $review['reviewText'],
					'rating' => $review['rating'],
					'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['submittedOn']))
				);
			}
		}

		return $reviews;
	}
}
