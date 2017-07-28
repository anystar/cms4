<?php

class toolbar extends prefab {

	private $buttonList;
	private $include;
	public $hasIncluded;

	function __construct($settings = null) {

		// Implement Auto-including

		Template::instance()->extend("toolbar", function ($args) {

			toolbar::instance()->hasIncluded = true;

			return '<?php
				if (admin::$signed)
					echo Template::instance()->render("/toolbar/toolbar.html", null, toolbar::instance()->getHive(), 0);
				else if (base::instance()->SESSION["show-login"])
				{
					echo Template::instance()->render("/toolbar/login_model.html");
					base::instance()->SESSION["show-login"] = false;
				}

			?>';

		});

		Template::instance()->extend("body", "Body::render");

		Template::instance()->extend("overlay", "Overlay::render");	

	}

	function append ($code) {
		$this->include[] = $code;
	}

	function getHive () {
		$f3 = base::instance();

		return ["include"=>$this->include, "buttonList"=>$this->buttonList, "CDN"=>$f3->CDN, "BASE"=>$f3->BASE, "PATH"=>$f3->PATH, "VISITS"=>$f3->VISITS];
	}

}

class Body extends \Template\TagHandler {

	function build ($attr, $content) {

		if ($attr)
			$attr = $this->resolveParams($attr);

		if (!toolbar::instance()->hasIncluded)
		{
			$toolbar = '<?php
				if (admin::$signed)
					echo Template::instance()->render("/toolbar/toolbar.html", null, toolbar::instance()->getHive(), 0);
				else if (base::instance()->SESSION["show-login"])
				{
					echo Template::instance()->render("/toolbar/login_model.html");
					base::instance()->SESSION["show-login"] = false;
				}
			?>';
		}

		return '<body'.$attr.'>'.$content."\n".
			$toolbar."\n".'</body>';
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