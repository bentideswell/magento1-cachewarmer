<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Crawler
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_CacheWarmer_Helper_Data extends Mage_Core_Helper_Abstract
{
	/**
	 * Determine whether to display output or not
	 *
	 * @var bool
	 */
	protected $_displayOutput = false;
	
	/**
	 * Currency codes per store
	 *
	 * @var array
	 */
	protected $_currencyCodes = array();
	
	/**
	 * Crawl generated URLs for given $storeId
	 *
	 * @param int $storeId = null
	 * @return Fishpig_CacheWarmer_Helper_Data
	 */
	public function run($storeId = null)
	{
		$this->_init();
		
		if ($storeId === null) {
			$storeId = Mage::app()->getStore()->getId();
		}

		if (PHP_SAPI !== 'cli' && !Mage::getStoreConfigFlag('cachewarmer/settings/run_from_browser', $storeId)) {
			throw new Exception('Configuration forbids running Cache Warmer from a browser.');
		}

		$initialEnvironmentInfo = Mage::getSingleton('core/app_emulation')->startEnvironmentEmulation($storeId);
		$displayOutput = $this->canDisplayOutput();
		$nl = PHP_SAPI === 'cli' ? "\n" : '<br/>';

		try {
			$cm = new Fishpig_Http_Curl_Multi();

			if ($this->canDisplayOutput()) {
				$cm->setShowProgress('%d/%d URLS downloaded - %s' . (PHP_SAPI === 'cli' ? "\n" : '<br/>'));
			}
			else {
				$cm->setShowProgress(false);
			}

			$cm->storeResponses(true);

			$results = $cm->process($this->_getUrlsForWarming(), 10);
			
			Mage::getSingleton('core/app_emulation')->stopEnvironmentEmulation($initialEnvironmentInfo);

			return $this;
		}
		catch (Exception $e) {
			Mage::getSingleton('core/app_emulation')->stopEnvironmentEmulation($initialEnvironmentInfo);
			
			throw $e;
		}
	}

	/**
	 * Get the URLS for warming
	 *
	 * @return array
	 */
	protected function _getUrlsForWarming()
	{
		$baseUrl = $this->_getBaseUrl();
		$userAgents = $this->_getUserAgents();
		$urlFunctions = array(
			'_getInitUrls',
			'_getCatalogCategoryUrls',
			'_getCatalogProductUrls',
			'_getCmsPageUrls',
			'_getWordPressUrls',
			'_getCustomUrls',
			'_getUrlsFromEventObservers',
		);
	
		$allUrls = array();

		foreach($urlFunctions as $urlFunction) {
			if ($urls = call_user_func(array($this, $urlFunction))) {
				foreach($urls as $url) {
					if (!trim($url)) {
						continue;
					}
	
					if (strpos($url, '://') === false) {
						$url = $baseUrl . ltrim($url, '/');
					}
						
					$url = rtrim($url, '/');
					
					foreach($userAgents as $userAgentType => $userAgent) {
						$allUrls[] = array(
							CURLOPT_URL => $url,
							CURLOPT_USERAGENT => $userAgent,
						);
					}
				}
			}
		}
		
		if (($currencies = $this->getCurrencyCodes()) !== false) {
			if (count($currencies) > 1) {
				$allUrlCopy = $allUrls;
				
				foreach($currencies as $currency) {
					$allUrls[] = $this->_getBaseUrl() . 'directory/currency/switch/currency/' . $currency . '/';
					
					foreach($allUrlCopy as $url) {
						$allUrls[] = $url;
					}
				}
			}
		}
		
		return $allUrls;
	}

	/**
	 * Get the URLs required to initialise the process
	 * This includes setting the store
	 *
	 * @return array
	 */
	protected function _getInitUrls()
	{
		return array(
			$this->_getBaseUrl() . '?___store=' . Mage::app()->getStore()->getCode()
		);
	}
	
	/**
	 * Get an array of URLs from the core URL rewrite table
	 *
	 * @return array
	 */
	protected function _getCatalogProductUrls()
	{
		if (!Fishpig_CacheWarmer_Model_System_Config_Source_Url_Sources::isSourceEnabled('catalog_product')) {
			return array();
		}

		$visibilityAttribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'visibility');
		$statusAttribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'status');		
		
		if (!$visibilityAttribute->getId() || !$statusAttribute->getId()) {
			return array();
		}

		$urlRewrites = Mage::getResourceModel('core/url_rewrite_collection')
			->removeAllFieldsFromSelect()
			->addFieldToSelect('request_path')
			->removeFieldFromSelect('url_rewrite_id')
			->addFieldToFilter('main_table.store_id', Mage::app()->getStore()->getId())
			->addFieldToFilter('product_id', array('notnull' => true));
			
		// Ensure there are no options set
		$urlRewrites->getSelect()->where('main_table.options IS NULL OR main_table.options = ?', '');
		
		// Don't include category path URLs
		if (!Mage::getStoreConfigFlag('catalog/seo/product_use_categories')) {
			$urlRewrites->getSelect()->where('main_table.category_id IS NULL OR main_table.category_id = ?', '');
		}
		
		// Ensure only visible products are returned
		$urlRewrites->getSelect()->distinct()->join(
			array('_visibility' => $visibilityAttribute->getBackendTable()),
			'_visibility.entity_id = main_table.product_id AND _visibility.attribute_id=' . (int)$visibilityAttribute->getId() . ' AND _visibility.store_id IN (0, ' . Mage::app()->getStore()->getId() . ') AND _visibility.value IN (' . Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG . ', ' . Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH . ')',
			null
		);

		// Ensure only Enabled products are returned
		$urlRewrites->getSelect()->distinct()->join(
			array('_status' => $statusAttribute->getBackendTable()),
			'_status.entity_id = main_table.product_id AND _status.attribute_id=' . (int)$statusAttribute->getId() . ' AND _status.store_id IN (0, ' . (int)Mage::app()->getStore()->getId() . ') AND _status.value = 1',
			null
		);

		return $this->_getReadAdapter()->fetchCol($urlRewrites->getSelect());
	}
	
	/**
	 * Get an array of URLs from the core URL rewrite table
	 *
	 * @return array
	 */
	protected function _getCatalogCategoryUrls()
	{
		if (!Fishpig_CacheWarmer_Model_System_Config_Source_Url_Sources::isSourceEnabled('catalog_category')) {
			return array();
		}

		$isActiveAttribute = Mage::getSingleton('eav/config')->getAttribute('catalog_category', 'is_active');		
		
		if (!$isActiveAttribute->getId()) {
			return array();
		}

		$urlRewrites = Mage::getResourceModel('core/url_rewrite_collection')
			->removeAllFieldsFromSelect()
			->addFieldToSelect('request_path')
			->removeFieldFromSelect('url_rewrite_id')
			->addFieldToFilter('category_id', array('notnull' => true))
			->addFieldToFilter('product_id', array('null' => true))
#			->addFieldToFilter('options', array('null' => true))
			->addFieldToFilter('main_table.store_id', Mage::app()->getStore()->getId());
			
		// Ensure there are no options set
		$urlRewrites->getSelect()->where('main_table.options IS NULL OR main_table.options = ?', '');
		
		// Ensure only visible products are returned
		$urlRewrites->getSelect()->distinct()->join(
			array('_visibility' => $isActiveAttribute->getBackendTable()),
			'_visibility.entity_id = main_table.category_id AND _visibility.attribute_id=' . $isActiveAttribute->getId() . ' AND _visibility.store_id=0 AND _visibility.value = 1',
			null
		);

		return $this->_getReadAdapter()->fetchCol($urlRewrites->getSelect());
	}
	
	/**
	 * Get an array of URLs for CMS pages
	 *
	 * @return array
	 */
	protected function _getCmsPageUrls()
	{
		if (!Fishpig_CacheWarmer_Model_System_Config_Source_Url_Sources::isSourceEnabled('cms_pages')) {
			return array();
		}
		
		$cmsPageUrlsSelect = $this->_getReadAdapter()->select()
			->distinct()
			->from(array('main_table' => $this->_getResource()->getTableName('cms/page')), 'identifier')
			->join(
				array('store_table' => $this->_getResource()->getTableName('cms_page_store')),
				'store_table.page_id=main_table.page_id',
				''
			)
			->where('store_table.store_id = ?', Mage::app()->getStore()->getId());
			
		return $this->_getReadAdapter()->fetchCol($cmsPageUrlsSelect);
	}
	
	/**
	 * Get an array of WordPress URLs
	 *
	 * @return array
	 */
	protected function _getWordPressUrls()
	{
		if (!Fishpig_CacheWarmer_Model_System_Config_Source_Url_Sources::isSourceEnabled('wordpress')) {
			return array();
		}
		
		if (!$this->_isModuleInstalled('Fishpig_Wordpress')) {
			return array();
		}

		$urls = array();

		try {
			$posts = Mage::getResourceModel('wordpress/post_collection')
				->addIsPublishedFilter()
				->addPostTypeFilter(array_keys(Mage::helper('wordpress/app')->init()->getPostTypes()))
				->load();

			if (count($posts) > 0) {
				foreach($posts as $post) {
					$urls[] = $post->getPermalink();
				}
			}
		}
		catch (Exception $e) {
			Mage::logException($e);
		}
		
		return $urls;
	}
	
	/**
	 * Get an array of custom URLs specified in the configuration
	 *
	 * @return array
	 */
	protected function _getCustomUrls()
	{
		if (!Fishpig_CacheWarmer_Model_System_Config_Source_Url_Sources::isSourceEnabled('custom')) {
			return array();
		}
		
		if ($customUrlString = trim(Mage::getStoreConfig('cachewarmer/settings/customurls'))) {
			return explode("\n", $customUrlString);
		}

		return array();
	}

	/**
	 * Get an array of custom URLs from an event
	 * This allows other modules to add URLs to the extension
	 *
	 * @return array
	 */
	protected function _getUrlsFromEventObservers()
	{
		$transport = new Varien_Object(array(
			'urls' => array(),
		));
		
		Mage::dispatchEvent('cachewarmer_add_urls', array('helper' => $this, 'transport' => $transport));

		return $transport->getUrls() && is_array($transport->getUrls()) && count($transport->getUrls()) > 0
			? $transport->getUrls()
			: array();
	}
	
	/**
	 *
	 *
	 * @return 
	 */
	protected function _getResource()
	{
		return Mage::getSingleton('core/resource');
	}
	
	/**
	 *
	 *
	 * @return 
	 */
	protected function _getReadAdapter()
	{
		return $this->_getResource()->getConnection('core_read');
	}
	
	/**
	 * Get an array of selected useragents
	 *
	 * @return array
	 */
	protected function _getUserAgents()
	{
		return Fishpig_CacheWarmer_Model_System_Config_Source_Useragent::getOptionsForMobileDetect();
	}
	
	/**
	 * Retrieve the value of self::_displayOutput
	 *
	 * @return bool
	 */
	public function canDisplayOutput()
	{
		return $this->_displayOutput === true;
	}

	/**
	 * Initialize the cache warmer
	 *
	 * @return $this
	 */
	protected function _init()
	{
		Mage::helper('cachewarmer/license')->validate();

		return $this;
	}
	
	/**
	 * Set the value of self::_displayOutput
	 *
	 * @param bool $flag
	 * @return Fishpig_CacheWarmer_Helper_Data
	 */
	public function setDisplayOutput($flag)
	{
		$this->_displayOutput = (bool)$flag;
		
		return $this;
	}
	
	/**
	 * Get the base URL for the current store
	 *
	 * @return string
	 */
	protected function _getBaseUrl()
	{
		return Mage::getUrl('', array(
			'_store' => Mage::app()->getStore()->getId(),
			'_nosid' => true,
		));
	}
	
	/**
	 * Determine whether $module is installed
	 *
	 * @param string $module
	 * @return bool
	 */
	protected function _isModuleInstalled($module)
	{
		return (string)Mage::app()->getConfig()->getNode('modules/' . $module . '/active') === 'true';
	}
	
	/**
	 * Get an array of currency codes
	 *
	 * @return array|false
	 */
	public function getCurrencyCodes()
	{
		$storeId = (int)Mage::app()->getStore()->getId();
		
		if (!isset($this->_currencyCodes[$storeId])) {
			// Set currency codes to false 
			$this->_currencyCodes[$storeId] = false;
			
			if (Mage::getStoreConfigFlag('cachewarmer/settings/currency_code_all')) {
				$codes = Mage::app()->getStore()->getAvailableCurrencyCodes(true);
				
				if (is_array($codes) && count($codes) > 1) {
					$currencies = array();
					$rates = Mage::getModel('directory/currency')->getCurrencyRates(
						Mage::app()->getStore()->getBaseCurrency(),
						$codes
					);
				
					foreach ($codes as $code) {
						if (isset($rates[$code])) {
							$currencies[] = $code;
						}
					}
					
					$this->_currencyCodes = $currencies;
				}
			}
			else {
				if ($currencies = Mage::getStoreConfig('cachewarmer/settings/currency_codes_specified')) {
					$currencies = @unserialize($currencies);
					
					if ($currencies) {
						$this->_currencyCodes[$storeId] = $currencies;
					}
				}
			}
		}

		return $this->_currencyCodes[$storeId];
	}
}