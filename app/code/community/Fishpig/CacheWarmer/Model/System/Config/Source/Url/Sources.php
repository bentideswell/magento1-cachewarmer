<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Crawler
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 * @SkipObfuscation
 */

class Fishpig_CacheWarmer_Model_System_Config_Source_Url_Sources
{
	/**
	 * Determine whether $source is enabled
	 *
	 * @param string $source
	 * @return bool
	 */
	static public function isSourceEnabled($source)
	{
		return $source && in_array($source, explode(',', Mage::getStoreConfig('cachewarmer/settings/sources')));
	}
	
	/**
	 * Get the raw options array
	 *
	 * @return array
	 */
	static public function getOptions()
	{
		$_helper = Mage::helper('cachewarmer');
		
		return array(
			'store_init' => $_helper->__('Store Initialization'),
			'catalog_product' => $_helper->__('Products'),
			'catalog_category' => $_helper->__('Categories'),
			'cms_pages' => $_helper->__('CMS Pages'),
			'wordpress' => $_helper->__('Magento WordPress Integration'),
			'custom' => $_helper->__('Custom URLs'),
		);
	}
	
	/**
	 * Get a option array of all options
	 *
	 * @return array
	 */
	static public function toOptionArray()
	{
		$options = array();
		
		foreach(self::getOptions() as $value => $label) {
			$options[] = array(
				'value' => $value,
				'label' => Mage::helper('cachewarmer')->__($label)
			);
		}
		
		return $options;
	}
}