<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Crawler
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Http_Curl_Multi
{
	/**
	 * Cookie file
	 *
	 * @var string
	 */
	protected $_cookieFile = null;
	
	/**
	 * Flag to determine whether to save the returned HTML
	 *
	 * @var bool
	 */
	protected $_storeResponses = true;
	
	/**
	 * Flag to determine whether to show the progress
	 *
	 * @var bool
	 */
	protected $_showProgressTemplate = "- %d/%s URLS downloaded (%s)            \r";
	
	/**
	 * The cache object
	 *
	 * @var bool|object
	 */
	protected $_cache = false;
	
	/**
	 * An array of results
	 *
	 * @var array
	 */
	protected $_results = array();
	
	/**
	 * Set a callback function for when data is found
	 *
	 * @var string
	 **/
	protected $_callback = null;
	
	/**
	 * Process an array of URLs
	 *
	 * @param array $urls
	 * @param int $limit
	 * @return array
	 */
	 public function process(array $urls, $limit = 5)
	 {
		if (count($urls) === 0) {
		 	throw new Exception('Empty URL array passed.');
		}
		 
		 // Initialise the curl_multi handle
		$mh = curl_multi_init();

		 // Save the total URLs for the progress bar
		 $total = count($urls);
 
		// Ensure the handle limit doesn't exceed
		 $limit = count($urls) < $limit ? count($urls): $limit;

		 $start = false;

		 for ($i = 0; $i < $limit; $i++) {
			 if (count($urls) > 0) {
			 	$start = $this->_createHandleAndAddToMulti($mh, $urls);
			 }
		}

		if ($start !== false) {
			do {
				$status = $this->_curlMultiExec($mh, $handlesActive);
	
				while($handlesActive && $status === CURLM_OK) {
					// Block a little
					$this->_curlMultiSelect($mh);
					
					// Do some real work
					$status = $this->_curlMultiExec($mh, $handlesActive);
	
					while (($complete = curl_multi_info_read($mh)) !== false) {
						$url = curl_getinfo($complete['handle'], CURLINFO_EFFECTIVE_URL);

						if ($this->storeResponses() || $this->hasCallback()) {							
							if ((int)$complete['msg'] === CURLMSG_DONE) {
								if ((int)$complete['result'] === CURLE_OK && (int)curl_getinfo($complete['handle'], CURLINFO_HTTP_CODE) === 200) {
									$content = curl_multi_getcontent($complete['handle']);
									
									if ($this->storeResponses()) {
										$this->_results[$url] = $content;
									}
									
									if ($this->hasCallback()) {
										call_user_func($this->_callback, $url, $content);
									}

									if ($this->_cache) {
										Fishpig_Cache::set(md5('get-' . $url), $this->_results[$url]);
									}
								}
								else {
									$this->_results[$url] = false;
								}
							}
						}
	
						if ($this->_showProgressTemplate) {
							echo sprintf($this->_showProgressTemplate, $total - count($urls), $total, $url);
						}
	
						$this->_createHandleAndAddToMulti($mh, $urls, $complete['handle']);
					}
				}
			} while(count($urls) > 0);
		}

		curl_multi_close($mh);

		return $this->storeResponses() ? $this->_results : false;
	 }

	 /**
		 * Create a CURL handle and it to our curl_multi resource
		 *
		 * @param resource $mh
		 * @param array|string $options
		 * @param resource|null $remove=null
		 * @return bool/resource
		 */
	 protected function _createHandleAndAddToMulti($mh, &$urls, $remove = null)
	 {
	 	if (!$urls) {
		 	return false;
	 	}

	 	$options = false;

	 	while(count($urls) > 0) {
		 	$options = array_shift($urls);
	
			if (!is_array($options)) {
				$options = array(CURLOPT_URL => $options,);
			}
			
			if ($this->_cache && Fishpig_Cache::exists(md5('get-' . $options[CURLOPT_URL]))) {
				$this->_results[$options[CURLOPT_URL]] = Fishpig_Cache::get('get-' . $options[CURLOPT_URL]);
				$options = false;
			}
			else {
				break;
			}
		}

		if (!$options) {
			return false;
		}

		$handle = curl_init();
		
		$cookieFile = $this->_getCookieFile();

		curl_setopt_array($handle, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HEADER => false,
			CURLOPT_ENCODING => '',
			CURLOPT_COOKIESESSION => true,
			CURLOPT_COOKIEJAR => $cookieFile,
			CURLOPT_COOKIEFILE => $cookieFile,
		) + $options);

		// Add it to the multi queue
		curl_multi_add_handle($mh, $handle);

		if (!is_null($remove)) {
			curl_multi_remove_handle($mh, $remove);
			curl_close($remove);
		}
		
		return $handle;
	 }
	 
	 /**
	 * Wrapper for the PHP core function curl_multi_exec
	 *
	 * @param resource $mh
	 * @param int &$handlesActive
	 * @return int
	 */
	 protected function _curlMultiExec($mh, &$handlesActive)
	 {
		do {
			$status = (int)curl_multi_exec($mh, $handlesActive);
		}
		while($handlesActive > 0 && $status === CURLM_CALL_MULTI_PERFORM);
		
		return $status;
	}
	
	/**
	 * Wrapper for the core PHP function core_multi_select
	 *
	 * @param resource $mh
	 * @return int
	 */
	protected function _curlMultiSelect($mh)
	{
		if ((int)curl_multi_select($mh) === -1) {
			usleep(200);
			return -1;
		}
		
		return 0;
	}
	
	/**
	 * Determine whether to store the responses
	 *
	 * @param bool $flag
	 * @return $this
	 */
	public function storeResponses($flag = null)
	{
		if (is_null($flag)) {
			return $this->_storeResponses;
		}
		
		$this->_storeResponses = (bool)$flag;
		
		return $this;
	}
	
	/**
	 * Determine whether to show the progress bar
	 *
	 * @param bool $flag
	 * @return bool|$this
	 */
	public function setShowProgress($template)
	{
		$this->_showProgressTemplate = $template;
		
		return $this;
	}
	
	/**
	 * Set the cache object
	 *
	 * @var object $cache
	 * @return $this
	 */
	public function setUseCache($flag)
	{
		$this->_cache = (bool)$flag;
		
		return $this;
	}
	
	/**
	 * Get the cookie filename
	 *
	 * @return string
	 */
	protected function _getCookieFile()
	{
		if (!is_null($this->_cookieFile)) {
			return $this->_cookieFile;
		}

		$this->_cookieFile = dirname(dirname(dirname(dirname(__DIR__)))) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . rand(1111, 9999) . '-cachewarmer.cookie';		

		return $this->_cookieFile;
	}
	
	/**
	 * Set a callback function
	 *
	 * @param string $func
	 * @return $this
	 */
	public function setCallback($func)
	{
		if (!is_callable($func)) {
			throw new Exception('Unable to set callback in ' . __CLASS__);
		}
		
		$this->_callback = $func;
		
		return $this;
	}
	
	/**
	 * Set a callback function
	 *
	 * @return bool
	 */
	public function hasCallback()
	{
		return $this->_callback !== null;
	}
}