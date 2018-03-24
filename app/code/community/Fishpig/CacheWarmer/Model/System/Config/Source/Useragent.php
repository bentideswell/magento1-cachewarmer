<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Crawler
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 * @SkipObfuscation
 */

class Fishpig_CacheWarmer_Model_System_Config_Source_Useragent
{
	/**
	 * Get an array of options for Mobile Detect
	 *
	 * @return array
	 */
	static public function getOptionsForMobileDetect()
	{
		$allValues = array(
			'ua_default' => '',
			'ua_mobile' => 'iPhone',
			'ua_tablet' => 'iPad',
		);	

		if ($selectedValues = Mage::getStoreConfig('cachewarmer/settings/useragent')) {
			$allValues = array_intersect_key($allValues, array_flip(explode(',', $selectedValues)));
		}
		
		return count($allValues) > 0 ? $allValues : array('ua_default' => '');
	}
	
	/**
	 * Get the raw options array
	 *
	 * @return array
	 */
	public function getOptions()
	{
		return array(
			'ua_default' => 'Desktop',
			'ua_mobile' => 'Mobile',
			'ua_tablet' => 'Tablet',
		);
	}
	
	/**
	 * Get a option array of all options
	 *
	 * @return array
	 */
	public function toOptionArray()
	{
		$options = array();
		
		foreach($this->getOptions() as $value => $label) {
			$options[] = array(
				'value' => $value,
				'label' => Mage::helper('cachewarmer')->__($label)
			);
		}
		
		return $options;
	}
}