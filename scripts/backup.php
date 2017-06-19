<?php

class backup {

	function __construct() {

		if (!admin::$signed)
			return;

		$this->excluded[] = getcwd()."/.cms/";

		$this->directory = ".cms/";

		if (!is_dir($this->directory))
			mkdir($this->directory);

		$f3 = base::instance();

		$f3->route("GET /admin/backup", function ($f3) {

			$backup = backup::instance();

			$filename = time().".tar";

			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(getcwd()."/"));

			$filterIterator = new CallbackFilterIterator($iterator, function ($current, $key, $iterator) {
				if ($current->isDir() && $iterator->isDot())
				 	return false;

				 if (strposa($key, backup::instance()->excluded)) {
				 	return false;
				 }

				return $current;
			});

			
			$phar = new PharData($this->directory."/".$filename);
			$phar->buildFromIterator($filterIterator, getcwd());

			$phar->compress(Phar::GZ);
			unlink($this->directory."/".$filename);
		});

	}

	function getBackups () {

		$backups = scandir($this->directory);

		k($backup);

	}
}

function strposa($haystack, $needle, $offset=0) {
    if(!is_array($needle)) $needle = array($needle);
    foreach($needle as $query) {
        if(strpos($haystack, $query, $offset) !== false) return true; // stop on first true result
    }
    return false;
}