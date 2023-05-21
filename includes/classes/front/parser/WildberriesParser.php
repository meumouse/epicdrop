<?php
/**
 * Wildberries data parser class
 *
 * @package: product-importer
 *
 */
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class WildberriesParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $combColors = array();
	private $jsonDataArray = array();
	private $attrDataArray = array();
	private $TITLE_SELECTOR = '//h1';
	private $REVIEW_SELECTOR = '//ul[@class="comments__list"]/li';
	private $DATA_SELECTOR = '//script[@id="item"]';
	private $PRICE_SELECTOR = '//span[@class="price-block__final-price"]|//div[@id="product-price"]/span/span[1]';
	private $LANG_SELECTOR = '//html/@lang';
	private $VIDEO_SELECTOR = '//video/@src';
	private $PRODUCT_ID_SELECTOR = '//div[@data-tag="productOptions"]/div/div/span';
	private $IMAGE_COVER_SELECTOR = '//img[@class="hover-zoom-hero-image"]/@src';
	private $IMAGE_SELECTOR = '//div[@class="sw-slider-kt-mix__wrap"]/div/ul/li/div/img/@src';
	private $META_TITLE_SELECTOR = '//title';
	private $META_DESCRIPTION_SELECTOR = '//meta[@name="description"]/@content';
	private $META_KEYWORDS_SELECTOR = '//meta[@name="keywords"]/@content';

	public function __construct( $content, $url) {
		$this->url = $url;
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
		$productId = $this->getValue($this->PRODUCT_ID_SELECTOR);
		if ($productId) {
			$productId = $productId[1];
		} elseif ($this->url && strpos($this->url, 'card=') !== false) {
			$v = parse_url($this->url);
			parse_str($v['query'], $urlID);
			$productId = $urlID['card'];
		}
		if (!$productId && preg_match('/\/catalog\/([0-9]+)\/detail\.aspx/', $this->url, $m)) {
			$productId = $m[1];
		}

		$url = 'https://wbx-content-v2.wbstatic.net/en/' . $productId . '.json';
		
		$json = $this->getContent($url);
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);

			$this->jsonDataArray = json_decode($json, true);
		}

		$this->jsonDataArray['productId'] = $productId;
		if (isset($this->jsonDataArray['colors']) && $this->jsonDataArray['colors']) {
			$totalId = $this->jsonDataArray['colors'];
			$totalId = implode(';', $totalId);

			$url = 'https://wbxcatalog-ru.wildberries.ru/nm-2-card/catalog?lang=en&locale=ru&nm=' . $totalId;
			$json = $this->getContent($url);
			if ($json) {
				$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
				$this->jsonDataArray = array_merge($this->jsonDataArray, json_decode($json, true));
			}
			
			foreach ($this->jsonDataArray['colors'] as $colorsId) {
				$url = 'https://wbx-content-v2.wbstatic.net/en/' . $colorsId . '.json';
				$json = $this->getContent($url);
				if ($json) {
					$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
					$variation = json_decode($json, true);
					if ($variation) {
						$this->jsonDataArray['data']['products'][] = $variation;
					}
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
		$titles = $this->getValue($this->TITLE_SELECTOR);
		return preg_replace('/\s+/', ' ', array_shift($titles));
	}

	public function getCategories() {
		$categories = array();
		if (isset($this->jsonDataArray['subj_name'])  &&  $this->jsonDataArray['subj_name']) {
			$categories[] = $this->jsonDataArray['subj_name'];
		}
		return $categories;
	}

	public function getShortDescription() {
		if (isset($this->jsonDataArray['productCard']['consist'])) {
			return $this->jsonDataArray['productCard']['consist'];
		}
		return '';
	}

	public function getDescription() {
		return '';
	}

	public function getPrice() {
		$price = 0;
		$prices = $this->getValue($this->PRICE_SELECTOR);
		
		if ($prices) {
			
			$priceText = array_shift($prices);
			
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
			
			$price = preg_replace('/[^0-9.]/', '', $priceText);
			
		} elseif (isset($this->jsonDataArray['data']['products']) && $this->jsonDataArray['data']['products']) {
			$price = $this->jsonDataArray['data']['products'];
			$price = array_shift($price);
			$price = $price['salePriceU']/100;
		}
		
		return $price;
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['nm_id'])) {
			return $this->jsonDataArray['nm_id'];
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['selling']['brand_name'])) {
			return $this->jsonDataArray['selling']['brand_name'];
		}
		return '';
	}

	public function getCoverImage() {
		$images = $this->getValue($this->IMAGE_COVER_SELECTOR);
		if ($images) {
			$image = array_shift($images);
			return 'https:' . strstr($image, '?', true);
		}
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}

		$image = $this->getValue($this->IMAGE_SELECTOR);

		$imgUrl = 'https://img2.wbstatic.net/big/new';

		if (isset($this->jsonDataArray['data']['products'])
			&& $this->jsonDataArray['data']['products']) {
			foreach ($this->jsonDataArray['data']['products'] as $comb) {
				if (isset($comb['id'])) {
					$folder = $comb['id'] - $comb['id'] % 10000;

					for ($i = 1; $i <= $comb['pics']; $i++) {
						$images[$comb['id']][] = $imgUrl . '/' . $folder . '/' . $comb['id'] . '-' . $i . '.jpg';
					}
				}
			}
		} elseif ($image) {
			foreach ($image as $img) {
				$images[0][] = 'https:' . str_replace('c246x328', 'big', $img);
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
	public function getWeight() {
		static $weight = array();

		if ($weight) {
			return $weight;
		}

		$features = $this->getFeatures();

		if ($features) {
			foreach ($features as $feature) {
				foreach ($feature['attributes'] as $attr) {
					if (stripos($attr['name'], 'bес') !== false) {
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

		if (isset($this->jsonDataArray['compositions']) && $this->jsonDataArray['compositions']) {
			foreach ($this->jsonDataArray['compositions'] as $attribute) {
				if (isset($attribute['value'])) {
					$attributes[] = array(
						'name' => $attribute['name'],
						'value' => $attribute['value']
					);
				}
			}
		}
		if (isset($this->jsonDataArray['options']) && $this->jsonDataArray['options']) {
			foreach ($this->jsonDataArray['options'] as $attribute) {
				if (isset($attribute['value'])) {
					$attributes[] = array(
						'name' => $attribute['name'],
						'value' => $attribute['value']
					);
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

	public function getAttributes() {
		static $attrGroups = array();

		if ($attrGroups) {
			return $attrGroups;
		}

		$colors = array();
		$sizes = array();

		if (isset($this->jsonDataArray['data']['products'])
			&& $this->jsonDataArray['data']['products']) {
			foreach ($this->jsonDataArray['data']['products'] as $comb) {
				if (isset($comb['colors']) && $comb['colors']) {
					$color = array_column($comb['colors'], 'name');
					$colors[$comb['id']] = implode('+', $color);
				} elseif (isset($comb['nm_colors_names'])) {
					$colors[$comb['nm_id']] = $comb['nm_colors_names'];
				}

				if (isset($comb['sizes'])) {
					foreach ($comb['sizes'] as $size) {
						if ($size['origName'] && in_array($size['origName'], $size)) {
							$sizes[] = $size['origName'];
						}
					}
				}

				if (isset($this->jsonDataArray['sizes_table']['values'])
				&& $this->jsonDataArray['sizes_table']['values']) {
					foreach ($this->jsonDataArray['sizes_table']['values'] as $size) {
						$sizes[] = $size['tech_size'];
					}
				}
			}
		} elseif (isset($this->jsonDataArray['sizes_table']['values'])
			&& $this->jsonDataArray['sizes_table']['values']) {
			if (isset($this->jsonDataArray['nm_colors_names'])) {
				$colors[] = $this->jsonDataArray['nm_colors_names'];
			}

			foreach ($this->jsonDataArray['sizes_table']['values'] as $size) {
				$sizes[] = $size['tech_size'];
			}
		} elseif (isset($this->jsonDataArray['nm_colors_names'])) {
			$colors[] = $this->jsonDataArray['nm_colors_names'];
		}

		if ($colors) {
			$attrGroups[] = array(
				'name' => 'Color',
				'is_color' => 1,
				'values' => array_unique($colors)
			);
		}
		if ($sizes) {
			$attrGroups[] = array(
				'name' => 'Size',
				'is_color' => 0,
				'values' => array_unique($sizes)
			);
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

				$combPrice = 0;

				if (isset($this->jsonDataArray['nomenclatures'][$imageIndex]['priceDetails']['promoPrice'])) {
					$combPrice = $this->jsonDataArray['nomenclatures'][$imageIndex]['priceDetails']['promoPrice'];
				}

				if (!$combPrice && isset($this->jsonDataArray['nomenclatures'][$imageIndex]['priceDetails']['basicPrice'])) {
					$combPrice = $this->jsonDataArray['nomenclatures'][$imageIndex]['priceDetails']['basicPrice'];
				}

				if ($combPrice) {
					$price = $combPrice;
				}

				$combinations[] = array(
					'sku' => $imageIndex,
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
		return $this->getTitle();
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
		$maxReviews = $maxReviews > 0 ? $maxReviews : 500;

		$productId = $this->jsonDataArray['productId'];

		$reviewlink = 'https://wbx-content-v2.wbstatic.net/ru-feedbacks/' . $productId . '.json';
		$json = $this->getContent($reviewlink);
		if ($json) {
			$reviewData = json_decode($json, true);

			if (isset($reviewData['feedbacks']) && $reviewData['feedbacks']) {
				foreach ($reviewData['feedbacks'] as $review) {
					$reviews[] = array(
						'author' => isset($review['author']['nickname']) ? $review['author']['nickname'] : '',
						'title' => isset($review['title']) ? $review['title'] : '',
						'content' => $review['text'],
						'rating' => $review['rate'],
						'timestamp' => gmdate('Y-m-d H:i:s', $review['datetime'])
					);
					if (count($reviews) >= $maxReviews) {
						break;
					}
				}
			}
		}

		return $reviews;
	}

	public function getVideos() {
		$videos = $this->getValue($this->VIDEO_SELECTOR);

		$videos = array_map(
			function ( $video) {
				return substr($video, 0, 2) == '//' ? 'https:' . $video : $video;
			},
			$videos
		);

		return array_unique($videos);
	}
}
