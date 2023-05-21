<?php
/**
 * Save configuration after the form submission in admin.
 *
 * @package: product-importer
 *
 */
 
if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

if (!class_exists('EpicDrop_Configuration_Save')) {

	class EpicDrop_Configuration_Save {

		public function saveConfiguration() {

			check_admin_referer('epicdrop_importer_fields_verify');

			if (!current_user_can('manage_options')) {
				wp_die('Você está autorizado a editar esta configuração.');
			}
			
			$secretKey = '';
			if (isset($_POST['secret_key'])) {
				$secretKey = sanitize_text_field($_POST['secret_key']);
			}
			if (isset($_POST['advance_option'])) {
				$advanceOption = 1;
			} else {
				$advanceOption = 0;
			}
			
			$affiliateId = '';
			if (isset($_POST['affiliate_id'])) {
				$affiliateId = sanitize_text_field($_POST['affiliate_id']);
			}
			
			$replace_texts = '';
			if (isset($_POST['replace_texts'])) {
				$replace_texts = sanitize_text_field($_POST['replace_texts']);
			}
			
			if (isset($_POST['log'])) {
				$log = 1;
			} else {
				$log = 0;
			}
			
			$messageAttribute = 'update';

			if (empty($secretKey)
				|| strlen($secretKey) < 8) {
				$messageAttribute = 'error';

			} else {
				update_option( 'importer_setting',
					array(
						'secret_key' => $secretKey,
						'advance_option' => $advanceOption,
						'affiliate_id' => $affiliateId,
						'replace_texts' => $replace_texts,
						'log' => $log
					)
				);
			}
			
			wp_redirect( get_admin_url() . 'edit.php?post_type=product&page=epicdrop_settings&' . $messageAttribute );
		}
	}
}