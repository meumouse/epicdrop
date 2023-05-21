<?php
/**
 * Parser abstract class
 *
 * @package: product-importer
 *
 */
 
namespace EpicDrop\Importer\classes\front\Parser;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

abstract class AbstractParser {

	abstract public function getTitle();
	
	abstract public function getCategories();
	
	public function getShortDescription() {
		return null;
	}
	
	abstract public function getDescription();
	
	abstract public function getPrice();
	
	public function getWeight() {
		return array();
	}
	
	public function getModel() {
		return '';
	}
	
	public function getDimension() {
		return '';
	}
	
	public function getPriceCurrency() {
		return 'USD';
	}
	
	public function getSKU() {
		return null;
	}
	
	public function getUPC() {
		return null;
	}
	
	public function getVideos() {
		return array();
	}
	
	abstract public function getBrand();
	
	abstract public function getMetaTitle();
	
	abstract public function getMetaDecription();
	
	abstract public function getMetaKeywords();
	
	abstract public function getImages();
	
	abstract public function getAttributes();
	
	abstract public function getCombinations();
	
	public function getFeatures() {
		return array();
	}
	
	public function getCustomerReviews() {
		return array();
	}
	
	protected function strpos( $haystack, $needle, $number = 0) {
		return strpos($haystack, $needle,
			$number > 1 ?
			$this->strpos($haystack, $needle, $number - 1) + strlen($needle) : 0
		);
	}
	
	protected function getJson( $string, $start, $end, $index = 0) {
		$string = ' ' . $string;
		$ini = $this->strpos($string, $start, $index);
		if (0 == $ini) {
			return '';
		}
		$ini += strlen($start);
		$len = strpos($string, $end, $ini) - $ini;
		return substr($string, $ini, $len);
	}
}
