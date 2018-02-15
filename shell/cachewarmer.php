<?php

	error_reporting(E_ALL);
	ini_set('display_errors', 1);	
	
	set_time_limit(0);

	// Determine correct run code
	$runCode = isset($_SERVER['MAGE_RUN_CODE']) ? $_SERVER['MAGE_RUN_CODE'] : '';
	$runType = isset($_SERVER['MAGE_RUN_TYPE']) ? $_SERVER['MAGE_RUN_TYPE'] : 'store';
	
	if (isset($_GET['store'])) {
		$runCode = $_GET['store'];
	}
	else if (isset($argv[1]) && $argv[1] === 'store' && isset($argv[2])) {
		$runCode = (string)$argv[2];
	}
	
	if (isset($_GET['type'])) {
		$runType = $_GET['type'];
	}

	$dirsToTry = array(
		dirname(__DIR__),
		getcwd(),
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
		
		Mage::app($runCode, $runType);
		umask(0);

		// Start the warmimg process
		Mage::helper('cachewarmer')->setDisplayOutput(
			true
		)->run();
		
		Mage::helper('cachewarmer')->cleanUp();
	}
	catch (Exception $e) {
		echo $e->getMessage() . (PHP_SAPI === 'cli' ? "\n" : '<br/>');
	}
