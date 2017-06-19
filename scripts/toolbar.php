<?php

class toolbar extends prefab {

	private $buttonList;
	private $include;

	function __construct($settings = null) {

		// Implement Auto-including

		Template::instance()->extend("toolbar", function ($args) {

			return '<?php
				if (admin::$signed)
					echo Template::instance()->render("/toolbar/toolbar.html", null, toolbar::instance()->getHive(), 0);
			?>';

		});


		Template::instance()->extend("overlay", "Overlay::render");	

	}

	function append ($code) {

		$this->include[] = $code;
	}

	function getHive () {
		$f3 = base::instance();

		return ["include"=>$this->include, "buttonList"=>$this->buttonList, "CDN"=>$f3->CDN, "BASE"=>$f3->BASE];
	}

}

class Overlay extends \Template\TagHandler {

	function build ($attr, $content) {

		check (1, base::instance()->devoid($attr["script"]), "Script `". $attr["script"]. "` cannot be found");

		$script = base::instance()->get($attr["script"]);

		check (1, !method_exists($script, "toolbar"), "Script `". $attr["script"]. "` does not have a toolbar method.");

		$toolbar = $script->toolbar();

		$attr["class"] = "editable";
		$attr["id"] = uniqid("editable_");
		$attr = $this->resolveParams($attr);

		return '<?php if (admin::$signed) { ?><div '.$attr.'><div class="toolbar">'.$toolbar.'</div></div><?php } ?>'.$content;
	}

}