<?php
/*
 * @SkipObfuscation
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);	

set_time_limit(0);

$dirsToTry = array(
	dirname(__DIR__),
	getcwd(),
	dirname(dirname(dirname(dirname(__FILE__)))),
);

$mageReady = false;

// Include Mage.php	
foreach($dirsToTry as $dirToTry) {
	$mageFile = $dirToTry . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Mage.php';
	
	if (is_file($mageFile)) {
		require_once($mageFile);
		$mageReady = true;
	}
}

try {
	if (!$mageReady) {
		throw new Exception('Unable to find Mage.php');
	}
	
	Mage::app();
	umask(0);
	
	$stores = Mage::getResourceModel('core/store_collection')->load();
		
	echo sprintf("\n\n##\n# FishPig's Cache Warmer\n##\n");
	
	$it = 1;
	
	foreach($stores as $store) {
		if ((int)$store->getId() > 0) {
			echo sprintf("# Warming store %d/%d (%s).                                  \r", $it++, count($stores), $store->getName());

			system(sprintf('php -f %s/cachewarmer.php store %s', __DIR__, $store->getCode()));
		}
	}
	
	echo "# Warming for all stores complete.\n\n";
	
	Mage::helper('cachewarmer')->cleanUp();
}
catch (Exception $e) {
	echo "\n\nEXCEPTION: " . $e->getMessage() . "\n\n";
}
