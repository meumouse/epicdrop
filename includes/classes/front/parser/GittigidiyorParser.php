<?php
/**
 * Gittigidiyor data parser class
 *
 * @package: product-importer
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/* Parser version 1.0 */

class GittigidiyorParser extends AbstractParser {

	private $url;
	private $dom;
	private $xpath;
	private $content;
	private $host;
	private $jsonDataArray = array();
	private $CATEGORY_SELECTOR = '//ul[contains(@class, "robot-productPage-breadcrumb-hiddenBreadCrumb")]/li/span/a';
	private $DESCRIPTION_SELECTOR = '//article|//div[@id="satici-aciklamasi"]';
	private $PRICE_SELECTOR = '//div[@id="sp-price-lowPrice"]|//span[@id="sp-price-highPrice"]';
	private $BRAND_SELECTOR = '//a[@id="spp-brand"]';
	private $COVER_IMG_SELECTOR = '//a[contains(@class, "to-first-tab")]/img/@data-original';
	private $IMAGE_SELECTOR = '//ul[contains(@class, "product-photos-ul ")]/li/img';
	private $FEATURE_SELECTOR = '//table[contains(@class, "spec-group-container")]';
	private $FEATURE_SELECTOR1 = '//table[contains(@class, "features")]';
	private $ATTRIBUTES_SELECTOR = '//div[@id="sp-spec-options"]/div';
	private $REVIEW_LINK_SELECTOR = '//a[@class="see-all-catalog-review"]/@href';
	private $REVIEW_SELECTOR = '//div[@id="catalog-review-comments"]/div';
	private $REVIEW_SELECTOR1 = '//article[@data-cy="review-list-box"]';
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

	public function getContent( $url, $postVar = array()) {
		$curl = curl_init();

		$curlopts = array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_HTTPHEADER => array(
				'cache-control: no-cache',
				"Origin: $url",
				'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36'
			),
		);

		if ($postVar) {
			$curlopts[CURLOPT_POST] = true;
			$curlopts[CURLOPT_POSTFIELDS] = $postVar;
		} else {
			$curlopts[CURLOPT_CUSTOMREQUEST] = 'GET';
		}

		curl_setopt_array(
			$curl,
			$curlopts
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
		$json = $this->getJson($this->content, '<script type="application/ld+json">', '</script>');
		if ($json) {
			$json = iconv('UTF-8', 'UTF-8//IGNORE', $json);
			$this->jsonDataArray = json_decode($json, true);
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
		if (isset($this->jsonDataArray['name'])) {
			return $this->jsonDataArray['name'];
		}
		return '';
	}

	public function getCategories() {
		$categories = array();
		$categories = $this->getValue($this->CATEGORY_SELECTOR);
		array_shift($categories);
		array_shift($categories);
		return array_unique($categories);
	}

	public function getDescription() {
		
		$descripts = $this->getValue($this->DESCRIPTION_SELECTOR, true);
		$descripts = array_shift($descripts);
		if ($descripts) {
			if (stripos($descripts, 'https://') !== false) {
				return $descripts;
			} else {
				return str_replace('src="', 'src="https://www.gittigidiyor.com', $descripts);
			}
		}
		return '';
	}

	public function getShortDescription() {
		if (isset($this->jsonDataArray['description'])) {
			return $this->jsonDataArray['description'];
		}
		return '';
	}

	public function getPrice() {
		$price = 0;
		$price = $this->getValue($this->PRICE_SELECTOR);
		$priceText = array_shift($price);

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

	public function getSKU() {
		if (isset($this->jsonDataArray['mainEntity']['offers']['itemOffered'][0]['sku'])) {
			return $this->jsonDataArray['mainEntity']['offers']['itemOffered'][0]['sku'];
		}
		return '';
	}


	public function getBrand() {
		$brand = $this->getValue($this->BRAND_SELECTOR);
		return array_shift($brand);
	}

	public function getCoverImage() {
		$img = $this->getValue($this->COVER_IMG_SELECTOR);
		$img = array_shift($img);
		return str_replace('tn50', 'tn500', $img);
	}

	public function getImages() {
		static $images = array();
		if ($images) {
			return $images;
		}

		$imgs = $this->xpath->query($this->IMAGE_SELECTOR);
		if ($imgs->length) {
			foreach ($imgs as $image) {
				$class = $image->getAttribute('class');
				preg_match('/galleryImage-(\d+)/', $class, $key);
				if ($key) {
					$images[$key[1]][] = str_replace('tn14', 'tn500', $image->getAttribute('data-original'));
				}
			}
		}
		$imag = array();
		if (isset($this->jsonDataArray['mainEntity']['offers']['itemOffered'][0]['image']) && $this->jsonDataArray['mainEntity']['offers']['itemOffered'][0]['image']) {
			foreach ($this->jsonDataArray['mainEntity']['offers']['itemOffered'][0]['image'] as $imgs) {
				if (isset($imgs['contentUrl'])) {
					$imag[] = $imgs['contentUrl'];
				}
			}
		}
		$images[0] = $imag;

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
		$featureArrayObject = $this->xpath->query($this->FEATURE_SELECTOR);
		$featureArrayObject1 = $this->xpath->query($this->FEATURE_SELECTOR1);
		if ($featureArrayObject->length) {
			foreach ($featureArrayObject as $featureObject) {
				$genral = $this->xpath->query('.//*[@class="catalog-title"]', $featureObject)->item(0)->nodeValue;
				$featuresNm = $this->xpath->query('.//td', $featureObject);
				if ($featuresNm->length) {
					for ($i=1; $i<$featuresNm->length; $i=$i+2) {
						if (( $i+1 ) <= $featuresNm->length) {
							$attributes[] = array(
								'name' => preg_replace('/\s+/', ' ', $featuresNm->item($i)->nodeValue),
								'value' => preg_replace('/\s+/', ' ', $featuresNm->item(( $i+1 ))->nodeValue)
							);
						}
					}
				}
				if ($attributes) {
					$featureGroups[] = array(
						'name' => $genral,
						'attributes' => $attributes
					);
				}
			}
		}
		if ($featureArrayObject1->length) {
			foreach ($featureArrayObject1 as $featureObject) {
				$featuresNm = $this->xpath->query('.//td', $featureObject);
				if ($featuresNm->length) {
					for ($i=0; $i<$featuresNm->length; $i=$i+2) {
						if (( $i+1 ) <= $featuresNm->length) {
							$attributes[] = array(
								'name' => preg_replace('/\s+/', ' ', $featuresNm->item($i)->nodeValue),
								'value' => preg_replace('/\s+/', ' ', $featuresNm->item(( $i+1 ))->nodeValue)
							);
						}
					}
				}
				if ($attributes) {
					$featureGroups[] = array(
						'name' => 'Genral',
						'attributes' => $attributes
					);
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
		$attributeArrayObject = $this->xpath->query($this->ATTRIBUTES_SELECTOR);

		if ($attributeArrayObject->length) {
			foreach ($attributeArrayObject as $attributeObject) {
				$attrName = $this->xpath->query('.//p[@class="sp-specName"]', $attributeObject)->item(0)->nodeValue;

				$attrGpId = $this->xpath->query('.//ul/@rel', $attributeObject)->item(0)->nodeValue;

				$attrs = $this->xpath->query('.//li', $attributeObject);
				$attrValue = array();

				if ($attrs->length) {
					foreach ($attrs as $attrVals) {
						$attrVlId = $attrVals->getAttribute('rel');
						if ($attrGpId != $attrVlId) {
							$attrVal = $attrVals->getAttribute('title');
							$attrValue[$attrVlId] = preg_replace('/\s+/', ' ', $attrVal);
						}
					}
				}
				$attrName = preg_replace('/\s+/', ' ', $attrName);
				$attrGroups[$attrGpId] = array(
					'name' => $attrName,
					'is_color' => ( stripos($attrName, 'Renk') !== false ) ? 1 : 0,
					'values' => $attrValue
				);
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
				$combinations[] = array(
					'sku' => $sku,
					'upc' => 0,
					'price' => $price,
					'weight' => 0,
					'image_index' => $imageIndex,
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

	public function getCustomerReviews() {
		$reviews = array();
		$rLink = $this->getValue($this->REVIEW_LINK_SELECTOR);

		$link = array_shift($rLink);
		if ($link) {
			if (stripos($link, 'https://') !==false) {
				$reviewsLink = $link;
			} else {
				$reviewsLink = 'https://www.gittigidiyor.com' . $link;
			}

			if ($reviewsLink) {
				$reviewHtml = $this->getContent($reviewsLink);
				if ($reviewHtml) {
					$dom = $this->getDomObj($reviewHtml);
					$xpath = new \DomXPath($dom);

					$reviewArrayObject = $xpath->query($this->REVIEW_SELECTOR);

					if ($reviewArrayObject->length) {
						foreach ($reviewArrayObject as $reviewObject) {
							$content = $xpath->query('.//div[contains(@class, "user-catalog-review-comment")]', $reviewObject);
							$titles = $xpath->query('.//div[contains(@class, "user-catalog-review-header")]/h3', $reviewObject);

							if ($titles->length) {
								$title = $titles->item(0)->nodeValue;
							} else {
								$title = '';
							}
							if ($content->length) {
								$stars = $xpath->query('.//div[@class="catalog-shining-stars"]/@rev', $reviewObject)->item(0)->nodeValue;
								$rating = preg_replace('/[^0-9]/', '', $stars)/20;

								$reviews[] = array(
									'author' => trim($xpath->query('.//a[@class="user-nick-name"]/p', $reviewObject)->item(0)->nodeValue),
									'title' => $title,
									'content' => preg_replace('/\s+/', ' ', $content->item(0)->nodeValue),
									'rating' => $rating,
									'timestamp' => trim($xpath->query('.//span[@class="user-catalog-review-date"]', $reviewObject)->item(0)->nodeValue)
								);
							}
						}
					}

					$reviewArrayObject1 = $xpath->query($this->REVIEW_SELECTOR1);
					if ($reviewArrayObject1->length) {
						foreach ($reviewArrayObject1 as $reviewObject) {
							$content = '';
							$contents = $xpath->query('.//div[contains(@class, "FjqEN")]/p', $reviewObject);
							if ($contents) {
								foreach ($contents as $comment) {
									$content .= $comment->nodeValue;
								}
							}
							$titles = $xpath->query('.//div[contains(@class, "iWSiUv")]/h2', $reviewObject);

							if ($titles->length) {
								$title = $titles->item(0)->nodeValue;
							} else {
								$title = '';
							}
							if ($content) {
								$stars = $xpath->query('.//div[contains(@class, "gTuRKZ")]/div/@class', $reviewObject)->item(0)->nodeValue;
								if (stripos($stars, 'gfVIgr') !==false) {
									$rating = 1;
								}
								if (stripos($stars, 'gfXjSV') !==false) {
									$rating = 2;
								}
								if (stripos($stars, 'gfWJoL') !==false) {
									$rating = 3;
								}
								if (stripos($stars, 'gfYeEl') !==false) {
									$rating = 4;
								}
								if (stripos($stars, 'jLwWXg') !==false) {
									$rating = 5;
								}

								$reviews[] = array(
									'author' => trim($xpath->query('.//p[contains(@class, "yLbdS")]', $reviewObject)->item(0)->nodeValue),
									'title' => $title,
									'content' => preg_replace('/\s+/', ' ', $content),
									'rating' => $rating,
									'timestamp' => trim($xpath->query('.//span[contains(@class, "fyMopb")]', $reviewObject)->item(0)->nodeValue)
								);
							}
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

		$reviewArrayObject1 = $this->xpath->query($this->REVIEW_SELECTOR);
		if ($reviewArrayObject1->length) {
			foreach ($reviewArrayObject1 as $reviewObject) {
				$content = $this->xpath->query('.//div[contains(@class, "user-catalog-review-comment")]', $reviewObject);
				$titles = $this->xpath->query('.//div[contains(@class, "user-catalog-review-header")]/h3', $reviewObject);

				if ($titles->length) {
					$title = $titles->item(0)->nodeValue;
				} else {
					$title = '';
				}
				if ($content->length) {
					$stars = $this->xpath->query('.//div[@class="catalog-shining-stars"]/@rev', $reviewObject)->item(0)->nodeValue;
					$rating = preg_replace('/[^0-9]/', '', $stars)/20;

					$reviews[] = array(
						'author' => trim($this->xpath->query('.//a[@class="user-nick-name"]/p', $reviewObject)->item(0)->nodeValue),
						'title' => $title,
						'content' => preg_replace('/\s+/', ' ', $content->item(0)->nodeValue),
						'rating' => $rating,
						'timestamp' => trim($this->xpath->query('.//span[@class="user-catalog-review-date"]', $reviewObject)->item(0)->nodeValue)
					);
				}
			}
		}
		return $reviews;
	}
}
