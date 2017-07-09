<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Crawler
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_CacheWarmer_Model_System_Config_Source_Currency_Specified
{
	/**
	 * Get the raw options array
	 *
	 * @return array
	 */
	public function getOptions()
	{
		$codes = Mage::helper('cachewarmer')->getCurrencyCodes();
		
		return array_combine($codes, $codes);
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