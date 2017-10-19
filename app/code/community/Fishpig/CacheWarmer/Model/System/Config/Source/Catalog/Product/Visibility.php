<?php
/*
 *
 */
class Fishpig_CacheWarmer_Model_System_Config_Source_Catalog_Product_Visibility extends Mage_Catalog_Model_Product_Visibility
{
	/*
	 *
	 *
	 * @return array
	 */
	public function toOptionArray()
	{
		$options = $this->getAllOptions();
		
		array_shift($options);
		
		foreach($options as $key => $value) {
			if (in_array((int)$value['value'], array(1, 3))) {
				unset($options[$key]);
			}
		}

		return $options;
	}
}