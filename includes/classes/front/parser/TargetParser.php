<?php
/**
 * Target data parser class
 *
 * @package: product-importer
 *
 */
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.1 */

class TargetParser extends AbstractParser {

	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $attributes = array();
	private $DATA_SELECTOR = '//script[@id="item"]';
	private $PRICE_SELECTOR = '//div[@data-test="product-price"]';
	private $REVIEW_SELECTOR = '//div[@class="h-padding-l-jumbo h-text-hd4"]';
	private $CATEGORY_SELECTOR = '//div[@data-test="breadcrumb"]/span/a/span';
	private $FEATURE_SELECTOR = '//div[@id="specAndDescript"]/div/div/div';
	private $IMAGE_COVER_SELECTOR = '//img[@class="hover-zoom-hero-image"]/@src';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content, $url) {
		$content = $this->getContent($url);
		$this->dom = $this->getDomObj($content);

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
		$json = $this->getJson($this->content, '"redsky-aggregations-pdp",', '],{"data":{"product"');
		if ($json) {
			$data = json_decode(preg_replace('/:(\w+)/i', ':"\1"', $json), true);

			if (isset($data['baseUrlForRest']) && $data['baseUrlForRest']) {
				$url = $data['baseUrlForRest'];
				$url .= '/pdp_client_v1?key=' . $data['apiKey'];
				$url .= '&tcin=' . $data['tcin'];
				$url .= '&pricing_store_id=' . $data['pricing_store_id'];

				$json = $this->getContent($url);

				if ($json) {
					$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
					$this->jsonDataArray = json_decode($json, true);
					if ($this->jsonDataArray) {
						$this->jsonDataArray['apiKey'] = $data['apiKey'];
					}
				}
			}
		}

		if (!$this->jsonDataArray) {
			$json = $this->getJson($this->content, '__PRELOADED_QUERIES__":', '}</script>');

			if ($json) {
				$json = '{"data":{' . $this->getJson($json, '{"data":{', ']]}');
				$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
				$this->jsonDataArray = json_decode($json, true);
			}
			
			if (!$this->jsonDataArray) {
				$json = $this->getJson(stripslashes($this->content), '__TGT_DATA__ = JSON.parse("', '");');
				$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
				$data = json_decode($json, true);
			
				if (isset($data['__PRELOADED_QUERIES__']['queries'][1][1]['product'])) {
					$this->jsonDataArray['data'] = $data['__PRELOADED_QUERIES__']['queries'][1][1];
				}
			}

			$apiKey = $this->getJson($this->content, '"nova":{"apiKey":"', '","base":');

			if ($apiKey) {
				$this->jsonDataArray['apiKey'] = $apiKey;
			}
			
			if (!isset($this->jsonDataArray['apiKey'])) {
				$json = $this->getJson(stripslashes($this->content), '__CONFIG__ = JSON.parse("', '");');
				$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
				$data = json_decode($json, true);
			
				if (isset($data['services']['nova']['apiKey'])) {
					$this->jsonDataArray['apiKey'] = $data['services']['nova']['apiKey'];
				}
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
		if (isset($this->jsonDataArray['data']['product']['item']['product_description']['title'])) {
			return $this->jsonDataArray['data']['product']['item']['product_description']['title'];
		}
		return '';
	}

	public function getCategories() {
		return $this->getValue($this->CATEGORY_SELECTOR);
	}

	public function getShortDescription() {
		if (isset($this->jsonDataArray['data']['product']['item']['product_description']['soft_bullet_description'])) {
			return $this->jsonDataArray['data']['product']['item']['product_description']['soft_bullet_description'];
		}
		return '';
	}

	public function getDescription() {
		if (isset($this->jsonDataArray['data']['product']['item']['product_description']['downstream_description'])) {
			return $this->jsonDataArray['data']['product']['item']['product_description']['downstream_description'];
		}
		return '';
	}

	public function getPrice() {
		if (isset($this->jsonDataArray['data']['product']['price']['current_retail'])) {
			return $this->jsonDataArray['data']['product']['price']['current_retail'];
		} elseif (isset($this->jsonDataArray['data']['product']['price']['current_retail_min'])) {
			return $this->jsonDataArray['data']['product']['price']['current_retail_min'];
		}

		$price = $this->getValue($this->PRICE_SELECTOR);

		if ($price) {
			return preg_replace('/[^0-9\.]/', '', array_shift($price));
		}

		return 0;
	}

	public function getDimension() {
		$dimension = array();

		if (isset($this->jsonDataArray['data']['product']['item']['package_dimensions'])) {
			$dimension = $this->jsonDataArray['data']['product']['item']['package_dimensions'];
		}
		
		if (!isset($dimension['width'])
			&& isset($this->jsonDataArray['data']['product']['children'])
		) {
			$childrens = $this->jsonDataArray['data']['product']['children'];
			
			if ($childrens) {
				$children = array_shift($childrens);
				
				if (isset($children['item']['package_dimensions'])) {
					$dimension = $children['item']['package_dimensions'];
				}
			}
		}

		if (isset($dimension['width'])) {
			return array(
				'length' => $dimension['depth'],
				'width' => $dimension['width'],
				'height' => $dimension['height'],
				'height' => $dimension['height'],
				'unit' => $dimension['dimension_unit_of_measure']
			);
		}

		return array();
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['data']['product']['tcin'])) {
			return $this->jsonDataArray['data']['product']['tcin'];
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['data']['product']['item']['primary_brand']['name'])) {
			return $this->jsonDataArray['data']['product']['item']['primary_brand']['name'];
		}
		return '';
	}

	public function getCoverImage() {
		if (isset($this->jsonDataArray['data']['product']['item']['enrichment']['images']['primary_image_url'])) {
			$cimage =  $this->jsonDataArray['data']['product']['item']['enrichment']['images']['primary_image_url'];
			return $cimage . '?fmt=jpg&wid=1635&hei=1635';
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}

		if (isset($this->jsonDataArray['data']['product']['children'])
			&& $this->jsonDataArray['data']['product']['children']) {
			
			$children = $this->jsonDataArray['data']['product']['children'];
			foreach ($children as $imgs) {
				$primaryImage = $imgs['item']['enrichment']['images']['primary_image_url'] . '?fmt=jpg&wid=1635&hei=1635';

				if (isset($imgs['item']['enrichment']['images']['alternate_image_urls'])) {
					$images[$imgs['tcin']] = array_map(
						function ( $img) {
							return $img . '?fmt=jpg&wid=1635&hei=1635';
						},
						$imgs['item']['enrichment']['images']['alternate_image_urls']
					);
				}

				array_unshift($images[$imgs['tcin']], $primaryImage);
			}
		} elseif (isset($this->jsonDataArray['data']['product']['item']['enrichment']['images'])) {
			
			$imgs = $this->jsonDataArray['data']['product']['item']['enrichment']['images'];
			
			if ($imgs) {
				$primaryImage = $imgs['primary_image_url'] . '?fmt=jpg&wid=1635&hei=1635';

				if (isset($imgs['alternate_image_urls'])) {
					$images[$this->jsonDataArray['data']['product']['tcin']] = array_map(
						function ( $img) {
							return $img . '?fmt=jpg&wid=1635&hei=1635';
						},
						$imgs['alternate_image_urls']
					);
				}

				array_unshift($images[$this->jsonDataArray['data']['product']['tcin']], $primaryImage);
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

		$this->getCombinations();
		$attrGroups = $this->attributes;

		return $attrGroups;
	}

	public function getCombAttributes( $combArray, &$combinations = array(), $attributes = array()) {
		foreach ($combArray as $combAttr) {
			
			$attribute = array();

			if (isset($combAttr['name'])) {
				$attribute = array(
					'name' => $combAttr['name'],
					'value' => $combAttr['value']
				);

				$key = base64_encode($combAttr['name']);

				if (!isset($this->attributes[$key])) {
					$this->attributes[$key] = array(
						'name' => $combAttr['name'],
						 'is_color' => ( stripos($combAttr['name'], 'color') !== false ) ? 1 : 0,
						'values' => array()
					);
				}

				if (!in_array($combAttr['value'], $this->attributes[$key]['values'])) {
					$this->attributes[$key]['values'][] = $combAttr['value'];
				}
				
				if (isset($combAttr['variation_hierarchy'])) {
					$this->getCombAttributes($combAttr['variation_hierarchy'], $combinations, array_merge($attributes, array($attribute)));
				} else {
					$combinations[$combAttr['tcin']] = array_merge($attributes, array($attribute));
				}
				
			}
		}
	}

	public function getCombinations() {
		static $combinations = array();
		if ($combinations) {
			return $combinations;
		}

		$weight = $this->getWeight();
		$price = $this->getPrice();

		if (isset($this->jsonDataArray['data']['product']['variation_hierarchy'])) {			
			
			$variationHierarchy = $this->jsonDataArray['data']['product']['variation_hierarchy'];
			
			if ($variationHierarchy) {
				$combsAttr = array();
				$this->getCombAttributes($variationHierarchy, $combsAttr);
				   
				foreach ($combsAttr as $tcin => $attributes) {

					$combPrice = $price;
					$combWeight = $weight;

					if (isset($this->jsonDataArray['data']['product']['children'])) {
						$children = $this->jsonDataArray['data']['product']['children'];
						
						if ($children) {
							$tcins = array_column($children, 'tcin');
							if (in_array($tcin, $tcins)) {
								$i = array_search($tcin, $tcins);
								if (isset($children[$i]['price']['current_retail'])) {
									$combPrice = $children[$i]['price']['current_retail'];
								}
							}

							if (isset($children[$i]['item']['package_dimensions']['weight'])) {
								$combWeight = array(
									'value' => $children[$i]['item']['package_dimensions']['weight'],
									'unit' => $children[$i]['item']['package_dimensions']['weight_unit_of_measure']
								);
							}
						}
					}

					$combinations[] = array(
						'sku' => $tcin,
						'upc' => 0,
						'price' => $combPrice,
						'weight' => $combWeight,
						'image_index' => $tcin,
						'attributes' => $attributes
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

		$dimension = array();

		if (isset($this->jsonDataArray['data']['product']['item']['package_dimensions'])) {
			$dimension = $this->jsonDataArray['data']['product']['item']['package_dimensions']; 
		}
		
		if (!isset($dimension['weight'])
			&& isset($this->jsonDataArray['data']['product']['children'])
		) {
			$childrens = $this->jsonDataArray['data']['product']['children'];
			
			if ($childrens) {
				$children = array_shift($childrens);
				if (isset($children['item']['package_dimensions'])) {
					$dimension = $children['item']['package_dimensions'];
				}
			}
		}

		if (isset($dimension['weight'])) {
			$weight = array(
				'value' => $dimension['weight'],
				'unit' => $dimension['weight_unit_of_measure']
			);

			return $weight;
		}

		$features = $this->getFeatures();

		if ($features) {
			foreach ($features as $feature) {
				foreach ($feature['attributes'] as $attr) {
					if (stripos($attr['name'], 'weight') !== false) {
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

		$features = $this->getValue($this->FEATURE_SELECTOR);
		if ($features) {
			foreach ($features as $feature) {
				list($name, $value) = explode(':', strip_tags($feature) . ':');
				$attributes[] = array(
					'name' => $name,
					'value'=> $value
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

	public function getCustomerReviews( $maxReviews = 0) {
		$reviews = array();

		$maxReviews = $maxReviews > 0 ? $maxReviews : 500;

		$tcin = $this->jsonDataArray['data']['product']['tcin'];
		$reviewlink = 'https://r2d2.target.com/ggc/reviews/v1/reviews?reviewType=PRODUCT&key=' . $this->jsonDataArray['apiKey'] . '&reviewedId=' . $tcin . '&size=' . (int) $maxReviews;

		$json = $this->getContent($reviewlink);

		if ($json) {
			$reviewData = json_decode($json, true);

			if (isset($reviewData['results']) && $reviewData['results']) {
				foreach ($reviewData['results'] as $review) {
					$reviews[] = array(
						'author' => isset($review['author']['nickname']) ? $review['author']['nickname'] : '',
						'title' => isset($review['title']) ? $review['title'] : '',
						'content' => $review['text'],
						'rating' => $review['Rating'],
						'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['submitted_at']))
					);
				}
			}
		}

		return $reviews;
	}

	public function getVideos() {
		$videos = array();

		if (isset($this->jsonDataArray['data']['product']['item']['enrichment']['videos'])
			&& $this->jsonDataArray['data']['product']['item']['enrichment']['videos']
		) {
			foreach ($this->jsonDataArray['data']['product']['item']['enrichment']['videos'] as $video) {
				foreach ($video['video_files'] as $file) {
					$videos[] = $file['video_url'];
				}
			}
		}

		return $videos;
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
					'cache-control: no-cache'
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
}
