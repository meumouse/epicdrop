<?php
/**
 * Main importer class file that responsible for all the front actions.
 *
 * @package: epicdrop
 * @since 1.0.0
 */
namespace Epicdrop\Classes\Frontend;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class Importer {

	protected $Parser;
	protected $Config = array();
	
	public function __construct() {
		
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('initialized', 'epicdrop'), __FILE__, __LINE__);

		$this->Config = get_option('importer_setting');
		$response = array();
		
		if ($this->getParam('action') != null && in_array( $this->getParam('action'), array('connect', 'import') )) {

			$response = $this->validateCredential();

			if (!$response) {
				$response = $this->{$this->getParam('action') . 'Action'}($this->getParam());
			}
		}
	
		if (!$response) {
			$response = array(
				'status' => 0,
				'message' => __('Ação inválida!', 'epicdrop')
			);
		}
		
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('completed', 'epicdrop'), __FILE__, __LINE__);
				
		$this->displayResponse($response);
		
	}
	
	protected function connectAction() {
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('called', 'epicdrop'), __FILE__, __LINE__);

		return array(
			'status' => 1,
			'message' => __('Sucesso.', 'epicdrop'),
			'data' => array(
				'shop' => array(
					'name' => get_bloginfo('name'),
					'url' => get_bloginfo('url'),
					'desc' => \Woo_EpicDrop_Hosts::getImportHostNames()
				),
				'form' => $this->getImportForm()
			)
		);
	}
	
	protected function getImportForm() {
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('called', 'epicdrop'), __FILE__, __LINE__);

		$formOptions = array(
			'options' => \Woo_EpicDrop_Init::getImportOptions(),
			'isAdvaceEnabled' => (int) $this->Config['advance_option'],
			'affiliateLink' => $this->Config['affiliate_id']
		);

		if ((int) $this->Config['advance_option']) {
			$categories = \Woo_EpicDrop_Init::getCategories();
			$taxClasses = \Woo_EpicDrop_Init::getTaxClasses();

			$formOptions = array_merge(
				$formOptions,
				array(
					'categories'=> $categories,
					'taxClasses' => $taxClasses
				)
			);
		}

		$form = EpicDrop_Import_form($formOptions);
		
		return $this->trim($form);
	}
	
	protected function validateHost( $document) {
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('called', 'epicdrop'), __FILE__, __LINE__);

		if (!isset($document['url']) || '' == $document['url'] || !isset($document['html']) || '' == $document['html']) {
			return false;
		}
		
		foreach (\Woo_EpicDrop_Init::getImportHosts() as $host) {
			if (preg_match($host['valid_url'], $document['url'])) {
				include_once EPICDROP_DIR . '/includes/classes/front/parser/AbstractParser.php';
				include_once EPICDROP_DIR . '/includes/classes/front/parser/' . $host['parser'] . '.php';
				$parserClass = '\\EpicDrop\\Importer\\classes\\front\\Parser\\' . $host['parser'];
				$this->Parser = new $parserClass(stripslashes($document['html']), $document['url']);
				return true;
			}
		}
		return false;
	}
	
	protected function importAction( $params) {
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('called', 'epicdrop'), __FILE__, __LINE__);

		ignore_user_abort(true);
		set_time_limit(0);
		wp_raise_memory_limit();
		ini_set('xdebug.max_nesting_level', -1);
		
		$document = $params['document'];

		if (!$this->validateHost($document)) {
			return array(
				'status' => 0,
				'message' => __('Os dados ou a página do produto são inválidos.', 'epicdrop')
			);
		}
		parse_str($params['form'], $importOptions);
		// Check to at least one import option must be selected except id_product, association
		if (count($importOptions) < 2) {
			return array(
				'status' => 0,
				'message' => __('Deve-se escolher pelo menos uma opção para importar o produto.', 'epicdrop')
			);
		}
		
		$importOptions['product_link'] = $document['url'];
		
		/* Set all options as false in default. */
		$optionNames = array_column(\Woo_EpicDrop_Init::getImportOptions(), 'name');
		array_unshift($optionNames, 'id_product', 'association', 'affiliate_link', 'product_link');
		
		$options = array();
		foreach ($optionNames as $option) {
			if (isset($importOptions[$option])) {
				$options[$option] = $importOptions[$option];
			} else {
				$options[$option] = 0;
			}
		}
		
		$response = $this->createProduct($options);
		if (!$response) {
			$response['status'] = false;
			$response['message'] = __('Erro na importação, tente novamente.');
		}
		
		return $response;
	}
	
	protected function createProduct( $options) {
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('called', 'epicdrop'), __FILE__, __LINE__);
		
		$response = array();
		
		if ($this->Parser->getTitle() == '') {
			return array(
				'status' => false,
				'message' => __('Os dados ou a página do produto são inválidos.', 'epicdrop')
			);
		}
		
		if (isset($options['association']['sku']) && $options['association']['sku']) {
			$sku = $options['association']['sku'];
		} else {
			$sku = $this->Parser->getSKU();
		}
		
		$sku = $this->cleanText($sku, 32, false);
		
		$id_from_sku = wc_get_product_id_by_sku( $sku );
		
		if ($options['id_product']) {
			
			$product = wc_get_product( $options['id_product'] );
			
			if (!$product) {
				return array(
					'status' => false,
					'message' => __('O produto não existe!', 'epicdrop')
				);
			}
			
			if ($id_from_sku) {
			
				$product = wc_get_product( $id_from_sku );
				
				if ($product && ( 0 < $product->get_parent_id() || $id_from_sku != $options['id_product'] )) {
					
					return array(
						'status' => false,
						'message' => __('Já existe outro produto com o mesmo SKU!', 'epicdrop')
					);
				}
			}
			
		} else {
			
			if ($id_from_sku) {
			
				$product = wc_get_product( $id_from_sku );		

				if ($product && 0 < $product->get_parent_id()) {
					$options['id_product'] = $product->get_parent_id();
				} else {
					$options['id_product'] = $id_from_sku;
				}
			}
			
			if (( !isset($options['association']['update_existing']) || ( isset($options['association']['update_existing']) && !$options['association']['update_existing'] ) ) && $id_from_sku) {
				return array(
					'status' => false,
					'message' => __('Este produto já existe!', 'epicdrop') .
					' #' . $id_from_sku
				);
			}				
		}

		if ($options['affiliate_link']) {
			\Woo_EpicDrop_Init::addLog(__METHOD__, __('external', 'epicdrop'), __FILE__, __LINE__);

			$product = new \WC_Product_External((int) $options['id_product']);
		} elseif ($options['variant'] && $this->Parser->getCombinations()) {
			\Woo_EpicDrop_Init::addLog(__METHOD__, __('combination', 'epicdrop'), __FILE__, __LINE__);

			$product = new \WC_Product_Variable((int) $options['id_product']);
		} else {
			\Woo_EpicDrop_Init::addLog(__METHOD__, __('simple', 'epicdrop'), __FILE__, __LINE__);

			$product = new \WC_Product((int) $options['id_product']);
		}
		
		try {
			
			if (( $product->get_id() && $options['name'] ) || !$product->get_id()) {
				$product->set_name($this->cleanText($this->replaceText(sanitize_text_field($this->Parser->getTitle())), 128));
			}
			
			if ($options['description']) {
				$product->set_short_description(
					$this->purifyHTML(
						$this->replaceText(
							$this->Parser->getShortDescription()
						)
					)
				);
				
				$videoHtml = '';
				
				$videos = $this->Parser->getVideos();
				
				if ($videos) {
					foreach ($videos as $video) {
						$videoHtml .= '<center>[embed]' . $video . '[/embed]</center><br/>';
					}
				}
				
				$product->set_description(
					$videoHtml .
					$this->purifyHTML(
						$this->replaceText(
							$this->replaceDescriptionImage(
								$this->Parser->getDescription()
							)
						)
					)
				);
			}
			
			if (!$product->get_id() && $options['sku'] && $sku) {
				$product->set_sku($sku);  //can be blank in case you don't have sku, but You can't add duplicate sku's
			}
			
			$price = (float) sanitize_text_field($this->Parser->getPrice());
			
			if (( $product->get_id() && $options['price'] ) || !$product->get_id()) {

				$price = $this->calculatePrice(max($price, 0), $options);
				$product->set_price($price);
				$product->set_regular_price($price);
			}
			
			$weight = $this->Parser->getWeight();
			
			if (isset($options['weight']) && $options['weight'] && $weight) {
				$product->set_weight($weight['value']);			
			}		
			
			if (isset($options['association']['tax_class'])) {
				$taxClass = sanitize_text_field($options['association']['tax_class']);
				if ($taxClass) {
					$product->set_tax_class($taxClass);
				}
			}
			
			if ($options['affiliate_link']) {
				$product->set_manage_stock(false);
			} elseif (isset($options['association']['quantity'])) {
				$quantity = (int) sanitize_text_field($options['association']['quantity']);
				if (0 < $quantity) {
					$product->set_manage_stock(true); // true or false
					$product->set_stock_quantity($quantity);
					$product->set_stock_status('instock');
				}
			}
			
			$visibility = 'visible';
			if (isset($options['association']['visibility'])) {
				$visibility = sanitize_text_field($options['association']['visibility']);
			}
			$product->set_catalog_visibility($visibility); // add the product visibility status
			
			$categories = array();
			if (isset($options['association']['categories'])) {
				$categories = (array) $options['association']['categories'];
			}
			if (!$categories && $options['category']) {
				$categories = $this->addCategories((array) $this->Parser->getCategories());
			}
			if ($categories) {
				$product->set_category_ids($categories); // array of category ids, You can get category id from WooCommerce Product Category Section of Wordpress Admin
			}
			
			if (isset($options['association']['post_status'])) {
				$postStatus = sanitize_text_field($options['association']['post_status']);
				$product->set_status($postStatus);  // can be publish,draft or any wordpress post status
			}
			
			$images = $this->Parser->getImages();
			if ($options['image'] && $images) {
				if (!$options['variant']) {
					$imgs = array();
					$combinations = $this->Parser->getCombinations();

					if ($combinations) {
						foreach ($combinations as $combination) {
							if ($this->Parser->getSKU() == $combination['sku']) {
								if (isset($images[$combination['image_index']])) {
									$imgs = $images[$combination['image_index']];
								}								
								break 1;
							}
						}
					}

					if ($imgs) {
						$images = $imgs;
					} else {
						$images = current($images);
					} 
				} else {
					$imgs = array();
					foreach ($images as $img) {
						$imgs = array_merge($imgs, $img);
					}
					$images = $imgs;
				}
				
				$productImagesIDs = $this->addImages(array_unique($images));
				
				if ($productImagesIDs) {
					$product->set_image_id(array_shift($productImagesIDs)); // set the first image as primary image of the product
					//in case we have more than 1 image, then add them to product gallery.
					if (count($productImagesIDs) > 0) {
						$product->set_gallery_image_ids($productImagesIDs);
					}
				}
			}
			
			$product->save(); // it will save the product and return the generated product id
			
			$features = array();
			
			if (isset($options['feature']) && $options['feature']) {
				
				$featureGroups = $this->Parser->getFeatures(); // Get all the product features to import.
				
				if ($featureGroups) {
					
					foreach ($featureGroups as $featureGroup) {
						foreach ($featureGroup['attributes'] as $feature) {
						
							$features[] = array(
								'name' => $feature['name'],
								'values' => array($feature['value'])
							);
						}
					}
				}
				
				$dimension = $this->Parser->getDimension();
			
				if ($dimension) {
					$product->set_length($dimension['length']);			
					$product->set_width($dimension['width']);			
					$product->set_height($dimension['height']);			
				}
			}
			
			$brand = sanitize_text_field($this->Parser->getBrand());

			if (isset($options['brand']) && $options['brand'] && $brand) {
				// Add manufacturer as attribute
				$features[] = array(
					'name' => __('Fabricante', 'epicdrop'),
					'values' => array( $brand )
				);
			}
			
			$upc = sanitize_text_field($this->Parser->getUPC());
			if (isset($options['upc']) && $options['upc'] && $upc) {
				// Add UPC as attribute
				$features[] = array(
					'name' => __('UPC', 'epicdrop'),
					'values' => array( $upc )
				);
			}
			
			if ($features) {
				// Product features will be added as attribute
				$this->addProductAttributes($features, $product);
			}
		   
			if (!$options['affiliate_link']) {
				$this->createProductVariations($product, $options);
			} else {
				// Add affiliate information
				$this->addAffiliate($product, $options);
			}
			
			if (isset($options['review']) && $options['review']) {
				
				$maxReviews = 10; // All reviews
				
				if (isset($options['association']['review'])) {
					$maxReviews = (int) $options['association']['review'];
				}
					
				$reviews = $this->Parser->getCustomerReviews($maxReviews);
				
				if (0 < $maxReviews) {
					$reviews = array_splice($reviews, 0, (int) $maxReviews);
				}
				
				$this->addReviews($reviews, $product);			
			}
		} catch (Exception $e) {
			\Woo_EpicDrop_Init::addLog(__METHOD__, __('error: ', 'epicdrop') . $e->getMessage(), __FILE__, __LINE__);
		}
		
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('completed', 'epicdrop'), __FILE__, __LINE__);
		
		if ($product->get_id()) {
			$response['status'] = true;
			if ($options['id_product']) {
				$message = __('Produto atualizado com sucesso.', 'epicdrop');
			} else {
				$message = __('Produto importado com sucesso.', 'epicdrop');
			}
			if ($product->get_status() == 'publish') {
				$message .= '<br><a href="' . get_permalink($product->get_id()) . '">' . get_permalink($product->get_id()) . '</a>';
			}
			$response['message'] = $message;
		}		

		return $response;
	}
	
	protected function addCategories( $categoryNameArray) {
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('called', 'epicdrop'), __FILE__, __LINE__);

		$categoryIds = array();
		$parentId = 0;
		$existingCategories = \Woo_EpicDrop_Init::getCategories();

		if (is_array($categoryNameArray)) {
			foreach ($categoryNameArray as $categoryName) {
				$key = array_search($categoryName, array_column($existingCategories, 'name'));
				
				if ('' != $key) {
					$parentId = $existingCategories[$key]['term_id'];
					$categoryIds[] = $parentId;
					continue;
				}
				$category = wp_insert_term(
					$categoryName,
					'product_cat',
					array(
						'description' => '',
						'slug' => \Woo_EpicDrop_Init::slugify($categoryName),
						'parent' => $parentId
					)
				);
				if (!is_wp_error($category) && isset($category['term_id'])) {
					$parentId = $category['term_id'];
					$categoryIds[] = $parentId;
				}
			}
		}
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('completed', 'epicdrop'), __FILE__, __LINE__);
		return $categoryIds;
	}
	
	protected function addImages( $imageUrls) {
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('called', 'epicdrop'), __FILE__, __LINE__);
		
		static $images = array();
		
		$imageIds = array();
		
		if ($imageUrls) {
			foreach ($imageUrls as $imageUrl) {
				$key = base64_encode($imageUrl);
				if (isset($images[$key])) {
					$imageIds[] = $images[$key];
				} else {				
					$id = $this->uploadAttachment($imageUrl);
					if ($id) {
						$images[$key] = $id;
						$imageIds[] = $id;
					}					
				}
			}
		}
		
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('completed', 'epicdrop'), __FILE__, __LINE__);
		
		return $imageIds;
	}
	
	public function createImage( $type, $filename) {
		switch ($type) {
			case IMAGETYPE_WEBP:
				return imagecreatefromwebp($filename);

				break;
				
			case IMAGETYPE_GIF:
				return imagecreatefromgif($filename);

				break;

			case IMAGETYPE_PNG:
				return imagecreatefrompng($filename);

				break;

			case IMAGETYPE_JPEG:
			default:
				return imagecreatefromjpeg($filename);

				break;
		}
	}
	
	public function writeImage( $resource, $filename) {
		$success = imagejpeg($resource, $filename, 100);
		imagedestroy($resource);
		@chmod($filename, 0664);

		return $success;
	}
	
	public function upload_image( $sourceFile, $destinationFile) {
		
		clearstatcache(true, $sourceFile);

		if (!file_exists($sourceFile) || !filesize($sourceFile)) {
			return false;
		}

		list($sourceWidth, $sourceHeight, $type) = getimagesize($sourceFile);
		
		if (!$sourceWidth || !$sourceHeight || !$type) {
			return false;
		}

		$destImage = imagecreatetruecolor($sourceWidth, $sourceHeight);
	   
		imagefilledrectangle($destImage, 0, 0, $sourceWidth, $sourceHeight, imagecolorallocate($destImage, 255, 255, 255));

		$srcImage = $this->createImage($type, $sourceFile);

		imagecopyresized($destImage, $srcImage, (int) 0, 0, 0, 0, $sourceWidth, $sourceHeight, $sourceWidth, $sourceHeight);
		
		$writeFile = $this->writeImage($destImage, $destinationFile);
		@imagedestroy($srcImage);

		return $writeFile;
	}

	public function getContent( $url) {
		
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

		$headers = array(
		  'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36 Edg/97.0.1072.62',
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
	
	protected function uploadAttachment( $url, $product_id = 0) {
		
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('called', 'epicdrop'), __FILE__, __LINE__);
		
		if ( empty( $url ) ) {
			return 0;
		}
		
		$id         = 0;
		$upload_dir = wp_upload_dir( null, false );
		
		try {
			
			if (stristr( $url, '://' ) ) {
				
				$file_name = pathinfo( $url, PATHINFO_FILENAME) . '.jpg'; 
				 
				$temp_name = uniqid() . '.jpg';
				
				$content = $this->getContent($url);
				
				if ($content) {
				
					$upload_bits_error = apply_filters(
						'wp_upload_bits',
						array(
							'name' => $file_name,
							'bits' => $content,
							'time' => null
						)
					);
					
					if ( ! is_array( $upload_bits_error ) ) {
						return 0;
					}
						
					$success = @file_put_contents($upload_dir['basedir'] . '/' . $temp_name, $content);
					
					if ($success) {
						
						$file_name = wp_unique_filename( $upload_dir['path'], $file_name );
						
						$success = $this->upload_image($upload_dir['basedir'] . '/' . $temp_name, $upload_dir['path'] . '/' . $file_name);
						
						if ($success) {
						
							$id = $this->set_uploaded_image_as_attachment( $upload_dir['path'] . '/' . $file_name, $upload_dir['url'] . '/' . $file_name, $product_id );
							
							if ($id) {
								// Save attachment source for future reference.
								update_post_meta( $id, '_wc_attachment_source', $url );
							}
						}
					}					
					
					@unlink($upload_dir['basedir'] . '/' . $temp_name);					
				}
			}
			
		} catch (Exception $e) {
			\Woo_EpicDrop_Init::addLog(__METHOD__, __('error: ', 'epicdrop') . $e->getMessage(), __FILE__, __LINE__);
		}
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('completed', 'epicdrop'), __FILE__, __LINE__);
		
		return $id;

	}
	
	public function set_uploaded_image_as_attachment( $file, $url, $id = 0) { 
		
		$info    = wp_check_filetype( $file );
		$title   = '';
		$content = '';

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			include_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$image_meta = wp_read_image_metadata( $file );
		if ( $image_meta ) {
			if ( trim( $image_meta['title'] ) && ! is_numeric( sanitize_title( $image_meta['title'] ) ) ) {
				$title = wc_clean( $image_meta['title'] );
			}
			if ( trim( $image_meta['caption'] ) ) {
				$content = wc_clean( $image_meta['caption'] );
			}
		}

		$attachment = array(
			'post_mime_type' => $info['type'],
			'guid'           => $url,
			'post_parent'    => $id,
			'post_title'     => $title ? $title : basename( $file ),
			'post_content'   => $content,
		);

		$attachment_id = wp_insert_attachment( $attachment, $file, $id );
		if ( ! is_wp_error( $attachment_id ) ) {
			wp_update_attachment_metadata(
				$attachment_id,
				wp_generate_attachment_metadata( $attachment_id, $file )
			);
		}

		return $attachment_id;
	} 
	
	public function get_attribute_taxonomy_id( $raw_name ) {
		global $wpdb;
		
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('called', 'epicdrop'), __FILE__, __LINE__);

		// These are exported as labels, so convert the label to a name if possible first.
		$attribute_labels = wp_list_pluck( wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name' );
		$attribute_name   = array_search( $raw_name, $attribute_labels, true );

		$attribute_id = wc_attribute_taxonomy_id_by_name( $attribute_name );

		// Get the ID from the name.
		if ( $attribute_id ) {
			return $attribute_id;
		}
		
		$attribute_name = wc_sanitize_taxonomy_name( trim(substr($raw_name, 0, 27)) );

		// If the attribute does not exist, create it.
		$attribute_id = wc_create_attribute(
			array(
				'name'         => $raw_name,
				'slug'         => $attribute_name,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			)
		);


		if ( !is_wp_error( $attribute_id ) ) {

			// Register as taxonomy while importing.
			$taxonomy_name = wc_attribute_taxonomy_name( $attribute_name );
			register_taxonomy(
				$taxonomy_name,
				apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy_name, array( 'product' ) ),
				apply_filters(
					'woocommerce_taxonomy_args_' . $taxonomy_name,
					array(
						'labels'       => array(
							'name' => $raw_name,
						),
						'hierarchical' => true,
						'show_ui'      => false,
						'query_var'    => true,
						'rewrite'      => false,
					)
				)
			);
		}
		
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('completed', 'epicdrop'), __FILE__, __LINE__);

		return $attribute_id;
	}
	
	protected function addProductAttributes( $attributes, $product, $variation = 0) {
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('called', 'epicdrop'), __FILE__, __LINE__);
		$position = 0;
		if ($attributes) {
			$productAttributes = get_post_meta($product->get_id(), '_product_attributes');
			if ($productAttributes) {
				$productAttributes = array_shift($productAttributes); // All the product attributes are stored at first index
			}
			foreach ($attributes as $attribute) {
				$name = wc_sanitize_taxonomy_name(stripslashes($attribute['name'])); // remove any unwanted chars and return the valid string for taxonomy name
				$taxonomy = wc_attribute_taxonomy_name($name); // woocommerce prepend pa_ to each attribute name
				$this->addTaxonomyIfNotExists($attribute['name'], $attribute['values']);
									
				if ($attribute['values']) {
					foreach ($attribute['values'] as $values) {
						wp_set_object_terms($product->get_id(), $values, $taxonomy, true); // save the possible option value for the attribute which will be used for variation later
					}
				}
				$productAttributes[ $taxonomy ] = array(
					//'name' => sanitize_title($taxonomy),
					'name' => $taxonomy,
					'value' => $attribute['values'],
					'position' => $position++,
					'is_visible' => 1,
					'is_variation' => $variation,
					'is_taxonomy' => '1'
				);
			}
			
			update_post_meta($product->get_id(), '_product_attributes', $productAttributes); // save the meta entry for product attributes
		}
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('completed', 'epicdrop'), __FILE__, __LINE__);
	}
	
	protected function addTaxonomyIfNotExists( $name, $values = array()) {
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('called', 'epicdrop'), __FILE__, __LINE__);
		
		$attribute_id = $this->get_attribute_taxonomy_id( $name );
		
		$taxonomy = wc_attribute_taxonomy_name_by_id( $attribute_id );
		
		if ( !is_wp_error( $attribute_id ) && $values) {
	   
			if ($values) {
				foreach ($values as $value) {
					if ($value && !term_exists($value, $taxonomy)) {
						$term_id = wp_insert_term($value, $taxonomy);
					}
				}
			}
		}
		
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('completed', 'epicdrop'), __FILE__, __LINE__);
		
		return true;
	}
	
	protected function createProductVariations( $product, $options) {
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('called', 'epicdrop'), __FILE__, __LINE__);
		
		$variations = $this->Parser->getCombinations();
		$images = $this->Parser->getImages();		
		
		if ($options['variant'] && $variations) {
			try {
				$this->addProductAttributes($this->Parser->getAttributes(), $product, 1);
				
				foreach ($variations as $variation) {
					
					if ($options['sku'] && $variation['sku']) {
						$sku = $this->cleanText($variation['sku'], 32);
					} elseif (isset($options['association']['sku']) && $options['association']['sku']) {
						$sku = $this->cleanText($options['association']['sku'], 32);
					} else {
						$sku = $product->get_id();
					}
					
					$sku = $this->generateSku($sku, $product->get_id());
				
					$productVariation = new \WC_Product_Variation((int) wc_get_product_id_by_sku( $sku ));
					
					if ($options['price'] || !$options['id_product']) {
					
						$price = (float) sanitize_text_field($variation['price']);
						
						$price = $this->calculatePrice(max($price, 0), $options);
						$productVariation->set_price($price);
						$productVariation->set_regular_price($price);
					}
					
					$productVariation->set_parent_id($product->get_id());
					
					if ($options['weight'] && isset($variation['weight']['value'])) {
						$productVariation->set_weight($variation['weight']['value']);
					}
					
					if ($options['sku'] && $sku) {
						$productVariation->set_sku($sku);  //can be blank in case you don't have sku, but You can't add duplicate sku's
					}
					
					if (isset($options['association']['quantity'])) {
						$quantity = (int) sanitize_text_field($options['association']['quantity']);
						if ($quantity) {
							$productVariation->set_manage_stock(true); // true or false
							$productVariation->set_stock_quantity($quantity);
							$productVariation->set_stock_status('instock');
						}
					}
					
					if ($options['image'] && $images) {
						if (isset($images[$variation['image_index']])) {
							$productImagesIDs = $this->addImages($images[$variation['image_index']]);
							if ($productImagesIDs) {
								$productVariation->set_image_id(array_shift($productImagesIDs)); // set the first image as variant primary image of the product
							}
						}
					}
					
					$varAttributes = array();
					foreach ($variation['attributes'] as $vattribute) {
						$taxonomy = wc_attribute_taxonomy_name(wc_sanitize_taxonomy_name(stripslashes($vattribute['name']))); // name of variant attribute should be same as the name used for creating product attributes
						$attrValSlug =  wc_sanitize_taxonomy_name(stripslashes($vattribute['value']));
						$varAttributes[$taxonomy] = $attrValSlug;
					}
					$productVariation->set_attributes($varAttributes);
					$productVariation->save();
				}
			} catch (Exception $e) {
				\Woo_EpicDrop_Init::addLog(__METHOD__, __('error: ', 'epicdrop') . $e->getMessage(), __FILE__, __LINE__);
			}
		}
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('completed', 'epicdrop'), __FILE__, __LINE__);
	}
	
	protected function addAffiliate( $product, $options) {
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('called', 'epicdrop'), __FILE__, __LINE__);
		
		if ($options['affiliate_link']) {
			$affiliateLink = urldecode($options['affiliate_link']);
			
			if (!filter_var($affiliateLink, FILTER_VALIDATE_URL)) {
				$affiliateParams = array();
				parse_str($affiliateLink, $affiliateParams);
				$parseProductUrl = explode('?', urldecode($options['product_link']));
				
				$urlParams = array();
				if (isset($parseProductUrl[1])) {
					parse_str($parseProductUrl[1], $urlParams);
				}
				
				foreach ($affiliateParams as $key => $value) {
					$urlParams[$key] = $value;
				}
				
				$affiliateLink = $parseProductUrl[0] . '?' . http_build_query($urlParams);
			}
			
			$product->set_product_url($affiliateLink);
			
			if (!$options['id_product']) {
				$product->set_button_text(__('Comprar agora', 'epicdrop'));
			}
			
			$product->save();
		}
		
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('completed', 'epicdrop'), __FILE__, __LINE__);
	}
	
	protected function addReviews( $reviews, $product) {
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('called', 'epicdrop'), __FILE__, __LINE__);
		
		foreach ($reviews as $review) {
			$review = wp_slash($review);
					
			$review['comment_type'] = '';
			
			$review = apply_filters('preprocess_comment', $review);
			
			$commentdata = array();	
			
			$commentdata['comment_post_ID'] = (int) $product->get_id();
			$commentdata['comment_date'] = gmdate('Y-m-d', strtotime($review['timestamp']));
			$commentdata['comment_date_gmt'] = gmdate('H:i:s', strtotime($review['timestamp']));
			$commentdata['comment_author'] = $review['author'];
			$commentdata['comment_content'] = '<b>' . $this->replaceText($review['title']) . '</b><br/>' . $this->replaceText($review['content']);
			$commentdata['comment_type'] = '';
			$commentdata['comment_author_IP'] = '';
			$commentdata['comment_author_url'] = '';
			$commentdata['comment_author_email'] = '';
			$commentdata['comment_parent'] = 0;
			$commentdata['comment_approved'] = 1;
			
			$rating = (int) $review['rating'];
			if (5 < $rating) {
				$rating /= 2;
			}
				
			$rating = intval($rating);
			if (0 < $rating) {
				$commentdata['comment_meta'] = array('rating' => $rating);
			}
		
			$commentdata = wp_filter_comment($commentdata);

			wp_insert_comment($commentdata);
		}
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('completed', 'epicdrop'), __FILE__, __LINE__);
	}
	
	protected function validateCredential() {
		\Woo_EpicDrop_Init::addLog(__METHOD__, __('called', 'epicdrop'), __FILE__, __LINE__);

		if (!$this->getParam('secret_key') || $this->getParam('secret_key') != $this->Config['secret_key']) {
			return array(
				'status' => 0,
				'message' => __('Credenciais inválidas!', 'epicdrop')
			);
		}
	}
	
	protected function calculatePrice( $price, $options) {
		if (isset($options['association']['price']) && trim($options['association']['price'])) {
			$customPrice = trim($options['association']['price']);

			if ('+' == substr($customPrice, 0, 1)) {
				$price += (float) substr($customPrice, 1);
			} elseif ('-' == substr($customPrice, 0, 1)) {
				$price -= (float) substr($customPrice, 1);
			} elseif ('*' == substr($customPrice, 0, 1)) {
				$price *= (float) substr($customPrice, 1);
			} elseif ('/' == substr($customPrice, 0, 1)) {
				$price /= (float) substr($customPrice, 1);
			} else {
				$price = (float) $customPrice;
			}
		}

		return round($price, 2);
	}
	
	protected function getParam( $key = null, $default = null) {
		if (null == $key) {
			return $_REQUEST;
		}
		if (isset($_REQUEST[$key])) {
			return sanitize_text_field($_REQUEST[$key]);
		}
		return $default;
	}
	
	protected function cleanText( $text, $length = null, $wordWrap = true) {
		$text = str_replace(array('^','<','>','=','{','}', '#', ';', '【', '】'), '', $text);
		if ($length && strlen($text) > $length) {
			
			if ($wordWrap) {
			
				$words = explode(' ', $text);
				
				$newText = '';
				
				foreach ($words as $word) {
					if (strlen($newText . ' ' . $word) <= $length) {
						$newText = trim($newText) . ' ' . $word;
					} else {
						break;
					}
				}
			} else {
				$newText = substr($text, 0, $length);
			}
			
			return $newText;
		}
		return  $text;
	}
	
	protected function trim( $text) {
		$text = preg_replace(array('/\s+/', '/<\s+/', '/\s+>/', '/>\s+</', '/&nbsp;/'), array(' ', '<', '>', '><', ''), $text);
		$text = preg_replace('/<!--([^-](?!(->)))*-->/', '', $text);
		return trim($text);
	}
	
	protected function getDomObject( $html ) {
		
		if (!$html) {
			return $html;
		}
		
		$dom = new \DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML('<?xml encoding="utf-8" ?><epicdrop>' . $html . '</epicdrop>', LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
		libxml_use_internal_errors(false);
		
		return $dom;
	}
	
	protected function removeElements( $html, $selectors) {
		
		if (!$html) {
			return $html;
		}
		
		$dom = $this->getDomObject( $html );
		$xpath = new \DOMXPath($dom);
	
		foreach ($selectors as $selector) {
			
			$nodes = $xpath->query($selector);
			
			foreach ($nodes as $node) {
				$node->parentNode->removeChild($node);
			}
		}
		
		return $this->getRealContent($dom->saveHTML());
	}
	
	protected function purifyHTML( $html) {    
		
		$html = preg_replace('/<!--(.|\s)*?-->/i', '', $html);
		$html = preg_replace('/\s+/', ' ', $html);
		$html = preg_replace('/javascript:/', '#', $html); // Remove javascript.
		$html = preg_replace('/max-height:/', 'a:', $html); // Remove max height attribute.
		
		$html = $this->removeElements(
			$html,
			array(
				'//div[contains(attribute::class, "a-expander-header")]',
				'//div[contains(attribute::class, "apm-tablemodule")]',
				'//div[contains(attribute::class, "-comparison-table")]',
				'//div[contains(attribute::class, "-carousel")]',
				'//*[@data-action="a-expander-toggle"]',
				'//a[@href="javascript:void(0)"]',
				'//iframe',
				'//script',
				'//style',
				'//form',
				'//object',
				'//embed',
				'//select',
				'//input',
				'//textarea',
				'//button',
				'//noscript',
			)
		);
		
		return trim($html);
	}	
	
	public function replaceText( $text) {
		$replace_texts = $this->Config['replace_texts'];
		
		if ($replace_texts) {
			
			$replace_texts = explode(',', $replace_texts);
			
			$replace_texts = array_map('trim', $replace_texts);
			
			foreach ($replace_texts as $replace_text) {
				$replace_text = explode(':', $replace_text);
				
				if (!isset($replace_text[0]) || !$replace_text[0]) {
					continue;
				}
				
				$find = $replace_text[0];
				
				if (isset($replace_text[1])) {
					$replace = $replace_text[1];
				} else {
					$replace = '';
				}
				
				$text = str_replace($find, $replace, $text);
			}		
		}
		
		return $text;		
	}
	
	public function replaceDescriptionImage( $html) {

		if (!$html) {
			return $html;
		}
		
		$dom = $this->getDomObject($html);
		$images = $dom->getElementsByTagName('img');
		
		foreach ($images as $image) {
			
			$img = $image->getAttribute('data-src');
			
			if (!$img) {
				$img = $image->getAttribute('data-a-hires');
			}
			
			if (!$img) {
				$img = $image->getAttribute('src');
			}
			
			if ($img) {
				$id = $this->uploadAttachment($img);

				if ($id) {
					$img = wp_get_attachment_url($id);
				}
			}
			
			if ($img) {
				$image->setAttribute('src', $img);
			}
		}

		return $this->getRealContent($dom->saveHTML());
		
	}
	
	protected function getRealContent( $html) {
		if (preg_match('/<epicdrop>(.*)<\/epicdrop>/', $html, $matches)) {
			return $matches[1];
		}

		return $html;
	}
	
	protected function displayResponse( $response) {
		header('Content-Type: application/json');
		die(json_encode($response));
	}
	
	public function generateSku( $sku, $parentId = 0) {
		
		$sku = $this->cleanText($sku, 32, false);
		
		$id_from_sku = wc_get_product_id_by_sku( $sku );
			
		if ($id_from_sku) {
		
			$product = wc_get_product( $id_from_sku );		

			if (( 0 < $product->get_parent_id() && $parentId != $product->get_parent_id()
				|| !$product->get_parent_id() )
			) {
				return $this->generateSku($this->changeSku($sku));
			}
		}
		
		return $sku;		
	}
	
	public function changeSku( $sku) {
		
		if (strlen($sku) >= 32) {
			$sku = rand(0, 9) . $sku;
		} else {
			$sku .= rand(0, 9);
		}
		return $sku;
	}
}

$Importer = new \Epicdrop\Classes\Frontend\Importer();