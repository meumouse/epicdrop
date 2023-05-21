<?php
/**
 * Add extension configuration menu link in admin side bar navigation.
 *
 * @package: product-importer
 *
 */

 // Exit if accessed directly
if (! defined('ABSPATH')) {
	exit; }

if (!class_exists('EpicDrop_Configuration')) {

	class EpicDrop_Configuration {

		public function getConfigurationPage() {
			
			add_submenu_page(
				'edit.php?post_type=product',
				__('EpicDrop', 'epicdrop'),
				__('EpicDrop', 'epicdrop'),
				'manage_options',
				'epicdrop_settings',
				'epicdrop_settings_callback'
			);
		}
		
	}
}
