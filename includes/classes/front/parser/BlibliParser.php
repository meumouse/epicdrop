<?php
/**
 * Blibli data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class BlibliParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $jsonDataArray = array();
	private $DESCRIPTION_SELECTOR = '//section[@class="pdp__description"]';
	private $PRICE_SELECTOR = '//div[@class="final-price"]';
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
					'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.51 Safari/537.36 Edg/99.0.1150.36'
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
		$skuId = $this->getSKU();
		$jsonUrl = 'https://www.blibli.com/backend/product-detail/products/' . $skuId . '/_summary';
		$json = $this->getContent($jsonUrl);
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = json_decode($json, true);
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
		if (isset($this->jsonDataArray['data']['name'])) {
			return $this->jsonDataArray['data']['name'];
		}
		return '';
	}

	public function getCategories() {
		$categories = array();
		if (isset($this->jsonDataArray['data']['categories']) && $this->jsonDataArray['data']['categories']) {
			foreach ($this->jsonDataArray['data']['categories'] as $categary) {
				$categories[] = $categary['name'];
			}
		}
		return $categories;
	}

	public function getShortDescription() {
		if (isset($this->jsonDataArray['data']['uniqueSellingPoint'])) {
			return $this->jsonDataArray['data']['uniqueSellingPoint'];
		}
		return '';
	}

	public function getDescription() {
		$description = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		$description = array_shift($description);

		$skuId = $this->getSKU();
		$id = explode('--', $skuId);
		$id = array_pop($id);
		$descript = 'https://www.blibli.com/backend/product-detail/products/' . $id . '/description';

		if ($descript) {
			$json = $this->getContent($descript);
			$descriptDataArray = json_decode($json, true);
			if (isset($descriptDataArray['data']['value'])) {
				return $descriptDataArray['data']['value'];
			} elseif ($description) {
				return $description;
			}
		}
		return '';
	}

	public function getPrice() {
		$price = $this->getValue($this->PRICE_SELECTOR);
		$price = array_shift($price);
		if ($price) {
			return preg_replace('/[^0-9.]/', '', $price);
		}
		$price = 0;
		if (isset($this->jsonDataArray['data']['price']['salePrice'])) {
			$price = $this->jsonDataArray['data']['price']['salePrice']/1000;
		}
		return $price;
	}

	public function getSKU() {
		$this->jsonDataArray['product'] = array();
		$url = explode('?', $this->url);
		$url = array_shift($url);
		$url = explode('/', $url);
		$sku = $url[5];
		if ($sku) {
			return $sku;
		}
		return '';
	}

	public function getBrand() {
		if (isset($this->jsonDataArray['data']['brand']['name'])) {
			return $this->jsonDataArray['data']['brand']['name'];
		}
		return '';
	}

	public function getCoverImage() {
		if (isset($this->jsonDataArray['data']['images'][0]['full'])) {
			return $this->jsonDataArray['data']['images'][0]['full'];
		}
		return '';
	}

	public function getImages() {
		static $images = array();
		if ($images) {
			return $images;
		}
		if (isset($this->jsonDataArray['data']['options']) && $this->jsonDataArray['data']['options']) {
			foreach ($this->jsonDataArray['data']['options'] as $key => $imgs) {
				if (isset($imgs['id'])) {
					$jsonUrl = 'https://www.blibli.com/backend/product-detail/products/' . $imgs['id'] . '/_summary';
					$json = $this->getContent($jsonUrl);
					$imageDataArray = json_decode($json, true);
					if (isset($imageDataArray['data']['images']) && $imageDataArray['data']['images']) {
						foreach ($imageDataArray['data']['images'] as $imgloop) {
							$images[$key][] = $imgloop['full'];
						}
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

	public function getFeatures() {
		static $featureGroups = array();
		if ($featureGroups) {
			return $featureGroups;
		}
		$skuId = $this->getSKU();
		$id = explode('--', $skuId);
		$id = array_pop($id);
		$attributes = array();
		$speci = 'https://www.blibli.com/backend/product-detail/products/' . $id . '/specifications';
		if ($speci) {
			$json = $this->getContent($speci);
			$fetureDataArray = json_decode($json, true);
			if (isset($fetureDataArray['data']) && $fetureDataArray['data']) {
				foreach ($fetureDataArray['data'] as $feature) {
					if (isset($feature['name'])) {
						$attributes[] = array(
							'name' => $feature['name'],
							'value' => $feature['value']
						);
					}
				}
			}
		}
		if ($attributes) {
			$featureGroups[] = array(
				'name' => 'Spesifikasi',
				'attributes' => $attributes
			);
		}
		return $featureGroups;
	}

	public function getAttributes() {
		static $attrGroups = array();
		if ($attrGroups) {
			return $attrGroups;
		}

		if (isset($this->jsonDataArray['data']['attributes']) && $this->jsonDataArray['data']['attributes']) {
			foreach ($this->jsonDataArray['data']['attributes'] as $attrs) {
				$attributes = array();
				if (isset($attrs['name'])) {
					foreach ($attrs['values'] as $attrVal) {
						if (isset($attrVal['value'])) {
							$attributes[] = $attrVal['value'];
						}
					}
					$attrGroups[] = array(
						'name' => $attrs['name'],
						'is_color' => 'Warna' == $attrs['name'] ? 1 : 0,
						'values' => $attributes
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
		$price = $this->getPrice();
		$sku = $this->getSKU();

		if (isset($this->jsonDataArray['data']['options']) && $this->jsonDataArray['data']['options']) {
			foreach ($this->jsonDataArray['data']['options'] as $key => $attrs) {
				if (isset($attrs['attributes']) && $attrs['attributes']) {
					$jsonUrl = 'https://www.blibli.com/backend/product-detail/products/' . $attrs['id'] . '/_summary';
					$json = $this->getContent($jsonUrl);
					$priceDataArray = json_decode($json, true);

					if (isset($priceDataArray['data']['price']['salePrice'])) {
						$comboPrice = $priceDataArray['data']['price']['salePrice']/1000;
					} elseif (isset($priceDataArray['data']['price']['listed'])) {
						$comboPrice = $priceDataArray['data']['price']['listed']/1000;
					} else {
						$comboPrice = $price;
					}

					$combinations[] = array(
						'sku' => isset($attrs['id']) ? $attrs['id'] : $sku,
						'upc' => '',
						'price' => $comboPrice,
						'weight' => 0,
						'image_index' => $key,
						'attributes' => $attrs['attributes']
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

	public function getCustomerReviews() {
		$reviews = array();
		if (isset($this->jsonDataArray['data']['options'])) {
			$productSku = $this->jsonDataArray['data']['productSku'];
		}
		$reviewLink = 'https://www.blibli.com/backend/product-review/public-reviews?page=1&itemPerPage=30&productSku=' . $productSku;

		if ($reviewLink) {
			$reviewJson = $this->getContent($reviewLink);

			if ($reviewJson) {
				$reviewArrayObject = json_decode($reviewJson, true);
				if (isset($reviewArrayObject['data']) && $reviewArrayObject['data']) {
					$isMaxReached = false;
					foreach ($reviewArrayObject['data'] as $reviewObject) {
						if (isset($reviewObject['name'])) {
							$reviews[] = array(
								'author' => $reviewObject['name'],
								'title' => isset($reviewObject['title']) ? $reviewObject['title'] : '',
								'content' => isset($reviewObject['content']) ? $reviewObject['content'] : '',
								'rating' =>$reviewObject['rating'],
								'timestamp' => gmdate('Y-m-d H:i:s', $reviewObject['createdDate']/1000)
							);
						}
					}
				}
			}
		}
		return $reviews;
	}
}
