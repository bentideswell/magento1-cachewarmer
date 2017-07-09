<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Crawler
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_CacheWarmer_Model_System_Config_Source_Currency_All
{
	/**
	 * @var const int
	 */
	const OPTION_ALL = 1;
	const OPTION_SPECIFIED = 0;
	
	/**
	 * Get the raw options array
	 *
	 * @return array
	 */
	public function getOptions()
	{
		return array(
			self::OPTION_ALL => Mage::helper('cachewarmer')->__('All Active Currencies'),
			self::OPTION_SPECIFIED => Mage::helper('cachewarmer')->__('Specified Below'),
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