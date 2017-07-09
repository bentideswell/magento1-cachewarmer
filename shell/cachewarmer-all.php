<?php

	error_reporting(E_ALL);
	ini_set('display_errors', 1);	
	
	set_time_limit(0);

	// Include Mage.php
	require_once(
		dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Mage.php'
	);
	
	Mage::app();
	umask(0);
	
	try {
		$stores = Mage::getResourceModel('core/store_collection')
			->load();
			
		echo sprintf("\n\n##\n# FishPig's Cache Warmer\n##\n");
		
		$it = 1;
		
		foreach($stores as $store) {
			if ((int)$store->getId() > 0) {
				echo sprintf("# Warming store %d/%d (%s).                                  \r", $it++, count($stores), $store->getName());

				system(sprintf('php -f %s/cachewarmer.php store %s', __DIR__, $store->getCode()));
			}
		}
		
		echo "# Warming for all stores complete.\n\n";
	}
	catch (Exception $e) {
		echo "\n\nEXCEPTION: " . $e->getMessage() . "\n\n";
	}
