<?php

class mytest extends prefab {

	public $call_once = true;

	function __construct() {

		if ($this->call_once)
		{
			$this->call_once = false;
			echo "I have not initizalized!<br>";
		}
	}

}