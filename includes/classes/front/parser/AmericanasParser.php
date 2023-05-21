<?php
/**
 * Americanas data parser class
 *
 * @package: product-importer
 *
 */
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class AmericanasParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $combColors = array();
	private $jsonDataArray = array();
	private $attrDataArray = array();
	private $REVIEW_SELECTOR = '//ul[@class="comments__list"]/li';
	private $DATA_SELECTOR = '//script[@id="item"]';
	private $PRICE_SELECTOR = '//div[@class="src__BestPrice-sc-1jvw02c-5 cBWOIB priceSales"]';
	private $SITE_LANG_SELECTOR = '//html/@lang';
	private $IMAGE_COVER_SELECTOR = '//img[@class="hover-zoom-hero-image"]/@src';
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

	private function setJsonData() {
		$json = $this->getJson($this->content, '__APOLLO_STATE__ = ', '</script>');
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = json_decode($json, true);
		}
		if ($this->url) {
			$url = explode('?', $this->url);
			$halfUrl = array_shift($url);
			$halfUrl = explode('/', $halfUrl);
			$productId = array_pop($halfUrl);
			$this->jsonDataArray['Id'] =  $productId;
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
		if (isset($this->jsonDataArray['Id'])) {
			$id = $this->jsonDataArray['Id'];
			if (isset($this->jsonDataArray['ROOT_QUERY']['product:{"productId":"' . $id . '"}']['name'])) {
				return $this->jsonDataArray['ROOT_QUERY']['product:{"productId":"' . $id . '"}']['name'];
			}
		}
		return '';
	}

	public function getCategories() {
		$categories = array();
		if (isset($this->jsonDataArray['Id'])) {
			$id = $this->jsonDataArray['Id'];
			$categorie = array();
			if (isset($this->jsonDataArray['ROOT_QUERY']['page:{"path":"/produto/' . $id . '"}']['breadcrumb']) && $this->jsonDataArray['ROOT_QUERY']['page:{"path":"/produto/' . $id . '"}']['breadcrumb']) {
				foreach ($this->jsonDataArray['ROOT_QUERY']['page:{"path":"/produto/' . $id . '"}']['breadcrumb'] as $category) {
					$categorie[] = $this->jsonDataArray[$category['__ref']]['name'];
				}
			}
			$categories = array_reverse($categorie);
		}
		return array_unique($categories);
	}

	public function getShortDescription() {
		if (isset($this->jsonDataArray['ROOT_QUERY']['description'])) {
			return $this->jsonDataArray['ROOT_QUERY']['description'];
		}
		return '';
	}

	public function getDescription() {
		if (isset($this->jsonDataArray['Id'])) {
			$id = $this->jsonDataArray['Id'];
			if (isset($this->jsonDataArray['ROOT_QUERY']['product:{"productId":"' . $id . '"}']['description']['content'])) {
				$descri = $this->jsonDataArray['ROOT_QUERY']['product:{"productId":"' . $id . '"}']['description']['content'];
				if (filter_var($descri, FILTER_VALIDATE_URL) !== false) {
					$content = $this->getContent($descri);
					if ($content) {
						$dom = $this->getDomObj($content);
						$xpath = new \DomXPath($dom);


						$content = $dom->saveHTML($xpath->query('//div[@id="conteudo"]')->item(0));

						$baseUrl = dirname($descri) . '/';
						return $this->replaceDescriptionImage($content, $baseUrl);
					}
				} else {
					return $descri;
				}
			}
		}
		return '';
	}

	public function getPrice() {
		$price = $this->getValue($this->PRICE_SELECTOR);
		if ($price) {
			$price = array_shift($price);
			$price = str_replace(array('.', ','), array('', '.'), $price);

			return preg_replace('/[^0-9\.]/', '', $price);
		}
	}

	public function getSKU() {
		if (isset($this->jsonDataArray['Id'])) {
			$id = $this->jsonDataArray['Id'];
			if (isset($this->jsonDataArray['ROOT_QUERY']['product:{"productId":"' . $id . '"}']['id'])) {
				return $this->jsonDataArray['ROOT_QUERY']['product:{"productId":"' . $id . '"}']['id'];
			}
		}
		return '';
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
					if (stripos($attr['name'], 'peso') !== false) {
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

	public function getBrand() {
		if (isset($this->jsonDataArray['ROOT_QUERY']['config']['brand'])) {
			return $this->jsonDataArray['ROOT_QUERY']['config']['brand'];
		}
		return '';
	}

	public function getImages() {
		static $images = array();

		if ($images) {
			return $images;
		}

		$this->getCombinations();

		$images = $this->images;

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

		$attrGroups = array_map(
			function ( $attr) {
				$attr['values'] = array_unique($attr['values']);
				return $attr;
			},
			$this->attributes
		);

		return $attrGroups;
	}

	public function getCombinations() {
		static $combinations = array();
		if ($combinations) {
			return $combinations;
		}

		$price = $this->getPrice();
		$weight = $this->getWeight();

		if (isset($this->jsonDataArray['Id'])) {
			$id = $this->jsonDataArray['Id'];
			if (isset($this->jsonDataArray['ROOT_QUERY']['product:{"productId":"' . $id . '"}']['skus'])
				&& $this->jsonDataArray['ROOT_QUERY']['product:{"productId":"' . $id . '"}']['skus']) {
				foreach ($this->jsonDataArray['ROOT_QUERY']['product:{"productId":"' . $id . '"}']['skus'] as $skuInfo) {
					if (isset($this->jsonDataArray[$skuInfo['__ref']])) {
						$comb = $this->jsonDataArray[$skuInfo['__ref']];

						if ($comb['images']) {
							foreach ($comb['images'] as $img) {
								$this->images[$comb['id']][] = $img['extraLarge'];
							}
						}

						$attrVals = array();

						if ($comb['diffs']) {
							foreach ($comb['diffs'] as $attr) {
								$attrVals[] = array(
									'name' => $attr['type'],
									'value' => $attr['value']
								);

								$key = base64_encode($attr['type']);

								if (!isset($this->attributes[$key])) {
									$this->attributes[$key] = array(
										'name' => $attr['type'],
										'is_color' => ( strpos(strtolower($attr['type']), 'cor') !== false ) ? 1 : 0,
										'values' => array()

									);
								}

								$this->attributes[$key]['values'][] = $attr['value'];
							}

							$combinations[] = array(
								'sku' => $comb['id'],
								'upc' => 0,
								'price' => $price,
								'weight' => $weight,
								'image_index' => $comb['id'],
								'attributes' => $attrVals
							);
						}
					}
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

	public function getFeatures() {
		static $featureGroups = array();

		if ($featureGroups) {
			return $featureGroups;
		}

		$attributes = array();
		if (isset($this->jsonDataArray['Id'])) {
			$id = $this->jsonDataArray['Id'];
			if (isset($this->jsonDataArray['ROOT_QUERY']['product:{"productId":"' . $id . '"}']['attributes']) && $this->jsonDataArray['ROOT_QUERY']['product:{"productId":"' . $id . '"}']['attributes']) {
				foreach ($this->jsonDataArray['ROOT_QUERY']['product:{"productId":"' . $id . '"}']['attributes'] as $attrs) {
					$attributes[] = array(
						'name' => $attrs['name'],
						'value'=> $attrs['value']
					);
				}
			}
		}
		if ($attributes) {
			$featureGroups[] = array(
				'name' => 'General',
				'attributes' => $attributes
			);
		}
		return $featureGroups;
	}

	public function getCustomerReviews() {
		$reviews = array();
		if (isset($this->jsonDataArray['Id'])) {
			$id = $this->jsonDataArray['Id'];
			if (isset($this->jsonDataArray['ROOT_QUERY']['product:{"productId":"' . $id . '"}']['reviews({"limit":6})']['result']) && $this->jsonDataArray['ROOT_QUERY']['product:{"productId":"' . $id . '"}']['reviews({"limit":6})']['result']) {
				foreach ($this->jsonDataArray['ROOT_QUERY']['product:{"productId":"' . $id . '"}']['reviews({"limit":6})']['result'] as $totReview) {
					if (isset($this->jsonDataArray[$totReview['__ref']])) {
						$review = $this->jsonDataArray[$totReview['__ref']];
						$reviews[] = array(
							'author' => $review['user'],
							'title' => $review['title'],
							'content' => $review['review'],
							'rating' => $review['rating'],
							'timestamp' => gmdate('Y-m-d H:i:s', strtotime($review['date']))
						);
					}
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
		   'Origin: https://www.americanas.com.br/',
		);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

		$resp = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);

		if ($err) {
			return false;
		}

		return $resp;
	}
	public function replaceDescriptionImage( $html, $baseUrl) {
		if (!$html) {
			return $html;
		}

		return preg_replace_callback(
			'/src="(.*?)"/',
			function ( $match) use ( $baseUrl) {
				$file = $match[1];

				if (filter_var($file, FILTER_VALIDATE_URL) === false) {
					$file = $baseUrl . $file;
				}

				return 'src="' . $file . '"';
			},
			$html
		);
	}
}
