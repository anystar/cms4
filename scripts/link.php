<?php

class link {

	static function dashboard ($settings) {

		if (isroute($settings["routes"]))
		{	
			return '<a target="_blank" href="'.base::instance()->BASE."/".ltrim($settings["href"], "/").'" class="webworkscms_button btn-fullwidth">'.$settings["label"].'</a>';
		}
		else
			return "";
	}
}