<?php

class debug extends prefab {

	function __construct() {
		$f3 = base::instance();

		if (admin::$signed)
		{
			$hive = $f3->hive();	

			$hive = $this->array_keys_blacklist($hive, ['HEADERS', 'GET', 'POST', 'COOKIE', 'REQUEST', 'SESSION', 'FILES', 'SERVER', 'ENV', 'CMS', 'ACE', 'DB', 'SETTINGS', 'AJAX','AGENT','ALIAS','ALIASES','AUTOLOAD','BASE','BITMASK','BODY','CACHE','CASELESS','CONFIG','CORS','DEBUG','DIACRITICS','DNSBL','EMOJI','ENCODING','ERROR','ESCAPE','EXCEPTION','EXEMPT','FALLBACK','FRAGMENT','HALT','HIGHLIGHT','HOST','IP','JAR','LANGUAGE','LOCALES','LOGS','ONERROR','ONREROUTE','PACKAGE','PARAMS','PATH','PATTERN','PLUGINS','PORT','PREFIX','PREMAP','QUERY','QUIET','RAW','REALM','RESPONSE','ROOT','ROUTES','SCHEME','SERIALIZER','TEMP','TIME','TZ','UI','UNLOAD','UPLOADS','URI','VERB','VERSION','XFRAME']);

			$hive = json_encode($hive);

			$code .= "<script>";
			$code .= "var obj = ".$hive.";";
			$code .= "console.log({'Template Variables' : obj});";
			$code .= "</script>";
			$f3->set("debug", $code);


			Template::instance()->filter("krumo", function ($array) {
				require_once $GLOBALS["settings"]["paths"]["krumo"];
				return krumo($array);
			});

			Template::instance()->filter("die", function () {
				die;
			});
		}
		else
		{
			$f3->set("debug", "");	
		}
	}

	function array_keys_blacklist( array $array, array $keys ) {
	   foreach ( $array as $key => $value ) {
	      if ( in_array( $key, $keys ) ) {
	         unset( $array[ $key ] );
	      }
	   }
	 
	   return $array;
	}
}